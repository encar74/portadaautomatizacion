<?php

namespace App\Services\PressReleases;

use App\DTOs\PressReleaseIngestionResult;
use App\DTOs\PressReleaseSourceData;
use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Models\PressRelease;
use App\Services\PressSources\PressSourceMatcher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PressReleaseService
{
    public function __construct(
        private readonly PressSourceMatcher $sourceMatcher,
        private readonly AttachmentStorageService $attachmentStorage,
    ) {}

    public function ingest(PressReleaseSourceData $data): PressReleaseIngestionResult
    {
        $messageId = $this->normalizeMessageId($data->messageId);
        $existing = PressRelease::query()->where('message_id', $messageId)->first();

        if ($existing !== null) {
            return new PressReleaseIngestionResult($existing, false);
        }

        $this->validate($data);
        $paths = [];

        try {
            $pressRelease = DB::transaction(function () use ($data, $messageId, &$paths): PressRelease {
                $senderEmail = mb_strtolower(trim($data->senderEmail));
                $source = $this->sourceMatcher->match($senderEmail);
                $status = match (true) {
                    $source === null => PressReleaseStatus::UnmatchedSender,
                    $source->processing_mode === ProcessingMode::Ignore => PressReleaseStatus::Ignored,
                    default => PressReleaseStatus::Received,
                };

                $pressRelease = PressRelease::create([
                    'press_source_id' => $source?->id,
                    'message_id' => $messageId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $this->nullableTrimmed($data->senderName),
                    'subject' => trim($data->subject),
                    'received_at' => $data->receivedAt,
                    'body_text' => $data->bodyText,
                    'body_html' => $data->bodyHtml,
                    'processing_status' => $status,
                ]);

                $rawPath = "press-releases/{$pressRelease->id}/original.eml";
                if (! $this->disk()->put($rawPath, $data->rawMessage)) {
                    throw new InvalidArgumentException('No se pudo conservar el email original.');
                }
                $paths[] = $rawPath;
                $pressRelease->update(['raw_message_path' => $rawPath]);

                foreach ($this->attachmentStorage->store($pressRelease, $data->attachments) as $path) {
                    $paths[] = $path;
                }

                return $pressRelease->load(['pressSource', 'attachments']);
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                $this->disk()->delete($path);
            }

            throw $exception;
        }

        return new PressReleaseIngestionResult($pressRelease, true);
    }

    private function validate(PressReleaseSourceData $data): void
    {
        if (filter_var(trim($data->senderEmail), FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('El remitente del email no es válido.');
        }

        if (trim($data->subject) === '') {
            throw new InvalidArgumentException('El email debe incluir un asunto.');
        }

        $rawSize = strlen($data->rawMessage);
        if ($rawSize === 0 || $rawSize > config('press_releases.max_raw_message_bytes')) {
            throw new InvalidArgumentException('El email original está vacío o supera el tamaño permitido.');
        }
    }

    private function normalizeMessageId(string $messageId): string
    {
        $messageId = mb_strtolower(trim($messageId));

        if ($messageId === '' || strlen($messageId) > 255 || preg_match('/\s/', $messageId)) {
            throw new InvalidArgumentException('El Message-ID no es válido.');
        }

        return $messageId;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), 255, '');
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('press_releases.disk'));
    }
}
