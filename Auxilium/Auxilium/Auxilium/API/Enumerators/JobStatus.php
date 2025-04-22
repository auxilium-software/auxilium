<?php

namespace Auxilium\Auxilium\API\Enumerators;

enum JobStatus: string
{
    case PENDING = 'PENDING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';
}
