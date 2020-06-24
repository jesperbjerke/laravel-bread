<?php

namespace Bjerke\Bread\Helpers;

use Illuminate\Http\Request;

/**
 * Class RequestParams
 * @package Bjerke\Bread\Helpers
 *
 * Helper methods for reading/manipulating request params
 */
class RequestParams
{

    /**
     * Extracts all request params
     *
     * @param Request $request
     *
     * @return array|mixed
     */
    public static function getParams(Request $request)
    {
        if ($request->isJson()) {
            if ($jsonParams = $request->json('data')) {
                $attributes = $jsonParams;
            } else {
                $attributes = [];
            }
        } else {
            $attributes = $request->all();
        }

        return $attributes;
    }

}
