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

class FinancialOverviewReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly CarbonImmutable $fromDate,
        public readonly CarbonImmutable $toDate,
        public readonly array $summary,
        public readonly array $transferReceiptsByAccount,
        public readonly array $accountActivity,
        public readonly array $analysis,
        private readonly string $pdf,
        private readonly string $filename,
        private readonly ?string $offersPdf,
        private readonly ?string $offersFilename,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: sprintf('Financial overview: %s to %s', $this->fromDate->toDateString(), $this->toDate->toDateString()));
    }

    public function content(): Content
    {
        // Database records created under an old/misconfigured character set can
        // contain invalid UTF-8. Symfony's mail encoder delegates those values
        // to iconv, which otherwise prevents the whole scheduled report from
        // being sent. Keep the outbound mail payload UTF-8 safe.
        return new Content(view: 'mail.financial-overview-report', with: [
            'userName' => $this->safeText($this->user->name),
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'summary' => $this->summary,
            'transferReceiptsByAccount' => $this->safeValues($this->transferReceiptsByAccount),
            'accountActivity' => $this->safeValues($this->accountActivity),
            'analysis' => $this->safeValues($this->analysis),
            'appName' => $this->safeText((string) config('app.name')),
        ]);
    }

    public function attachments(): array
    {
        $attachments = [
            Attachment::fromData(fn (): string => $this->pdf, $this->filename)
                ->withMime('application/pdf'),
        ];

        if ($this->offersPdf !== null && $this->offersFilename !== null) {
            $attachments[] = Attachment::fromData(fn (): string => $this->offersPdf, $this->offersFilename)
                ->withMime('application/pdf');
        }

        return $attachments;
    }

    private function safeValues(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->safeText($value);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->safeValues($item), $value);
        }

        return $value;
    }

    private function safeText(?string $value): string
    {
        $value = (string) $value;

        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        // This byte-oriented expression does not invoke iconv. Invalid source
        // bytes are replaced rather than being allowed into the MIME encoder.
        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }
}
