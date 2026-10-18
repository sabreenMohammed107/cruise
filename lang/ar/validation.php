<?php

/*
| Arabic for Laravel's built-in validation messages on the website (sign in, register, account, booking).
| Only the rules the website uses are listed; anything missing falls back to Laravel's English.
| The site's own messages (clearer wording) are in lang/{en,ar}/site.php.
*/

return [
    'accepted' => 'يجب الموافقة على :attribute.',
    'array' => 'قيمة :attribute غير صالحة.',
    'before' => 'يجب أن يكون :attribute قبل :date.',
    'boolean' => 'قيمة :attribute غير صالحة.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => ':attribute ليس تاريخًا صالحًا.',
    'date_format' => ':attribute ليس تاريخًا صالحًا.',
    'email' => 'أدخل :attribute صالحًا.',
    'in' => 'قيمة :attribute غير صالحة.',
    'integer' => 'يجب أن يكون :attribute رقمًا صحيحًا.',
    'max' => [
        'numeric' => 'يجب ألا يزيد :attribute عن :max.',
        'string' => 'يجب ألا يزيد :attribute عن :max حرفًا.',
    ],
    'min' => [
        'numeric' => 'يجب ألا يقل :attribute عن :min.',
        'string' => 'يجب ألا يقل :attribute عن :min أحرف.',
    ],
    'numeric' => 'يجب أن يكون :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن تحتوي :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن تحتوي :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن تحتوي :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن تحتوي :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت :attribute في تسريب بيانات. اختر :attribute مختلفة.',
    ],
    'regex' => 'صيغة :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'string' => 'يجب أن يكون :attribute نصًا.',
    'unique' => ':attribute مستخدم بالفعل.',

    'attributes' => [
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'first_name' => 'الاسم الأول',
        'last_name' => 'اسم العائلة',
        'first' => 'الاسم الأول',
        'last' => 'اسم العائلة',
        'phone' => 'رقم الجوال',
        'dial' => 'رمز الدولة',
        'dob' => 'تاريخ الميلاد',
        'nationality' => 'الجنسية',
        'code' => 'رمز الخصم',
    ],
];
