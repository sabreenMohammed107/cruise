<?php

namespace App\Http\Requests;

use App\Enums\TravellerBand;
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
            'guests.adult' => ['required', 'integer', 'min:1', 'max:9'],
            'guests.child' => ['required', 'integer', 'min:0', 'max:9'],
            'guests.infant' => ['required', 'integer', 'min:0', 'max:9'],
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
        return [
            'guests.adult.min' => 'Every booking needs at least one adult.',
            'travellers.*.first.*' => 'Enter each first name using letters only, in Arabic or English.',
            'travellers.*.last.*' => 'Enter each last name using letters only, in Arabic or English.',
            'travellers.*.dob.*' => 'Enter a date of birth for every traveller.',
            'travellers.*.gender.*' => 'Select a gender for every traveller.',
            'travellers.*.nationality.*' => 'Select a nationality for every traveller.',
            'contact.email.*' => 'Enter an email like name@example.com.',
            'contact.dial.*' => 'Choose a country code.',
            'contact.phone.*' => 'Enter a mobile number.',
            'agreed.accepted' => 'Tick the box to accept the terms before you continue.',
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
        if (! $this->sailing || ! $this->sailing->cruise->is_active) {
            $validator->errors()->add('sailing', 'This sailing is no longer available. Choose another date.');

            return;
        }

        $cabin = CabinCategory::where('ship_id', $this->sailing->cruise->ship_id)->where('slug', $this->input('cabin'))->first();
        $this->price = $cabin ? $this->sailing->cabinPrices()->with('cabinCategory')->where('cabin_category_id', $cabin->id)->first() : null;
        if (! $this->price) {
            $validator->errors()->add('cabin', 'Choose a cabin for this sailing.');

            return;
        }
        if ($this->price->is_sold_out) {
            $validator->errors()->add('cabin', "{$cabin->name_en} is sold out on this date. Choose another cabin.");

            return;
        }

        $guests = $this->guests();
        $total = array_sum($guests);
        if ($guests['infant'] > $guests['adult']) {
            $validator->errors()->add('guests', 'Each infant must travel with an adult in the same cabin.');
        }
        if ($total > $cabin->max_guests) {
            $validator->errors()->add('guests', "{$cabin->name_en} fits up to {$cabin->max_guests} guests, and you've added {$total}.");
        }

        $this->checkTravellers($validator, $guests);

        if ($error = $this->phoneError($this->input('contact.dial'), (string) $this->input('contact.phone'))) {
            $validator->errors()->add('contact.phone', $error);
        }

        if ($this->filled('promoCode')) {
            $this->promo = PromoCode::findRedeemable($this->input('promoCode'));
            if (! $this->promo) {
                $validator->errors()->add('promoCode', "That code isn't valid for this sailing. Remove it and try again.");
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
            $validator->errors()->add('travellers', 'Enter the details of every traveller.');

            return;
        }

        $ages = app(AgeBandValidator::class);
        foreach ($expected as $key) {
            [$band, $n] = explode('-', $key);
            $error = $ages->check(TravellerBand::from($band), $this->input("travellers.$key.dob"), $this->sailing->departure_date);
            if ($error) {
                $validator->errors()->add("travellers.$key.dob", ucfirst($band)." $n: $error");
            }
        }
    }

    /** Same rules as phoneError() in booking.js. */
    private function phoneError(?string $dial, string $raw): ?string
    {
        $n = ltrim(preg_replace('/[\s\-()]/', '', $raw), '0');

        return match (true) {
            $n === '' => 'Enter a mobile number.',
            ! ctype_digit($n) => 'Use digits only.',
            $dial === '+966' && ! preg_match('/^5\d{8}$/', $n) => 'Saudi mobile numbers have 9 digits and start with 5.',
            $dial === '+20' && ! preg_match('/^1\d{9}$/', $n) => 'Egyptian mobile numbers have 10 digits and start with 1.',
            strlen($n) < 7 || strlen($n) > 12 => 'Enter a mobile number with 7 to 12 digits.',
            default => null,
        };
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
