<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleValidationRequest;
use App\Enums\AIExecutionStatus;
use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\AIExecution;
use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NewsValidationService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly PromptRepository $prompts,
    ) {}

    public function validate(GeneratedArticle $article): GeneratedArticle
    {
        if ($article->validation_risk !== null) {
            return $article;
        }

        $article->loadMissing('pressRelease.pressSource');
        if (! filled($article->pressRelease->source_text)) {
            throw new RuntimeException('La nota de prensa no contiene texto fuente para validar la noticia.');
        }

        $version = (string) config('ai.prompt_version');
        $execution = AIExecution::create([
            'press_release_id' => $article->press_release_id,
            'generated_article_id' => $article->id,
            'provider' => $this->provider->name(),
            'model' => (string) config('ai.models.validation'),
            'operation' => 'validation',
            'status' => AIExecutionStatus::Pending,
            'prompt_version' => $version,
        ]);
        $startedAt = hrtime(true);

        try {
            $response = $this->provider->validateArticle(new AIArticleValidationRequest(
                sourceText: mb_substr($article->pressRelease->source_text, 0, config('ai.max_source_chars')),
                articleText: mb_substr($this->articleText($article), 0, config('ai.max_article_chars')),
                systemPrompt: $this->prompts->get('validation-system', $version),
                validationPrompt: $this->prompts->get('validation', $version),
                promptVersion: $version,
            ));

            $validated = DB::transaction(function () use ($article, $response): GeneratedArticle {
                $locked = GeneratedArticle::query()->with('pressRelease.pressSource')->lockForUpdate()->findOrFail($article->id);
                if ($locked->validation_risk !== null) {
                    return $locked;
                }

                $validation = $response->validation;
                $locked->update([
                    'warnings' => array_values(array_unique(array_merge($locked->warnings ?? [], $validation->warnings))),
                    'validation_risk' => $validation->risk,
                    'validation_issues' => $validation->issues,
                ]);

                $needsReview = $validation->risk === ValidationRisk::High
                    || $locked->pressRelease->pressSource?->processing_mode === ProcessingMode::Review;
                $locked->pressRelease->update([
                    'processing_status' => $needsReview
                        ? PressReleaseStatus::NeedsReview
                        : PressReleaseStatus::Processed,
                    'error_message' => null,
                ]);

                return $locked;
            });

            $execution->update([
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'total_tokens' => $response->totalTokens,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => AIExecutionStatus::Successful,
            ]);

            return $validated->refresh();
        } catch (Throwable $exception) {
            $execution->update([
                'duration_ms' => $this->durationMs($startedAt),
                'status' => AIExecutionStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 65535, ''),
            ]);

            throw $exception;
        }
    }

    private function articleText(GeneratedArticle $article): string
    {
        return implode("\n\n", array_filter([
            '[HEADLINE]'."\n".$article->headline,
            $article->subheadline ? '[SUBHEADLINE]'."\n".$article->subheadline : null,
            '[LEAD]'."\n".$article->lead,
            '[BODY]'."\n".$article->body,
            '[SEO_TITLE]'."\n".$article->seo_title,
            '[SEO_DESCRIPTION]'."\n".$article->seo_description,
        ]));
    }

    private function durationMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
