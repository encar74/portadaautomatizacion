<?php

namespace App\Services\PressReleases;

use App\Models\PressRelease;
use Illuminate\Support\Facades\DB;

class PressReleaseContentExtractionService
{
    public function __construct(
        private readonly AttachmentTextExtractor $attachmentExtractor,
        private readonly PressReleaseSourceBuilder $sourceBuilder,
    ) {}

    public function extract(PressRelease $pressRelease): void
    {
        DB::transaction(function () use ($pressRelease): void {
            $pressRelease->load('attachments');

            foreach ($pressRelease->attachments as $attachment) {
                $text = $this->attachmentExtractor->extract($attachment);
                if ($text !== null) {
                    $attachment->update(['extracted_text' => $text]);
                }
            }

            $pressRelease->refresh()->load('attachments');
            $pressRelease->update([
                'source_text' => $this->sourceBuilder->build($pressRelease),
                'content_extracted_at' => now(),
            ]);
        });
    }
}
