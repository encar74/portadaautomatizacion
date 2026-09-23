<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\Enums\AIExecutionStatus;
use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Models\AIExecution;
use App\Models\GeneratedArticle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NewsRepairService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly PromptRepository $prompts,
    ) {}

    public function repair(GeneratedArticle $article): GeneratedArticle
    {
        $reserved = DB::transaction(function () use ($article): bool {
            $locked = GeneratedArticle::query()->with('pressRelease')->lockForUpdate()->findOrFail($article->id);
            if ($locked->repair_attempted_at !== null) {
                return false;
            }
            if (! in_array($locked->validation_risk, [ValidationRisk::Medium, ValidationRisk::High], true)) {
                throw new RuntimeException('Solo se pueden reparar automáticamente artículos con riesgo medio o alto.');
            }

            $locked->update(['repair_status' => 'running', 'repair_attempted_at' => now()]);
            $locked->pressRelease->update([
                'processing_status' => PressReleaseStatus::Repairing,
                'error_message' => null,
            ]);

            return true;
        });

        if (! $reserved) {
            return $article->fresh();
        }

        $article->refresh()->loadMissing('pressRelease');
        if (! filled($article->pressRelease->source_text)) {
            throw new RuntimeException('La nota de prensa no contiene texto fuente para reparar la noticia.');
        }

        $version = (string) config('ai.prompt_version');
        $execution = AIExecution::create([
            'press_release_id' => $article->press_release_id,
            'generated_article_id' => $article->id,
            'provider' => $this->provider->name(),
            'model' => (string) config('ai.models.generation'),
            'operation' => 'repair',
            'status' => AIExecutionStatus::Pending,
            'prompt_version' => $version,
        ]);
        $startedAt = hrtime(true);

        try {
            $response = $this->provider->generateArticle(new AIArticleGenerationRequest(
                sourceText: mb_substr($article->pressRelease->source_text, 0, config('ai.max_source_chars')),
                systemPrompt: $this->prompts->get('system', $version),
                generationPrompt: $this->repairPrompt($article, $version),
                promptVersion: $version,
            ));

            $repaired = DB::transaction(function () use ($article, $response, $version): GeneratedArticle {
                $locked = GeneratedArticle::query()->lockForUpdate()->findOrFail($article->id);
                $data = $response->article;
                $nextVersion = ((int) $locked->versions()->max('version')) + 1;

                $locked->update([
                    'headline' => Str::limit($data->headline, 255, ''),
                    'subheadline' => $data->subheadline ? Str::limit($data->subheadline, 255, '') : null,
                    'lead' => $data->lead,
                    'body' => $data->body,
                    'seo_title' => Str::limit($data->seoTitle, 255, ''),
                    'seo_description' => $data->seoDescription,
                    'suggested_category' => $data->suggestedCategory ? Str::limit($data->suggestedCategory, 255, '') : null,
                    'suggested_tags' => $data->suggestedTags,
                    'location' => $data->location ? Str::limit($data->location, 255, '') : null,
                    'validation_risk' => null,
                    'validation_issues' => [],
                    'repair_status' => 'awaiting_validation',
                    'repaired_at' => now(),
                ]);

                $locked->versions()->create([
                    'version' => $nextVersion,
                    'origin' => ArticleVersionOrigin::AIRepair,
                    'headline' => $locked->headline,
                    'subheadline' => $locked->subheadline,
                    'lead' => $locked->lead,
                    'body' => $locked->body,
                    'seo_title' => $locked->seo_title,
                    'seo_description' => $locked->seo_description,
                    'category' => $locked->suggested_category,
                    'tags' => $locked->suggested_tags,
                    'ai_provider' => $this->provider->name(),
                    'ai_model' => $response->model,
                    'prompt_version' => $version,
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

            return $repaired->refresh();
        } catch (Throwable $exception) {
            $execution->update([
                'duration_ms' => $this->durationMs($startedAt),
                'status' => AIExecutionStatus::Failed,
                'error_message' => Str::limit($exception->getMessage(), 65535, ''),
            ]);
            $article->update(['repair_status' => 'failed']);
            $article->pressRelease()->update([
                'processing_status' => PressReleaseStatus::NeedsReview,
                'error_message' => Str::limit('Falló la reparación automática: '.$exception->getMessage(), 65535, ''),
            ]);

            throw $exception;
        }
    }

    private function repairPrompt(GeneratedArticle $article, string $version): string
    {
        $issues = json_encode($article->validation_issues ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->prompts->get('article-repair', $version)
            ."\n\nVALIDATION_ISSUES_BEGIN\n{$issues}\nVALIDATION_ISSUES_END"
            ."\n\nARTICLE_TO_REPAIR_BEGIN\n{$this->articleText($article)}\nARTICLE_TO_REPAIR_END";
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
