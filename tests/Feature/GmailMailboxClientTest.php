<?php

namespace Tests\Feature;

use App\Services\Mail\Gmail\GmailApiClient;
use App\Services\Mail\Gmail\GmailMailboxClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailMailboxClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('mail_ingestion.gmail', [
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'refresh_token' => 'refresh-token',
            'user_id' => 'me',
            'query' => 'in:inbox -label:portada-importado',
            'imported_label' => 'portada-importado',
            'max_results' => 50,
        ]);
    }

    public function test_it_fetches_maps_and_labels_a_gmail_message(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                $url === 'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token', 'expires_in' => 3600]),
                str_contains($url, '/messages/gmail-1/attachments/attachment-1') => Http::response(['data' => $this->base64Url($this->pdf())]),
                str_contains($url, '/messages/gmail-1/modify') => Http::response(['id' => 'gmail-1']),
                str_contains($url, '/messages/gmail-1') && str_contains($url, 'format=full') => Http::response($this->fullMessage()),
                str_contains($url, '/messages/gmail-1') && str_contains($url, 'format=raw') => Http::response(['id' => 'gmail-1', 'raw' => $this->base64Url('RAW EMAIL')]),
                str_ends_with(parse_url($url, PHP_URL_PATH), '/messages') => Http::response(['messages' => [['id' => 'gmail-1', 'threadId' => 'thread-1']]]),
                str_ends_with(parse_url($url, PHP_URL_PATH), '/labels') && $request->method() === 'GET' => Http::response(['labels' => [['id' => 'Label_42', 'name' => 'portada-importado']]]),
                default => Http::response(['error' => ['message' => 'Unexpected request: '.$url]], 500),
            };
        });

        $client = app(GmailMailboxClient::class);
        $messages = iterator_to_array($client->messages());

        $this->assertCount(1, $messages);
        $message = $messages[0];
        $this->assertSame('<press-123@example.com>', $message->messageId);
        $this->assertSame('prensa@example.com', $message->senderEmail);
        $this->assertSame('Gabinete de Prensa', $message->senderName);
        $this->assertSame('Nueva campaña', $message->subject);
        $this->assertSame('Contenido en texto', $message->bodyText);
        $this->assertSame('<p>Contenido HTML</p>', $message->bodyHtml);
        $this->assertSame('RAW EMAIL', $message->rawMessage);
        $this->assertSame('nota.pdf', $message->attachments[0]->originalFilename);
        $this->assertSame($this->pdf(), $message->attachments[0]->content);

        $client->markImported($message->messageId);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/messages/gmail-1/modify')
            && $request['addLabelIds'] === ['Label_42']
            && $request->hasHeader('Authorization', 'Bearer access-token'));
    }

    public function test_it_creates_the_imported_label_when_it_does_not_exist(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response(['access_token' => 'access-token']);
            }

            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/labels') && $request->method() === 'GET') {
                return Http::response(['labels' => []]);
            }

            return Http::response(['id' => 'Label_new']);
        });

        $this->assertSame('Label_new', app(GmailApiClient::class)->importedLabelId());

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/labels')
            && $request['name'] === 'portada-importado');
    }

    private function fullMessage(): array
    {
        return [
            'id' => 'gmail-1',
            'threadId' => 'thread-1',
            'internalDate' => '1789984800000',
            'payload' => [
                'mimeType' => 'multipart/mixed',
                'headers' => [
                    ['name' => 'Message-ID', 'value' => '<press-123@example.com>'],
                    ['name' => 'From', 'value' => 'Gabinete de Prensa <prensa@example.com>'],
                    ['name' => 'Subject', 'value' => 'Nueva campaña'],
                ],
                'parts' => [
                    [
                        'mimeType' => 'multipart/alternative',
                        'parts' => [
                            ['mimeType' => 'text/plain', 'headers' => [['name' => 'Content-Type', 'value' => 'text/plain; charset=UTF-8']], 'body' => ['data' => $this->base64Url('Contenido en texto')]],
                            ['mimeType' => 'text/html', 'headers' => [['name' => 'Content-Type', 'value' => 'text/html; charset=UTF-8']], 'body' => ['data' => $this->base64Url('<p>Contenido HTML</p>')]],
                        ],
                    ],
                    ['mimeType' => 'application/pdf', 'filename' => 'nota.pdf', 'body' => ['attachmentId' => 'attachment-1', 'size' => strlen($this->pdf())]],
                ],
            ],
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n%%EOF";
    }
}
