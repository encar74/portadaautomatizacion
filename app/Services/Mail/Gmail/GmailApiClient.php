<?php

namespace App\Services\Mail\Gmail;

use App\Exceptions\MailboxConfigurationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GmailApiClient
{
    private const API_BASE = 'https://gmail.googleapis.com/gmail/v1';

    private ?string $accessToken = null;

    private ?string $labelId = null;

    public function listMessages(?string $query = null): array
    {
        $response = $this->request()->get($this->userUrl('/messages'), [
            'q' => $query ?? config('mail_ingestion.gmail.query'),
            'maxResults' => min(max(config('mail_ingestion.gmail.max_results'), 1), 500),
            'includeSpamTrash' => false,
        ])->throw();

        return $response->json('messages', []);
    }

    public function message(string $gmailId, string $format): array
    {
        return $this->request()
            ->get($this->userUrl('/messages/'.rawurlencode($gmailId)), ['format' => $format])
            ->throw()
            ->json();
    }

    public function attachment(string $gmailId, string $attachmentId): string
    {
        $data = $this->request()
            ->get($this->userUrl('/messages/'.rawurlencode($gmailId).'/attachments/'.rawurlencode($attachmentId)))
            ->throw()
            ->json('data');

        return $this->decodeBase64Url((string) $data);
    }

    public function addLabel(string $gmailId, string $labelId): void
    {
        $this->request()
            ->post($this->userUrl('/messages/'.rawurlencode($gmailId).'/modify'), [
                'addLabelIds' => [$labelId],
                'removeLabelIds' => [],
            ])
            ->throw();
    }

    public function importedLabelId(): string
    {
        if ($this->labelId !== null) {
            return $this->labelId;
        }

        $labelName = (string) config('mail_ingestion.gmail.imported_label');
        $labels = $this->request()->get($this->userUrl('/labels'))->throw()->json('labels', []);

        foreach ($labels as $label) {
            if (($label['name'] ?? null) === $labelName) {
                return $this->labelId = (string) $label['id'];
            }
        }

        return $this->labelId = (string) $this->request()->post($this->userUrl('/labels'), [
            'name' => $labelName,
            'labelListVisibility' => 'labelShow',
            'messageListVisibility' => 'show',
        ])->throw()->json('id');
    }

    public function decodeBase64Url(string $encoded): string
    {
        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \UnexpectedValueException('Gmail devolvió contenido base64url inválido.');
        }

        return $decoded;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->timeout(30)
            ->retry(3, 250);
    }

    private function accessToken(): string
    {
        $this->assertConfigured();
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::asForm()->timeout(15)->retry(3, 250)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('mail_ingestion.gmail.client_id'),
            'client_secret' => config('mail_ingestion.gmail.client_secret'),
            'refresh_token' => config('mail_ingestion.gmail.refresh_token'),
            'grant_type' => 'refresh_token',
        ])->throw();

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new MailboxConfigurationException('Google no devolvió un access token válido.');
        }

        return $this->accessToken = $token;
    }

    private function assertConfigured(): void
    {
        foreach (['client_id', 'client_secret', 'refresh_token', 'user_id', 'imported_label'] as $key) {
            if (blank(config("mail_ingestion.gmail.{$key}"))) {
                throw new MailboxConfigurationException("Falta configurar mail_ingestion.gmail.{$key}.");
            }
        }
    }

    private function userUrl(string $path): string
    {
        return self::API_BASE.'/users/'.rawurlencode((string) config('mail_ingestion.gmail.user_id')).$path;
    }
}
