<?php

namespace App\DTOs;

final readonly class AIValidationProviderResponse
{
    public function __construct(
        public ArticleValidationData $validation,
        public string $model,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        public ?int $totalTokens = null,
    ) {}
}
