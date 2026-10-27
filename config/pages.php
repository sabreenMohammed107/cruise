<?php

/*
|--------------------------------------------------------------------------
| Sections of the other public pages (Admin → Cruise pages)
|--------------------------------------------------------------------------
| Same format as config/home.php 'sections' (name, fields, link, image, labels, content, items).
| The lists themselves (ship facts, zones, cabins, sailings, destinations…) come from their own admin
| screens; these are the headings, texts, buttons and photos around them, in page order.
|
|   can_hide false when the page script decides when the section shows (no show/hide checkbox)
|   help     note under the section name in the admin
|   labels   admin label for a column when its generic name would be unclear, e.g. caption_label
|            used as "Facts heading" on the ship page.
|
| Field groups: eyebrow, title, body, primary, secondary, image, caption (two short texts),
| subheading (one short text), items (cards / list rows). Every text has an Arabic twin ({column}_ar).
| Sections without an editable title still store one (the column is required); it is not shown.
*/

return [

    'ship' => [
        'name' => 'Ship page',
        'route' => 'ship',
        'sections' => [
            'hero' => [
                'name' => 'Hero',
                'fields' => ['primary', 'secondary', 'image'],
                'help' => 'The ship name, tagline and fact chips come from Admin → Ships and Ship facts.',
                'image' => ['ship' => 'hero_image'],
                'content' => [
                    'title' => 'AROYA', 'title_ar' => 'أرويا',
                    'primary_label' => 'See sailings', 'primary_label_ar' => 'شاهد الرحلات', 'primary_url' => '/cruises',
                    'secondary_label' => 'View cabins', 'secondary_label_ar' => 'اعرض الكبائن', 'secondary_url' => '#cabins',
                    'image_alt' => 'AROYA at sea', 'image_alt_ar' => 'أرويا في عرض البحر',
                ],
            ],
            'about' => [
                'name' => 'About the ship and facts',
                'fields' => ['title', 'caption'],
                'help' => 'The story paragraphs come from Admin → Ships; the figures from Ship facts.',
                'labels' => ['caption_label' => 'Facts heading', 'caption_value' => 'Note under the facts'],
                'content' => [
                    'title' => 'Built for the Red Sea', 'title_ar' => 'صُممت للبحر الأحمر',
                    'caption_label' => 'Ship facts', 'caption_label_ar' => 'حقائق عن السفينة',
                    'caption_value' => "Figures are indicative and may differ slightly from the operator's published data.",
                    'caption_value_ar' => 'الأرقام تقريبية وقد تختلف قليلًا عن البيانات المنشورة من المشغّل.',
                ],
            ],
            'onboard' => [
                'name' => 'Life on board',
                'fields' => ['title', 'body', 'subheading'],
                'labels' => ['caption_label' => 'Heading above the facilities'],
                'content' => [
                    'title' => 'Life on board', 'title_ar' => 'الحياة على متن السفينة',
                    'body' => 'Dining, pools and shows are included in your fare. Spa treatments, specialty restaurants and shopping are extra.',
                    'body_ar' => 'الطعام والمسابح والعروض مشمولة في سعر رحلتك. علاجات السبا والمطاعم المتخصصة والتسوّق برسوم إضافية.',
                    'caption_label' => 'Everything on deck', 'caption_label_ar' => 'كل ما على السطح',
                ],
            ],
            'cabins' => [
                'name' => 'Cabins',
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Cabins', 'title_ar' => 'الكبائن',
                    'body' => 'Three categories, bookable online for every sailing.',
                    'body_ar' => 'ثلاث فئات، متاحة للحجز عبر الإنترنت في كل رحلة.',
                ],
            ],
            'essentials' => [
                'name' => 'Good to know',
                'fields' => ['title'],
                'content' => ['title' => 'Good to know', 'title_ar' => 'معلومات مهمة'],
            ],
            'faq' => [
                'name' => 'Questions',
                'fields' => ['title'],
                'content' => ['title' => 'Questions about the ship', 'title_ar' => 'أسئلة عن السفينة'],
            ],
            'cta' => [
                'name' => 'Closing call to action',
                'fields' => ['title', 'body', 'primary'],
                'content' => [
                    'title' => 'Ready to sail on AROYA?', 'title_ar' => 'مستعد للإبحار على متن أرويا؟',
                    'body' => 'Pick a date and see the price for every guest.', 'body_ar' => 'اختر موعدًا وشاهد السعر لكل ضيف.',
                    'primary_label' => 'Find a sailing', 'primary_label_ar' => 'ابحث عن رحلة', 'primary_url' => '/cruises',
                ],
            ],
        ],
    ],

    'catalog' => [
        'name' => 'Cruises list',
        'route' => 'cruises.index',
        'sections' => [
            'head' => [
                'name' => 'Page heading and search',
                'fields' => ['title', 'body', 'subheading'],
                'labels' => ['caption_label' => 'Search box placeholder'],
                'content' => [
                    'title' => 'Choose your sailing', 'title_ar' => 'اختر رحلتك',
                    'body' => 'Every departure of every cruise. Pick a date, then choose your cabin on the next step.',
                    'body_ar' => 'كل مواعيد المغادرة لكل الرحلات. اختر موعدًا، ثم اختر كابينتك في الخطوة التالية.',
                    'caption_label' => 'Search by cruise name, e.g. Red Sea', 'caption_label_ar' => 'ابحث باسم الرحلة، مثلًا: البحر الأحمر',
                ],
            ],
            'empty' => [
                'name' => 'No results',
                'fields' => ['title', 'body', 'primary'],
                'link' => false,
                'can_hide' => false, // shown by catalog.js only when no sailing matches
                'help' => 'Shown only when no sailing matches the filters.',
                'content' => [
                    'title' => 'No sailings match these filters', 'title_ar' => 'لا توجد رحلات تطابق هذه الفلاتر',
                    'body' => 'Remove a filter or widen the price range to see more departures.',
                    'body_ar' => 'أزل أحد الفلاتر أو وسّع نطاق السعر لترى مواعيد أكثر.',
                    'primary_label' => 'Clear all filters', 'primary_label_ar' => 'مسح كل الفلاتر',
                ],
            ],
        ],
    ],

    'cruise' => [
        'name' => 'Cruise details',
        'route' => null, // one page per cruise
        'sections' => [
            'dates' => [
                'name' => 'Departure dates',
                'can_hide' => false, // needed to book
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Choose your departure date', 'title_ar' => 'اختر موعد المغادرة',
                    'body' => 'Prices update for the date you pick.', 'body_ar' => 'تتغير الأسعار حسب الموعد الذي تختاره.',
                ],
            ],
            'about' => [
                'name' => 'About this cruise',
                'fields' => ['title'],
                'help' => 'The text itself is each cruise\'s summary (Admin → Cruises).',
                'content' => ['title' => 'About this cruise', 'title_ar' => 'عن هذه الرحلة'],
            ],
            'included' => [
                'name' => 'Included in your fare',
                'fields' => ['title', 'items'],
                'content' => ['title' => 'Included in your fare', 'title_ar' => 'مشمول في سعر الرحلة'],
                'items' => [
                    ['icon' => 'check', 'tone' => 'lagoon', 'title' => 'Your cabin for every night of the cruise', 'title_ar' => 'كابينتك طوال ليالي الرحلة'],
                    ['icon' => 'check', 'tone' => 'lagoon', 'title' => 'Breakfast, lunch and dinner in main restaurants', 'title_ar' => 'الإفطار والغداء والعشاء في المطاعم الرئيسية'],
                    ['icon' => 'check', 'tone' => 'lagoon', 'title' => 'Pools, water park, kids club and theatre shows', 'title_ar' => 'المسابح والحديقة المائية ونادي الأطفال وعروض المسرح'],
                    ['icon' => 'check', 'tone' => 'lagoon', 'title' => 'Taxes and port charges (included in the price)', 'title_ar' => 'الضرائب ورسوم الموانئ (مشمولة في السعر)'],
                ],
            ],
            'not_included' => [
                'name' => 'Not included',
                'fields' => ['title', 'items'],
                'content' => ['title' => 'Not included', 'title_ar' => 'غير مشمول'],
                'items' => [
                    ['icon' => 'minus', 'tone' => 'ocean', 'title' => 'Shore excursions and specialty restaurants', 'title_ar' => 'الرحلات البرية والمطاعم المتخصصة'],
                    ['icon' => 'minus', 'tone' => 'ocean', 'title' => 'Spa treatments and onboard shopping', 'title_ar' => 'علاجات السبا والتسوّق على متن السفينة'],
                    ['icon' => 'minus', 'tone' => 'ocean', 'title' => 'Flights and transfers to the port', 'title_ar' => 'الطيران والانتقال إلى الميناء'],
                    ['icon' => 'minus', 'tone' => 'ocean', 'title' => 'Visas for ports outside Saudi Arabia, where needed', 'title_ar' => 'تأشيرات الموانئ خارج السعودية عند الحاجة'],
                ],
            ],
            'itinerary' => [
                'name' => 'Day-by-day itinerary',
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Day-by-day itinerary', 'title_ar' => 'برنامج الرحلة يومًا بيوم',
                    'body' => 'Times are local and may change with sea conditions.', 'body_ar' => 'الأوقات بالتوقيت المحلي وقد تتغير حسب أحوال البحر.',
                ],
            ],
            'travellers' => [
                'name' => "Who's travelling",
                'can_hide' => false, // needed to book
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => "Who's travelling?", 'title_ar' => 'من سيسافر؟',
                    'body' => 'Each age group has its own price. Ages are counted on the departure date.',
                    'body_ar' => 'لكل فئة عمرية سعرها. تُحسب الأعمار في تاريخ المغادرة.',
                ],
            ],
            'cabins' => [
                'name' => 'Choose your cabin',
                'can_hide' => false, // needed to book
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Choose your cabin', 'title_ar' => 'اختر كابينتك',
                    'body' => 'Prices below are for one person in the cabin, for the whole cruise.',
                    'body_ar' => 'الأسعار أدناه لشخص واحد في الكابينة، للرحلة كاملة.',
                ],
            ],
            'amenities' => [
                'name' => 'On board',
                'fields' => ['title'],
                'content' => ['title' => 'On board AROYA', 'title_ar' => 'على متن أرويا'],
            ],
            'safety' => [
                'name' => 'Health & safety',
                'fields' => ['title', 'body', 'items'],
                'content' => [
                    'title' => 'Health & safety', 'title_ar' => 'الصحة والسلامة',
                    'body' => 'What happens on board to keep you safe, and what to bring with you.',
                    'body_ar' => 'ما يحدث على متن السفينة للحفاظ على سلامتك، وما عليك إحضاره معك.',
                ],
                'items' => [
                    ['icon' => 'pulse', 'tone' => 'ocean', 'title' => 'Medical centre on board', 'title_ar' => 'مركز طبي على متن السفينة',
                        'text' => 'Doctors and nurses are on call 24 hours a day. Basic consultations and first aid are available; charges apply for treatment and medicine.',
                        'text_ar' => 'أطباء وممرضون متاحون على مدار 24 ساعة. تتوفر الاستشارات الأساسية والإسعافات الأولية، وتُطبق رسوم على العلاج والأدوية.'],
                    ['icon' => 'buoy', 'tone' => 'ocean', 'title' => 'Safety drill and life jackets', 'title_ar' => 'تدريب السلامة وسترات النجاة',
                        'text' => 'Every guest attends a short muster drill before departure. Life jackets in adult, child and infant sizes are stored in every cabin and at each muster station.',
                        'text_ar' => 'يحضر كل ضيف تدريب تجمّع قصيرًا قبل المغادرة. تتوفر سترات نجاة بمقاسات البالغين والأطفال والرضّع في كل كابينة وعند كل نقطة تجمّع.'],
                    ['icon' => 'shield', 'tone' => 'ocean', 'title' => 'Hygiene and food safety', 'title_ar' => 'النظافة وسلامة الغذاء',
                        'text' => 'Restaurants follow international food-safety standards, all food served is halal, and public areas are cleaned throughout the day.',
                        'text_ar' => 'تتبع المطاعم معايير سلامة الغذاء الدولية، وكل الطعام المقدَّم حلال، وتُنظَّف المناطق العامة طوال اليوم.'],
                    ['icon' => 'ticket', 'tone' => 'ocean', 'title' => 'Travel documents', 'title_ar' => 'وثائق السفر',
                        'text' => 'Bring a passport valid for at least six months, or a Saudi national ID for domestic-only sailings. Check visa needs for Egypt and Jordan before you book.',
                        'text_ar' => 'أحضر جواز سفر صالحًا لستة أشهر على الأقل، أو الهوية الوطنية السعودية للرحلات الداخلية فقط. تحقّق من متطلبات التأشيرة لمصر والأردن قبل الحجز.'],
                    ['icon' => 'smile', 'tone' => 'ocean', 'title' => 'Travelling with infants', 'title_ar' => 'السفر مع الرضّع',
                        'text' => 'Infants must be at least 6 months old on the departure date. Cots are free on request, and each infant must travel with an adult in the same cabin.',
                        'text_ar' => 'يجب ألا يقل عمر الرضيع عن 6 أشهر في تاريخ المغادرة. أسرّة الأطفال مجانية عند الطلب، ويجب أن يسافر كل رضيع مع شخص بالغ في الكابينة نفسها.'],
                ],
            ],
            'note' => [
                'name' => 'Note under the booking summary',
                'fields' => ['title'],
                'labels' => ['title' => 'Note'],
                'content' => [
                    'title' => 'No online payment. Our team contacts you to arrange payment.',
                    'title_ar' => 'لا يوجد دفع عبر الإنترنت. سيتواصل معك فريقنا لترتيب الدفع.',
                ],
            ],
        ],
    ],

    'destinations' => [
        'name' => 'Destinations',
        'route' => 'destinations.index',
        'sections' => [
            'head' => [
                'name' => 'Page heading',
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Where AROYA calls', 'title_ar' => 'أين ترسو أرويا',
                    'body' => 'Six ports across three countries. See what to do in each, the visa rules, and which cruises stop there.',
                    'body_ar' => 'ستة موانئ في ثلاث دول. اكتشف ما يمكنك فعله في كل منها، وقواعد التأشيرة، والرحلات التي تتوقف فيها.',
                ],
            ],
            'cta' => [
                'name' => 'Closing call to action',
                'fields' => ['title', 'body', 'primary'],
                'content' => [
                    'title' => 'Found your port?', 'title_ar' => 'وجدت مينائك؟',
                    'body' => 'Compare every sailing and its dates in one list.', 'body_ar' => 'قارن كل الرحلات ومواعيدها في قائمة واحدة.',
                    'primary_label' => 'See all cruises', 'primary_label_ar' => 'عرض كل الرحلات', 'primary_url' => '/cruises',
                ],
            ],
        ],
    ],

    // "Become an agent" (AgentController); the applications are in Admin → Agents → Agent requests
    'agents' => [
        'name' => 'Become an agent',
        'route' => 'agents',
        'sections' => [
            'head' => [
                'name' => 'Page heading',
                'fields' => ['eyebrow', 'title', 'body', 'primary'],
                'can_hide' => false,
                'content' => [
                    'eyebrow' => 'Safer Agents Program', 'eyebrow_ar' => 'برنامج وكلاء سفر',
                    'title' => 'Become our agent', 'title_ar' => 'كن وكيلنا',
                    'body' => 'Join the Safer agents program and sell AROYA Red Sea cruises to your customers at competitive prices, with a dedicated team behind you.',
                    'body_ar' => 'انضم إلى برنامج وكلاء سفر وقدّم رحلات أرويا في البحر الأحمر لعملائك بأسعار تنافسية، مع فريق مختص يدعمك.',
                    'primary_label' => 'Apply now', 'primary_label_ar' => 'قدّم طلبك الآن', 'primary_url' => '#apply',
                ],
            ],
            'program' => [
                'name' => 'About the program',
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Safer Agents Program', 'title_ar' => 'برنامج وكلاء سفر',
                    'body' => 'A platform for travel agencies and travel professionals. Book cruises, cabins and travel programs for your customers at competitive prices, with remote support from our specialised team at every step.',
                    'body_ar' => 'منصة لوكالات السفر والعاملين في مجال السياحة. احجز الرحلات البحرية والكبائن والبرامج السياحية لعملائك بأسعار تنافسية، مع دعم عن بُعد من فريقنا المختص في كل خطوة.',
                ],
            ],
            'vision' => [
                'name' => 'Our vision',
                'fields' => ['title', 'body'],
                'content' => [
                    'title' => 'Our vision', 'title_ar' => 'رؤيتنا',
                    'body' => 'To build lasting partnerships with travel agencies by offering quality trips for Arab travellers that respect their culture, values and privacy.',
                    'body_ar' => 'بناء شراكات دائمة مع وكالات السفر من خلال تقديم رحلات عالية الجودة للمسافر العربي تحترم ثقافته وقيمه وخصوصيته.',
                ],
            ],
            'benefits' => [
                'name' => 'Benefits of joining',
                'fields' => ['title', 'items'],
                'content' => ['title' => 'Benefits of joining the program', 'title_ar' => 'مزايا الانضمام إلى البرنامج'],
                'items' => [
                    ['icon' => 'award', 'tone' => 'ocean', 'title' => 'Strong returns', 'title_ar' => 'عوائد مميزة',
                        'text' => 'Competitive agent prices that leave you a healthy margin on every booking.',
                        'text_ar' => 'أسعار تنافسية للوكلاء تترك لك هامش ربح جيدًا في كل حجز.'],
                    ['icon' => 'bed', 'tone' => 'lagoon', 'title' => 'Choice for every customer', 'title_ar' => 'خيارات لكل عميل',
                        'text' => 'Interior, ocean-view and balcony cabins on every sailing, for families, couples and groups.',
                        'text_ar' => 'كبائن داخلية وبإطلالة على البحر وبشرفة في كل رحلة، للعائلات والأزواج والمجموعات.'],
                    ['icon' => 'tag', 'tone' => 'sun', 'title' => 'Exclusive discounts', 'title_ar' => 'خصومات حصرية',
                        'text' => 'Agent-only offers and promo codes that help you win more customers.',
                        'text_ar' => 'عروض وأكواد خصم خاصة بالوكلاء تساعدك على كسب عملاء أكثر.'],
                    ['icon' => 'calendar', 'tone' => 'ocean', 'title' => 'Sailings all year', 'title_ar' => 'رحلات طوال العام',
                        'text' => 'New departures for every season, holiday and long weekend.',
                        'text_ar' => 'مواعيد جديدة لكل موسم وعطلة ونهاية أسبوع طويلة.'],
                    ['icon' => 'headset', 'tone' => 'lagoon', 'title' => 'Ongoing support', 'title_ar' => 'دعم مستمر',
                        'text' => 'A dedicated team to answer your questions and help with every booking.',
                        'text_ar' => 'فريق مختص للإجابة عن أسئلتك ومساعدتك في كل حجز.'],
                    ['icon' => 'users', 'tone' => 'sun', 'title' => 'Training & workshops', 'title_ar' => 'تدريب وورش عمل',
                        'text' => 'Regular sessions on the ship, the itineraries and how to sell them.',
                        'text_ar' => 'جلسات دورية عن السفينة وبرامج الرحلات وطرق تسويقها.'],
                ],
            ],
            'form' => [
                'name' => 'Application form',
                'fields' => ['title', 'body'],
                'can_hide' => false, // the reason for the page
                'help' => 'Heading and text next to the form. Applications arrive in Admin → Agents → Agent requests.',
                'content' => [
                    'title' => 'Join the Safer agents program', 'title_ar' => 'انضم إلى برنامج وكلاء سفر',
                    'body' => 'Start your journey towards success in tourism. Leave your details and our partnerships team will contact you.',
                    'body_ar' => 'ابدأ رحلتك نحو النجاح في عالم السياحة. اترك بياناتك وسيتواصل معك فريق الشراكات لدينا.',
                ],
            ],
        ],
    ],

];
