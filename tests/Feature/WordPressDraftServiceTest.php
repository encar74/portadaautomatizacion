<?php

namespace Tests\Feature;

use App\Enums\AttachmentType;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\PressReleaseAttachment;
use App\Models\PressSource;
use App\Models\WordPressPublication;
use App\Services\WordPressDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WordPressDraftServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('wordpress.url', 'https://portada.test');
        config()->set('wordpress.username', 'editor');
        config()->set('wordpress.application_password', 'secret');
        config()->set('wordpress.sync_taxonomies', true);
        config()->set('wordpress.upload_images', true);
        config()->set('press_releases.disk', 'wordpress-test');
        Storage::fake('wordpress-test');
    }

    public function test_it_uploads_images_resolves_taxonomies_and_never_creates_the_same_post_twice(): void
    {
        $article = $this->automaticArticle([
            'validation_risk' => ValidationRisk::Low,
            'suggested_category' => 'Local',
            'suggested_tags' => ['Actualidad'],
        ]);
        $article->pressRelease->pressSource->update(['default_tags' => ['Municipal']]);
        $image = PressReleaseAttachment::factory()->for($article->pressRelease)->create([
            'attachment_type' => AttachmentType::Image,
            'original_filename' => 'piscina.jpg',
            'stored_filename' => 'safe-image.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'storage_path' => 'images/safe-image.jpg',
        ]);
        Storage::disk('wordpress-test')->put($image->storage_path, 'image bytes');
        $this->fakeWordPress();
        $service = app(WordPressDraftService::class);

        $first = $service->create($article);
        $same = $service->create($article);

        $this->assertTrue($first->is($same));
        $this->assertSame(321, $first->wordpress_post_id);
        $this->assertSame('synced', $first->sync_status);
        $this->assertSame(55, $image->fresh()->wordpress_media_id);
        $createRequests = Http::recorded(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://portada.test/wp-json/wp/v2/posts');
        $this->assertCount(1, $createRequests);
        $payload = $createRequests->first()[0];
        $this->assertSame('draft', $payload['status']);
        $this->assertSame(55, $payload['featured_media']);
        $this->assertSame([7], $payload['categories']);
        $this->assertEqualsCanonicalizing([11, 12], $payload['tags']);
    }

    public function test_changed_article_updates_the_existing_wordpress_post(): void
    {
        config()->set('wordpress.sync_taxonomies', false);
        config()->set('wordpress.upload_images', false);
        $article = $this->automaticArticle();
        $this->fakeWordPress();
        $service = app(WordPressDraftService::class);
        $service->create($article);
        $article->update(['headline' => 'Titular actualizado']);

        $publication = $service->create($article->fresh());

        $this->assertSame(321, $publication->wordpress_post_id);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://portada.test/wp-json/wp/v2/posts/321'
            && $request['title'] === 'Titular actualizado');
    }

    public function test_it_reuses_term_id_when_wordpress_reports_term_exists(): void
    {
        config()->set('wordpress.upload_images', false);
        $article = $this->automaticArticle([
            'suggested_category' => 'Actualidad',
            'suggested_tags' => [],
        ]);
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/categories')) {
                return Http::response([]);
            }
            if ($request->method() === 'POST' && str_contains($request->url(), '/categories')) {
                return Http::response([
                    'code' => 'term_exists',
                    'message' => 'Ya existe en esta taxonomía un término con el nombre y el slug facilitados.',
                    'data' => ['status' => 400, 'term_id' => '44'],
                ], 400);
            }
            if ($request->method() === 'GET' && str_contains($request->url(), '/posts')) {
                return Http::response([]);
            }

            return Http::response(['id' => 321, 'link' => 'https://portada.test/?p=321'], 201);
        });

        $publication = app(WordPressDraftService::class)->create($article);

        $this->assertSame(321, $publication->wordpress_post_id);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/posts')
            && $request['categories'] === [44]);
    }

    public function test_it_recovers_an_existing_remote_post_by_its_idempotency_slug(): void
    {
        config()->set('wordpress.sync_taxonomies', false);
        config()->set('wordpress.upload_images', false);
        $article = $this->automaticArticle();
        WordPressPublication::factory()->for($article)->create([
            'wordpress_post_id' => null,
            'idempotency_key' => 'portada-article-'.$article->id,
            'sync_status' => 'failed',
        ]);
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response([['id' => 987, 'slug' => 'portada-article-1']]);
            }

            return Http::response(['id' => 987, 'link' => 'https://portada.test/?p=987']);
        });

        $publication = app(WordPressDraftService::class)->create($article);

        $this->assertSame(987, $publication->wordpress_post_id);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://portada.test/wp-json/wp/v2/posts/987');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === 'https://portada.test/wp-json/wp/v2/posts');
    }

    public function test_sync_failure_is_persisted_for_diagnosis_and_retry(): void
    {
        config()->set('wordpress.sync_taxonomies', false);
        config()->set('wordpress.upload_images', false);
        $article = $this->automaticArticle();
        Http::fake(fn () => Http::response(['message' => 'WordPress no disponible'], 503));

        try {
            app(WordPressDraftService::class)->create($article);
            $this->fail('La sincronización debería haber fallado.');
        } catch (\Throwable) {
            $publication = $article->wordpressPublication()->sole();
            $this->assertSame('failed', $publication->sync_status);
            $this->assertNotNull($publication->last_error);
            $this->assertNotNull($publication->last_attempted_at);
            $this->assertSame(PressReleaseStatus::AwaitingWordPressApproval, $article->pressRelease->fresh()->processing_status);
        }
    }

    private function fakeWordPress(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if ($request->method() === 'GET' && str_contains($url, '/categories')) {
                return Http::response([['id' => 7, 'name' => 'Local']]);
            }
            if ($request->method() === 'GET' && str_contains($url, '/tags')) {
                return Http::response([['id' => 11, 'name' => 'Actualidad'], ['id' => 12, 'name' => 'Municipal']]);
            }
            if ($request->method() === 'POST' && str_contains($url, '/media')) {
                return Http::response(['id' => 55], 201);
            }
            if ($request->method() === 'GET' && str_contains($url, '/posts')) {
                return Http::response([]);
            }
            if ($request->method() === 'POST' && str_ends_with($url, '/posts')) {
                return Http::response(['id' => 321, 'link' => 'https://portada.test/?p=321'], 201);
            }
            if ($request->method() === 'POST' && str_ends_with($url, '/posts/321')) {
                return Http::response(['id' => 321, 'link' => 'https://portada.test/?p=321']);
            }

            return Http::response([], 404);
        });
    }

    private function automaticArticle(array $attributes = []): GeneratedArticle
    {
        $article = GeneratedArticle::factory()->create(array_merge(['validation_risk' => ValidationRisk::Low], $attributes));
        $source = PressSource::factory()->create();
        $article->pressRelease()->update(['press_source_id' => $source->id]);

        return $article->fresh();
    }
}
