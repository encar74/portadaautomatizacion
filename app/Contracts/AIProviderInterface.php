<?php

namespace App\Contracts;

use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIArticleValidationRequest;
use App\DTOs\AIProviderResponse;
use App\DTOs\AIValidationProviderResponse;

interface AIProviderInterface
{
    public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse;

    public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse;

    public function name(): string;
}
