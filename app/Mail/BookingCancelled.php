<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the guest when their booking was cancelled automatically because nothing was paid within the hold
 * (App\Services\UnpaidBookings). In the language they booked in; words in lang/{en,ar}/emails.php.
 */
class BookingCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->locale($booking->emailLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.cancelled_subject', ['ref' => $this->booking->reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-cancelled', with: [
            'booking' => $this->booking->loadMissing(BookingEmailData::RELATIONS),
        ]);
    }
}
