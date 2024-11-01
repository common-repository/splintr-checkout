<?php

namespace Splintr\Wp\Plugin\SplintrCheckout\Helpers;

class MoneyHelper
{
    /**
     * Format the amount of money for Splintr SDK
     *
     * @param $amount
     *
     * @return float
     */
    public static function formatNumber($amount)
    {
        return floatval(number_format($amount, 2, ".", ""));
    }

    /**
     * Format the amount of money for general with 2 decimals
     *
     * @param $amount
     *
     * @return string
     */
    public static function formatNumberGeneral($amount)
    {
        return number_format($amount, 2, ".", "");
    }

    /**
     * Format the amount of money for rounding with 2 decimals
     *
     * @param string|int $amount
     *
     * @return float
     */
    public static function formatAndRoundNumber($amount)
    {
	    return round(number_format(floatval($amount), 2, "", ""), 2);
    }
}
