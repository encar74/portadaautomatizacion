<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\AIArticleGenerationRequest;
use App\DTOs\AIProviderResponse;
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
                    'schema' => $this->schema(),
                ],
            ],
            'max_output_tokens' => config('ai.max_output_tokens'),
            'store' => false,
            'metadata' => ['prompt_version' => $request->promptVersion],
        ])->throw()->json();

        $text = $this->outputText($response);

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

    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI rechazó generar la noticia: '.($content['refusal'] ?? 'sin motivo'));
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI no devolvió contenido de texto utilizable.');
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function schema(): array
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
}
