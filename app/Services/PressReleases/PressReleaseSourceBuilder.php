<?php

namespace App\Services\PressReleases;

use App\Models\PressRelease;

class PressReleaseSourceBuilder
{
    public function build(PressRelease $pressRelease): string
    {
        $pressRelease->loadMissing('attachments');
        $sections = [
            "[METADATA]\nAsunto: {$pressRelease->subject}\nRemitente: {$pressRelease->sender_name} <{$pressRelease->sender_email}>",
            "[EMAIL_BODY]\n".$this->emailBody($pressRelease),
        ];

        foreach ($pressRelease->attachments as $attachment) {
            if (filled($attachment->extracted_text)) {
                $sections[] = "[ATTACHMENT filename=\"{$attachment->original_filename}\"]\n{$attachment->extracted_text}";
            }
        }

        return mb_substr(
            implode("\n\n", $sections),
            0,
            config('press_releases.max_source_text_chars'),
        );
    }

    private function emailBody(PressRelease $pressRelease): string
    {
        if (filled($pressRelease->body_text)) {
            return trim($pressRelease->body_text);
        }

        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', '', (string) $pressRelease->body_html);
        $html = preg_replace('~<br\s*/?>|</(?:p|div|tr|li|h[1-6])\s*>~i', "\n", $html ?? '');

        return trim(html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
