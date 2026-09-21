<?php

namespace App\DTOs;

use App\Models\PressRelease;

final readonly class PressReleaseIngestionResult
{
    public function __construct(
        public PressRelease $pressRelease,
        public bool $wasCreated,
    ) {}
}
