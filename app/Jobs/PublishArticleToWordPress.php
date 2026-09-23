<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
use App\Models\ArticleEditorialAction;
use App\Models\GeneratedArticle;
use App\Services\WordPressDraftService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class PublishArticleToWordPress implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public readonly int $generatedArticleId,
        public readonly bool $allowMediumRisk = false,
        public readonly ?int $editorialActionId = null,
    ) {
        $this->onQueue(config('press_releases.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->generatedArticleId;
    }

    public function handle(WordPressDraftService $service): void
    {
        $publication = $service->create(
            GeneratedArticle::query()->findOrFail($this->generatedArticleId),
            $this->allowMediumRisk,
            $this->editorialActionId !== null,
        );
        if ($this->editorialActionId !== null) {
            ArticleEditorialAction::query()->whereKey($this->editorialActionId)->update([
                'status' => 'completed',
                'metadata' => ['wordpress_publication_id' => $publication->id],
            ]);
        }
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(?Throwable $exception): void
    {
        $article = GeneratedArticle::query()->find($this->generatedArticleId);
        if ($article === null) {
            return;
        }
        if ($this->editorialActionId !== null) {
            ArticleEditorialAction::query()->whereKey($this->editorialActionId)->update([
                'status' => 'failed',
                'metadata' => ['error' => Str::limit($exception?->getMessage() ?? 'Error desconocido', 1000, '')],
            ]);
        }
        $article->pressRelease()->update([
            'processing_status' => PressReleaseStatus::NeedsReview,
            'error_message' => Str::limit('No se pudo crear el borrador en WordPress: '.($exception?->getMessage() ?? 'error desconocido'), 65535, ''),
        ]);
    }
}
