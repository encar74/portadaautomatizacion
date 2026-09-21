<?php

namespace App\Services\PressSources;

use App\Enums\PressSourceMatchType;
use App\Models\PressSource;

class PressSourceMatcher
{
    public function match(string $senderEmail): ?PressSource
    {
        $email = mb_strtolower(trim($senderEmail));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $baseQuery = static fn () => PressSource::query()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id');

        $exact = $baseQuery()
            ->where('match_type', PressSourceMatchType::ExactEmail)
            ->where('email', $email)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        $domain = mb_strtolower(substr(strrchr($email, '@'), 1));

        return $baseQuery()
            ->where('match_type', PressSourceMatchType::Domain)
            ->where('domain', $domain)
            ->first();
    }
}
