<?php

return [
    'token' => env('TELEGRAM_BOT_TOKEN'),
    'username' => env('TELEGRAM_BOT_USERNAME'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'api_url' => 'https://api.telegram.org',
    'default_character' => env('TELEGRAM_DEFAULT_CHARACTER', 'default'),
];
