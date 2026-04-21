<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vacilada · tu código de verificación: ' . $this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp', with: ['name' => $this->name, 'code' => $this->code]);
    }
}
