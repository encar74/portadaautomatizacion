<?php

namespace App\DTOs;

use InvalidArgumentException;

final readonly class GeneratedArticleData
{
    /** @param list<string> $suggestedTags */
    public function __construct(
        public string $headline,
        public ?string $subheadline,
        public string $lead,
        public string $body,
        public string $seoTitle,
        public string $seoDescription,
        public ?string $suggestedCategory,
        public array $suggestedTags,
        public ?string $location,
    ) {}

    public static function fromArray(array $data): self
    {
        foreach (['headline', 'lead', 'body', 'seo_title', 'seo_description', 'suggested_tags'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("La respuesta de IA no contiene {$key}.");
            }
        }

        if (! is_array($data['suggested_tags']) || array_filter($data['suggested_tags'], fn ($tag) => ! is_string($tag)) !== []) {
            throw new InvalidArgumentException('La respuesta de IA contiene etiquetas inválidas.');
        }

        foreach (['headline', 'lead', 'body', 'seo_title', 'seo_description'] as $key) {
            if (! is_string($data[$key]) || trim($data[$key]) === '') {
                throw new InvalidArgumentException("La respuesta de IA contiene un valor inválido para {$key}.");
            }
        }

        return new self(
            headline: trim($data['headline']),
            subheadline: self::nullableString($data['subheadline'] ?? null),
            lead: trim($data['lead']),
            body: trim($data['body']),
            seoTitle: trim($data['seo_title']),
            seoDescription: trim($data['seo_description']),
            suggestedCategory: self::nullableString($data['suggested_category'] ?? null),
            suggestedTags: array_values(array_map('trim', $data['suggested_tags'])),
            location: self::nullableString($data['location'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
