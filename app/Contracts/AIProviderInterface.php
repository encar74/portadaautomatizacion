<?php

namespace App\Contracts;

use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIProviderResponse;

interface AIProviderInterface
{
    public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse;

    public function name(): string;
}
