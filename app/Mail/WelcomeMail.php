<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public int $credits, public ?string $referralCode = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '¡Bienvenido a Vacilada, ' . $this->name . '!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome', with: [
            'name' => $this->name,
            'credits' => $this->credits,
            'referralCode' => $this->referralCode,
        ]);
    }
}
