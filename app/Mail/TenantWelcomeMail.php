<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $companyName,
        public string $subdomain,
        public string $adminName,
        public string $adminEmail,
        public string $password,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your {$this->companyName} HRM workspace is ready");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.tenant-welcome', with: [
            'loginUrl' => 'https://' . $this->subdomain . '.' . (config('app.tenant_domain', 'hrmplatform.com')),
        ]);
    }
}
