<?php

namespace Tests\Feature;

use App\Enums\AttachmentType;
use App\Enums\PressReleaseStatus;
use App\Jobs\ExtractPressReleaseContent;
use App\Models\PressRelease;
use App\Models\PressReleaseAttachment;
use App\Models\PressSource;
use App\Services\PressReleases\PressReleaseContentExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PressReleaseContentExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config()->set('press_releases.disk', 'local');
    }

    public function test_job_extracts_docx_and_builds_a_bounded_untrusted_source(): void
    {
        $release = PressRelease::factory()->create([
            'subject' => 'Presentación municipal',
            'sender_email' => 'prensa@example.com',
            'sender_name' => 'Gabinete',
            'body_text' => 'Texto del correo',
            'processing_status' => PressReleaseStatus::Queued,
        ]);
        $attachment = $this->attachment($release, 'nota.docx', 'docx');
        Storage::disk('local')->put($attachment->storage_path, $this->docx('Contenido del documento'));

        $job = new ExtractPressReleaseContent($release->id);
        $job->handle(app(PressReleaseContentExtractionService::class));

        $release->refresh();
        $attachment->refresh();
        $this->assertSame(PressReleaseStatus::Processed, $release->processing_status);
        $this->assertNotNull($release->content_extracted_at);
        $this->assertSame('Contenido del documento', $attachment->extracted_text);
        $this->assertStringContainsString('[EMAIL_BODY]', $release->source_text);
        $this->assertStringContainsString('Texto del correo', $release->source_text);
        $this->assertStringContainsString('[ATTACHMENT filename="nota.docx"]', $release->source_text);
        $this->assertStringContainsString('Contenido del documento', $release->source_text);
    }

    public function test_images_are_preserved_but_not_treated_as_text(): void
    {
        $release = PressRelease::factory()->create(['processing_status' => PressReleaseStatus::Queued]);
        $attachment = $this->attachment($release, 'foto.jpg', 'jpg', AttachmentType::Image);
        Storage::disk('local')->put($attachment->storage_path, 'image bytes');

        (new ExtractPressReleaseContent($release->id))->handle(app(PressReleaseContentExtractionService::class));

        $this->assertNull($attachment->fresh()->extracted_text);
        $this->assertStringNotContainsString('foto.jpg', $release->fresh()->source_text);
    }

    public function test_job_extracts_text_from_pdf(): void
    {
        $release = PressRelease::factory()->create(['processing_status' => PressReleaseStatus::Queued]);
        $attachment = $this->attachment($release, 'comunicado.pdf', 'pdf');
        Storage::disk('local')->put($attachment->storage_path, $this->pdf('Texto extraido del PDF'));

        (new ExtractPressReleaseContent($release->id))->handle(app(PressReleaseContentExtractionService::class));

        $this->assertStringContainsString('Texto extraido del PDF', $attachment->fresh()->extracted_text);
        $this->assertStringContainsString('Texto extraido del PDF', $release->fresh()->source_text);
    }

    public function test_job_is_idempotent_after_success(): void
    {
        $extractedAt = now()->subHour();
        $release = PressRelease::factory()->create([
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => 'Fuente ya preparada',
            'content_extracted_at' => $extractedAt,
        ]);

        (new ExtractPressReleaseContent($release->id))->handle(app(PressReleaseContentExtractionService::class));

        $release->refresh();
        $this->assertSame('Fuente ya preparada', $release->source_text);
        $this->assertSame($extractedAt->timestamp, $release->content_extracted_at->timestamp);
    }

    public function test_extraction_failure_marks_the_release_with_a_useful_error(): void
    {
        $release = PressRelease::factory()->create(['processing_status' => PressReleaseStatus::Queued]);
        $this->attachment($release, 'ausente.pdf', 'pdf');

        try {
            (new ExtractPressReleaseContent($release->id))->handle(app(PressReleaseContentExtractionService::class));
            $this->fail('La extracción debería haber fallado.');
        } catch (\Throwable) {
            $release->refresh();
            $this->assertSame(PressReleaseStatus::Error, $release->processing_status);
            $this->assertNotEmpty($release->error_message);
        }
    }

    public function test_command_queues_only_pending_releases_from_processable_sources(): void
    {
        Queue::fake();
        $source = PressSource::factory()->create();
        $pending = PressRelease::factory()->create([
            'press_source_id' => $source->id,
            'processing_status' => PressReleaseStatus::Received,
        ]);
        PressRelease::factory()->create(['processing_status' => PressReleaseStatus::UnmatchedSender]);
        PressRelease::factory()->create([
            'press_source_id' => $source->id,
            'processing_status' => PressReleaseStatus::Processed,
        ]);

        $this->artisan('press-releases:queue-extraction')
            ->expectsOutput('Extracciones encoladas: 1.')
            ->assertSuccessful();

        $this->assertSame(PressReleaseStatus::Queued, $pending->fresh()->processing_status);
        Queue::assertPushed(
            ExtractPressReleaseContent::class,
            fn (ExtractPressReleaseContent $job) => $job->pressReleaseId === $pending->id,
        );
    }

    private function attachment(
        PressRelease $release,
        string $filename,
        string $extension,
        AttachmentType $type = AttachmentType::Document,
    ): PressReleaseAttachment {
        return PressReleaseAttachment::factory()->create([
            'press_release_id' => $release->id,
            'original_filename' => $filename,
            'stored_filename' => "stored.{$extension}",
            'mime_type' => $extension === 'docx'
                ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                : 'application/pdf',
            'extension' => $extension,
            'attachment_type' => $type,
            'storage_path' => "press-releases/{$release->id}/attachments/stored.{$extension}",
        ]);
    }

    private function docx(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test-docx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
        $contents = file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    private function pdf(string $text): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $stream = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";
        $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
