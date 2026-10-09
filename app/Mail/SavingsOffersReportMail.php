<?php
namespace App\Mail;
use Illuminate\Mail\Mailable; use Illuminate\Mail\Mailables\Attachment; use Illuminate\Mail\Mailables\Content; use Illuminate\Mail\Mailables\Envelope;
class SavingsOffersReportMail extends Mailable { public function __construct(public array $advice, private string $pdf, private string $filename) {} public function envelope(): Envelope { return new Envelope(subject: 'Monthly Savings Offers'); } public function content(): Content { return new Content(view: 'mail.savings-offers-report'); } public function attachments(): array { return [Attachment::fromData(fn() => $this->pdf, $this->filename)->withMime('application/pdf')]; } }
