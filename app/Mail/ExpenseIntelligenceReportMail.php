<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExpenseIntelligenceReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $frequency,
        public readonly string $title,
        public readonly CarbonImmutable $fromDate,
        public readonly CarbonImmutable $toDate,
        public readonly array $summary,
        private readonly string $pdf,
        private readonly string $filename,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s: %s to %s',
                $this->title,
                $this->fromDate->toDateString(),
                $this->toDate->toDateString()
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.expense-intelligence-report',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
