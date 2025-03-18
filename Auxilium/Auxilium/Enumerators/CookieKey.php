<?php

namespace Auxilium\Enumerators;

/**
 * String backed enumerator for cookie keys.
 */
enum CookieKey: string
{
    case LANGUAGE = "lang";
    case PROGRESSIVE_LOAD = "progressiveload";
    case SESSION_KEY = "session_key";
    case STYLE = "style";
}
