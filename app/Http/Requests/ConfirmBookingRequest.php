<?php

namespace App\Http\Requests;

use App\Enums\TravellerBand;
use App\Models\AgeBand;
use App\Models\CabinCategory;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Services\AgeBandValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Confirm booking" (booking.js). Re-checks on the server everything the template checks in the
 * browser (details.js guest limits, booking.js traveller and contact rules) plus availability.
 */
class ConfirmBookingRequest extends FormRequest
{
    public const NAME_PATTERN = "/^[\p{L}][\p{L}\s'’\-]{0,48}$/u";

    public ?Sailing $sailing = null;

    public ?SailingCabinPrice $price = null;

    public ?PromoCode $promo = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sailing' => ['required', 'string', 'max:20'],
            'cabin' => ['required', 'string', 'max:40'],
            'guests' => ['required', 'array'],
            'guests.adult' => ['required', 'integer', 'min:1', 'max:'.config('cruise.max_cabin_guests')],
            'guests.child' => ['required', 'integer', 'min:0', 'max:'.config('cruise.max_cabin_guests')],
            'guests.infant' => ['required', 'integer', 'min:0', 'max:'.config('cruise.max_infants_per_cabin')],
            'travellers' => ['required', 'array'],
            'travellers.*.first' => ['required', 'string', 'regex:'.self::NAME_PATTERN],
            'travellers.*.last' => ['required', 'string', 'regex:'.self::NAME_PATTERN],
            'travellers.*.dob' => ['required', 'date_format:Y-m-d'],
            'travellers.*.gender' => ['required', Rule::in(['Male', 'Female', 'male', 'female'])],
            'travellers.*.nationality' => ['required', Rule::in(config('cruise.nationalities'))],
            'contact' => ['required', 'array'],
            'contact.email' => ['required', 'string', 'email:rfc,strict', 'max:255'],
            'contact.dial' => ['required', Rule::in(array_column(config('cruise.dial_codes'), 0))],
            'contact.phone' => ['required', 'string', 'max:20'],
            'promoCode' => ['nullable', 'string', 'max:40'],
            'agreed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        // In the visitor's language (lang/{en,ar}/site.php → booking.errors)
        $e = fn (string $key) => __("site.booking.errors.$key");

        return [
            'guests.adult.min' => $e('adult_required'),
            'travellers.*.first.*' => $e('first_each'),
            'travellers.*.last.*' => $e('last_each'),
            'travellers.*.dob.*' => $e('dob_each'),
            'travellers.*.gender.*' => $e('gender_each'),
            'travellers.*.nationality.*' => $e('nationality_each'),
            'contact.email.*' => $e('email_invalid'),
            'contact.dial.*' => $e('dial'),
            'contact.phone.*' => $e('phone_required'),
            'agreed.accepted' => $e('agree'),
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkBooking($validator)];
    }

    private function checkBooking(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->sailing = Sailing::upcoming()->with('cruise')->where('code', $this->input('sailing'))->first();
        if (! $this->sailing || ! $this->sailing->cruise->is_active || $this->sailing->cruise->trashed()) {
            $validator->errors()->add('sailing', __('site.booking.errors.sailing_gone'));

            return;
        }

        $cabin = CabinCategory::where('ship_id', $this->sailing->cruise->ship_id)->where('slug', $this->input('cabin'))->first();
        $this->price = $cabin ? $this->sailing->cabinPrices()->with('cabinCategory')->where('cabin_category_id', $cabin->id)->first() : null;
        if (! $this->price) {
            $validator->errors()->add('cabin', __('site.booking.errors.choose_cabin'));

            return;
        }
        $cabinName = app()->getLocale() === 'ar' ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en;
        if ($this->price->is_sold_out) {
            $validator->errors()->add('cabin', __('site.booking.errors.cabin_sold_out', ['cabin' => $cabinName]));

            return;
        }

        $guests = $this->guests();
        // Adults and children fill the cabin (at most 3); infants are free and limited separately (at most 4)
        $paying = $guests['adult'] + $guests['child'];
        if ($paying > $cabin->capacity()) {
            $validator->errors()->add('guests', __('site.booking.errors.cabin_too_small', ['cabin' => $cabinName, 'max' => $cabin->capacity(), 'count' => $paying]));
        }
        $maxInfants = (int) config('cruise.max_infants_per_cabin');
        if ($guests['infant'] > $maxInfants) {
            $validator->errors()->add('guests', __('site.booking.errors.too_many_infants', ['max' => $maxInfants]));
        }

        $this->checkTravellers($validator, $guests);

        if ($error = $this->phoneError($this->input('contact.dial'), (string) $this->input('contact.phone'))) {
            $validator->errors()->add('contact.phone', $error);
        }

        if ($this->filled('promoCode')) {
            $this->promo = PromoCode::findRedeemable($this->input('promoCode'));
            if (! $this->promo) {
                $validator->errors()->add('promoCode', __('site.booking.errors.promo_invalid'));
            }
        }
    }

    /**
     * Travellers must be exactly the template's slots (adult-1, adult-2, child-1, …) and each
     * date of birth must fit its age band on the departure date.
     */
    private function checkTravellers(Validator $validator, array $guests): void
    {
        $expected = [];
        foreach (TravellerBand::cases() as $band) {
            for ($i = 1; $i <= $guests[$band->value]; $i++) {
                $expected[] = "{$band->value}-{$i}";
            }
        }

        $given = array_keys($this->input('travellers', []));
        sort($given);
        $sorted = $expected;
        sort($sorted);
        if ($given !== $sorted) {
            $validator->errors()->add('travellers', __('site.booking.errors.travellers'));

            return;
        }

        $ages = app(AgeBandValidator::class);
        // "Adult 1", "رضيع 1": band labels from Admin → Age bands, in the visitor's language
        $labels = AgeBand::all()->mapWithKeys(fn (AgeBand $b) => [$b->key->value => $b->tr('label')]);
        foreach ($expected as $key) {
            [$band, $n] = explode('-', $key);
            $error = $ages->check(TravellerBand::from($band), $this->input("travellers.$key.dob"), $this->sailing->departure_date);
            if ($error) {
                $validator->errors()->add("travellers.$key.dob", __('site.booking.errors.traveller', ['traveller' => ($labels[$band] ?? ucfirst($band))." $n", 'error' => $error]));
            }
        }
    }

    /** Same rules as phoneError() in booking.js. */
    private function phoneError(?string $dial, string $raw): ?string
    {
        $n = ltrim(preg_replace('/[\s\-()]/', '', $raw), '0');

        $key = match (true) {
            $n === '' => 'phone_required',
            ! ctype_digit($n) => 'phone_digits',
            $dial === '+966' && ! preg_match('/^5\d{8}$/', $n) => 'phone_sa',
            $dial === '+20' && ! preg_match('/^1\d{9}$/', $n) => 'phone_eg',
            strlen($n) < 7 || strlen($n) > 12 => 'phone_length',
            default => null,
        };

        return $key ? __("site.booking.errors.$key") : null;
    }

    /** @return array{adult: int, child: int, infant: int} */
    public function guests(): array
    {
        return [
            'adult' => (int) $this->input('guests.adult'),
            'child' => (int) $this->input('guests.child'),
            'infant' => (int) $this->input('guests.infant'),
        ];
    }

    /** Phone number as the template normalises it (no spaces, dashes or leading zeros). */
    public function normalisedPhone(): string
    {
        return ltrim(preg_replace('/[\s\-()]/', '', (string) $this->input('contact.phone')), '0');
    }
}
