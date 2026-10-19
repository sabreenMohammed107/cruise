<?php

/*
|--------------------------------------------------------------------------
| Footer & contact (Admin → Website content → Footer & contact)
|--------------------------------------------------------------------------
| The fields of the screen, grouped as shown there, with the text the website shows until an admin saves
| something else (saved values live in the site_settings table, see App\Models\SiteSetting).
|
| type: text (default) | textarea | url | email | phone | whatsapp.  bi: English + Arabic box.
| default: a string, or ['en' => …, 'ar' => …] for bi fields. An empty value hides the line on the website.
*/

return [

    'footer' => [
        'Description' => [
            'about' => [
                'label' => 'Text under the logo', 'type' => 'textarea', 'bi' => true, 'max' => 600,
                'default' => [
                    'en' => 'Safer Cruise is the cruise booking site of Safar Tourism, an international travel agency licensed by the Ministry of Tourism (License No. 14079).',
                    'ar' => 'Safer Cruise هو موقع حجز الرحلات البحرية التابع لشركة سفر للسياحة، وهي وكالة سفر دولية مرخّصة من وزارة السياحة (ترخيص رقم 14079).',
                ],
            ],
            'website_label' => ['label' => 'Website link: text', 'bi' => true, 'max' => 60, 'default' => ['en' => 'Visit safer.travel', 'ar' => 'زُر safer.travel']],
            'website_url' => ['label' => 'Website link: address', 'type' => 'url', 'default' => 'https://safer.travel', 'help' => 'Leave empty to hide the link.'],
        ],

        'Contact' => [
            'phone_1_label' => ['label' => 'First phone: label', 'bi' => true, 'max' => 40, 'default' => ['en' => 'Individuals:', 'ar' => 'للأفراد:']],
            'phone_1' => ['label' => 'First phone: number', 'type' => 'phone', 'default' => '+90 552 150 1111'],
            'phone_2_label' => ['label' => 'Second phone: label', 'bi' => true, 'max' => 40, 'default' => ['en' => 'Companies:', 'ar' => 'للشركات:']],
            'phone_2' => ['label' => 'Second phone: number', 'type' => 'phone', 'default' => '+90 544 501 9185'],
            'email' => ['label' => 'Email', 'type' => 'email', 'default' => 'Info@Safer.Travel'],
            'address' => ['label' => 'Address', 'bi' => true, 'max' => 255, 'default' => ['en' => 'Watan Square, Al-Fateh, Istanbul, Turkey', 'ar' => 'ميدان وطن، الفاتح، إسطنبول، تركيا']],
            'whatsapp' => [
                'label' => 'WhatsApp number', 'type' => 'whatsapp', 'default' => '905016451111',
                'help' => 'Country code and number, digits only (e.g. 905016451111). Used by the green “Chat with us” button and the WhatsApp links on the website.',
            ],
        ],

        'Social media' => [
            'facebook' => ['label' => 'Facebook', 'type' => 'url', 'social' => true, 'default' => 'https://www.facebook.com/SaferTourismOfficial'],
            'instagram' => ['label' => 'Instagram', 'type' => 'url', 'social' => true, 'default' => 'https://www.instagram.com/SaferTourismOfficial'],
            'youtube' => ['label' => 'YouTube', 'type' => 'url', 'social' => true, 'default' => 'https://www.youtube.com/@SaferTourismOfficial'],
            'x' => ['label' => 'X (Twitter)', 'type' => 'url', 'social' => true, 'default' => ''],
            'tiktok' => ['label' => 'TikTok', 'type' => 'url', 'social' => true, 'default' => ''],
            'snapchat' => ['label' => 'Snapchat', 'type' => 'url', 'social' => true, 'default' => ''],
            'linkedin' => ['label' => 'LinkedIn', 'type' => 'url', 'social' => true, 'default' => ''],
        ],
    ],

];
