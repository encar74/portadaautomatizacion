<?php

namespace App\Services\PressReleases;

use App\Exceptions\BlockedPressReleaseAttachment;
use App\Models\PressRelease;
use Illuminate\Support\Facades\DB;

class PressReleaseContentExtractionService
{
    public function __construct(
        private readonly AttachmentTextExtractor $attachmentExtractor,
        private readonly PressReleaseSourceBuilder $sourceBuilder,
    ) {}

    public function extract(PressRelease $pressRelease): bool
    {
        return DB::transaction(function () use ($pressRelease): bool {
            $pressRelease->load('attachments');
            $hasBlockedAttachments = false;

            foreach ($pressRelease->attachments as $attachment) {
                try {
                    $text = $this->attachmentExtractor->extract($attachment);
                    if ($text !== null) {
                        $attachment->update([
                            'extracted_text' => $text,
                            'is_blocked' => false,
                            'blocked_reason' => null,
                        ]);
                    }
                } catch (BlockedPressReleaseAttachment $exception) {
                    $hasBlockedAttachments = true;
                    $attachment->update([
                        'extracted_text' => null,
                        'is_blocked' => true,
                        'blocked_reason' => $exception->getMessage(),
                    ]);
                }
            }

            $pressRelease->refresh()->load('attachments');
            $pressRelease->update([
                'source_text' => $this->sourceBuilder->build($pressRelease),
                'content_extracted_at' => now(),
            ]);

            return $hasBlockedAttachments;
        });
    }
}
