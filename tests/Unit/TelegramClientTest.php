<?php

namespace Tests\Unit;

use App\Services\Telegram\TelegramClient;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramClientTest extends TestCase
{
    public function test_it_hides_telegram_http_protocol_behind_a_typed_result(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 55, 'chat' => ['id' => 10]],
            ]),
        ]);

        $client = new TelegramClient(app(Factory::class), 'secret', 'https://telegram.test');
        $result = $client->sendMessage(10, 'Hello', ['parse_mode' => 'HTML']);

        $this->assertSame(55, $result->messageId);
        $this->assertSame(10, $result->chatId);
        Http::assertSent(fn ($request) => $request->url() === 'https://telegram.test/botsecret/sendMessage'
            && $request['chat_id'] === 10
            && $request['text'] === 'Hello'
            && $request['parse_mode'] === 'HTML');
    }
}
