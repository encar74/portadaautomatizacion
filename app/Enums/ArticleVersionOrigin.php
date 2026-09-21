<?php

namespace App\Enums;

enum ArticleVersionOrigin: string
{
    case AI = 'ai';
    case Human = 'human';
}
