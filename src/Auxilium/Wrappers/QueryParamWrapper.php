<?php

namespace Auxilium\Wrappers;

use Auxilium\Enumerators\QueryParamKey;

class QueryParamWrapper
{
    public static function Get(QueryParamKey $key, string $default, bool $writeToIfNotSet = false): string
    {
        if(array_key_exists(key: $key->value, array: $_GET))
        {
            if(isset($_GET[$key->value]))
            {
                return $_GET[$key->value];
            }
        }
        if($writeToIfNotSet)
        {
            $_GET[$key->value] = $default;
        }
        return $default;
    }
}
