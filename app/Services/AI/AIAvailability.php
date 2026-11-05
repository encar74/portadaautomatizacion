<?php

namespace App\Services\AI;

use App\Enums\AIExecutionStatus;
use App\Models\AIExecution;
use Illuminate\Http\Client\RequestException;
use Throwable;

class AIAvailability
{
    public static function errorCode(Throwable $exception): ?string
    {
        if (! $exception instanceof RequestException) {
            return null;
        }

        $code = $exception->response->json('error.code') ?? $exception->response->json('error.type');

        return is_string($code) ? mb_substr($code, 0, 100) : null;
    }

    public function indicator(): array
    {
        $state = ['tone' => 'gray', 'label' => 'Sin comprobar', 'detail' => 'Se actualiza al procesar artículos.', 'checked_at' => null];
        if (config('ai.provider') !== 'openai' || ! filled(config('ai.openai.api_key'))) {
            return array_replace($state, ['label' => 'Sin configurar', 'detail' => 'La conexión con OpenAI no está configurada.']);
        }
        $execution = AIExecution::query()->where('provider', 'openai')
            ->whereIn('status', ['successful', 'failed'])
            ->orderByRaw('COALESCE(finished_at, created_at) DESC')->orderByDesc('id')->first();
        if ($execution === null) {
            return $state;
        }
        $state['checked_at'] = $execution->finished_at ?? $execution->created_at;
        if ($execution->status === AIExecutionStatus::Successful) {
            if ($state['checked_at']->lt(now()->subDay())) {
                return array_replace($state, ['label' => 'Sin comprobación reciente', 'detail' => 'La última petición fue correcta. No hay información reciente sobre el saldo.']);
            }

            return array_replace($state, ['tone' => 'green', 'label' => 'Operativa', 'detail' => 'La última petición funcionó. No indica cuánto saldo queda.']);
        }

        return array_replace($state, match ($execution->provider_error_code) {
            'credit_balance_exhausted' => ['tone' => 'red', 'label' => 'Saldo agotado', 'detail' => 'OpenAI indicó que no quedan créditos. Revisa la facturación.'],
            'insufficient_quota', 'organization_usage_limit_exceeded', 'billing_hard_limit_reached', 'billing_not_active' => ['tone' => 'red', 'label' => 'Crédito o límite bloqueado', 'detail' => 'Revisa el crédito y los límites de tu cuenta en OpenAI.'],
            'rate_limit_exceeded' => ['tone' => 'amber', 'label' => 'Límite temporal', 'detail' => 'Demasiadas peticiones. Este error no confirma falta de saldo.'],
            'invalid_api_key' => ['tone' => 'amber', 'label' => 'Revisar conexión', 'detail' => 'OpenAI rechazó la clave configurada.'],
            default => ['tone' => 'amber', 'label' => 'Revisar conexión', 'detail' => 'La última operación falló. No se ha confirmado falta de saldo.'],
        });
    }
}
