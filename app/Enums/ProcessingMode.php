<?php

namespace App\Enums;

enum ProcessingMode: string
{
    case Automatic = 'automatic';
    case ProcessOnly = 'process_only';
    case Review = 'review';
    case Ignore = 'ignore';
}
