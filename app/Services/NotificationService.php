<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class NotificationService
{
    public function sendEmail(
        string|array $to,
        string $subject,
        ?string $view = null,
        array $data = [],
        ?string $body = null,
        string|array|null $cc = null,
        string|array|null $bcc = null,
        array $attachments = [],
    ): void {
        if ($view === null && $body === null) {
            throw new InvalidArgumentException('Email view or body is required.');
        }

        $callback = function (Message $message) use ($to, $subject, $cc, $bcc, $attachments): void {
            $message->to($this->normalizeRecipients($to))
                ->subject($subject);

            $normalizedCc = $this->normalizeRecipients($cc);
            if ($normalizedCc !== []) {
                $message->cc($normalizedCc);
            }

            $normalizedBcc = $this->normalizeRecipients($bcc);
            if ($normalizedBcc !== []) {
                $message->bcc($normalizedBcc);
            }

            foreach ($attachments as $attachment) {
                $this->attachToMessage($message, $attachment);
            }
        };

        if ($view !== null) {
            Mail::send($view, $data, $callback);

            return;
        }

        Mail::html((string) $body, $callback);
    }

    private function normalizeRecipients(string|array|null $recipients): array
    {
        if ($recipients === null) {
            return [];
        }

        $recipients = is_array($recipients) ? $recipients : [$recipients];

        return array_filter($recipients, static fn (mixed $recipient): bool => filled($recipient));
    }

    private function attachToMessage(Message $message, array $attachment): void
    {
        if (array_key_exists('data', $attachment)) {
            $message->attachData(
                $attachment['data'],
                $attachment['name'] ?? $attachment['filename'] ?? 'attachment',
                ['mime' => $attachment['mime'] ?? null]
            );

            return;
        }

        if (! empty($attachment['path'])) {
            $message->attach($attachment['path'], [
                'as' => $attachment['name'] ?? $attachment['filename'] ?? null,
                'mime' => $attachment['mime'] ?? null,
            ]);
        }
    }
}
