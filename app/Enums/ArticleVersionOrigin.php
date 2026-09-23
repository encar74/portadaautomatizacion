<?php

namespace App\Enums;

enum ArticleVersionOrigin: string
{
    case AI = 'ai';
    case AIRepair = 'ai_repair';
    case AIGuided = 'ai_guided';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::AI => 'Generación inicial con IA',
            self::AIRepair => 'Reparación automática con IA',
            self::AIGuided => 'Corrección con IA solicitada por periodista',
            self::Human => 'Edición manual',
        };
    }
}
