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
    'fees' => 'Taxes and port fees', // bookings made before step 21 only
    'tax_included' => 'Tax included',
    'discount' => 'Discount',
    'discount_code' => 'Discount (:code)',
    'total' => 'Total',
    'paid' => 'Paid',
    'due' => 'Amount due',
    'prices_note' => 'Prices in US dollars, for the whole cruise.',

    'footer_rights' => '© :year Safer. All rights reserved.',
    'footer_why' => 'You received this email because a booking was made on Safer Cruise with this address.',
];
