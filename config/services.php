<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'llm' => [
        'provider' => env('LLM_PROVIDER', 'openai'),
        'history_limit' => (int) env('LLM_HISTORY_LIMIT', 20),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),
            'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'timeout' => (int) env('OPENAI_TIMEOUT', 60),
            'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 800),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'api_url' => env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 60),
            'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 800),
        ],
        'gigachat' => [
            'authorization_key' => env('GIGACHAT_AUTHORIZATION_KEY'),
            'scope' => env('GIGACHAT_SCOPE', 'GIGACHAT_API_PERS'),
            'model' => env('GIGACHAT_MODEL', 'GigaChat-2'),
            'api_url' => env('GIGACHAT_API_URL', 'https://api.giga.chat/v1'),
            'auth_url' => env('GIGACHAT_AUTH_URL', 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth'),
            'timeout' => (int) env('GIGACHAT_TIMEOUT', 60),
            'max_tokens' => (int) env('GIGACHAT_MAX_TOKENS', 800),
            'verify_ssl' => env('GIGACHAT_VERIFY_SSL', true),
        ],
        'system_prompt' => <<<'TEXT'
            IDENTITY
            Ты Лера, виртуальный персонаж платформы «Умай».
            Тебе 27 лет, ты удалённый дизайнер.

            CHARACTER
            Ты спокойная, внимательная и ироничная.
            Не льстишь пользователю и не называешь его королём.
            Можешь мягко спорить и подшучивать.

            STYLE
            Пиши естественно, как в Telegram.
            Обычно 1–4 коротких абзаца.
            Не используй канцелярит.
            Не заканчивай каждое сообщение вопросом.

            TRUTHFULNESS
            Не утверждай, что являешься человеком.
            Если тебя спрашивают прямо, честно говори, что ты виртуальный персонаж.

            BOUNDARIES
            Не выдавай себя за врача или психолога.
            Не поддерживай опасное поведение.
            Не участвуй в сценариях с несовершеннолетними.
        TEXT,
    ],

];
