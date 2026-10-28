<?php

/*
| رسائل الحجز (App\Mail\BookingConfirmed للضيف، App\Mail\NewBookingForTeam للفريق)، الخطوة 20.
| تُرسل بلغة الموقع التي حجز بها الضيف (bookings.locale). نفس مفاتيح lang/en/emails.php.
*/

return [
    'brand' => 'Safer Cruise',
    'tagline' => 'من :site',

    // للضيف: تأكيد الحجز
    'guest_subject' => 'تم حجز رحلتك البحرية: :ref',
    'guest_title' => 'تم حجز رحلتك البحرية',
    'guest_greeting' => 'مرحبًا :name،',
    'guest_intro' => 'شكرًا لحجزك مع Safer Cruise. تم تأكيد حجزك والكابينة محجوزة لك.',
    'next_title' => 'الخطوات التالية',
    'next_contact' => 'سيتواصل معك فريقنا خلال :hours ساعة على الرقم :phone لترتيب الدفع.',
    'next_no_online' => 'لا يوجد أي دفع عبر الإنترنت.',
    'next_documents' => 'أحضر جواز سفر صالحًا لمدة ستة أشهر على الأقل عند تسجيل الوصول.',
    'view_booking' => 'عرض حجزك',
    'questions' => 'لديك سؤال؟ راسلنا عبر واتساب.',
    'whatsapp' => 'واتساب',

    // للفريق: حجز جديد
    'team_subject' => 'حجز جديد :ref: ترتيب الدفع',
    'team_title' => 'حجز جديد :ref',
    'team_intro' => 'تواصل مع الضيف خلال :hours ساعة لترتيب دفع مبلغ :amount.',
    'booked_in' => 'لغة الحجز',
    'language_names' => ['en' => 'الإنجليزية', 'ar' => 'العربية'],
    'booked_at' => 'تاريخ الحجز',
    'account' => 'الحساب',
    'guest_checkout' => 'حجز بدون حساب',
    'open_admin' => 'فتح في لوحة التحكم',
    'call' => 'اتصال',
    'email_guest' => 'بريد',

    // تفاصيل الحجز المشتركة
    'reference' => 'رقم الحجز',
    'status' => 'الحالة',
    'confirmed' => 'مؤكد',
    'trip' => 'رحلتك',
    'trip_team' => 'الرحلة',
    'cruise' => 'الرحلة البحرية',
    'ship' => 'السفينة',
    'sailing_code' => 'موعد الإبحار',
    'departs' => 'المغادرة',
    'returns' => 'العودة',
    'duration' => 'المدة',
    'route' => 'خط السير',
    'cabins_title' => 'الكبائن والضيوف',
    'cabin_n' => 'الكابينة :n',
    'travellers' => 'المسافرون',
    'primary' => 'الضيف الرئيسي',
    'born' => 'تاريخ الميلاد :date',
    'contact' => 'التواصل',
    'price_title' => 'السعر',
    'fare' => 'سعر الرحلة',
    'fees' => 'الضرائب ورسوم الموانئ', // للحجوزات قبل الخطوة 21 فقط
    'tax_included' => 'شامل الضريبة',
    'discount' => 'الخصم',
    'discount_code' => 'الخصم (:code)',
    'total' => 'الإجمالي',
    'paid' => 'المدفوع',
    'due' => 'المبلغ المستحق',
    'prices_note' => 'الأسعار بالدولار الأمريكي للرحلة كاملة.',
    'prices_note_display' => 'الأسعار للرحلة كاملة بعملة :currency، بسعر 1 USD = :rate :currency يوم الحجز. المبلغ المستحق بالدولار الأمريكي: :usd.',
    'guest_currency' => 'حجز الضيف وهو يرى الأسعار بعملة :currency: :total بسعر 1 USD = :rate :currency.',

    'reset_subject' => 'إعادة تعيين كلمة مرور Safer Cruise',
    'reset_title' => 'إعادة تعيين كلمة المرور',
    'reset_intro' => 'تلقّينا طلبًا لإعادة تعيين كلمة المرور لحسابك على Safer Cruise. اضغط على الزر أدناه لاختيار كلمة مرور جديدة.',
    'reset_button' => 'اختر كلمة مرور جديدة',
    'reset_expires' => 'تنتهي صلاحية هذا الرابط خلال :minutes دقيقة.',
    'reset_ignore' => 'إذا لم تطلب ذلك، يمكنك تجاهل هذه الرسالة: ستبقى كلمة المرور كما هي.',
    'reset_footer' => 'وصلتك هذه الرسالة لأنه تم طلب إعادة تعيين كلمة المرور لحسابك على Safer Cruise.',

    'footer_rights' => '© :year Safer. جميع الحقوق محفوظة.',
    'footer_why' => 'وصلتك هذه الرسالة لأنه تم إجراء حجز على Safer Cruise بهذا البريد الإلكتروني.',
];
