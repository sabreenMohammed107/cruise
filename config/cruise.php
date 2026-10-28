<?php

/*
|--------------------------------------------------------------------------
| Safer Cruise reference data
|--------------------------------------------------------------------------
| Static values that rarely change, ported from legacy-html/assets/js/data.js
| and core.js. Anything admins need to edit lives in the database instead.
*/

return [

    // Prices are stored and booked in US dollars (data.js: currency / currencySymbol).
    'currency' => 'USD',
    'currency_symbol' => '$',

    // Currencies a visitor can show prices in (header switcher, App\Services\CurrencyService). Display only: the
    // database, quotes and bookings stay in USD. EGP uses the live rate or the admin's rate (Admin → Currency).
    'display_currencies' => [
        'USD' => ['symbol' => '$'],
        'EGP' => ['symbol' => 'EGP '],
    ],

    // EGP per 1 USD until an admin saves one; also the fallback when the live rate can't be fetched
    'default_exchange_rate' => 50.00,

    // Cabin capacity (PROJECT_STEPS.md step 11): the most guests any cabin can take, counting adults, children AND
    // infants. Each cabin's own "Maximum guests" (Admin → Cabin categories) applies up to this number.
    // Every infant needs at least one adult in the booking. Infants are free (tiered pricing, step 9).
    'max_cabin_guests' => 10,

    // Cabins in one booking (step 15): each with its own guests and tiered price, all on the same sailing.
    'max_cabins_per_booking' => 5,

    // No online payment: confirming creates the booking and the team arranges payment offline.
    'payment_contact_hours' => 24,

    'whatsapp_number' => '905016451111',

    // Receives a "new booking" email so the team can arrange payment.
    'team_email' => env('CRUISE_TEAM_EMAIL', 'Info@Safer.Travel'),

    'nationalities' => [
        'Saudi Arabia', 'Egypt', 'United Arab Emirates', 'Kuwait', 'Qatar', 'Bahrain', 'Oman', 'Jordan', 'Lebanon',
        'Syria', 'Iraq', 'Palestine', 'Yemen', 'Sudan', 'Libya', 'Tunisia', 'Algeria', 'Morocco', 'Turkey', 'Pakistan',
        'India', 'Bangladesh', 'Indonesia', 'Malaysia', 'Philippines', 'United Kingdom', 'United States', 'Canada',
        'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Russia', 'China', 'Other',
    ],

    // [dial code, ISO country code, English name]: every country of the phone widget (config/dial_codes.php, step 25)
    'dial_codes' => require __DIR__.'/dial_codes.php',

    // Phone widget (intl-tel-input, as on safer.travel): country chosen at first, then these at the top of the list
    'phone_initial_country' => 'sa',
    'phone_preferred_countries' => ['sa', 'ae', 'eg', 'kw', 'qa', 'bh', 'om'],

    'terms' => [
        'text' => [
            'By completing this reservation you agree to the booking terms set out by your travel provider.',
            'Prices, availability and itineraries are subject to change until your booking is confirmed. Payments must be made by the stated due dates; failure to pay on time may result in cancellation of your reservation.',
            'Cancellations and amendments are governed by the cancellation policy applicable to this booking. The full contract terms are provided with your booking confirmation documents.',
        ],
        'text_ar' => [
            'بإتمام هذا الحجز فإنك توافق على شروط الحجز التي يحددها مزوّد خدمة السفر.',
            'الأسعار والتوافر وبرامج الرحلات قابلة للتغيير حتى تأكيد حجزك. يجب سداد المبالغ في المواعيد المحددة، وقد يؤدي التأخر في السداد إلى إلغاء الحجز.',
            'تخضع عمليات الإلغاء والتعديل لسياسة الإلغاء المطبقة على هذا الحجز. تُرسل شروط العقد الكاملة مع مستندات تأكيد الحجز.',
        ],
        // [period before departure, penalty]
        'cancellation' => [
            ['121 days or more before departure', 'No penalty'],
            ['61–120 days before departure', 'Full deposit amount'],
            ['15–60 days before departure', '50% of total price'],
            ['14 days or fewer before departure', '100% of total price'],
        ],
        'cancellation_ar' => [
            ['121 يومًا أو أكثر قبل المغادرة', 'بدون غرامة'],
            ['من 61 إلى 120 يومًا قبل المغادرة', 'كامل مبلغ العربون'],
            ['من 15 إلى 60 يومًا قبل المغادرة', '50% من السعر الإجمالي'],
            ['14 يومًا أو أقل قبل المغادرة', '100% من السعر الإجمالي'],
        ],
    ],

];