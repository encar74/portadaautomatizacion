<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIArticleValidationRequest;
use App\DTOs\AIProviderResponse;
use App\DTOs\AIValidationProviderResponse;
use App\DTOs\ArticleValidationData;
use App\DTOs\GeneratedArticleData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class OpenAIProvider implements AIProviderInterface
{
    public function generateArticle(AIArticleGenerationRequest $request): AIProviderResponse
    {
        $model = $this->model();
        $response = $this->client()->post('/responses', [
            'model' => $model,
            'instructions' => $request->systemPrompt,
            'input' => $request->generationPrompt
                ."\n\nSOURCE_CONTENT_BEGIN\n"
                .$request->sourceText
                ."\nSOURCE_CONTENT_END",
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'portada_generated_article',
                    'strict' => true,
                    'schema' => $this->generationSchema(),
                ],
            ],
            'max_output_tokens' => config('ai.max_output_tokens'),
            'store' => false,
            'metadata' => ['prompt_version' => $request->promptVersion],
        ])->throw()->json();

        $this->assertCompleted($response, 'generación');
        $text = $this->outputText($response, 'generar la noticia');

        try {
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('OpenAI devolvió una respuesta JSON inválida.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('OpenAI devolvió una respuesta estructurada inválida.');
        }

        return new AIProviderResponse(
            article: GeneratedArticleData::fromArray($data),
            model: (string) ($response['model'] ?? $model),
            inputTokens: $this->nullableInt($response['usage']['input_tokens'] ?? null),
            outputTokens: $this->nullableInt($response['usage']['output_tokens'] ?? null),
            totalTokens: $this->nullableInt($response['usage']['total_tokens'] ?? null),
        );
    }

    public function validateArticle(AIArticleValidationRequest $request): AIValidationProviderResponse
    {
        $model = $this->validationModel();
        $response = $this->client()->post('/responses', [
            'model' => $model,
            'instructions' => $request->systemPrompt,
            'input' => $request->validationPrompt
                ."\n\nSOURCE_CONTENT_BEGIN\n"
                .$request->sourceText
                ."\nSOURCE_CONTENT_END\n\nGENERATED_ARTICLE_BEGIN\n"
                .$request->articleText
                ."\nGENERATED_ARTICLE_END",
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'portada_article_validation',
                    'strict' => true,
                    'schema' => $this->validationSchema(),
                ],
            ],
            'max_output_tokens' => config('ai.max_output_tokens'),
            'store' => false,
            'metadata' => ['prompt_version' => $request->promptVersion, 'operation' => 'validation'],
        ])->throw()->json();

        $this->assertCompleted($response, 'validación');
        $text = $this->outputText($response, 'validar la noticia');

        try {
            $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('OpenAI devolvió una validación JSON inválida.', previous: $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('OpenAI devolvió una validación estructurada inválida.');
        }

        return new AIValidationProviderResponse(
            validation: ArticleValidationData::fromArray($data),
            model: (string) ($response['model'] ?? $model),
            inputTokens: $this->nullableInt($response['usage']['input_tokens'] ?? null),
            outputTokens: $this->nullableInt($response['usage']['output_tokens'] ?? null),
            totalTokens: $this->nullableInt($response['usage']['total_tokens'] ?? null),
        );
    }

    public function name(): string
    {
        return 'openai';
    }

    private function client(): PendingRequest
    {
        $apiKey = config('ai.openai.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Falta configurar OPENAI_API_KEY.');
        }

        return Http::baseUrl(rtrim((string) config('ai.openai.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout(config('ai.timeout'));
    }

    private function model(): string
    {
        $model = config('ai.models.generation');
        if (! is_string($model) || $model === '') {
            throw new RuntimeException('Falta configurar AI_MODEL_GENERATION.');
        }

        return $model;
    }

    private function validationModel(): string
    {
        $model = config('ai.models.validation');
        if (! is_string($model) || $model === '') {
            throw new RuntimeException('Falta configurar AI_MODEL_VALIDATION.');
        }

        return $model;
    }

    private function outputText(array $response, string $operation): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException("OpenAI rechazó {$operation}: ".($content['refusal'] ?? 'sin motivo'));
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI no devolvió contenido de texto utilizable.');
    }

    private function assertCompleted(array $response, string $operation): void
    {
        if (($response['status'] ?? null) !== 'completed') {
            $reason = $response['incomplete_details']['reason']
                ?? $response['error']['message']
                ?? 'estado desconocido';

            throw new RuntimeException("OpenAI no completó la {$operation}: {$reason}.");
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function generationSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'headline', 'subheadline', 'lead', 'body', 'seo_title', 'seo_description',
                'suggested_category', 'suggested_tags', 'location',
            ],
            'properties' => [
                'headline' => ['type' => 'string'],
                'subheadline' => ['type' => ['string', 'null']],
                'lead' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'seo_title' => ['type' => 'string'],
                'seo_description' => ['type' => 'string'],
                'suggested_category' => ['type' => ['string', 'null']],
                'suggested_tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'location' => ['type' => ['string', 'null']],
            ],
        ];
    }

    private function validationSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['risk', 'issues', 'warnings'],
            'properties' => [
                'risk' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high'],
                    'description' => 'Riesgo factual global de la noticia respecto de la fuente.',
                ],
                'issues' => [
                    'type' => 'array',
                    'description' => 'Incidencias factuales concretas. Debe estar vacío cuando no existan.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'severity', 'claim', 'explanation'],
                        'properties' => [
                            'type' => ['type' => 'string', 'description' => 'Tipo breve de incidencia factual.'],
                            'severity' => ['type' => 'string', 'enum' => ['medium', 'high']],
                            'claim' => ['type' => 'string', 'description' => 'Afirmación exacta o resumida afectada.'],
                            'explanation' => ['type' => 'string', 'description' => 'Comparación concreta con la fuente.'],
                        ],
                    ],
                ],
                'warnings' => [
                    'type' => 'array',
                    'description' => 'Observaciones editoriales no consideradas incidencias factuales.',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
