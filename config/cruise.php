<?php

/*
|--------------------------------------------------------------------------
| Safer Cruise reference data
|--------------------------------------------------------------------------
| Static values that rarely change, ported from legacy-html/assets/js/data.js
| and core.js. Anything admins need to edit lives in the database instead.
*/

return [

    // All prices are shown in US dollars (data.js: currency / currencySymbol).
    'currency' => 'USD',
    'currency_symbol' => '$',

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

    // [dial code, ISO country code]
    'dial_codes' => [
        ['+966', 'SA'], ['+20', 'EG'], ['+971', 'AE'], ['+965', 'KW'], ['+974', 'QA'], ['+973', 'BH'], ['+968', 'OM'],
        ['+962', 'JO'], ['+961', 'LB'], ['+964', 'IQ'], ['+970', 'PS'], ['+967', 'YE'], ['+249', 'SD'], ['+218', 'LY'],
        ['+216', 'TN'], ['+213', 'DZ'], ['+212', 'MA'], ['+90', 'TR'], ['+92', 'PK'], ['+91', 'IN'], ['+44', 'GB'],
        ['+1', 'US'], ['+49', 'DE'], ['+33', 'FR'],
    ],

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
