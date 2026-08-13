<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReminderSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Invoice $invoice,
        public Client $client,
        public ReminderSchedule $reminderSchedule,
        public string $renderedSubject,
        public string $renderedBody,
        private ?string $pdfContent = null,
        private ?string $pdfFilename = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reminders.invoice',
            with: [
                'invoice' => $this->invoice,
                'client' => $this->client,
                'reminderSchedule' => $this->reminderSchedule,
                'workspace' => $this->invoice->workspace,
                'businessProfile' => $this->invoice->businessProfile(),
                'outstandingBalance' => $this->invoice->remaining_balance,
                'renderedSubject' => $this->renderedSubject,
                'renderedBody' => $this->renderedBody,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfContent === null || $this->pdfFilename === null) {
            return [];
        }

        return [
            Attachment::fromData(fn (): string => $this->pdfContent, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
