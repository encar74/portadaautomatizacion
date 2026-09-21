<?php

namespace App\Enums;

enum PressSourceMatchType: string
{
    case ExactEmail = 'exact_email';
    case Domain = 'domain';
}
