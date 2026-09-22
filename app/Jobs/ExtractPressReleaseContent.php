<?php

namespace App\Jobs;

use App\Enums\PressReleaseStatus;
use App\Models\PressRelease;
use App\Services\PressReleases\PressReleaseContentExtractionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExtractPressReleaseContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

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
        return [30, 120, 300];
    }

    public function handle(PressReleaseContentExtractionService $service): void
    {
        $pressRelease = PressRelease::query()->with('pressSource')->findOrFail($this->pressReleaseId);

        if (in_array($pressRelease->processing_status, [PressReleaseStatus::Ignored, PressReleaseStatus::UnmatchedSender], true)) {
            return;
        }

        if ($pressRelease->processing_status === PressReleaseStatus::Processed && $pressRelease->content_extracted_at !== null) {
            return;
        }

        $pressRelease->update([
            'processing_status' => PressReleaseStatus::Processing,
            'error_message' => null,
        ]);

        try {
            $hasBlockedAttachments = $service->extract($pressRelease);
            $pressRelease->update([
                'processing_status' => $hasBlockedAttachments
                    ? PressReleaseStatus::NeedsReview
                    : PressReleaseStatus::Processed,
                'error_message' => $hasBlockedAttachments
                    ? 'Se ha bloqueado al menos un adjunto cifrado. Revisión manual necesaria.'
                    : null,
            ]);

            if (! $hasBlockedAttachments && config('ai.generation_enabled')) {
                GenerateArticle::dispatch($pressRelease->id);
            }
        } catch (Throwable $exception) {
            $pressRelease->update([
                'processing_status' => PressReleaseStatus::Error,
                'error_message' => mb_substr($exception->getMessage(), 0, 65535),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        PressRelease::query()->whereKey($this->pressReleaseId)->update([
            'processing_status' => PressReleaseStatus::Error,
            'error_message' => mb_substr($exception?->getMessage() ?? 'La extracción agotó sus reintentos.', 0, 65535),
        ]);
    }
}
