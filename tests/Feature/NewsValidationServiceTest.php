<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIArticleValidationRequest;
use App\DTOs\AIProviderResponse;
use App\DTOs\AIValidationProviderResponse;
use App\DTOs\ArticleValidationData;
use App\Enums\AIExecutionStatus;
use App\Enums\PressReleaseStatus;
use App\Enums\ProcessingMode;
use App\Enums\ValidationRisk;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Models\PressSource;
use App\Services\AI\NewsValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NewsValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.models.validation', 'validation-model');
    }

    public function test_low_risk_validation_is_stored_and_is_idempotent(): void
    {
        $provider = $this->provider(new ArticleValidationData(
            risk: ValidationRisk::Low,
            issues: [],
            warnings: ['Revisar el estilo del último párrafo.'],
        ));
        app()->instance(AIProviderInterface::class, $provider);
        $article = $this->article();
        $service = app(NewsValidationService::class);

        $validated = $service->validate($article);
        $same = $service->validate($validated);

        $this->assertTrue($validated->is($same));
        $this->assertSame(1, $provider->calls);
        $this->assertSame(ValidationRisk::Low, $validated->validation_risk);
        $this->assertSame([], $validated->validation_issues);
        $this->assertSame(['Revisar el estilo del último párrafo.'], $validated->warnings);
        $this->assertSame(PressReleaseStatus::Processed, $validated->pressRelease->fresh()->processing_status);
        $execution = $article->aiExecutions()->where('operation', 'validation')->sole();
        $this->assertSame(AIExecutionStatus::Successful, $execution->status);
        $this->assertSame(300, $execution->total_tokens);
    }

    public function test_high_risk_validation_requires_review(): void
    {
        app()->instance(AIProviderInterface::class, $this->provider(new ArticleValidationData(
            risk: ValidationRisk::High,
            issues: [[
                'type' => 'invented_number',
                'severity' => 'high',
                'claim' => 'Asistieron 5.000 personas.',
                'explanation' => 'La fuente no ofrece ninguna cifra de asistencia.',
            ]],
            warnings: [],
        )));

        $validated = app(NewsValidationService::class)->validate($this->article());

        $this->assertSame(ValidationRisk::High, $validated->validation_risk);
        $this->assertSame('invented_number', $validated->validation_issues[0]['type']);
        $this->assertSame(PressReleaseStatus::NeedsReview, $validated->pressRelease->fresh()->processing_status);
    }

    public function test_review_processing_mode_requires_review_even_with_low_risk(): void
    {
        app()->instance(AIProviderInterface::class, $this->provider(new ArticleValidationData(
            ValidationRisk::Low,
            [],
            [],
        )));
        $source = PressSource::factory()->create(['processing_mode' => ProcessingMode::Review]);

        $validated = app(NewsValidationService::class)->validate($this->article($source));

        $this->assertSame(ValidationRisk::Low, $validated->validation_risk);
        $this->assertSame(PressReleaseStatus::NeedsReview, $validated->pressRelease->fresh()->processing_status);
    }

    public function test_low_risk_after_editorial_intervention_waits_for_wordpress_approval(): void
    {
        app()->instance(AIProviderInterface::class, $this->provider(new ArticleValidationData(
            ValidationRisk::Low,
            [],
            [],
        )));
        $article = $this->article();
        $article->update(['requires_editorial_approval' => true]);

        $validated = app(NewsValidationService::class)->validate($article);

        $this->assertSame(ValidationRisk::Low, $validated->validation_risk);
        $this->assertSame(
            PressReleaseStatus::AwaitingWordPressApproval,
            $validated->pressRelease->fresh()->processing_status,
        );
    }

    public function test_provider_failure_is_recorded_without_assigning_risk(): void
    {
        app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
        {
            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                throw new RuntimeException('No utilizado.');
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                throw new RuntimeException('No se pudo validar.');
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        $article = $this->article();

        try {
            app(NewsValidationService::class)->validate($article);
            $this->fail('La validación debería haber fallado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo validar.', $exception->getMessage());
        }

        $this->assertNull($article->fresh()->validation_risk);
        $execution = $article->aiExecutions()->where('operation', 'validation')->sole();
        $this->assertSame(AIExecutionStatus::Failed, $execution->status);
        $this->assertSame('No se pudo validar.', $execution->error_message);
    }

    private function article(?PressSource $source = null): GeneratedArticle
    {
        $source ??= PressSource::factory()->create();
        $release = PressRelease::factory()->create([
            'press_source_id' => $source->id,
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => '[EMAIL_BODY] El Ayuntamiento presenta un servicio.',
            'content_extracted_at' => now(),
        ]);

        return GeneratedArticle::factory()->create([
            'press_release_id' => $release->id,
            'validation_risk' => null,
            'validation_issues' => [],
            'warnings' => [],
        ]);
    }

    private function provider(ArticleValidationData $validation): AIProviderInterface
    {
        return new class($validation) implements AIProviderInterface
        {
            public int $calls = 0;

            public function __construct(private readonly ArticleValidationData $validation) {}

            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                throw new RuntimeException('No utilizado.');
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                $this->calls++;

                return new AIValidationProviderResponse(
                    validation: $this->validation,
                    model: 'validation-model-2026',
                    inputTokens: 200,
                    outputTokens: 100,
                    totalTokens: 300,
                );
            }

            public function name(): string
            {
                return 'fake';
            }
        };
    }
}
