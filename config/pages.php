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
|   item_links  true when each card can have its own link (shown as a "Link" field on every card)
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

    // Pre-boarding guide (PreBoardingGuideController), from AROYA's "Pre-Embarkation Guide" (MED, Istanbul).
    // The questions at the bottom of the page are the Ship FAQs (Admin → Ship FAQs).
    'guide' => [
        'name' => 'Pre-boarding guide',
        'route' => 'guide',
        'sections' => [
            'head' => [
                'name' => 'Page heading',
                'fields' => ['eyebrow', 'title', 'body', 'primary', 'secondary', 'image'],
                'can_hide' => false,
                'image' => ['ship' => 'hero_image'],
                'content' => [
                    'eyebrow' => 'Before you sail', 'eyebrow_ar' => 'قبل أن تُبحر',
                    'title' => 'Pre-embarkation guide', 'title_ar' => 'دليل ما قبل الصعود إلى السفينة',
                    'body' => 'Welcome on board AROYA. To make your embarkation smooth, go through the requirements below and make sure you meet every one of them before you travel.',
                    'body_ar' => 'مرحبًا بك على متن أرويا. لتكون إجراءات صعودك سلسة، راجع المتطلبات التالية وتأكد من استيفائها جميعًا قبل السفر.',
                    'primary_label' => 'Visa requirements', 'primary_label_ar' => 'متطلبات التأشيرة', 'primary_url' => '#visas',
                    'secondary_label' => 'Check-in times', 'secondary_label_ar' => 'مواعيد تسجيل الوصول', 'secondary_url' => '#checkin',
                    'image_alt' => 'AROYA at sea', 'image_alt_ar' => 'أرويا في عرض البحر',
                ],
            ],
            'passport' => [
                'name' => 'Passport notice',
                'fields' => ['title', 'body'],
                'help' => 'Highlighted box at the top of the travel and visa requirements.',
                'content' => [
                    'title' => 'Physical passport with 6 months validity', 'title_ar' => 'جواز سفر أصلي صالح لمدة 6 أشهر',
                    'body' => 'Mandatory: every guest must carry their original passport, valid for at least six months.',
                    'body_ar' => 'إلزامي: يجب أن يحمل كل ضيف جواز سفره الأصلي، صالحًا لمدة ستة أشهر على الأقل.',
                ],
            ],
            'visas' => [
                'name' => 'Visa requirements by country',
                'fields' => ['title', 'body', 'items'],
                'item_links' => true,
                'help' => 'One card per country on the itinerary.',
                'content' => [
                    'title' => 'Travel & visa requirements', 'title_ar' => 'متطلبات السفر والتأشيرات',
                    'body' => 'Safer Cruise and AROYA are not responsible for arranging guests\' visas. Guests without the visas they need are denied boarding.',
                    'body_ar' => 'لا تتولى Safer Cruise ولا أرويا استخراج تأشيرات الضيوف. يُمنع من الصعود كل ضيف لا يحمل التأشيرات المطلوبة.',
                ],
                'items' => [
                    ['icon' => 'globe', 'tone' => 'ocean', 'title' => 'Türkiye visa', 'title_ar' => 'تأشيرة تركيا',
                        'text' => 'Make sure you hold a multiple-entry Turkish visa: it is required to re-enter Türkiye during the itinerary.',
                        'text_ar' => 'تأكد من حصولك على تأشيرة تركية متعددة الدخول، فهي مطلوبة لإعادة الدخول إلى تركيا خلال برنامج الرحلة.',
                        'url' => 'https://www.mfa.gov.tr/visa-information-for-foreigners.en.mfa'],
                    ['icon' => 'globe', 'tone' => 'sun', 'title' => 'Egypt visa', 'title_ar' => 'تأشيرة مصر',
                        'text' => "Visitors to Egypt must obtain a visa from an Egyptian diplomatic mission, unless they are visa-exempt or eligible for a visa on arrival.\n• Saudi Arabia and other GCC citizens: visa on arrival.\n• Schengen country citizens: visa on arrival.\n• All other nationalities: check the requirements with the Egyptian embassy or consulate.\nWithout an Egypt visa, guests are denied boarding.",
                        'text_ar' => "يجب على زوار مصر الحصول على تأشيرة من إحدى البعثات الدبلوماسية المصرية، ما لم يكونوا من الدول المعفاة من التأشيرة أو المؤهلة للتأشيرة عند الوصول.\n• مواطنو السعودية ودول الخليج الأخرى: تأشيرة عند الوصول.\n• مواطنو دول شنغن: تأشيرة عند الوصول.\n• جميع الجنسيات الأخرى: تحقق من المتطلبات لدى السفارة أو القنصلية المصرية.\nيُمنع من الصعود كل ضيف لا يحمل تأشيرة مصر."],
                    ['icon' => 'globe', 'tone' => 'lagoon', 'title' => 'Greece visa (Schengen)', 'title_ar' => 'تأشيرة اليونان (شنغن)',
                        'text' => 'To board AROYA at Galataport Istanbul for a sailing to Greece, you must hold a valid Schengen visa.',
                        'text_ar' => 'للصعود إلى أرويا من محطة جالاتابورت إسطنبول في رحلة إلى اليونان، يجب أن تحمل تأشيرة شنغن سارية.'],
                ],
            ],
            'turkey_links' => [
                'name' => 'Official Türkiye links',
                'fields' => ['title', 'items'],
                'item_links' => true,
                'help' => 'Links to the Turkish Ministry of Foreign Affairs, shown under the visa cards.',
                'content' => ['title' => 'Official Türkiye entry information', 'title_ar' => 'معلومات الدخول الرسمية إلى تركيا'],
                'items' => [
                    ['icon' => 'external', 'tone' => 'ocean', 'title' => 'Passport validity requirements', 'title_ar' => 'متطلبات صلاحية جواز السفر',
                        'url' => 'https://www.mfa.gov.tr/passport-validity-requirements-while-entering-turkey-in-accordance-with-law-on-foreigners-and-international-protection.en.mfa'],
                    ['icon' => 'external', 'tone' => 'ocean', 'title' => 'Countries allowed to enter Türkiye with a national ID', 'title_ar' => 'الدول المسموح لمواطنيها بدخول تركيا بالهوية الوطنية',
                        'url' => 'https://www.mfa.gov.tr/countries-whose-citizens-are-allowed-to-enter-T%C3%BCrkiye-with-their-national-id_s.en.mfa'],
                    ['icon' => 'external', 'tone' => 'ocean', 'title' => 'General entry requirements', 'title_ar' => 'متطلبات الدخول العامة',
                        'url' => 'https://www.mfa.gov.tr/sub.en.mfa?cc4e437c-6769-4d79-9017-10b63c651224'],
                    ['icon' => 'external', 'tone' => 'ocean', 'title' => 'Visa information for foreigners', 'title_ar' => 'معلومات التأشيرة للأجانب',
                        'url' => 'https://www.mfa.gov.tr/visa-information-for-foreigners.en.mfa'],
                ],
            ],
            'visa_help' => [
                'name' => 'Visa assistance',
                'fields' => ['title', 'body', 'primary'],
                'content' => [
                    'title' => 'Need help with your visa?', 'title_ar' => 'تحتاج مساعدة في التأشيرة؟',
                    'body' => 'A concierge visa facilitation service is available through AROYA.',
                    'body_ar' => 'تتوفر خدمة مساعدة في استخراج التأشيرات عن طريق أرويا.',
                    'primary_label' => 'Request visa assistance', 'primary_label_ar' => 'اطلب المساعدة في التأشيرة',
                    'primary_url' => 'https://aroya.com/en/documents/visa-booking-request',
                ],
            ],
            'steps' => [
                'name' => 'Pre-boarding steps',
                'fields' => ['title', 'body', 'items', 'primary', 'secondary'],
                'help' => 'Numbered in card order. The buttons are the AROYA app in Google Play and the App Store.',
                'content' => [
                    'title' => 'Pre-boarding steps', 'title_ar' => 'خطوات ما قبل الصعود',
                    'body' => 'Steps 1 to 3 are mandatory. Your boarding pass is issued in the AROYA app 24 hours before sailing.',
                    'body_ar' => 'الخطوات من 1 إلى 3 إلزامية. تصدر بطاقة الصعود في تطبيق أرويا قبل 24 ساعة من الإبحار.',
                    'primary_label' => 'Get it on Google Play', 'primary_label_ar' => 'حمّله من Google Play',
                    'primary_url' => 'https://play.google.com/store/apps/details?id=com.aroya.guestapp&hl=en',
                    'secondary_label' => 'Download on the App Store', 'secondary_label_ar' => 'حمّله من App Store',
                    'secondary_url' => 'https://apps.apple.com/eg/app/aroya-cruises/id6737065220',
                ],
                'items' => [
                    ['icon' => 'download', 'tone' => 'sun', 'title' => 'Download the AROYA app', 'title_ar' => 'حمّل تطبيق أرويا',
                        'text' => 'Mandatory. Your boarding pass is issued in the app 24 hours before sailing.',
                        'text_ar' => 'إلزامي. تصدر بطاقة الصعود في التطبيق قبل 24 ساعة من الإبحار.'],
                    ['icon' => 'user', 'tone' => 'sun', 'title' => 'Upload your personal data', 'title_ar' => 'ارفع بياناتك الشخصية',
                        'text' => 'Mandatory. Add the passport details of every guest in your booking.',
                        'text_ar' => 'إلزامي. أضف بيانات جواز السفر لكل ضيف في حجزك.'],
                    ['icon' => 'card', 'tone' => 'sun', 'title' => 'Register your credit card', 'title_ar' => 'سجّل بطاقتك الائتمانية',
                        'text' => 'Mandatory. All payments on board are charged to it.',
                        'text_ar' => 'إلزامي. تُحتسب عليها جميع المدفوعات على متن السفينة.'],
                    ['icon' => 'bag', 'tone' => 'ocean', 'title' => 'Print and attach your luggage tag', 'title_ar' => 'اطبع بطاقة الأمتعة وثبّتها',
                        'text' => 'One tag on every suitcase you check in at the terminal.',
                        'text_ar' => 'بطاقة على كل حقيبة تسلّمها في المحطة.'],
                    ['icon' => 'clock', 'tone' => 'ocean', 'title' => 'Arrive within check-in times', 'title_ar' => 'احضر خلال مواعيد تسجيل الوصول',
                        'text' => 'Check-in opens at 1:00 PM and closes at 7:00 PM.',
                        'text_ar' => 'يبدأ تسجيل الوصول الساعة 1:00 ظهرًا وينتهي الساعة 7:00 مساءً.'],
                ],
            ],
            'checkin' => [
                'name' => 'Check-in times',
                'fields' => ['title', 'items'],
                'content' => ['title' => 'Check-in times', 'title_ar' => 'مواعيد تسجيل الوصول'],
                'items' => [
                    ['icon' => 'clock', 'tone' => 'lagoon', 'title' => 'Check-in opens', 'title_ar' => 'بدء تسجيل الوصول', 'text' => '1:00 PM', 'text_ar' => '1:00 ظهرًا'],
                    ['icon' => 'clock', 'tone' => 'sun', 'title' => 'Check-in closes', 'title_ar' => 'انتهاء تسجيل الوصول', 'text' => '7:00 PM', 'text_ar' => '7:00 مساءً'],
                ],
            ],
            'payment' => [
                'name' => 'Payment on board',
                'fields' => ['title', 'items'],
                'content' => ['title' => 'Payment on board', 'title_ar' => 'الدفع على متن السفينة'],
                'items' => [
                    ['icon' => 'x', 'tone' => 'ocean', 'title' => 'No cash payment on board', 'title_ar' => 'لا يُقبل الدفع النقدي على متن السفينة'],
                    ['icon' => 'tag', 'tone' => 'ocean', 'title' => 'All payments are charged in USD', 'title_ar' => 'تُحتسب جميع المدفوعات بالدولار الأمريكي'],
                    ['icon' => 'card', 'tone' => 'ocean', 'title' => 'Your AROYA Pass is used for all payments on board', 'title_ar' => 'تُستخدم بطاقة AROYA Pass لجميع المدفوعات على متن السفينة'],
                ],
            ],
            'terminal' => [
                'name' => 'Embarkation terminal',
                'fields' => ['title', 'body', 'primary', 'image'],
                'help' => 'No photo is shown until one is uploaded.',
                'content' => [
                    'title' => 'Galataport Istanbul terminal', 'title_ar' => 'محطة جالاتابورت إسطنبول',
                    'body' => "Galataport Istanbul terminal is in the city centre of Istanbul. Car parking is available at the port; charges may apply according to local regulations.\nWhen you enter the port, security may ask to see your reservation confirmation or your AROYA boarding pass (printed or in the AROYA app).",
                    'body_ar' => "تقع محطة جالاتابورت إسطنبول في وسط مدينة إسطنبول. تتوفر مواقف سيارات في الميناء، وقد تُطبق رسوم وفق الأنظمة المحلية.\nعند دخول الميناء قد يطلب منك الأمن تأكيد الحجز أو بطاقة الصعود من أرويا (مطبوعة أو في تطبيق أرويا).",
                    'primary_label' => 'Open in Google Maps', 'primary_label_ar' => 'افتح في خرائط Google',
                    'primary_url' => 'https://maps.app.goo.gl/6MNVv6Jzs1Hk2yEQA',
                    'image_alt' => 'Galataport Istanbul cruise terminal', 'image_alt_ar' => 'محطة الرحلات البحرية في جالاتابورت إسطنبول',
                ],
            ],
            'packages' => [
                'name' => 'Onboard packages',
                'fields' => ['title', 'body', 'items'],
                'content' => [
                    'title' => 'Onboard packages', 'title_ar' => 'الباقات على متن السفينة',
                    'body' => 'Available to buy, with exclusive offers when you buy before boarding.',
                    'body_ar' => 'متاحة للشراء، مع عروض حصرية عند الشراء قبل الصعود.',
                ],
                'items' => [
                    ['icon' => 'globe', 'tone' => 'ocean', 'title' => 'AROYA Net packages', 'title_ar' => 'باقات إنترنت أرويا'],
                    ['icon' => 'coffee', 'tone' => 'lagoon', 'title' => 'Beverage packages', 'title_ar' => 'باقات المشروبات'],
                    ['icon' => 'star', 'tone' => 'sun', 'title' => 'Entertainment packages', 'title_ar' => 'باقات الترفيه'],
                    ['icon' => 'utensils', 'tone' => 'ocean', 'title' => 'Restaurant packages', 'title_ar' => 'باقات المطاعم'],
                ],
            ],
            'prohibited' => [
                'name' => 'Prohibited items',
                'fields' => ['title', 'body', 'items'],
                'content' => [
                    'title' => 'Prohibited items on board', 'title_ar' => 'المواد الممنوعة على متن السفينة',
                    'body' => 'These items are not allowed on board and are held by security at the terminal.',
                    'body_ar' => 'هذه المواد غير مسموح بها على متن السفينة، ويحتجزها الأمن في المحطة.',
                ],
                'items' => array_map(fn ($pair) => ['icon' => 'x', 'tone' => 'ocean', 'title' => $pair[0], 'title_ar' => $pair[1]], [
                    ['Weapons and sharp objects', 'الأسلحة والأدوات الحادة'],
                    ['Disabling chemicals and other dangerous items', 'المواد الكيميائية المُعطِّلة والمواد الخطرة الأخرى'],
                    ['Explosives and flammable items', 'المتفجرات والمواد القابلة للاشتعال'],
                    ['Outside food and drinks', 'الأطعمة والمشروبات من خارج السفينة'],
                    ['Martial arts and self-defence tools', 'أدوات الفنون القتالية والدفاع عن النفس'],
                    ['Items that violate public decency laws', 'المواد المخالفة لقوانين الآداب العامة'],
                    ['Sharp tools', 'العُدد الحادة'],
                    ['Alcohol', 'الكحول'],
                    ['Irons', 'المكاوي'],
                    ['Hair dryers', 'مجففات الشعر'],
                    ['Kettles and coffee machines', 'الغلايات وآلات القهوة'],
                    ['Bakhoor burners', 'مباخر البخور'],
                    ['Hair straighteners and hair irons', 'أجهزة فرد الشعر ومكاوي الشعر'],
                    ['Electrical thermoses', 'الترامس الكهربائية'],
                    ['Shisha burners', 'مواقد الشيشة'],
                    ['Steamers', 'أجهزة الكي بالبخار'],
                    ['Candles', 'الشموع'],
                    ['Cooking appliances', 'أجهزة الطهي'],
                ]),
            ],
            'kids' => [
                'name' => 'Travelling with kids & teens',
                'fields' => ['title', 'items'],
                'item_links' => true,
                'help' => 'Cards with a link show a "Download form" button.',
                'content' => ['title' => 'Travelling with kids & teens', 'title_ar' => 'السفر مع الأطفال والمراهقين'],
                'items' => [
                    ['icon' => 'users', 'tone' => 'sun', 'title' => 'Guardian Consent Form', 'title_ar' => 'نموذج موافقة الوصي',
                        'text' => 'If a child travels with someone other than their parent or legal guardian, a signed Guardian Consent Form is required to authorise the guardian(s). Download it and present the signed form at check-in.',
                        'text_ar' => 'إذا سافر الطفل مع شخص غير والديه أو وليّ أمره القانوني، يلزم تقديم نموذج موافقة الوصي موقّعًا لتفويض المرافقين. حمّل النموذج وقدّمه موقّعًا عند تسجيل الوصول.',
                        'url' => 'https://online.flippingbook.com/view/1033697715/'],
                    ['icon' => 'shield', 'tone' => 'ocean', 'title' => 'Safety instructions for families', 'title_ar' => 'تعليمات السلامة للعائلات',
                        'text' => 'Pay attention to the safety instructions for guests travelling with children in the onboard safety video and in the safety briefing by our Kids & Teens staff.',
                        'text_ar' => 'انتبه إلى تعليمات السلامة الخاصة بالضيوف المسافرين مع أطفال في فيديو السلامة على متن السفينة وفي جلسة السلامة التي يقدمها فريق الأطفال والمراهقين.'],
                    ['icon' => 'smile', 'tone' => 'lagoon', 'title' => 'Coloured wristbands for children 12 and under', 'title_ar' => 'أساور ملوّنة للأطفال حتى 12 عامًا',
                        'text' => 'Every child aged 12 and under receives a coloured wristband at check-in that matches their assembly station, so the crew can guide them to it even when they are not in a kids activity.',
                        'text_ar' => 'يحصل كل طفل عمره 12 عامًا أو أقل عند تسجيل الوصول على سوار ملوّن يطابق نقطة التجمّع الخاصة به، ليتمكن الطاقم من توجيهه إليها حتى إن لم يكن مشاركًا في أنشطة الأطفال.'],
                    ['icon' => 'heart', 'tone' => 'sun', 'title' => 'Kids Individual Care Plan Form', 'title_ar' => 'نموذج خطة الرعاية الفردية للطفل',
                        'text' => 'Tell us about each child in your booking: medical conditions, special needs, allergies, dietary requirements and anything else that helps our team. Download the form and submit it signed at check-in.',
                        'text_ar' => 'أخبرنا عن كل طفل في حجزك: الحالات الطبية والاحتياجات الخاصة والحساسية والمتطلبات الغذائية وأي معلومات أخرى تساعد فريقنا. حمّل النموذج وقدّمه موقّعًا عند تسجيل الوصول.',
                        'url' => 'https://online.flippingbook.com/view/613291161/'],
                ],
            ],
            'faq' => [
                'name' => 'Questions',
                'fields' => ['title', 'body', 'subheading'],
                'help' => 'The questions themselves are the Ship FAQs (Admin → Ship FAQs), also shown on the ship page.',
                'labels' => ['caption_label' => 'Search box placeholder'],
                'content' => [
                    'title' => 'Frequently asked questions', 'title_ar' => 'الأسئلة الشائعة',
                    'body' => 'Visas, check-in, payment on board and what to pack.', 'body_ar' => 'التأشيرات وتسجيل الوصول والدفع على متن السفينة وما يجب أن تحزمه.',
                    'caption_label' => 'Search the questions, e.g. visa', 'caption_label_ar' => 'ابحث في الأسئلة، مثلًا: تأشيرة',
                ],
            ],
            'more' => [
                'name' => 'Additional information',
                'fields' => ['title', 'body', 'primary', 'secondary'],
                'content' => [
                    'title' => 'Additional information', 'title_ar' => 'معلومات إضافية',
                    'body' => "For more details, see the AROYA terms & conditions. For any other question, visit the FAQ on AROYA's website or contact us.\nAROYA Guest Support (Saudi Arabia): +966 920022277 · Reservations@Aroya.com\nWe look forward to welcoming you aboard AROYA for a remarkably Arabian cruise experience!",
                    'body_ar' => "لمزيد من التفاصيل، راجع الشروط والأحكام لدى أرويا. ولأي سؤال آخر، زر قسم الأسئلة الشائعة على موقع أرويا أو تواصل معنا.\nدعم ضيوف أرويا (السعودية): ‎+966 920022277 · Reservations@Aroya.com\nنتطلع إلى استقبالك على متن أرويا في تجربة إبحار عربية استثنائية!",
                    'primary_label' => 'Contact us', 'primary_label_ar' => 'تواصل معنا', 'primary_url' => '/contact',
                    'secondary_label' => 'AROYA FAQ', 'secondary_label_ar' => 'أسئلة أرويا الشائعة', 'secondary_url' => 'https://aroya.com/en/faq',
                ],
            ],
            'booking_notice' => [
                'name' => 'Booking page: before you confirm',
                'fields' => ['title', 'body', 'items', 'primary'],
                'help' => 'Not on this page: the "important information" box on the booking review step, above the terms checkbox.',
                'content' => [
                    'title' => 'Important before you confirm', 'title_ar' => 'معلومات مهمة قبل التأكيد',
                    'body' => 'Please read these points: guests who do not meet them are denied boarding.',
                    'body_ar' => 'يرجى قراءة هذه النقاط: يُمنع من الصعود كل ضيف لا يستوفيها.',
                    'primary_label' => 'Read the full pre-boarding guide', 'primary_label_ar' => 'اقرأ دليل ما قبل الصعود كاملًا', 'primary_url' => '/pre-boarding-guide',
                ],
                'items' => [
                    ['icon' => 'ticket', 'tone' => 'sun', 'title' => 'Passport and visas', 'title_ar' => 'جواز السفر والتأشيرات',
                        'text' => 'An original passport valid for 6 months, a multiple-entry Türkiye visa, an Egypt visa (or visa on arrival) and a Schengen visa for Greece, as your itinerary needs.',
                        'text_ar' => 'جواز سفر أصلي صالح لمدة 6 أشهر، وتأشيرة تركية متعددة الدخول، وتأشيرة مصر (أو تأشيرة عند الوصول)، وتأشيرة شنغن لليونان، حسب برنامج رحلتك.'],
                    ['icon' => 'clock', 'tone' => 'ocean', 'title' => 'Check-in from 1:00 PM to 7:00 PM', 'title_ar' => 'تسجيل الوصول من 1:00 ظهرًا إلى 7:00 مساءً',
                        'text' => 'At Galataport Istanbul terminal. Your boarding pass is issued in the AROYA app 24 hours before sailing.',
                        'text_ar' => 'في محطة جالاتابورت إسطنبول. تصدر بطاقة الصعود في تطبيق أرويا قبل 24 ساعة من الإبحار.'],
                    ['icon' => 'x', 'tone' => 'ocean', 'title' => 'No irons, hair dryers, kettles or heating appliances', 'title_ar' => 'ممنوع المكاوي ومجففات الشعر والغلايات والأجهزة الحرارية',
                        'text' => 'Also no outside food and drinks, candles, bakhoor or shisha burners.',
                        'text_ar' => 'ويُمنع كذلك إدخال الأطعمة والمشروبات من الخارج والشموع ومباخر البخور ومواقد الشيشة.'],
                    ['icon' => 'card', 'tone' => 'ocean', 'title' => 'No cash on board', 'title_ar' => 'لا يُقبل النقد على متن السفينة',
                        'text' => 'Onboard spending is charged in USD to the credit card registered in the AROYA app.',
                        'text_ar' => 'تُحتسب المشتريات على متن السفينة بالدولار الأمريكي على البطاقة الائتمانية المسجلة في تطبيق أرويا.'],
                ],
            ],
        ],
    ],

];
