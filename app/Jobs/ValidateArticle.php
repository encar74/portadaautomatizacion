<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Services\AI\NewsValidationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ValidateArticle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

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

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(NewsValidationService $service): void
    {
        $article = GeneratedArticle::query()->findOrFail($this->generatedArticleId);
        $validated = $service->validate($article);
        $validated->loadMissing('pressRelease.pressSource');

        if (in_array($validated->validation_risk, [ValidationRisk::Medium, ValidationRisk::High], true)
            && config('ai.auto_repair_enabled')
            && $validated->repair_attempted_at === null
            && $validated->pressRelease->pressSource?->processing_mode === ProcessingMode::Automatic) {
            RepairArticle::dispatch($validated->id);

            return;
        }

        if ($validated->validation_risk === ValidationRisk::Low
            && config('wordpress.enabled')
            && $validated->pressRelease->pressSource?->processing_mode === ProcessingMode::Automatic) {
            PublishArticleToWordPress::dispatch($validated->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $article = GeneratedArticle::query()->find($this->generatedArticleId);
        if ($article === null) {
            return;
        }

        PressRelease::query()->whereKey($article->press_release_id)->update([
            'processing_status' => PressReleaseStatus::Error,
            'error_message' => Str::limit($exception?->getMessage() ?? 'La validación agotó sus reintentos.', 65535, ''),
        ]);
    }
}
