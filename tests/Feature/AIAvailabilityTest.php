<?php

namespace Tests\Feature;

use App\Models\AIExecution;
use App\Models\User;
use App\Services\AI\AIAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);
    }

    public function test_indicator_is_unknown_until_a_real_openai_operation_finishes(): void
    {
        Http::fake();
        AIExecution::factory()->create(['provider' => 'fake']);
        AIExecution::factory()->create(['provider' => 'openai', 'status' => 'pending']);
        $this->assertSame('Sin comprobar', app(AIAvailability::class)->indicator()['label']);
        $this->actingAs(User::factory()->create())->get('/press-sources')->assertOk()->assertSee('Estado de la IA')->assertSee('Sin comprobar');
        Http::assertNothingSent();
    }

    public function test_success_is_green_and_stale_success_is_not(): void
    {
        $this->freezeTime();
        $execution = AIExecution::factory()->create(['provider' => 'openai', 'status' => 'successful']);
        $this->assertSame('green', app(AIAvailability::class)->indicator()['tone']);
        $this->assertNotNull($execution->finished_at);
        $this->travel(25)->hours();
        $this->assertSame('gray', app(AIAvailability::class)->indicator()['tone']);
        $this->actingAs(User::factory()->create())->get('/press-sources')->assertOk()
            ->assertSee('Sin comprobación reciente')->assertSee('bg-slate-400')->assertDontSee('bg-amber-500');
    }

    public function test_credit_exhaustion_is_distinct_from_temporary_and_general_errors(): void
    {
        foreach ([
            'credit_balance_exhausted' => ['red', 'Saldo agotado'],
            'insufficient_quota' => ['red', 'Crédito o límite bloqueado'],
            'organization_usage_limit_exceeded' => ['red', 'Crédito o límite bloqueado'],
            'rate_limit_exceeded' => ['amber', 'Límite temporal'],
            'invalid_api_key' => ['amber', 'Revisar conexión'],
            null => ['amber', 'Revisar conexión'],
        ] as $code => [$tone, $label]) {
            AIExecution::factory()->create(['provider' => 'openai', 'status' => 'failed', 'provider_error_code' => $code ?: null, 'error_message' => 'private diagnostic']);
            $indicator = app(AIAvailability::class)->indicator();
            $this->assertSame($tone, $indicator['tone']);
            $this->assertSame($label, $indicator['label']);
        }
        $this->actingAs(User::factory()->create())->get('/press-sources')->assertOk()->assertDontSee('private diagnostic');
    }

    public function test_latest_completion_wins_when_requests_finish_out_of_order(): void
    {
        $older = AIExecution::factory()->create(['provider' => 'openai', 'status' => 'pending']);
        AIExecution::factory()->create(['provider' => 'openai', 'status' => 'successful']);
        $this->travel(1)->minutes();
        $older->update(['status' => 'failed', 'provider_error_code' => 'credit_balance_exhausted']);
        $this->assertSame('Saldo agotado', app(AIAvailability::class)->indicator()['label']);
        $this->travel(1)->minutes();
        AIExecution::factory()->create(['provider' => 'openai', 'status' => 'successful']);
        $this->assertSame('Operativa', app(AIAvailability::class)->indicator()['label']);
    }

    public function test_structured_error_code_is_read_without_exposing_provider_message(): void
    {
        Http::fake(['*' => Http::response(['error' => ['code' => 'rate_limit_exceeded', 'type' => 'insufficient_quota', 'message' => 'private']], 429)]);
        $exception = Http::get('https://openai.test/responses')->toException();
        $this->assertSame('rate_limit_exceeded', AIAvailability::errorCode($exception));
        $this->assertNull(AIAvailability::errorCode(new \RuntimeException('insufficient_quota')));
        config(['ai.openai.api_key' => null]);
        $this->assertSame('Sin configurar', app(AIAvailability::class)->indicator()['label']);
    }
}
