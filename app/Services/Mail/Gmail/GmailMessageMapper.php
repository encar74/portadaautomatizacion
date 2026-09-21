<?php

namespace App\Services\Mail\Gmail;

use App\DTOs\PressReleaseAttachmentData;
use App\DTOs\PressReleaseSourceData;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

class GmailMessageMapper
{
    public function __construct(private readonly GmailApiClient $client) {}

    public function map(array $message, string $rawMessage): PressReleaseSourceData
    {
        $headers = $this->headers($message['payload']['headers'] ?? []);
        [$senderEmail, $senderName] = $this->sender($headers['from'] ?? '');
        $bodies = ['text/plain' => [], 'text/html' => []];
        $attachments = [];
        $this->walkParts((string) $message['id'], $message['payload'] ?? [], $bodies, $attachments);

        return new PressReleaseSourceData(
            messageId: $headers['message-id'] ?? '<gmail-'.$message['id'].'@gmail-api.local>',
            senderEmail: $senderEmail,
            senderName: $senderName,
            subject: $this->decodeHeader($headers['subject'] ?? '(Sin asunto)'),
            receivedAt: CarbonImmutable::createFromTimestampMs((int) $message['internalDate'])
                ->setTimezone(config('app.timezone')),
            bodyText: $this->joinedBody($bodies['text/plain']),
            bodyHtml: $this->joinedBody($bodies['text/html']),
            rawMessage: $rawMessage,
            attachments: $attachments,
        );
    }

    private function walkParts(string $gmailId, array $part, array &$bodies, array &$attachments): void
    {
        $mimeType = mb_strtolower((string) ($part['mimeType'] ?? 'application/octet-stream'));
        $filename = $this->decodeHeader((string) ($part['filename'] ?? ''));
        $body = $part['body'] ?? [];

        if ($filename !== '') {
            $content = isset($body['data'])
                ? $this->client->decodeBase64Url((string) $body['data'])
                : $this->client->attachment($gmailId, (string) ($body['attachmentId'] ?? ''));
            $attachments[] = new PressReleaseAttachmentData($filename, $content, $mimeType);
        } elseif (array_key_exists($mimeType, $bodies) && isset($body['data'])) {
            $content = $this->client->decodeBase64Url((string) $body['data']);
            $bodies[$mimeType][] = $this->toUtf8($content, $part['headers'] ?? []);
        }

        foreach ($part['parts'] ?? [] as $child) {
            $this->walkParts($gmailId, $child, $bodies, $attachments);
        }
    }

    private function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $header) {
            if (isset($header['name'], $header['value'])) {
                $result[mb_strtolower($header['name'])] = $header['value'];
            }
        }

        return $result;
    }

    private function sender(string $from): array
    {
        $addresses = imap_rfc822_parse_adrlist($from, 'gmail.invalid');
        $address = $addresses[0] ?? null;

        if ($address === null || empty($address->mailbox) || empty($address->host)) {
            throw new UnexpectedValueException('Gmail devolvió un remitente no válido.');
        }

        $name = isset($address->personal) ? $this->decodeHeader($address->personal) : null;

        return [mb_strtolower($address->mailbox.'@'.$address->host), $name];
    }

    private function decodeHeader(string $value): string
    {
        if (! str_contains($value, '=?')) {
            return $value;
        }

        return iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value;
    }

    private function toUtf8(string $content, array $headers): string
    {
        $headers = $this->headers($headers);
        preg_match('/charset=["\']?([^;"\'\s]+)/i', $headers['content-type'] ?? '', $matches);
        $charset = $matches[1] ?? 'UTF-8';

        return mb_convert_encoding($content, 'UTF-8', $charset);
    }

    private function joinedBody(array $parts): ?string
    {
        $body = trim(implode("\n", $parts));

        return $body === '' ? null : $body;
    }
}
