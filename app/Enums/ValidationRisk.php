<?php

namespace App\Enums;

enum ValidationRisk: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
