<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the team: a new booking needs a call within 24 hours to arrange payment.
 */
class NewBookingForTeam extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New booking {$this->booking->reference}: arrange payment");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-booking-for-team', with: [
            'booking' => $this->booking->loadMissing(['sailing.cruise', 'cabinCategory', 'travellers']),
        ]);
    }
}
