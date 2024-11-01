<?php


namespace Splintr\Wp\Plugin\SplintrCheckout\Helpers;


class UrlHelper
{
    /**
     * @param string $url
     *
     * @return string
     */
    public static function removeTrailingSlashes($url)
    {
        return rtrim(trim($url), DIRECTORY_SEPARATOR);
    }
}