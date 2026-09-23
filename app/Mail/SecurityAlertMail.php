<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $body)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Security] ' . $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.security-alert');
    }
}
