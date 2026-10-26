<?php

/*
|--------------------------------------------------------------------------
| Homepage sections (pages/home.blade.php)
|--------------------------------------------------------------------------
| One entry per section, in page order. Each entry says:
|   name     label in the admin panel
|   fields   which columns the admin can edit: eyebrow, title, body, primary, secondary,
|            image, caption, items (primary/secondary = button label + link; label only
|            when 'link' is false)
|   image    default picture when nothing is uploaded: ['asset' => path under public/]
|            or ['ship' => 'image' | 'hero_image'] (that photo of the ship, from Admin → Ships)
|   content  default copy, from legacy-html/index.html. HomeSectionSeeder writes it to the
|            database, and HomeSection::forHome() falls back to it if a row is missing.
|
| Every text field has an Arabic twin ({field}_ar). Links and photos are shared by both languages.
*/

return [

    'upload_dir' => 'uploads/home',

    // Text field groups and their columns; each column also has an Arabic `{column}_ar`
    'text_fields' => [
        'eyebrow' => ['eyebrow'],
        'title' => ['title'],
        'body' => ['body'],
        'primary' => ['primary_label'],
        'secondary' => ['secondary_label'],
        'image' => ['image_alt'],
        'caption' => ['caption_label', 'caption_value'],
        'subheading' => ['caption_label'], // one extra short text (config/pages.php names it per section)
    ],

    // Icons available in the template's icon set (public/assets/js/core.js)
    'icons' => [
        'anchor', 'award', 'tag', 'check', 'headset', 'shield', 'lock', 'star', 'heart', 'ship', 'users', 'calendar',
        'pin', 'globe', 'sun', 'waves', 'utensils', 'coffee', 'music', 'smile', 'droplet', 'flower', 'bag', 'crown',
        'chat', 'phone', 'mail', 'clock', 'ticket', 'route', 'bell', 'buoy', 'info', 'card', 'bed',
    ],

    // Card colours used by the template (bg-{tone}-50, text-{tone}-600)
    'tones' => ['ocean' => 'Blue', 'lagoon' => 'Teal', 'sun' => 'Sand'],

    'sections' => [

        'hero' => [
            'name' => 'Hero',
            'fields' => ['eyebrow', 'title', 'body', 'primary', 'secondary', 'image'],
            'image' => ['asset' => 'assets/img/hero-home.jpg'],
            'content' => [
                'eyebrow' => 'Sailing from Jeddah and Yanbu',
                'eyebrow_ar' => 'رحلات من جدة وينبع',
                'title' => 'Sail the Red Sea aboard AROYA',
                'title_ar' => 'أبحر في البحر الأحمر على متن أرويا',
                'body' => 'Pick a sailing, choose your cabin and see the price for every adult, child and infant before you book.',
                'body_ar' => 'اختر رحلتك وكابينتك، وشاهد السعر لكل بالغ وطفل ورضيع قبل أن تحجز.',
                'primary_label' => 'Explore cruises',
                'primary_label_ar' => 'استكشف الرحلات',
                'primary_url' => '/cruises',
                'secondary_label' => 'Meet the ship',
                'secondary_label_ar' => 'تعرّف على السفينة',
                'secondary_url' => '#ship',
                'image_alt' => 'Cruise ship moored by a turquoise beach',
                'image_alt_ar' => 'سفينة سياحية راسية بجوار شاطئ فيروزي',
            ],
        ],

        'search' => [
            'name' => 'Search widget',
            'fields' => ['title', 'primary'],
            'link' => false,
            'content' => [
                'title' => 'Find your sailing',
                'title_ar' => 'ابحث عن رحلتك',
                'primary_label' => 'Search cruises',
                'primary_label_ar' => 'ابحث عن الرحلات',
            ],
        ],

        'ship' => [
            'name' => 'The ship',
            'fields' => ['title', 'body', 'primary', 'image', 'caption'],
            'image' => ['ship' => 'image'],
            'content' => [
                'title' => 'AROYA at a glance',
                'title_ar' => 'أرويا في لمحة',
                'body' => "Saudi Arabia's first cruise ship, built for families in the region: halal dining, prayer areas, and separate pool decks.",
                'body_ar' => 'أول سفينة سياحية سعودية، صُممت للعائلات في المنطقة: طعام حلال، ومصليات، وأسطح مسابح منفصلة.',
                'primary_label' => 'Discover the ship',
                'primary_label_ar' => 'اكتشف السفينة',
                'primary_url' => '/ship',
                'image_alt' => 'AROYA at sea',
                'image_alt_ar' => 'أرويا في عرض البحر',
                'caption_label' => 'Flag and home port',
                'caption_label_ar' => 'العلم وميناء الانطلاق',
                'caption_value' => 'Saudi Arabia, Jeddah',
                'caption_value_ar' => 'المملكة العربية السعودية، جدة',
            ],
        ],

        'sailings' => [
            'name' => 'Upcoming sailings',
            'fields' => ['title', 'body', 'primary'],
            'content' => [
                'title' => 'Upcoming sailings',
                'title_ar' => 'الرحلات القادمة',
                'body' => 'Prices are per person, from the lowest available cabin.',
                'body_ar' => 'الأسعار للشخص الواحد، بدءًا من أقل كابينة متاحة.',
                'primary_label' => 'See all cruises',
                'primary_label_ar' => 'عرض كل الرحلات',
                'primary_url' => '/cruises',
            ],
        ],

        'why' => [
            'name' => 'Why Safer Cruise',
            'fields' => ['title', 'body', 'items'],
            'content' => [
                'title' => 'Book with the team behind Safer Travel',
                'title_ar' => 'احجز مع الفريق الذي يقف وراء Safer Travel',
                'body' => 'Safer Cruise is run by Safar Tourism, a licensed agency that has organised trips across Turkey, Egypt, Saudi Arabia and beyond for years.',
                'body_ar' => 'تدير Safer Cruise شركة سفر للسياحة، وهي وكالة مرخّصة تنظّم منذ سنوات رحلات إلى تركيا ومصر والسعودية وغيرها.',
            ],
            'items' => [
                [
                    'icon' => 'award', 'tone' => 'ocean',
                    'title' => 'Licensed agency', 'title_ar' => 'وكالة مرخّصة',
                    'text' => 'Ministry of Tourism License No. 14079. Your booking is a contract with a registered company.',
                    'text_ar' => 'ترخيص وزارة السياحة رقم 14079. حجزك عقد مع شركة مسجّلة.',
                ],
                [
                    'icon' => 'tag', 'tone' => 'lagoon',
                    'title' => 'A price for every guest', 'title_ar' => 'سعر لكل ضيف',
                    'text' => 'Adults, children and infants are priced separately, and every price includes tax.',
                    'text_ar' => 'يُسعَّر البالغون والأطفال والرضّع كلٌّ على حدة، وكل الأسعار شاملة الضريبة.',
                ],
                [
                    'icon' => 'check', 'tone' => 'sun',
                    'title' => 'Book now, pay later', 'title_ar' => 'احجز الآن وادفع لاحقًا',
                    'text' => 'Confirm your cabin online with no card needed. Our team contacts you to arrange payment.',
                    'text_ar' => 'أكّد كابينتك عبر الإنترنت دون الحاجة إلى بطاقة، وسيتواصل معك فريقنا لترتيب الدفع.',
                ],
                [
                    'icon' => 'headset', 'tone' => 'ocean',
                    'title' => 'Help when you need it', 'title_ar' => 'مساعدة وقتما تحتاجها',
                    'text' => 'Message our team on WhatsApp before, during or after your cruise, in Arabic or English.',
                    'text_ar' => 'راسل فريقنا عبر واتساب قبل رحلتك وأثناءها وبعدها، بالعربية أو الإنجليزية.',
                ],
            ],
        ],

        'facilities' => [
            'name' => 'Life on board',
            'fields' => ['title', 'body'],
            'content' => [
                'title' => 'Life on board',
                'title_ar' => 'الحياة على متن السفينة',
                'body' => 'Everything below is included in your cruise fare, except spa treatments and shopping.',
                'body_ar' => 'كل ما يلي مشمول في سعر رحلتك، باستثناء علاجات السبا والتسوّق.',
            ],
        ],

        'cabins' => [
            'name' => 'Cabin showcase',
            'fields' => ['title', 'body'],
            'content' => [
                'title' => 'Step inside your cabin',
                'title_ar' => 'ادخل إلى كابينتك',
                'body' => 'Three cabin categories, bookable online for every sailing.',
                'body_ar' => 'ثلاث فئات من الكبائن، متاحة للحجز عبر الإنترنت في كل رحلة.',
            ],
        ],

        'destinations' => [
            'name' => 'Destinations',
            'fields' => ['title', 'body', 'primary'],
            'content' => [
                'title' => 'Where AROYA calls',
                'title_ar' => 'أين ترسو أرويا',
                'body' => 'Ports in Saudi Arabia, Egypt and Jordan.',
                'body_ar' => 'موانئ في السعودية ومصر والأردن.',
                'primary_label' => 'All destinations',
                'primary_label_ar' => 'كل الوجهات',
                'primary_url' => '/destinations',
            ],
        ],

        'testimonials' => [
            'name' => 'Testimonials',
            'fields' => ['title'],
            'content' => [
                'title' => 'What guests say',
                'title_ar' => 'ماذا يقول ضيوفنا',
            ],
        ],

        'newsletter' => [
            'name' => 'Newsletter',
            'fields' => ['title', 'body', 'primary'],
            'link' => false,
            'content' => [
                'title' => 'Hear about new sailings first',
                'title_ar' => 'كن أول من يعرف بالرحلات الجديدة',
                'body' => 'One email when new dates open or prices drop. No more than twice a month.',
                'body_ar' => 'رسالة واحدة عند فتح مواعيد جديدة أو انخفاض الأسعار، وبحد أقصى مرتين في الشهر.',
                'primary_label' => 'Subscribe',
                'primary_label_ar' => 'اشترك',
            ],
        ],

    ],

];
