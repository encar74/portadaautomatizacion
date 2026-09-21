<?php

namespace App\DTOs;

final readonly class PressReleaseAttachmentData
{
    public function __construct(
        public string $originalFilename,
        public string $content,
        public ?string $declaredMimeType = null,
    ) {}
}
