<?php

namespace Tests\Feature;

use App\Enums\ArticleVersionOrigin;
use App\Enums\AttachmentType;
use App\Enums\PressReleaseStatus;
use App\Enums\PressSourceMatchType;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\AIExecution;
use App\Models\ArticleVersion;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Models\PressReleaseAttachment;
use App\Models\PressSource;
use App\Models\WordPressPublication;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_relationships_are_connected(): void
    {
        $source = PressSource::factory()->create();
        $release = PressRelease::factory()->for($source)->create();
        $attachment = PressReleaseAttachment::factory()->for($release)->create();
        $article = GeneratedArticle::factory()->for($release)->create();
        $version = ArticleVersion::factory()->for($article)->create();
        $execution = AIExecution::factory()->for($release)->for($article)->create();
        $publication = WordPressPublication::factory()->for($article)->create();

        $this->assertTrue($release->pressSource->is($source));
        $this->assertTrue($source->pressReleases->first()->is($release));
        $this->assertTrue($release->attachments->first()->is($attachment));
        $this->assertTrue($release->generatedArticle->is($article));
        $this->assertTrue($article->versions->first()->is($version));
        $this->assertTrue($article->aiExecutions->first()->is($execution));
        $this->assertTrue($article->wordpressPublication->is($publication));
    }

    public function test_enum_json_boolean_and_datetime_casts(): void
    {
        $source = PressSource::factory()->create(['default_tags' => ['economía']]);
        $release = PressRelease::factory()->for($source)->create();
        $attachment = PressReleaseAttachment::factory()->for($release)->create();
        $article = GeneratedArticle::factory()->for($release)->create([
            'suggested_tags' => ['empresa'], 'warnings' => ['Revisar cifra'],
            'validation_issues' => [['field' => 'body']],
        ]);
        $version = ArticleVersion::factory()->for($article)->create(['tags' => ['empresa']]);

        $this->assertSame(PressSourceMatchType::ExactEmail, $source->match_type);
        $this->assertSame(ProcessingMode::Automatic, $source->processing_mode);
        $this->assertTrue($source->is_active);
        $this->assertSame(['economía'], $source->default_tags);
        $this->assertSame(PressReleaseStatus::Received, $release->processing_status);
        $this->assertSame(AttachmentType::Document, $attachment->attachment_type);
        $this->assertSame(ValidationRisk::Low, $article->validation_risk);
        $this->assertSame(['empresa'], $article->suggested_tags);
        $this->assertSame(ArticleVersionOrigin::AI, $version->origin);
        $this->assertNotNull($article->generated_at);
    }

    public function test_message_id_is_unique(): void
    {
        PressRelease::factory()->create(['message_id' => '<unique@example.com>']);
        $this->expectException(QueryException::class);
        PressRelease::factory()->create(['message_id' => '<unique@example.com>']);
    }

    public function test_an_article_cannot_have_duplicate_version_numbers(): void
    {
        $article = GeneratedArticle::factory()->create();
        ArticleVersion::factory()->for($article)->create(['version' => 1]);
        $this->expectException(QueryException::class);
        ArticleVersion::factory()->for($article)->create(['version' => 1]);
    }

    public function test_only_one_generated_article_and_wordpress_publication_are_allowed(): void
    {
        $release = PressRelease::factory()->create();
        $article = GeneratedArticle::factory()->for($release)->create();
        WordPressPublication::factory()->for($article)->create(['wordpress_post_id' => 10]);

        $this->expectException(QueryException::class);
        WordPressPublication::factory()->for($article)->create(['wordpress_post_id' => 11]);
    }
}
