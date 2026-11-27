<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ContactMessageService
{
    public const TABLE = 'portfolio_contact_messages';

    public const STATUSES = ['unread', 'read', 'replied', 'archived'];

    public function createMessage(array $data): object
    {
        $messageId = DB::table(self::TABLE)->insertGetId([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'subject' => isset($data['subject']) && trim((string) $data['subject']) !== '' ? trim($data['subject']) : null,
            'message' => trim($data['message']),
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => isset($data['user_agent']) ? mb_substr((string) $data['user_agent'], 0, 500) : null,
            'status' => 'unread',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findMessage($messageId);
    }

    private function findMessage(int $messageId): object
    {
        $message = DB::table(self::TABLE)->where('id', $messageId)->first();

        if (! $message) {
            throw new RuntimeException('Contact message not found.');
        }

        return $message;
    }
}
