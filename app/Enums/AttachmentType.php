<?php

namespace App\Enums;

enum AttachmentType: string
{
    case Document = 'document';
    case Image = 'image';
    case Unknown = 'unknown';
}
