<?php

namespace Tests\Feature;

use App\Enums\PressReleaseStatus;
use App\Models\GeneratedArticle;
use App\Models\WordPressPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanTodayDraftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_preserves_older_drafts_and_published_posts(): void
    {
        config(['wordpress.url' => 'https://wp.example', 'wordpress.username' => 'test', 'wordpress.application_password' => 'test']);
        Storage::fake('local');
        $today = GeneratedArticle::factory()->create(['created_at' => '2026-11-05 12:00:00']);
        $older = GeneratedArticle::factory()->create(['created_at' => '2026-11-04 12:00:00']);
        $published = GeneratedArticle::factory()->create(['created_at' => '2026-11-05 12:00:00']);
        foreach ([$today, $older, $published] as $i => $article) {
            WordPressPublication::factory()->create(['generated_article_id' => $article->id, 'wordpress_post_id' => $i + 10]);
        }
        Http::fake(fn ($request) => Http::response([
            'id' => str_ends_with($request->url(), '/12?context=edit') ? 12 : 10,
            'date_gmt' => '2026-11-05T11:00:00',
            'status' => $request->method() === 'DELETE' ? 'trash' : (str_contains($request->url(), '/12') ? 'publish' : 'draft'),
        ]));
        $this->artisan('articles:clean-drafts', ['date' => '2026-11-05', '--execute' => true])->assertSuccessful();
        $this->assertModelMissing($today);
        $this->assertModelExists($older);
        $this->assertModelExists($published);
        $this->assertSame(PressReleaseStatus::Ignored, $today->pressRelease->fresh()->processing_status);
        Storage::disk('local')->assertExists('cleanup/2026-11-05/article-'.$today->id.'.json');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/11'));
        Http::assertSentCount(3);
    }

    public function test_older_wordpress_draft_is_preserved_even_when_linked_today(): void
    {
        config(['wordpress.url' => 'https://wp.example', 'wordpress.username' => 'test', 'wordpress.application_password' => 'test']);
        $article = GeneratedArticle::factory()->create(['created_at' => '2026-11-05 12:00:00']);
        WordPressPublication::factory()->create(['generated_article_id' => $article->id]);
        Http::fake(['*' => Http::response(['status' => 'draft', 'date_gmt' => '2026-11-04T11:00:00'])]);
        $this->artisan('articles:clean-drafts', ['date' => '2026-11-05', '--execute' => true])->assertSuccessful();
        $this->assertModelExists($article);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_preview_does_not_delete_anything(): void
    {
        config(['wordpress.url' => 'https://wp.example', 'wordpress.username' => 'test', 'wordpress.application_password' => 'test']);
        $article = GeneratedArticle::factory()->create(['created_at' => '2026-11-05 12:00:00']);
        WordPressPublication::factory()->create(['generated_article_id' => $article->id]);
        Http::fake(['*' => Http::response(['status' => 'draft', 'date_gmt' => '2026-11-05T11:00:00'])]);
        $this->artisan('articles:clean-drafts', ['date' => '2026-11-05'])->assertSuccessful();
        $this->assertModelExists($article);
        Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
    }

    public function test_failed_wordpress_deletion_keeps_the_local_article(): void
    {
        config(['wordpress.url' => 'https://wp.example', 'wordpress.username' => 'test', 'wordpress.application_password' => 'test']);
        Storage::fake('local');
        $article = GeneratedArticle::factory()->create(['created_at' => '2026-11-05 12:00:00']);
        WordPressPublication::factory()->create(['generated_article_id' => $article->id]);
        Http::fake(fn ($request) => $request->method() === 'DELETE'
            ? Http::response([], 403)
            : Http::response(['status' => 'draft', 'date_gmt' => '2026-11-05T11:00:00']));
        $this->artisan('articles:clean-drafts', ['date' => '2026-11-05', '--execute' => true])->assertFailed();
        $this->assertModelExists($article);
    }
}
