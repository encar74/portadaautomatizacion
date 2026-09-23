<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIArticleValidationRequest;
use App\DTOs\AIProviderResponse;
use App\DTOs\AIValidationProviderResponse;
use App\DTOs\ArticleValidationData;
use App\DTOs\GeneratedArticleData;
use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Enums\ValidationRisk;
use App\Jobs\PublishArticleToWordPress;
use App\Jobs\RepairArticle;
use App\Jobs\ValidateArticle;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Models\PressSource;
use App\Services\AI\NewsRepairService;
use App\Services\AI\NewsValidationService;
use App\Services\WordPressDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomaticArticleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.models.generation', 'generation-model');
        config()->set('ai.models.validation', 'validation-model');
        config()->set('ai.auto_repair_enabled', true);
    }

    public function test_initial_high_risk_is_recorded_and_dispatches_one_repair(): void
    {
        Queue::fake();
        $article = $this->article();
        app()->instance(AIProviderInterface::class, $this->validationProvider(ValidationRisk::High));

        (new ValidateArticle($article->id))->handle(app(NewsValidationService::class));

        $article->refresh();
        $this->assertSame(ValidationRisk::High, $article->validation_risk);
        $this->assertSame(PressReleaseStatus::Repairing, $article->pressRelease->fresh()->processing_status);
        $this->assertSame('queued', $article->repair_status);
        $this->assertSame(ValidationRisk::High, $article->validationAttempts()->sole()->risk);
        Queue::assertPushed(RepairArticle::class, fn ($job) => $job->generatedArticleId === $article->id);
    }

    public function test_initial_medium_risk_also_dispatches_one_repair(): void
    {
        Queue::fake();
        $article = $this->article();
        app()->instance(AIProviderInterface::class, $this->validationProvider(ValidationRisk::Medium));

        (new ValidateArticle($article->id))->handle(app(NewsValidationService::class));

        $article->refresh();
        $this->assertSame(ValidationRisk::Medium, $article->validation_risk);
        $this->assertSame(PressReleaseStatus::Repairing, $article->pressRelease->fresh()->processing_status);
        $this->assertSame('queued', $article->repair_status);
        $this->assertSame(ValidationRisk::Medium, $article->validationAttempts()->sole()->risk);
        Queue::assertPushed(RepairArticle::class, fn ($job) => $job->generatedArticleId === $article->id);
    }

    public function test_repair_creates_a_new_version_and_cannot_run_twice(): void
    {
        $article = $this->article(['validation_risk' => ValidationRisk::High]);
        $article->versions()->create($this->versionData(1, ArticleVersionOrigin::AI));
        $provider = $this->repairProvider();
        app()->instance(AIProviderInterface::class, $provider);
        $service = app(NewsRepairService::class);

        $repaired = $service->repair($article);
        $same = $service->repair($repaired);

        $this->assertSame(1, $provider->generationCalls);
        $this->assertSame('Titular corregido', $same->headline);
        $this->assertNull($same->validation_risk);
        $this->assertSame('awaiting_validation', $same->repair_status);
        $this->assertSame([ArticleVersionOrigin::AI, ArticleVersionOrigin::AIRepair], $same->versions()->pluck('origin')->all());
    }

    public function test_medium_risk_article_can_be_repaired(): void
    {
        $article = $this->article(['validation_risk' => ValidationRisk::Medium]);
        $article->versions()->create($this->versionData(1, ArticleVersionOrigin::AI));
        $provider = $this->repairProvider();
        app()->instance(AIProviderInterface::class, $provider);

        $repaired = app(NewsRepairService::class)->repair($article);

        $this->assertSame(1, $provider->generationCalls);
        $this->assertNull($repaired->validation_risk);
        $this->assertSame('awaiting_validation', $repaired->repair_status);
    }

    public function test_low_risk_after_repair_is_audited_and_queued_for_wordpress(): void
    {
        Queue::fake();
        config()->set('wordpress.enabled', true);
        $article = $this->article([
            'repair_status' => 'awaiting_validation',
            'repair_attempted_at' => now(),
            'repaired_at' => now(),
        ]);
        $version = $article->versions()->create($this->versionData(2, ArticleVersionOrigin::AIRepair));
        $article->validationAttempts()->create([
            'article_version_id' => $version->id,
            'sequence' => 1,
            'context' => 'initial',
            'risk' => ValidationRisk::High,
            'issues' => [['claim' => 'Dato inventado', 'explanation' => 'No aparece en la fuente']],
            'warnings' => [],
            'provider' => 'fake',
            'model' => 'validation-model',
            'prompt_version' => 'v2',
        ]);
        app()->instance(AIProviderInterface::class, $this->validationProvider(ValidationRisk::Low));

        (new ValidateArticle($article->id))->handle(app(NewsValidationService::class));

        $article->refresh();
        $this->assertSame('resolved_low', $article->repair_status);
        $this->assertSame([ValidationRisk::High, ValidationRisk::Low], $article->validationAttempts()->pluck('risk')->all());
        Queue::assertPushed(PublishArticleToWordPress::class, fn ($job) => $job->generatedArticleId === $article->id);
        Queue::assertNotPushed(RepairArticle::class);
    }

    public function test_persistent_high_risk_stays_in_review_without_another_repair(): void
    {
        Queue::fake();
        $article = $this->article([
            'repair_status' => 'awaiting_validation',
            'repair_attempted_at' => now(),
            'repaired_at' => now(),
        ]);
        app()->instance(AIProviderInterface::class, $this->validationProvider(ValidationRisk::High));

        (new ValidateArticle($article->id))->handle(app(NewsValidationService::class));

        $this->assertSame('requires_review', $article->fresh()->repair_status);
        $this->assertSame(PressReleaseStatus::NeedsReview, $article->pressRelease->fresh()->processing_status);
        Queue::assertNotPushed(RepairArticle::class);
        Queue::assertNotPushed(PublishArticleToWordPress::class);
    }

    public function test_low_risk_article_creates_only_a_wordpress_draft(): void
    {
        config()->set('wordpress.url', 'https://portada.test');
        config()->set('wordpress.username', 'editor');
        config()->set('wordpress.application_password', 'secret');
        Http::fake([
            'https://portada.test/wp-json/wp/v2/posts' => Http::response([
                'id' => 321,
                'link' => 'https://portada.test/?p=321',
            ], 201),
        ]);
        $article = $this->article(['validation_risk' => ValidationRisk::Low]);

        $publication = app(WordPressDraftService::class)->create($article);

        $this->assertSame(321, $publication->wordpress_post_id);
        $this->assertSame('draft', $publication->wordpress_status);
        $this->assertSame(PressReleaseStatus::WordPressDraftCreated, $article->pressRelease->fresh()->processing_status);
        Http::assertSent(fn ($request) => $request['status'] === 'draft' && $request['title'] === $article->headline);
    }

    private function article(array $attributes = []): GeneratedArticle
    {
        $source = PressSource::factory()->create();
        $release = PressRelease::factory()->create([
            'press_source_id' => $source->id,
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => 'Texto fuente verificado.',
            'content_extracted_at' => now(),
        ]);

        return GeneratedArticle::factory()->create(array_merge([
            'press_release_id' => $release->id,
            'validation_risk' => null,
            'validation_issues' => [],
        ], $attributes));
    }

    private function validationProvider(ValidationRisk $risk): AIProviderInterface
    {
        return new class($risk) implements AIProviderInterface
        {
            public function __construct(private readonly ValidationRisk $risk) {}

            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                throw new \RuntimeException('No utilizado.');
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                return new AIValidationProviderResponse(
                    new ArticleValidationData($this->risk, $this->risk === ValidationRisk::High ? [[
                        'type' => 'invented_fact',
                        'severity' => 'high',
                        'claim' => 'Dato inventado',
                        'explanation' => 'No aparece en la fuente',
                    ]] : [], []),
                    'validation-model', 10, 10, 20,
                );
            }

            public function name(): string
            {
                return 'fake';
            }
        };
    }

    private function repairProvider(): AIProviderInterface
    {
        return new class implements AIProviderInterface
        {
            public int $generationCalls = 0;

            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                $this->generationCalls++;

                return new AIProviderResponse(new GeneratedArticleData(
                    'Titular corregido', null, 'Entradilla corregida.', 'Cuerpo corregido.',
                    'SEO corregido', 'Descripción corregida.', 'Local', ['Villena'], 'Villena',
                ), 'generation-model', 20, 20, 40);
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                throw new \RuntimeException('No utilizado.');
            }

            public function name(): string
            {
                return 'fake';
            }
        };
    }

    private function versionData(int $version, ArticleVersionOrigin $origin): array
    {
        return [
            'version' => $version,
            'origin' => $origin,
            'headline' => 'Titular',
            'subheadline' => null,
            'lead' => 'Entradilla',
            'body' => 'Cuerpo',
            'seo_title' => 'SEO',
            'seo_description' => 'Descripción',
            'category' => null,
            'tags' => [],
            'ai_provider' => 'fake',
            'ai_model' => 'model',
            'prompt_version' => 'v2',
        ];
    }
}
