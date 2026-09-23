<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
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

    public function __construct(public readonly int $generatedArticleId)
    {
        $this->onQueue(config('press_releases.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->generatedArticleId;
    }

    public function handle(WordPressDraftService $service): void
    {
        $service->create(GeneratedArticle::query()->findOrFail($this->generatedArticleId));
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
        $article->pressRelease()->update([
            'processing_status' => PressReleaseStatus::NeedsReview,
            'error_message' => Str::limit('No se pudo crear el borrador en WordPress: '.($exception?->getMessage() ?? 'error desconocido'), 65535, ''),
        ]);
    }
}
