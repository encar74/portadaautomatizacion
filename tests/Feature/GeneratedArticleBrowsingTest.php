<?php

namespace Tests\Feature;

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
}
