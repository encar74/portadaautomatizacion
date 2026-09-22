<?php

namespace App\Providers;

use App\Contracts\AIProviderInterface;
use App\Contracts\MailboxClientInterface;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Mail\Gmail\GmailMailboxClient;
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
        //
    }
}
