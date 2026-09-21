<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class PressReleaseSourceData
{
    /**
     * @param  list<PressReleaseAttachmentData>  $attachments
     */
    public function __construct(
        public string $messageId,
        public string $senderEmail,
        public ?string $senderName,
        public string $subject,
        public CarbonImmutable $receivedAt,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public string $rawMessage,
        public array $attachments = [],
    ) {}
}
