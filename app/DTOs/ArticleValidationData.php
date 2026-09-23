<?php

namespace App\DTOs;

use App\Enums\ValidationRisk;
use InvalidArgumentException;

final readonly class ArticleValidationData
{
    /**
     * @param  list<array{type: string, severity: string, claim: string, explanation: string}>  $issues
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ValidationRisk $risk,
        public array $issues,
        public array $warnings,
    ) {}

    public static function fromArray(array $data): self
    {
        if (! is_string($data['risk'] ?? null) || ValidationRisk::tryFrom($data['risk']) === null) {
            throw new InvalidArgumentException('La validación de IA contiene un nivel de riesgo inválido.');
        }
        if (! is_array($data['issues'] ?? null) || ! is_array($data['warnings'] ?? null)) {
            throw new InvalidArgumentException('La validación de IA no contiene listas válidas.');
        }

        $issues = [];
        foreach ($data['issues'] as $issue) {
            if (! is_array($issue)) {
                throw new InvalidArgumentException('La validación de IA contiene una incidencia inválida.');
            }
            foreach (['type', 'severity', 'claim', 'explanation'] as $key) {
                if (! is_string($issue[$key] ?? null) || trim($issue[$key]) === '') {
                    throw new InvalidArgumentException("La incidencia no contiene {$key} válido.");
                }
            }
            if (! in_array($issue['severity'], ['medium', 'high'], true)) {
                throw new InvalidArgumentException('La incidencia contiene una gravedad inválida.');
            }
            $issues[] = [
                'type' => trim($issue['type']),
                'severity' => $issue['severity'],
                'claim' => trim($issue['claim']),
                'explanation' => trim($issue['explanation']),
            ];
        }

        if (array_filter($data['warnings'], fn ($warning) => ! is_string($warning)) !== []) {
            throw new InvalidArgumentException('La validación de IA contiene advertencias inválidas.');
        }

        $risk = ValidationRisk::from($data['risk']);
        if ($risk === ValidationRisk::Low && $issues !== []) {
            throw new InvalidArgumentException('Una validación de riesgo bajo no puede contener incidencias factuales.');
        }
        if ($risk === ValidationRisk::High && ! collect($issues)->contains('severity', 'high')) {
            throw new InvalidArgumentException('Una validación de riesgo alto debe identificar una incidencia grave.');
        }

        return new self(
            risk: $risk,
            issues: $issues,
            warnings: array_values(array_filter(array_map('trim', $data['warnings']))),
        );
    }
}
