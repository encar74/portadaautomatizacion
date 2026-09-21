<?php

namespace App\Providers;

use App\Contracts\MailboxClientInterface;
use App\Services\Mail\Gmail\GmailMailboxClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
