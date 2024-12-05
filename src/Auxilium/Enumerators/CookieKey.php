<?php

namespace Auxilium\Enumerators;

enum CookieKey: string
{
    case LANGUAGE = "lang";
    case PROGRESSIVE_LOAD = "progressiveload";
    case SESSION_KEY = "session_key";
    case STYLE = "style";
}
