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
 * Written in the language the guest booked in (bookings.locale, step 20); words in lang/{en,ar}/emails.php.
 */
class BookingConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  ?string  $password  set when an account was made for this guest booking (App\Services\GuestAccounts): the
     *                             email then also welcomes them and gives their sign-in details. Send this mail, never
     *                             queue it, so the password isn't stored in the jobs table.
     */
    public function __construct(public Booking $booking, public ?string $password = null)
    {
        // Set here, not in envelope()/content(): Mailable::send() switches to $this->locale before building them
        $this->locale($booking->emailLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('emails.guest_subject', ['ref' => $this->booking->reference]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.booking-confirmed', with: [
            'booking' => $this->booking->loadMissing(BookingEmailData::RELATIONS),
            'password' => $this->password,
        ]);
    }
}
