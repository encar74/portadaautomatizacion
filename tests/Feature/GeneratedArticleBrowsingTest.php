<?php

namespace Tests\Feature;

use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratedArticleBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_browse_articles(): void
    {
        $this->get(route('generated-articles.index'))->assertRedirect(route('login'));
    }

    public function test_articles_are_listed_with_risk_status_and_link_to_detail(): void
    {
        $release = PressRelease::factory()->create([
            'subject' => 'Nota sobre movilidad urbana',
            'processing_status' => PressReleaseStatus::NeedsReview,
        ]);
        $article = GeneratedArticle::factory()->for($release)->create([
            'headline' => 'Villena presenta su nuevo plan de movilidad',
            'validation_risk' => ValidationRisk::High,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('generated-articles.index'))
            ->assertOk()
            ->assertSee($article->headline)
            ->assertSee('Riesgo alto')
            ->assertSee('Pendiente de revisión')
            ->assertSee(route('press-releases.show', $release).'#articulo', false);
    }

    public function test_articles_can_be_searched_and_filtered(): void
    {
        $matchingRelease = PressRelease::factory()->create(['processing_status' => PressReleaseStatus::NeedsReview]);
        $matching = GeneratedArticle::factory()->for($matchingRelease)->create([
            'headline' => 'Cultura local y patrimonio',
            'validation_risk' => ValidationRisk::High,
        ]);
        $other = GeneratedArticle::factory()->create([
            'headline' => 'Balance deportivo semanal',
            'validation_risk' => ValidationRisk::Low,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('generated-articles.index', [
                'q' => 'Cultura',
                'risk' => 'high',
                'status' => 'needs_review',
            ]))
            ->assertOk()
            ->assertSee($matching->headline)
            ->assertDontSee($other->headline);
    }

    public function test_journalist_can_see_that_a_low_risk_article_was_repaired_from_high_risk(): void
    {
        $release = PressRelease::factory()->create(['processing_status' => PressReleaseStatus::Processed]);
        $article = GeneratedArticle::factory()->for($release)->create([
            'validation_risk' => ValidationRisk::Low,
            'repair_status' => 'resolved_low',
            'repair_attempted_at' => now(),
            'repaired_at' => now(),
        ]);
        $version = $article->versions()->create([
            'version' => 1,
            'origin' => ArticleVersionOrigin::AI,
            'headline' => $article->headline,
            'lead' => $article->lead,
            'body' => $article->body,
            'seo_title' => $article->seo_title,
            'seo_description' => $article->seo_description,
        ]);
        $article->validationAttempts()->create([
            'article_version_id' => $version->id,
            'sequence' => 1,
            'context' => 'initial',
            'risk' => ValidationRisk::High,
            'issues' => [['claim' => 'Una cifra', 'explanation' => 'No consta en la fuente']],
            'warnings' => [],
            'provider' => 'fake',
            'model' => 'model',
            'prompt_version' => 'v2',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('press-releases.show', $release))
            ->assertOk()
            ->assertSee('Reparado automáticamente después de una validación de riesgo medio o alto')
            ->assertSee('Una cifra')
            ->assertSee('No consta en la fuente');
    }
}
