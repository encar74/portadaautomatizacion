<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\MailboxClientInterface;
use App\Services\AI\AIAvailability;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Mail\Gmail\GmailMailboxClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('ai.provider') === 'openai') {
            $this->app->bind(AIProviderInterface::class, OpenAIProvider::class);
        }

        if (config('mail_ingestion.provider') === 'gmail') {
            $this->app->bind(MailboxClientInterface::class, GmailMailboxClient::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('aiIndicator', app(AIAvailability::class)->indicator());
        });
        ResetPassword::toMailUsing(function ($user, string $token) {
            return (new MailMessage)
                ->subject('Restablece tu contraseña · Portada.info')
                ->view('emails.reset-password', [
                    'resetUrl' => route('password.reset', [
                        'token' => $token,
                        'email' => $user->getEmailForPasswordReset(),
                    ]),
                    'expires' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });
    }
}
