<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
use App\Models\PressRelease;
use App\Services\AI\NewsGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GenerateArticle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $pressReleaseId)
    {
        $this->onQueue(config('press_releases.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->pressReleaseId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(NewsGenerationService $service): void
    {
        $pressRelease = PressRelease::query()->with('generatedArticle')->findOrFail($this->pressReleaseId);
        if ($pressRelease->generatedArticle !== null) {
            return;
        }

        $service->generate($pressRelease);
    }

    public function failed(?Throwable $exception): void
    {
        PressRelease::query()->whereKey($this->pressReleaseId)->update([
            'processing_status' => PressReleaseStatus::Error,
            'error_message' => Str::limit($exception?->getMessage() ?? 'La generación agotó sus reintentos.', 65535, ''),
        ]);
    }
}
