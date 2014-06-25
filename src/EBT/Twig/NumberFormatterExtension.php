<?php

/*
 * This file is a part of the EBDate library.
 *
 * (c) 2013 Ebidtech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EBT\Twig;

class NumberFormatterExtension extends \Twig_Extension
{
    /**
     * Units and threshold values to be used for human number filter.
     * Must be ordered from bigger to smaller units.
     *
     * @var array
     */
    private static $humanReadableUnits = array(
        'M' => 1000000,
        'K' => 1000
    );

    /**
     * The locale to be used for localized formatters.
     *
     * @var string
     */
    protected $locale;

    /**
     * ISO-??? currency code.
     * When set it overrides default locale settings.
     *
     * @var string
     */
    protected $currency;

    /**
     * {@inheritDoc}
     * @see Twig_ExtensionInterface::getName()
     */
    public function getName()
    {
        return 'eb_number_formatter_extension';
    }

    /**
     * {@inheritDoc}
     * @see Twig_Extension::getFilters()
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter(
                'number_human',
                array($this, 'numberHumanFilter'),
                array('needs_environment' => true)
            ),
            new \Twig_SimpleFilter(
                'percent_format',
                array($this, 'percentFormatFilter'),
                array('needs_environment' => true)
            ),
            new \Twig_SimpleFilter(
                'currency_format',
                array($this, 'currencyFormatFilter')
            )
        );
    }

    /**
     * {@inheritDoc}
     * @see Twig_Extension::getFunctions()
     */
    public function getFunctions()
    {
        return array(
            'currency_symbol' => new \Twig_Function_Method($this, 'currencySymbolFunction'),
            'percent_symbol' => new \Twig_Function_Method($this, 'percentSymbolFunction'),
        );
    }

    /**
     * Gets the currency code.
     *
     * @return string
     */
    public function getCurrency()
    {
        if (null === $this->currency) {
            $this->currency = (new \NumberFormatter($this->getLocale(), \NumberFormatter::CURRENCY))
                ->getSymbol(\NumberFormatter::INTL_CURRENCY_SYMBOL);
        }

        return $this->currency;
    }

    /**
     * Sets the currency code.
     *
     * @param string $currency
     *
     * @return NumberFormatterExtension
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Gets the locale defined.
     *
     * @return string
     */
    public function getLocale()
    {
        if (null === $this->locale) {
            $this->locale = \Locale::getDefault();
        }

        return $this->locale;
    }

    /**
     * Sets the locale.
     *
     * @param string $locale
     *
     * @return NumberFormatterExtension
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Formats a number in human readable format.
     *
     * @param \Twig_Environment $env                Twig environment.
     * @param mixed             $number             The number to format.
     * @param integer|null      $decimal            The number of decimal places to keep,
     *                                              when null it'll use the default.
     * @param string|null       $decimalPoint       The decimal point separator to use when formatting,
     *                                              when null it'll use the default.
     * @param string|null       $thousandSep        The thousand separator to use, when null it'll use the default
     * @param string|null       $notUseReadableUnit Not use letters to represent thousand (K) or million (M)
     *
     * @return string
     */
    public function numberHumanFilter(
        \Twig_Environment $env,
        $number,
        $decimal = null,
        $decimalPoint = null,
        $thousandSep = null,
        $notUseReadableUnit = null
    ) {
        if (null === $number) {
            $number = (int) $number;
        } elseif (!is_numeric($number)) {
            return $number;
        }

        $format = false;
        if ($notUseReadableUnit == null) {
            foreach (self::$humanReadableUnits as $unit => $threshold) {
                if ($number > $threshold) {
                    $format = true;
                    break;
                }
            }
        }

        if ($format) {
            $number /= $threshold;

            $number = sprintf(
                '%s %s',
                twig_number_format_filter($env, $number, $decimal, $decimalPoint, $thousandSep),
                $unit
            );
        } else {
            $number = twig_number_format_filter($env, $number, $decimal, $decimalPoint, $thousandSep);
        }

        return $number;
    }

    /**
     * Currency formatter function, according to defined locale and currency.
     *
     * @param mixed       $number       The number to format.
     * @param integer     $decimal      The decimal places to keep.
     * @param string      $decimalPoint The decimal point character to use.
     * @param string      $thousandSep  The thousand separator character to use.
     * @param string|null $currency     The currency code to use when formatting, when null it'll use the default.
     * @param string|null $locale       The locale used when formatting, when null it'll use the default.
     * @param boolean     $omitSymbol   Whether or not to omit the currency symbol.
     *
     * @return string
     */
    public function currencyFormatFilter(
        $number,
        $decimal = null,
        $decimalPoint = null,
        $thousandSep = null,
        $currency = null,
        $locale = null,
        $omitSymbol = false
    ) {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $currency) {
            $currency = $this->getCurrency();
        }

        $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimal);
        $fmt->setAttribute(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, $decimalPoint);
        $fmt->setAttribute(\NumberFormatter::MONETARY_GROUPING_SEPARATOR_SYMBOL, $thousandSep);
        $fmt->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        $currency = $fmt->formatCurrency($number, $currency);

        if ($omitSymbol) {
            $currency = str_replace($this->extractCurrencySymbol($currency), '', $currency);
            $currency = trim($currency);
        }

        return $currency;
    }

    /**
     * Get the currency symbol for a specific locale or currency code.
     *
     * @param string|null $currency        Currency ISO code, default is the currently defined currency code.
     * @param string|null $locale          The locale, when null it'll use the default.
     * @param boolean     $prefixWithSpace Whether or not the prefix the currency symbol with a space, default is true.
     *
     * @return string
     */
    public function currencySymbolFunction($currency = null, $locale = null, $prefixWithSpace = true)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if (null === $currency) {
            $currency = $this->getCurrency();
        }

        if (!empty($currency)) {
            $bogusCurrencyStr = $this->currencyFormatFilter(123, $currency, $locale);
            $symbol = $this->extractCurrencySymbol($bogusCurrencyStr);
        } else {
            $symbol = (new \NumberFormatter($locale, \NumberFormatter::CURRENCY))
                ->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
        }

        return $prefixWithSpace ? ' ' . $symbol : $symbol;
    }

    /**
     * Percentage formatter.
     *
     * @param \Twig_Environment $env          Twig environment.
     * @param mixed             $number       The number to format.
     * @param boolean           $divideBy100  Whether or not to divide by 100 when formatting, default is false.
     * @param string|null       $decimal      Decimal places to keep.
     * @param string|null       $decimalPoint Decimal symbol to use.
     * @param string|null       $locale       Locale used to obtain the percentage symbol.
     *
     * @return string
     */
    public function percentFormatFilter(
        \Twig_Environment $env,
        $number,
        $divideBy100 = false,
        $decimal = null,
        $decimalPoint = null,
        $locale = null
    ) {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        if ($divideBy100) {
            $number /= $number;
        }

        // Don't use any thousand separator as it doesn't make sense for percentages
        $number = twig_number_format_filter($env, $number, $decimal, $decimalPoint, '');

        return sprintf('%s %s', $number, $this->percentSymbolFunction($locale, false));
    }

    /**
     * Get the percentage symbol for a specific locale.
     *
     * @param string|null $locale          The locale, when null it'll use the default.
     * @param boolean     $prefixWithSpace Whether or not to prefix the symbol with a space, default is true.
     *
     * @return string
     */
    public function percentSymbolFunction($locale = null, $prefixWithSpace = true)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        }

        $symbol = (new \NumberFormatter($locale, \NumberFormatter::PERCENT))
            ->getSymbol(\NumberFormatter::PERCENT_SYMBOL);

        return $prefixWithSpace ? ' ' . $symbol : $symbol;
    }

    /**
     * Hackish way of getting the currency symbol from a string.
     * {@see NumberFormatterExtension::currencySymbolFunction()}
     *
     * @param string $str The string to extract the symbol from.
     *
     * @return string     The symbol.
     */
    protected function extractCurrencySymbol($str)
    {
        $symbol = '';

        $matches = array();
        if (preg_match('/^(\D*)\s*([\d,\.\s]+)\s*(\D*)$/u', $str, $matches)) {
            $symbol = empty($matches[1]) ? $matches[3] : $matches[1];
            $symbol = trim($symbol);
        }

        return $symbol;
    }
}
