<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the team (CRUISE_TEAM_EMAIL): a new booking needs a call within 24 hours to arrange payment.
 * Written in the language the guest booked in (bookings.locale, step 20), so the team also sees which
 * language to call in; words in lang/{en,ar}/emails.php.
 */
class NewBookingForTeam extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking)
    {
        // Set here, not in envelope()/content(): Mailable::send() switches to $this->locale before building them
        $this->locale($booking->emailLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.team_subject', ['ref' => $this->booking->reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.new-booking-for-team', with: [
            'booking' => $this->booking->loadMissing([...BookingEmailData::RELATIONS, 'user']),
        ]);
    }
}
