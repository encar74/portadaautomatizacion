<?php

namespace App\Services\Mail\Gmail;

use App\Contracts\MailboxClientInterface;
use LogicException;

class GmailMailboxClient implements MailboxClientInterface
{
    /** @var array<string, string> */
    private array $gmailIdsByMessageId = [];

    public function __construct(
        private readonly GmailApiClient $client,
        private readonly GmailMessageMapper $mapper,
    ) {}

    public function messages(): iterable
    {
        foreach ($this->client->listMessages() as $summary) {
            $gmailId = (string) $summary['id'];
            $full = $this->client->message($gmailId, 'full');
            $raw = $this->client->message($gmailId, 'raw');
            $data = $this->mapper->map($full, $this->client->decodeBase64Url((string) $raw['raw']));
            $this->gmailIdsByMessageId[$data->messageId] = $gmailId;

            yield $data;
        }
    }

    public function markImported(string $messageId): void
    {
        $gmailId = $this->gmailIdsByMessageId[$messageId] ?? null;
        if ($gmailId === null) {
            throw new LogicException('No se puede etiquetar un mensaje que no fue obtenido en esta ejecución.');
        }

        $this->client->addLabel($gmailId, $this->client->importedLabelId());
    }
}
