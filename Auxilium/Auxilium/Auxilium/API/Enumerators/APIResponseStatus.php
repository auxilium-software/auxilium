<?php

namespace Auxilium\Auxilium\API\Enumerators;

enum APIResponseStatus: string
{
    case OK = "OK";
    case ERROR = "ERROR";
    case UNAUTHORISED = "UNAUTHORIZED";
}
