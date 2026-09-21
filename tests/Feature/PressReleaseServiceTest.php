<?php

namespace Tests\Feature;

use App\Contracts\MailboxClientInterface;
use App\DTOs\PressReleaseAttachmentData;
use App\DTOs\PressReleaseSourceData;
use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Exceptions\InvalidPressReleaseAttachment;
use App\Models\PressSource;
use App\Services\PressReleases\PressReleaseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class PressReleaseServiceTest extends TestCase
{
    use RefreshDatabase;

    private PressReleaseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('press_releases.disk', 'local');
        $this->service = app(PressReleaseService::class);
    }

    public function test_unmatched_email_is_stored_with_original_and_valid_attachment(): void
    {
        $result = $this->service->ingest($this->sourceData(attachments: [
            new PressReleaseAttachmentData('../Comunicado final.PDF', $this->pdf(), 'text/plain'),
        ]));

        $release = $result->pressRelease;
        $this->assertTrue($result->wasCreated);
        $this->assertSame(PressReleaseStatus::UnmatchedSender, $release->processing_status);
        $this->assertNull($release->press_source_id);
        $this->assertSame('press@example.com', $release->sender_email);
        Storage::disk('local')->assertExists($release->raw_message_path);
        $attachment = $release->attachments->sole();
        $this->assertSame('Comunicado final.PDF', $attachment->original_filename);
        $this->assertSame('application/pdf', $attachment->mime_type);
        $this->assertSame('pdf', $attachment->extension);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_active_source_modes_determine_initial_status(): void
    {
        foreach ([
            ProcessingMode::Automatic->value => PressReleaseStatus::Received,
            ProcessingMode::ProcessOnly->value => PressReleaseStatus::Received,
            ProcessingMode::Review->value => PressReleaseStatus::Received,
            ProcessingMode::Ignore->value => PressReleaseStatus::Ignored,
        ] as $mode => $status) {
            $source = PressSource::factory()->create([
                'email' => "{$mode}@example.com",
                'processing_mode' => $mode,
            ]);

            $result = $this->service->ingest($this->sourceData(
                messageId: "<{$mode}@mail.test>",
                senderEmail: "{$mode}@example.com",
            ));

            $this->assertTrue($result->pressRelease->pressSource->is($source));
            $this->assertSame($status, $result->pressRelease->processing_status);
        }
    }

    public function test_inactive_source_is_treated_as_unmatched(): void
    {
        PressSource::factory()->inactive()->create(['email' => 'press@example.com']);

        $result = $this->service->ingest($this->sourceData());

        $this->assertSame(PressReleaseStatus::UnmatchedSender, $result->pressRelease->processing_status);
    }

    public function test_message_id_is_normalized_and_ingestion_is_idempotent(): void
    {
        $first = $this->service->ingest($this->sourceData(messageId: ' <ABC@MAIL.TEST> '));
        $second = $this->service->ingest($this->sourceData(messageId: '<abc@mail.test>'));

        $this->assertTrue($first->wasCreated);
        $this->assertFalse($second->wasCreated);
        $this->assertTrue($first->pressRelease->is($second->pressRelease));
        $this->assertDatabaseCount('press_releases', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_declared_extension_and_mime_cannot_disguise_invalid_content(): void
    {
        $this->expectException(InvalidPressReleaseAttachment::class);

        $this->service->ingest($this->sourceData(attachments: [
            new PressReleaseAttachmentData('documento.pdf', '<?php echo "unsafe";', 'application/pdf'),
        ]));
    }

    public function test_invalid_attachment_rolls_back_database_and_stored_files(): void
    {
        try {
            $this->service->ingest($this->sourceData(attachments: [
                new PressReleaseAttachmentData('valid.pdf', $this->pdf()),
                new PressReleaseAttachmentData('invalid.exe', 'MZ executable'),
            ]));
            $this->fail('La importación debería haber fallado.');
        } catch (InvalidPressReleaseAttachment) {
            $this->assertDatabaseCount('press_releases', 0);
            $this->assertDatabaseCount('press_release_attachments', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_attachment_size_limit_is_enforced(): void
    {
        config()->set('press_releases.max_attachment_bytes', 5);
        $this->expectException(InvalidPressReleaseAttachment::class);

        $this->service->ingest($this->sourceData(attachments: [
            new PressReleaseAttachmentData('large.pdf', $this->pdf()),
        ]));
    }

    public function test_invalid_sender_subject_message_id_and_raw_message_are_rejected(): void
    {
        foreach ([
            $this->sourceData(senderEmail: 'invalid'),
            $this->sourceData(messageId: 'has spaces'),
            $this->sourceData(subject: '   '),
            $this->sourceData(rawMessage: ''),
        ] as $data) {
            try {
                $this->service->ingest($data);
                $this->fail('Los datos deberían haberse rechazado.');
            } catch (InvalidArgumentException) {
                $this->assertDatabaseCount('press_releases', 0);
            }
        }
    }

    public function test_fetch_command_imports_and_acknowledges_messages_through_the_contract(): void
    {
        $message = $this->sourceData();
        $mailbox = new class($message) implements MailboxClientInterface
        {
            public array $acknowledged = [];

            public function __construct(private readonly PressReleaseSourceData $message) {}

            public function messages(): iterable
            {
                yield $this->message;
            }

            public function markImported(string $messageId): void
            {
                $this->acknowledged[] = $messageId;
            }
        };
        app()->instance(MailboxClientInterface::class, $mailbox);

        $this->artisan('press-releases:fetch')
            ->expectsOutput('Importación terminada: 1 nuevos, 0 duplicados, 0 errores.')
            ->assertSuccessful();

        $this->assertDatabaseCount('press_releases', 1);
        $this->assertSame([$message->messageId], $mailbox->acknowledged);
    }

    public function test_fetch_command_fails_cleanly_without_a_mailbox_adapter(): void
    {
        $this->artisan('press-releases:fetch')
            ->expectsOutput('No hay ningún proveedor de correo configurado para MailboxClientInterface.')
            ->assertFailed();
    }

    /** @param list<PressReleaseAttachmentData> $attachments */
    private function sourceData(
        string $messageId = '<message@mail.test>',
        string $senderEmail = 'Press@Example.com',
        string $subject = 'Nueva nota de prensa',
        string $rawMessage = "Message-ID: <message@mail.test>\r\nSubject: Nueva nota\r\n\r\nContenido",
        array $attachments = [],
    ): PressReleaseSourceData {
        return new PressReleaseSourceData(
            messageId: $messageId,
            senderEmail: $senderEmail,
            senderName: 'Gabinete de prensa',
            subject: $subject,
            receivedAt: CarbonImmutable::parse('2026-09-21 10:00:00', 'Europe/Madrid'),
            bodyText: 'Contenido de la nota',
            bodyHtml: '<p>Contenido de la nota</p>',
            rawMessage: $rawMessage,
            attachments: $attachments,
        );
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    }
}
