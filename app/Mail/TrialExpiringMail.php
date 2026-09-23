<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $subdomain,
        public int $daysLeft,
        public \DateTimeInterface $endsAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->companyName} trial ends in {$this->daysLeft} day(s)");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.trial-expiring');
    }
}
