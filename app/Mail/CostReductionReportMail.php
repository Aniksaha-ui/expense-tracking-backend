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

class CostReductionReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
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
                'Cost reduction report: %s to %s',
                $this->fromDate->toDateString(),
                $this->toDate->toDateString()
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.cost-reduction-report',
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
