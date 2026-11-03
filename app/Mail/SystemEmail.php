<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SystemEmail extends Mailable
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->data['subject']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.system', text: 'mail.system-text', with: ['messageData' => $this->data]);
    }
}
