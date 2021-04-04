<?php

namespace Bjerke\Bread\Helpers;

class Strings
{
    /**
     * Check if something is a valid Json string
     *
     * @param $str
     *
     * @return bool
     */
    public static function isJson($str): bool
    {
        $json = (is_string($str)) ? json_decode($str) : false;
        return $json && $str !== $json;
    }
}
