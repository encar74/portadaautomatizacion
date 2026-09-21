<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('press-sources.index'))->assertRedirect(route('login'));
    }

    public function test_login_screen_is_available_to_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Accede a tu cuenta');
    }

    public function test_user_can_log_in_with_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => 'editor@portada.info',
            'password' => 'a-secure-password',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => ' EDITOR@PORTADA.INFO ',
            'password' => 'a-secure-password',
        ]);

        $response->assertRedirect(route('press-sources.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        User::factory()->create(['email' => 'editor@portada.info']);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'editor@portada.info',
            'password' => 'incorrecta',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_cannot_open_login_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('press-sources.index'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        RateLimiter::clear('editor@portada.info|127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'editor@portada.info',
                'password' => 'incorrecta',
            ]);
        }

        $response = $this->post(route('login.store'), [
            'email' => 'editor@portada.info',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString('Demasiados intentos', $response->getSession()->get('errors')->first('email'));
    }

    public function test_admin_creation_command_creates_a_hashed_user(): void
    {
        $this->artisan('app:create-admin', [
            '--name' => 'Administradora',
            '--email' => 'admin@portada.info',
        ])
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'very-secure-password')
            ->expectsQuestion('Repite la contraseña', 'very-secure-password')
            ->expectsOutput('Administrador admin@portada.info creado correctamente.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@portada.info']);
        $this->assertNotSame('very-secure-password', User::firstOrFail()->getRawOriginal('password'));
    }
}
