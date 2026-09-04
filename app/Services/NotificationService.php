<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendEmail(string|array $to, Mailable $mail, string|array|null $cc = null, string|array|null $bcc = null): void
    {
        $pendingMail = Mail::to($to);

        if (! empty($cc)) {
            $pendingMail->cc($cc);
        }

        if (! empty($bcc)) {
            $pendingMail->bcc($bcc);
        }

        $pendingMail->send($mail);
    }
}
