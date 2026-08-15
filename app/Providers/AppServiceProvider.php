<?php

namespace App\Providers;

use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramClientInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramClientInterface::class, fn ($app) => new TelegramClient(
            http: $app->make(Factory::class),
            token: (string) config('telegram.token'),
            apiUrl: (string) config('telegram.api_url'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
