<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
use App\Models\GeneratedArticle;
use App\Services\AI\NewsRepairService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class RepairArticle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $generatedArticleId)
    {
        $this->onQueue(config('press_releases.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->generatedArticleId;
    }

    public function handle(NewsRepairService $service): void
    {
        $article = $service->repair(GeneratedArticle::query()->findOrFail($this->generatedArticleId));
        if ($article->repair_status === 'awaiting_validation') {
            ValidateArticle::dispatch($article->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $article = GeneratedArticle::query()->find($this->generatedArticleId);
        if ($article === null) {
            return;
        }
        $article->update(['repair_status' => 'failed']);
        $article->pressRelease()->update([
            'processing_status' => PressReleaseStatus::NeedsReview,
            'error_message' => Str::limit($exception?->getMessage() ?? 'La reparación automática falló.', 65535, ''),
        ]);
    }
}
