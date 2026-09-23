<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIProviderResponse;
use App\DTOs\GeneratedArticleData;
use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Jobs\ApplyGuidedArticleCorrection;
use App\Jobs\PublishArticleToWordPress;
use App\Jobs\ValidateArticle;
use App\Models\GeneratedArticle;
use App\Models\User;
use App\Services\AI\GuidedArticleCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ArticleEditorialReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_edit_creates_an_audited_version_and_dispatches_validation(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $article = GeneratedArticle::factory()->create(['validation_risk' => ValidationRisk::High]);

        $response = $this->actingAs($user)->put(route('generated-articles.update', $article), [
            'headline' => 'Titular corregido por el periodista',
            'subheadline' => '',
            'lead' => 'Entradilla comprobada.',
            'body' => 'Contenido revisado con la fuente original.',
            'seo_title' => 'Titular SEO corregido',
            'seo_description' => 'Descripción SEO corregida y comprobada.',
            'suggested_category' => 'Local',
            'suggested_tags' => 'Villena, Ayuntamiento',
            'editorial_note' => 'Se ha eliminado una cifra no respaldada.',
        ]);

        $response->assertRedirect(route('press-releases.show', $article->press_release_id).'#articulo');
        $article->refresh();
        $this->assertNull($article->validation_risk);
        $this->assertTrue($article->requires_editorial_approval);
        $version = $article->versions()->sole();
        $this->assertSame(ArticleVersionOrigin::Human, $version->origin);
        $this->assertSame($user->id, $version->created_by);
        $this->assertSame('manual_edit', $article->editorialActions()->sole()->type);
        Queue::assertPushed(ValidateArticle::class, fn ($job) => $job->generatedArticleId === $article->id);
    }

    public function test_journalist_can_request_a_guided_ai_correction(): void
    {
        Queue::fake();
        $article = GeneratedArticle::factory()->create(['validation_risk' => ValidationRisk::High]);

        $this->actingAs(User::factory()->create())
            ->post(route('generated-articles.ai-correction', $article), [
                'instruction' => 'Elimina la cifra que no aparece en la fuente y conserva el resto.',
            ])->assertRedirect();

        $action = $article->editorialActions()->sole();
        $this->assertSame('ai_correction_requested', $action->type);
        $this->assertSame('pending', $action->status);
        Queue::assertPushed(ApplyGuidedArticleCorrection::class, fn ($job) => $job->editorialActionId === $action->id);
    }

    public function test_medium_risk_requires_a_justification_before_wordpress(): void
    {
        Queue::fake();
        $article = GeneratedArticle::factory()->create(['validation_risk' => ValidationRisk::Medium]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('generated-articles.wordpress-draft', $article), ['justification' => 'Corta'])
            ->assertSessionHasErrors('justification');
        Queue::assertNothingPushed();

        $this->actingAs($user)
            ->post(route('generated-articles.wordpress-draft', $article), [
                'justification' => 'He contrastado personalmente el dato con la fuente municipal.',
            ])->assertRedirect();

        $action = $article->editorialActions()->sole();
        $this->assertSame('medium_risk_override', $action->type);
        $this->assertSame('pending', $action->status);
        Queue::assertPushed(PublishArticleToWordPress::class, fn ($job) => $job->allowMediumRisk && $job->editorialActionId === $action->id);
    }

    public function test_guided_ai_correction_creates_a_new_attributed_version(): void
    {
        config()->set('ai.models.generation', 'generation-model');
        $user = User::factory()->create();
        $article = GeneratedArticle::factory()->create([
            'validation_risk' => ValidationRisk::High,
            'validation_issues' => [['claim' => 'Una cifra', 'explanation' => 'No consta']],
        ]);
        $article->pressRelease()->update([
            'source_text' => 'Texto fuente original.',
            'processing_status' => PressReleaseStatus::NeedsReview,
        ]);
        $action = $article->editorialActions()->create([
            'user_id' => $user->id,
            'type' => 'ai_correction_requested',
            'status' => 'pending',
            'risk' => ValidationRisk::High,
            'notes' => 'Elimina la cifra que no consta en el texto fuente.',
        ]);
        $provider = $this->mock(AIProviderInterface::class);
        $provider->shouldReceive('name')->andReturn('fake');
        $provider->shouldReceive('generateArticle')->once()->andReturn(new AIProviderResponse(
            new GeneratedArticleData(
                'Titular corregido', null, 'Entradilla corregida.', 'Cuerpo corregido.',
                'SEO corregido', 'Descripción corregida.', null, [], null,
            ),
            'generation-model', 20, 10, 30,
        ));

        $corrected = app(GuidedArticleCorrectionService::class)->correct($action);

        $this->assertSame('Titular corregido', $corrected->headline);
        $this->assertNull($corrected->validation_risk);
        $this->assertTrue($corrected->requires_editorial_approval);
        $version = $corrected->versions()->sole();
        $this->assertSame(ArticleVersionOrigin::AIGuided, $version->origin);
        $this->assertSame($user->id, $version->created_by);
        $this->assertSame('completed', $action->fresh()->status);
    }

    public function test_high_risk_cannot_be_approved_for_wordpress(): void
    {
        Queue::fake();
        $article = GeneratedArticle::factory()->create(['validation_risk' => ValidationRisk::High]);

        $this->actingAs(User::factory()->create())
            ->post(route('generated-articles.wordpress-draft', $article))
            ->assertSessionHasErrors('publication');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('article_editorial_actions', 0);
    }
}
