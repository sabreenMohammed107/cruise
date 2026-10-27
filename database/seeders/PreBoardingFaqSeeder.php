<?php

namespace Database\Seeders;

use App\Models\Ship;
use Illuminate\Database\Seeder;

/**
 * Questions from AROYA's pre-embarkation guide (visas, check-in, payment, prohibited items, children), in English and
 * Arabic, added after the ship's own FAQs. They show on the ship page and the pre-boarding guide, and are edited in
 * Admin → Ship FAQs. Safe to re-run: a question that already exists is updated, not duplicated.
 */
class PreBoardingFaqSeeder extends Seeder
{
    public function run(): void
    {
        $ship = Ship::query()->orderBy('id')->first();
        if (! $ship) {
            return;
        }

        $faqs = [
            [
                'Do I need a visa for Türkiye?', 'هل أحتاج إلى تأشيرة لدخول تركيا؟',
                'Yes, unless your nationality is exempt. Hold a multiple-entry Turkish visa: you re-enter Türkiye during the itinerary. The pre-boarding guide links to the official requirements, including the countries whose citizens can enter with a national ID.',
                'نعم، ما لم تكن جنسيتك معفاة. احرص على تأشيرة تركية متعددة الدخول، لأنك تعود إلى تركيا خلال برنامج الرحلة. يضم دليل ما قبل الصعود روابط المتطلبات الرسمية، ومنها الدول التي يمكن لمواطنيها الدخول بالهوية الوطنية.',
            ],
            [
                'Do I need a visa for Egypt?', 'هل أحتاج إلى تأشيرة لدخول مصر؟',
                "Visitors to Egypt must get a visa from an Egyptian diplomatic mission unless they are visa-exempt or eligible for a visa on arrival. Saudi and other GCC citizens, and citizens of Schengen countries, can get a visa on arrival. All other nationalities should check with the Egyptian embassy or consulate. Guests without an Egypt visa are denied boarding.",
                'يجب على زوار مصر الحصول على تأشيرة من إحدى البعثات الدبلوماسية المصرية، ما لم يكونوا معفيين أو مؤهلين للتأشيرة عند الوصول. يمكن لمواطني السعودية ودول الخليج الأخرى ومواطني دول شنغن الحصول على تأشيرة عند الوصول، وعلى بقية الجنسيات مراجعة السفارة أو القنصلية المصرية. يُمنع من الصعود كل ضيف لا يحمل تأشيرة مصر.',
            ],
            [
                'Do I need a Schengen visa for Greece?', 'هل أحتاج إلى تأشيرة شنغن لليونان؟',
                'Yes. To board AROYA at Galataport Istanbul for a sailing to Greece, you must hold a valid Schengen visa.',
                'نعم. للصعود إلى أرويا من محطة جالاتابورت إسطنبول في رحلة إلى اليونان، يجب أن تحمل تأشيرة شنغن سارية.',
            ],
            [
                'Can Safer Cruise or AROYA arrange my visa?', 'هل يمكن لـ Safer Cruise أو أرويا استخراج التأشيرة لي؟',
                'Visas are each guest\'s responsibility. AROYA offers a concierge visa facilitation service; the link is in the pre-boarding guide.',
                'التأشيرة مسؤولية كل ضيف. تقدّم أرويا خدمة مساعدة في استخراج التأشيرات، ورابطها في دليل ما قبل الصعود.',
            ],
            [
                'How long must my passport be valid?', 'ما مدة صلاحية جواز السفر المطلوبة؟',
                'Bring your original, physical passport, valid for at least six months.',
                'أحضر جواز سفرك الأصلي، صالحًا لمدة ستة أشهر على الأقل.',
            ],
            [
                'What time is check-in at the port?', 'ما مواعيد تسجيل الوصول في الميناء؟',
                'Check-in opens at 1:00 PM and closes at 7:00 PM, at Galataport Istanbul terminal in the city centre. Your boarding pass is issued in the AROYA app 24 hours before sailing.',
                'يبدأ تسجيل الوصول الساعة 1:00 ظهرًا وينتهي الساعة 7:00 مساءً في محطة جالاتابورت إسطنبول بوسط المدينة. تصدر بطاقة الصعود في تطبيق أرويا قبل 24 ساعة من الإبحار.',
            ],
            [
                'What do I need to do before boarding?', 'ماذا عليّ أن أفعل قبل الصعود؟',
                "1. Download the AROYA app (mandatory).\n2. Upload your personal data (mandatory).\n3. Register your credit card (mandatory).\n4. Print and attach your luggage tags.\n5. Arrive within check-in times.",
                "1. حمّل تطبيق أرويا (إلزامي).\n2. ارفع بياناتك الشخصية (إلزامي).\n3. سجّل بطاقتك الائتمانية (إلزامي).\n4. اطبع بطاقات الأمتعة وثبّتها.\n5. احضر خلال مواعيد تسجيل الوصول.",
            ],
            [
                'How do I pay on board?', 'كيف أدفع على متن السفينة؟',
                'There is no cash payment on board. Everything is charged in US dollars to your AROYA Pass, linked to the credit card you register in the AROYA app.',
                'لا يُقبل الدفع النقدي على متن السفينة. تُحتسب كل المشتريات بالدولار الأمريكي على بطاقة AROYA Pass المرتبطة بالبطاقة الائتمانية التي تسجّلها في تطبيق أرويا.',
            ],
            [
                'Which items are not allowed on board?', 'ما المواد الممنوعة على متن السفينة؟',
                'Weapons and sharp objects, dangerous chemicals, explosives and flammable items, outside food and drinks, alcohol, irons, hair dryers, hair straighteners, kettles and coffee machines, electrical thermoses, steamers, cooking appliances, candles, bakhoor burners and shisha burners. See the pre-boarding guide for the full list.',
                'الأسلحة والأدوات الحادة، والمواد الكيميائية الخطرة، والمتفجرات والمواد القابلة للاشتعال، والأطعمة والمشروبات من الخارج، والكحول، والمكاوي، ومجففات الشعر، وأجهزة فرد الشعر، والغلايات وآلات القهوة، والترامس الكهربائية، وأجهزة الكي بالبخار، وأجهزة الطهي، والشموع، ومباخر البخور، ومواقد الشيشة. راجع دليل ما قبل الصعود للقائمة الكاملة.',
            ],
            [
                'My child is travelling without a parent. What do I need?', 'طفلي مسافر دون أحد والديه، ماذا أحتاج؟',
                'A signed Guardian Consent Form authorising the accompanying adult(s), presented at check-in. Parents can also submit a Kids Individual Care Plan Form with medical, allergy and dietary details. Both forms are in the pre-boarding guide.',
                'نموذج موافقة الوصي موقّعًا لتفويض البالغين المرافقين، ويُقدَّم عند تسجيل الوصول. ويمكن للوالدين أيضًا تقديم نموذج خطة الرعاية الفردية للطفل بالتفاصيل الطبية والحساسية والمتطلبات الغذائية. النموذجان متاحان في دليل ما قبل الصعود.',
            ],
        ];

        // After the ship's other questions (not counting these, so re-running keeps the same order)
        $order = (int) $ship->faqs()->whereNotIn('question', array_column($faqs, 0))->max('sort_order');
        foreach ($faqs as $i => [$question, $questionAr, $answer, $answerAr]) {
            $ship->faqs()->updateOrCreate(['question' => $question], [
                'question_ar' => $questionAr, 'answer' => $answer, 'answer_ar' => $answerAr, 'sort_order' => $order + $i + 1,
            ]);
        }
    }
}
