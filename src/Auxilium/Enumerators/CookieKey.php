<?php

namespace Auxilium\Enumerators;

enum CookieKey: string
{
    case LANGUAGE = "lang";
    case PROGRESSIVE_LOAD = "progressive_load";
    case SESSION_KEY = "session_key";
    case STYLE = "style";
}
