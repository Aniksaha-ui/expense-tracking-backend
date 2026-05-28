<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    public function sendMessage(string $message): bool
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful();
    }
}
