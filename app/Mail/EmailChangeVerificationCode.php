<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailChangeVerificationCode extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public string $code, public string $firstName) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Verify your new Artemis 2.0 email address'); }
    public function content(): Content { return new Content(view: 'emails.email-change-verification'); }
}
