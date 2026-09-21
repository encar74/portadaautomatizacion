<?php

namespace App\Enums;

enum AIExecutionStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
}
