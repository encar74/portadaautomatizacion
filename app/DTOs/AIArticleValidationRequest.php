<?php

namespace App\DTOs;

final readonly class AIArticleValidationRequest
{
    public function __construct(
        public string $sourceText,
        public string $articleText,
        public string $systemPrompt,
        public string $validationPrompt,
        public string $promptVersion,
    ) {}
}
