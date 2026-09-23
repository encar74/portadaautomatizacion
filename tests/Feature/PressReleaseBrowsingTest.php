<?php

namespace Tests\Feature;

use App\Enums\PressReleaseStatus;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Models\PressReleaseAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PressReleaseBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_browse_or_download_emails(): void
    {
        $attachment = PressReleaseAttachment::factory()->create();
        foreach ([
            route('press-releases.index'),
            route('press-releases.show', $attachment->press_release_id),
            route('press-releases.attachments.download', [$attachment->press_release_id, $attachment]),
        ] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_list_is_searchable_filterable_and_ordered_by_received_date(): void
    {
        $older = PressRelease::factory()->create(['subject' => 'Boletín antiguo', 'received_at' => now()->subDay(), 'processing_status' => PressReleaseStatus::UnmatchedSender]);
        $newer = PressRelease::factory()->create(['subject' => 'Boletín reciente', 'received_at' => now(), 'processing_status' => PressReleaseStatus::Received]);
        $this->actingAs(User::factory()->create());
        $this->get(route('press-releases.index'))->assertOk()->assertSeeInOrder([$newer->subject, $older->subject]);
        $this->get(route('press-releases.index', ['q' => 'Boletín', 'status' => 'unmatched_sender']))
            ->assertOk()->assertSee($older->subject)->assertDontSee($newer->subject)->assertSee('Sin fuente asociada');
    }

    public function test_html_only_email_is_shown_as_escaped_text(): void
    {
        $release = PressRelease::factory()->create([
            'body_text' => null,
            'body_html' => '<p>Contenido del boletín</p><script>alert("unsafe")</script><img src="https://example.com/tracker"><p>&lt;script&gt;texto&lt;/script&gt;</p>',
        ]);
        $this->actingAs(User::factory()->create())->get(route('press-releases.show', $release))
            ->assertOk()->assertSee('Contenido del boletín')->assertSee('&lt;script&gt;texto&lt;/script&gt;', false)
            ->assertDontSee('alert("unsafe")', false)->assertDontSee('https://example.com/tracker', false);
    }

    public function test_list_shows_when_an_email_has_a_generated_article(): void
    {
        $release = PressRelease::factory()->create();
        GeneratedArticle::factory()->for($release)->create(['headline' => 'Noticia generada']);

        $this->actingAs(User::factory()->create())
            ->get(route('press-releases.index'))
            ->assertOk()
            ->assertSee('Ver artículo')
            ->assertSee('Riesgo bajo');
    }

    public function test_download_requires_matching_email_and_existing_file(): void
    {
        Storage::fake('mail-test');
        config(['press_releases.disk' => 'mail-test']);
        $attachment = PressReleaseAttachment::factory()->create();
        Storage::disk('mail-test')->put($attachment->storage_path, 'document contents');
        $this->actingAs(User::factory()->create());
        $url = route('press-releases.attachments.download', [$attachment->press_release_id, $attachment]);
        $this->get($url)->assertDownload($attachment->original_filename);
        $other = PressRelease::factory()->create();
        $this->get(route('press-releases.attachments.download', [$other, $attachment]))->assertNotFound();
        Storage::disk('mail-test')->delete($attachment->storage_path);
        $this->get($url)->assertNotFound();
    }

    public function test_blocked_attachment_cannot_be_downloaded(): void
    {
        Storage::fake('mail-test');
        config(['press_releases.disk' => 'mail-test']);
        $attachment = PressReleaseAttachment::factory()->create([
            'is_blocked' => true,
            'blocked_reason' => 'PDF cifrado o protegido con contraseña.',
        ]);
        Storage::disk('mail-test')->put($attachment->storage_path, 'encrypted contents');

        $this->actingAs(User::factory()->create())
            ->get(route('press-releases.attachments.download', [$attachment->press_release_id, $attachment]))
            ->assertStatus(423);
    }
}
