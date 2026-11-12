<?php

/*
| Booking emails (App\Mail\BookingConfirmed to the guest, App\Mail\NewBookingForTeam to the team), step 20.
| Sent in the language the guest booked in (bookings.locale). Same keys in lang/ar/emails.php.
*/

return [
    'brand' => 'Safer Cruise',
    'tagline' => 'by :site',

    // Guest: booking confirmation
    'guest_subject' => 'Your cruise is booked: :ref',
    'guest_title' => 'Your cruise is booked',
    'guest_greeting' => 'Hello :name,',
    'guest_intro' => 'Thank you for booking with Safer Cruise. Your booking is confirmed and your cabin is held for you.',
    'next_title' => 'What happens next',
    'next_contact' => 'Our team will contact you within :hours hours on :phone to arrange payment.',
    'next_no_online' => 'There is nothing to pay online.',
    'next_documents' => 'Bring a passport valid for at least six months to check-in.',
    'view_booking' => 'View your booking',
    'questions' => 'Questions? Message us on WhatsApp.',
    'whatsapp' => 'WhatsApp',

    // Team: new booking
    'team_subject' => 'New booking :ref: arrange payment',
    'team_title' => 'New booking :ref',
    'team_intro' => 'Contact the guest within :hours hours to arrange payment of :amount.',
    'booked_in' => 'Booked in',
    'language_names' => ['en' => 'English', 'ar' => 'Arabic'],
    'booked_at' => 'Booked on',
    'account' => 'Account',
    'guest_checkout' => 'Guest checkout (no account)',
    'open_admin' => 'Open in admin',
    'call' => 'Call',
    'email_guest' => 'Email',

    // Shared booking details
    'reference' => 'Booking reference',
    'status' => 'Status',
    'confirmed' => 'Confirmed',
    'trip' => 'Your trip',
    'trip_team' => 'Trip',
    'cruise' => 'Cruise',
    'ship' => 'Ship',
    'sailing_code' => 'Sailing',
    'departs' => 'Departs',
    'returns' => 'Returns',
    'duration' => 'Duration',
    'route' => 'Route',
    'cabins_title' => 'Cabins and guests',
    'cabin_n' => 'Cabin :n',
    'travellers' => 'Travellers',
    'primary' => 'Primary guest',
    'born' => 'Born :date',
    'contact' => 'Contact',
    'price_title' => 'Price',
    'fare' => 'Cruise fare',
    'fees' => 'Port & departure taxes',
    'tax_included' => 'Tax included',
    'discount' => 'Discount',
    'discount_code' => 'Discount (:code)',
    'offer' => 'Offer: :name', // time-limited offers (Admin → Offers)
    'agent_discount' => 'Agent commission', // agent bookings: already in the fare
    'agent_saved' => ':amount saved',
    'total' => 'Total',
    'paid' => 'Paid',
    'due' => 'Amount due',
    'prices_note' => 'Prices in US dollars, for the whole cruise.',
    // Guest email of a booking made in EGP (bookings.display_currency); the team email stays in USD
    'prices_note_display' => 'Prices for the whole cruise in :currency, at 1 USD = :rate :currency on the booking day. Charged in US dollars: :usd.',
    'guest_currency' => 'The guest booked seeing :currency prices: :total at 1 USD = :rate :currency.',

    // Customer: forgot password (App\Notifications\ResetPassword)
    'reset_subject' => 'Reset your Safer Cruise password',
    'reset_title' => 'Reset your password',
    'reset_intro' => 'We received a request to reset the password for your Safer Cruise account. Click the button below to choose a new one.',
    'reset_button' => 'Choose a new password',
    'reset_expires' => 'This link expires in :minutes minutes.',
    'reset_ignore' => "If you didn't ask for this, you can ignore this email: your password stays the same.",
    'reset_footer' => 'You received this email because a password reset was requested for your account on Safer Cruise.',

    // Approved agent: sign-in details (App\Notifications\AgentApproved)
    'agent_subject' => 'Your Safer Cruise agent account is ready',
    'agent_title' => 'Welcome aboard, partner',
    'agent_intro' => 'Your agent application for :company has been approved. Sign in on the website to see your agent rates on every cruise and book for your clients.',
    'agent_details' => 'Your sign-in details',
    'agent_email' => 'Email',
    'agent_password' => 'Temporary password',
    'agent_change_password' => 'For your security, change this password after you sign in: My account → Password.',
    'agent_button' => 'Sign in',
    'agent_footer' => 'You received this email because your agent application on Safer Cruise was approved.',

    // Guest booking without an account: one is made for them, sign-in details in the booking email (App\Services\GuestAccounts)
    'new_account_title' => 'Welcome aboard: your Safer Cruise account is ready',
    'new_account_intro' => 'We created an account for you with this email, so you can sign in at any time to see this booking, its payment status and your future trips.',
    'new_account_button' => 'Sign in to my account',
    'new_account_change_password' => 'For your security, change this password after you sign in: My account → Password. Lost it? Use "Forgot password?" on the sign-in page.',
    'team_account_created' => 'New account created with this booking; sign-in details were sent to the guest.',

    'footer_rights' => '© :year Safer. All rights reserved.',
    'footer_why' => 'You received this email because a booking was made on Safer Cruise with this address.',
];
