<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $planName,
        public int $callsIncluded,
        public int $totalCreditsNow,
        public float $amountMxn,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vacilada · recibo de tu plan ' . $this->planName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.receipt', with: [
            'name' => $this->name,
            'planName' => $this->planName,
            'callsIncluded' => $this->callsIncluded,
            'totalCreditsNow' => $this->totalCreditsNow,
            'amountMxn' => $this->amountMxn,
        ]);
    }
}
