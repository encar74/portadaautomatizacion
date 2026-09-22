<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\Enums\AIExecutionStatus;
use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Models\AIExecution;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NewsGenerationService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly PromptRepository $prompts,
    ) {}

    public function generate(PressRelease $pressRelease): GeneratedArticle
    {
        if ($existing = $pressRelease->generatedArticle()->first()) {
            return $existing;
        }

        if ($pressRelease->processing_status !== PressReleaseStatus::Processed || ! filled($pressRelease->source_text)) {
            throw new RuntimeException('La nota de prensa no está preparada para generar una noticia.');
        }

        $version = (string) config('ai.prompt_version');
        $execution = AIExecution::create([
            'press_release_id' => $pressRelease->id,
            'provider' => $this->provider->name(),
            'model' => (string) config('ai.models.generation'),
            'operation' => 'generation',
            'status' => AIExecutionStatus::Pending,
            'prompt_version' => $version,
        ]);
        $startedAt = hrtime(true);

        try {
            $response = $this->provider->generateArticle(new AIArticleGenerationRequest(
                sourceText: mb_substr($pressRelease->source_text, 0, config('ai.max_source_chars')),
                systemPrompt: $this->prompts->get('system', $version),
                generationPrompt: $this->prompts->get('news-generation', $version),
                promptVersion: $version,
            ));

            $article = DB::transaction(function () use ($pressRelease, $response, $version): GeneratedArticle {
                $lockedRelease = PressRelease::query()->lockForUpdate()->findOrFail($pressRelease->id);
                if ($existing = $lockedRelease->generatedArticle()->first()) {
                    return $existing;
                }

                $data = $response->article;
                $article = GeneratedArticle::create([
                    'press_release_id' => $lockedRelease->id,
                    'headline' => Str::limit($data->headline, 255, ''),
                    'subheadline' => $data->subheadline ? Str::limit($data->subheadline, 255, '') : null,
                    'lead' => $data->lead,
                    'body' => $data->body,
                    'seo_title' => Str::limit($data->seoTitle, 255, ''),
                    'seo_description' => $data->seoDescription,
                    'suggested_category' => $data->suggestedCategory ? Str::limit($data->suggestedCategory, 255, '') : null,
                    'suggested_tags' => $data->suggestedTags,
                    'location' => $data->location ? Str::limit($data->location, 255, '') : null,
                    'warnings' => [],
                    'validation_risk' => null,
                    'validation_issues' => [],
                    'generated_at' => now(),
                ]);

                $article->versions()->create([
                    'version' => 1,
                    'origin' => ArticleVersionOrigin::AI,
                    'headline' => $article->headline,
                    'subheadline' => $article->subheadline,
                    'lead' => $article->lead,
                    'body' => $article->body,
                    'seo_title' => $article->seo_title,
                    'seo_description' => $article->seo_description,
                    'category' => $article->suggested_category,
                    'tags' => $article->suggested_tags,
                    'ai_provider' => $this->provider->name(),
                    'ai_model' => $response->model,
                    'prompt_version' => $version,
                ]);

                return $article;
            });

            $execution->update([
                'generated_article_id' => $article->id,
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'total_tokens' => $response->totalTokens,
                'duration_ms' => $this->durationMs($startedAt),
                'status' => AIExecutionStatus::Successful,
            ]);

            return $article->load('versions');
        } catch (Throwable $exception) {
            $execution->update([
                'duration_ms' => $this->durationMs($startedAt),
                'status' => AIExecutionStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 65535, ''),
            ]);

            throw $exception;
        }
    }

    private function durationMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
