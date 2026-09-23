<?php

namespace Tests\Feature;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIArticleValidationRequest;
use App\DTOs\AIProviderResponse;
use App\DTOs\AIValidationProviderResponse;
use App\DTOs\GeneratedArticleData;
use App\Enums\AIExecutionStatus;
use App\Enums\ArticleVersionOrigin;
use App\Enums\PressReleaseStatus;
use App\Jobs\GenerateArticle;
use App\Jobs\ValidateArticle;
use App\Models\GeneratedArticle;
use App\Models\PressRelease;
use App\Services\AI\NewsGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class NewsGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_article_version_and_execution_idempotently(): void
    {
        config()->set('ai.models.generation', 'test-model');
        $provider = new class implements AIProviderInterface
        {
            public int $calls = 0;

            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                $this->calls++;
                if (! str_contains($request->systemPrompt, 'contenido')) {
                    throw new RuntimeException('Prompt no cargado.');
                }

                return new AIProviderResponse(
                    article: new GeneratedArticleData(
                        headline: 'Nuevo servicio municipal',
                        subheadline: null,
                        lead: 'El Ayuntamiento presenta el servicio.',
                        body: 'El servicio comenzará el próximo lunes.',
                        seoTitle: 'Nuevo servicio municipal',
                        seoDescription: 'Información sobre el nuevo servicio municipal.',
                        suggestedCategory: 'Local',
                        suggestedTags: ['Ayuntamiento', 'Servicios'],
                        location: 'Villena',
                    ),
                    model: 'test-model-2026',
                    inputTokens: 120,
                    outputTokens: 80,
                    totalTokens: 200,
                );
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                throw new RuntimeException('No utilizado.');
            }

            public function name(): string
            {
                return 'fake';
            }
        };
        app()->instance(AIProviderInterface::class, $provider);
        $release = PressRelease::factory()->create([
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => '[EMAIL_BODY] Texto fuente',
            'content_extracted_at' => now(),
        ]);
        $service = app(NewsGenerationService::class);

        $article = $service->generate($release);
        $sameArticle = $service->generate($release);

        $this->assertTrue($article->is($sameArticle));
        $this->assertSame(1, $provider->calls);
        $this->assertSame('Nuevo servicio municipal', $article->headline);
        $this->assertNull($article->validation_risk);
        $version = $article->versions->sole();
        $this->assertSame(1, $version->version);
        $this->assertSame(ArticleVersionOrigin::AI, $version->origin);
        $this->assertSame('test-model-2026', $version->ai_model);
        $execution = $release->aiExecutions()->sole();
        $this->assertSame(AIExecutionStatus::Successful, $execution->status);
        $this->assertSame(200, $execution->total_tokens);
        $this->assertSame($article->id, $execution->generated_article_id);
    }

    public function test_provider_failure_is_recorded_without_creating_an_article(): void
    {
        config()->set('ai.models.generation', 'test-model');
        app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
        {
            public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
            {
                throw new RuntimeException('Proveedor no disponible.');
            }

            public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
            {
                throw new RuntimeException('No utilizado.');
            }

            public function name(): string
            {
                return 'fake';
            }
        });
        $release = PressRelease::factory()->create([
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => 'Texto fuente',
            'content_extracted_at' => now(),
        ]);

        try {
            app(NewsGenerationService::class)->generate($release);
            $this->fail('La generación debería haber fallado.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Proveedor no disponible.', $exception->getMessage());
        }

        $this->assertDatabaseCount('generated_articles', 0);
        $execution = $release->aiExecutions()->sole();
        $this->assertSame(AIExecutionStatus::Failed, $execution->status);
        $this->assertSame('Proveedor no disponible.', $execution->error_message);
    }

    public function test_generation_job_dispatches_validation_when_enabled(): void
    {
        Queue::fake();
        config()->set('ai.validation_enabled', true);
        $release = PressRelease::factory()->create([
            'processing_status' => PressReleaseStatus::Processed,
            'source_text' => 'Texto fuente',
            'content_extracted_at' => now(),
        ]);
        $article = GeneratedArticle::factory()->create([
            'press_release_id' => $release->id,
            'validation_risk' => null,
        ]);

        (new GenerateArticle($release->id))->handle(app(NewsGenerationService::class));

        Queue::assertPushed(
            ValidateArticle::class,
            fn (ValidateArticle $job) => $job->generatedArticleId === $article->id,
        );
    }
}
