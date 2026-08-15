<?php

namespace App\Providers;

use App\Services\AI\GigaChatLlmProvider;
use App\Services\AI\LlmProviderInterface;
use App\Services\AI\OpenAiLlmProvider;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramClientInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LlmProviderInterface::class, function ($app): LlmProviderInterface {
            $http = $app->make(Factory::class);

            return match ((string) config('services.llm.provider')) {
                'openai' => new OpenAiLlmProvider(
                    http: $http,
                    apiKey: (string) config('services.llm.openai.api_key'),
                    model: (string) config('services.llm.openai.model'),
                    apiUrl: (string) config('services.llm.openai.api_url'),
                    timeout: (int) config('services.llm.openai.timeout'),
                    maxOutputTokens: (int) config('services.llm.openai.max_output_tokens'),
                ),
                'gigachat' => new GigaChatLlmProvider(
                    http: $http,
                    authorizationKey: (string) config('services.llm.gigachat.authorization_key'),
                    scope: (string) config('services.llm.gigachat.scope'),
                    model: (string) config('services.llm.gigachat.model'),
                    apiUrl: (string) config('services.llm.gigachat.api_url'),
                    authUrl: (string) config('services.llm.gigachat.auth_url'),
                    timeout: (int) config('services.llm.gigachat.timeout'),
                    maxTokens: (int) config('services.llm.gigachat.max_tokens'),
                    verifySsl: (bool) config('services.llm.gigachat.verify_ssl'),
                ),
                default => throw new InvalidArgumentException('Unsupported LLM provider.'),
            };
        });

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
