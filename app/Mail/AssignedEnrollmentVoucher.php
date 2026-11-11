<?php

namespace App\Mail;

use App\Models\Voucher;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AssignedEnrollmentVoucher extends Mailable
{
    public function __construct(public Voucher $voucher) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your GRA Test Prep enrollment voucher');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assigned-enrollment-voucher');
    }
}
