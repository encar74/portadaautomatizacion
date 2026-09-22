<?php

namespace App\DTOs;

final readonly class AIProviderResponse
{
    public function __construct(
        public GeneratedArticleData $article,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $totalTokens = null,
    ) {}
}
