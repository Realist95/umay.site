<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Services\ConversationService;
use App\Models\TelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('Telegram webhook received', [
            'header' => $request->header(),
            'content' => $request->getContent(),
            'queryString' => $request->getQueryString(),
            'all' => $request->all(),
        ]);

        $secret = (string) config('telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        abort_unless(
            $secret !== '' && hash_equals($secret, $providedSecret),
            403
        );

        $updateId = $request->integer('update_id');

        abort_unless($updateId > 0, 422);

        $payload = $request->all();

        $update = TelegramUpdate::firstOrCreate(
            ['telegram_update_id' => $updateId],
            [
                'update_type' => $this->detectType($payload),
                'payload' => $payload,
                'status' => 'received',
            ]
        );

        if ($update->wasRecentlyCreated) {
            ProcessTelegramUpdate::dispatch($update->id);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function detectType(array $payload): string
    {
        if (data_get($payload, 'callback_query.message.chat.type') === 'private') {
            return 'callback_query';
        }

        if (data_get($payload, 'message.chat.type') !== 'private') {
            return 'unsupported';
        }

        $text = data_get($payload, 'message.text');

        if (! is_string($text)) {
            return 'unsupported';
        }

        return str_starts_with($text, '/') ? 'command' : 'text';
    }
}
