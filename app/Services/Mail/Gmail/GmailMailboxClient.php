<?php

namespace App\Services\Mail\Gmail;

use App\Contracts\MailboxClientInterface;
use App\Enums\PressSourceMatchType;
use App\Models\PressSource;
use App\Services\PressSources\PressSourceMatcher;
use LogicException;

class GmailMailboxClient implements MailboxClientInterface
{
    /** @var array<string, string> */
    private array $gmailIdsByMessageId = [];

    public function __construct(
        private readonly GmailApiClient $client,
        private readonly GmailMessageMapper $mapper,
        private readonly PressSourceMatcher $sourceMatcher,
    ) {}

    public function messages(): iterable
    {
        $senders = PressSource::query()->where('is_active', true)->get()
            ->map(function (PressSource $source) {
                $value = $source->match_type === PressSourceMatchType::ExactEmail ? $source->email : $source->domain;

                return filled($value) ? 'from:"'.addcslashes($value, '\\"').'"' : null;
            })->filter()->unique()->values();

        // No configured sources means no authorized senders, never the whole inbox.
        if ($senders->isEmpty()) {
            return;
        }

        $baseQuery = trim((string) config('mail_ingestion.gmail.query'));
        $query = ($baseQuery === '' ? '' : '('.$baseQuery.') ').'{'.$senders->implode(' ').'}';

        foreach ($this->client->listMessages($query) as $summary) {
            $gmailId = (string) $summary['id'];
            $full = $this->client->message($gmailId, 'full');
            $raw = $this->client->message($gmailId, 'raw');
            $data = $this->mapper->map($full, $this->client->decodeBase64Url((string) $raw['raw']));
            // Gmail searches can be broader than our exact email/domain rules.
            if ($this->sourceMatcher->match($data->senderEmail) === null) {
                continue;
            }
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
