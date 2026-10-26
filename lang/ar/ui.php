<?php

/*
| Arabic version of lang/en/ui.php (same keys).
*/

return [

    'error' => 'حدث خطأ ما. يُرجى المحاولة مرة أخرى.',
    'per_person_short' => 'للشخص',
    'from' => 'من',
    'from_price' => 'من :price',
    'from_per_person' => 'يبدأ من، للشخص',
    'photo_n' => 'الصورة :n',
    'photo_of' => ':n من :total',
    'previous_photo' => 'الصورة السابقة',
    'next_photo' => 'الصورة التالية',
    'close' => 'إغلاق',
    'guest_rating' => 'تقييم الضيوف',
    'stars' => ':rating من 5 نجوم',
    'cruises' => '{1} رحلة واحدة|{2} رحلتان|[3,10] :count رحلات|[11,*] :count رحلة',
    'guests_up_to' => 'حتى :count',
    'guests_up_to_long' => 'حتى :count ضيوف',
    'balcony_size' => 'بلكونة :size',
    'list_or' => ' أو ',

    'card' => [
        'departs' => 'المغادرة',
        'returns' => 'العودة',
        'length' => 'المدة',
        'ship_from' => ':ship، من :port',
        'select' => 'اختر الرحلة',
        'also_departs' => 'تغادر أيضًا :dates',
        'and_more' => ' و:count مواعيد أخرى',
        'image_alt' => ':cruise على متن :ship',
        'ask_whatsapp' => 'اسأل عن هذه الرحلة عبر واتساب',
        'whatsapp_message' => 'مرحبًا، أنا مهتم برحلة :cruise المغادرة في :date.',
    ],

    'tags' => [
        'visaFree' => 'بدون تأشيرة', // باقي الوسوم: لوحة التحكم ← Cruises ← Highlights (الخطوة 22)
    ],

    'favorites' => [
        'save' => 'أضف إلى المفضلة',
        'remove' => 'أزل من المفضلة',
        'saved' => 'تمت الإضافة إلى المفضلة',
        'removed' => 'تمت الإزالة من المفضلة',
        'error' => 'تعذّر تحديث المفضلة. حاول مرة أخرى.',
    ],

    'catalog' => [
        'visiting' => 'تزور',
        'any_port' => 'أي ميناء',
        'highlights' => [
            'visaFree' => 'بدون تأشيرة', // الباقي: لوحة التحكم ← Cruises ← Highlights (الخطوة 22)
        ],
        'chip_from_port' => 'من :port',
        'chip_visiting' => 'تزور :port',
        'chip_from_date' => 'من :date',
        'chip_up_to' => 'حتى :price',
        'previous_page' => 'الصفحة السابقة',
        'next_page' => 'الصفحة التالية',
    ],

    'cabin' => [
        'private_balcony' => 'بلكونة خاصة',
        'no_balcony' => 'بدون بلكونة',
        'max_guests' => 'أقصى عدد للضيوف',
        'guests' => 'الضيوف',
        'size' => 'المساحة',
        'cabin_size' => 'مساحة الكابينة',
        'balcony' => 'البلكونة',
        'beds' => 'الأسرّة',
        'bedroom' => 'غرفة النوم والصالة',
        'in_cabin' => 'في الكابينة',
        'photos' => 'صور الكابينة',
        'choose' => 'اختر هذه الكابينة',
        'choose_suite' => 'اختر هذا الجناح',
        'view_details' => 'عرض التفاصيل',
        'all_details' => 'كل التفاصيل',
        'details_photos' => 'عرض التفاصيل والصور',
        'find_sailings' => 'ابحث عن رحلات',
        'photo_alt' => ':cabin، الصورة :n',
        'show_photo' => 'اعرض الصورة :n',
    ],

    'terms' => [
        'title' => 'الشروط والأحكام وسياسة الإلغاء',
        'terms' => 'الشروط والأحكام',
        'cancellation' => 'سياسة الإلغاء',
        'intro' => 'تُحسب الغرامات حسب عدد الأيام قبل المغادرة.',
        'when' => 'موعد الإلغاء',
        'penalty' => 'الغرامة',
        'back' => 'العودة إلى حجزي',
    ],

    'browse_sailings' => 'تصفّح الرحلات',
    'ship_zone_alt' => ':zone على متن :ship',

    'home' => [
        'email_invalid' => 'أدخل بريدًا إلكترونيًا مثل name@example.com',
        'subscribed' => 'تم الاشتراك. ترقّب بريدك لمعرفة الرحلات الجديدة.',
    ],

    'destinations' => [
        'all_ports' => 'كل الموانئ',
        'things_to_do' => 'ماذا تفعل هناك',
        'cruises_here' => 'رحلات ترسو هنا',
        'next_departure' => ':nights، المغادرة القادمة :date',
        'none' => 'لا توجد رحلات قادمة ترسو هنا حتى الآن.',
        'all_sailings_to' => 'كل الرحلات إلى :port',
        // الخطوة 21: صفحة تُعرض من الخادم، الأقرب موعدًا أولًا
        'sailings_count' => '{1} رحلة قادمة واحدة|{2} رحلتان قادمتان|[3,10] :count رحلات قادمة|[11,*] :count رحلة قادمة',
        'showing_next' => 'أقرب :shown من :total رحلة',
        'see_all' => 'عرض كل الرحلات إلى :port (:count)',
        'from_pp' => 'يبدأ من، للشخص',
    ],

    'details' => [
        'round_trip' => 'ذهاب وعودة من :port',
        'route' => 'من :from إلى :to',
        'link_copied' => 'تم نسخ الرابط',
        'copy_failed' => 'انسخ الرابط من شريط العنوان',
        'open_photo' => 'افتح الصورة :n',
        'photo_alt' => ':cruise، الصورة :n',
        'duration' => 'المدة',
        'days' => '{1} يوم واحد|{2} يومان|[3,10] :count أيام|[11,*] :count يومًا',
        'ship' => 'السفينة',
        'sailing_code' => 'الرحلة :code',
        'from_pp' => 'من :price للشخص',
        'sold_out_change' => ':cabin محجوزة بالكامل في هذا الموعد. اختر كابينة أخرى.',
        'day' => 'اليوم',
        'expand_all' => 'توسيع الكل',
        'collapse_all' => 'طي الكل',
        'too_small' => '<b>:cabin</b> تتسع حتى :max ضيوف، وقد أضفت :count.',
        'choose_instead' => 'اختر :cabins بدلًا منها.',
        'reduce_guests' => 'قلّل عدد الضيوف.',
        'no_longer_fits' => 'لم تعد كابينتك تتسع للجميع. اختر كابينة أكبر.',
        'sold_out_date' => 'محجوزة بالكامل في هذا الموعد',
        'sold_out' => 'محجوزة بالكامل',
        'fits_up_to' => 'تتسع حتى :count ضيوف',
        'selected' => 'تم الاختيار',
        'cabin_selected' => 'تم اختيار :cabin',
        // أسعار الكابينة حسب عدد الضيوف، للرحلة كاملة
        'single' => 'ضيف واحد',
        'double' => 'ضيفان',
        'extra' => 'الضيف الثالث (بالغ أو طفل)',
        'infant' => 'رضيع (أقل من سنتين)',
        'free' => 'مجانًا',
        'per_cabin' => 'سعر الكابينة للرحلة كاملة',
        'cabin_n' => 'الكابينة :n',
        'your_cabins' => 'كبائنك',
        'add_cabin' => 'أضف كابينة أخرى',
        'remove_cabin' => 'إزالة الكابينة :n',
        'cabin_added' => 'تمت إضافة الكابينة :n. اختر ضيوفها ونوعها.',
        'editing_cabin' => 'تختار الآن الضيوف ونوع الكابينة للكابينة :n من :total',
        'cabins_count' => '{1} كابينة واحدة|{2} كابينتان|[3,10] :count كبائن|[11,*] :count كابينة',
        'choose_cabin_n' => 'اختر نوع الكابينة :n.',
        'your_sailing' => 'رحلتك',
        'guests' => 'الضيوف',
        'cabin' => 'الكابينة',
        'not_chosen' => 'لم تُختر بعد',
        'tax_included' => 'شامل الضريبة',
        'total' => 'الإجمالي',
        'choose_to_see_total' => 'اختر كابينة لترى السعر الإجمالي.',
        'continue_guests' => 'متابعة إلى بيانات الضيوف',
        'choose_cabin' => 'اختر كابينة',
        'choose_to_continue' => 'اختر كابينة للمتابعة',
    ],

];
