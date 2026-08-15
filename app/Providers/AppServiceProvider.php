<?php

namespace App\Providers;

use App\Services\AI\LlmProviderInterface;
use App\Services\AI\OpenAiLlmProvider;
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
        $this->app->singleton(LlmProviderInterface::class, fn ($app) => new OpenAiLlmProvider(
            http: $app->make(Factory::class),
            apiKey: (string) config('services.llm.openai.api_key'),
            model: (string) config('services.llm.openai.model'),
            apiUrl: (string) config('services.llm.openai.api_url'),
            timeout: (int) config('services.llm.openai.timeout'),
            maxOutputTokens: (int) config('services.llm.openai.max_output_tokens'),
        ));

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
