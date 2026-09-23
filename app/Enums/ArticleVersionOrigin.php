<?php

namespace App\Enums;

enum ArticleVersionOrigin: string
{
    case AI = 'ai';
    case AIRepair = 'ai_repair';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::AI => 'Generación inicial con IA',
            self::AIRepair => 'Reparación automática con IA',
            self::Human => 'Edición manual',
        };
    }
}
