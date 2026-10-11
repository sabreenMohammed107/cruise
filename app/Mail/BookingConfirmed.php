<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the guest: "We've sent your booking details to …" on the confirmation page.
 */
class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your cruise is booked: {$this->booking->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.booking-confirmed', with: [
            'booking' => $this->booking->loadMissing(['sailing.cruise.ship', 'cabinCategory', 'travellers']),
        ]);
    }
}
