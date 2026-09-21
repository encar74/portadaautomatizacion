<?php

namespace App\Contracts;

use App\DTOs\PressReleaseSourceData;

interface MailboxClientInterface
{
    /** @return iterable<PressReleaseSourceData> */
    public function messages(): iterable;

    public function markImported(string $messageId): void;
}
