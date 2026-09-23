<?php

namespace App\Enums;

enum ValidationRisk: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Riesgo bajo',
            self::Medium => 'Riesgo medio',
            self::High => 'Riesgo alto',
        };
    }
}
