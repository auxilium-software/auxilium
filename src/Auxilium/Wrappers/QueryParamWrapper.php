<?php

namespace Auxilium\Wrappers;

use Auxilium\Enumerators\QueryParamKey;

/**
 * Handles operations related to the $_GET superglobal.
 */
class QueryParamWrapper
{
    /**
     * Retrieves a value from the $_GET superglobal based on the provided key.
     * If the key exists in the $_GET array and is not null, it will return the associated value.
     * If the key does not exist, the default value is returned.
     * Optionally writes the default value to the $_GET array if the key is not set and $writeToIfNotSet is true.
     *
     * @param QueryParamKey $key The key to lookup in the $_GET array.
     * @param string $default The default value to return if the key is not found.
     * @param bool $writeToIfNotSet Optional. If true, writes the default value to the $_GET array when the key is not set.
     *
     * @return string The value associated with the given key in the $_GET array, or the default value if the key does not exist.
     */
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
