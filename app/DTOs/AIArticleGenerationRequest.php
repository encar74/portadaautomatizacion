<?php

namespace App\DTOs;

final readonly class AIArticleGenerationRequest
{
    public function __construct(
        public string $sourceText,
        public string $systemPrompt,
        public string $generationPrompt,
        public string $promptVersion,
    ) {}
}
