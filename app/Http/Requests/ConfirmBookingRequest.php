<?php

namespace App\Http\Requests;

use App\Enums\TravellerBand;
use App\Models\AgeBand;
use App\Models\CabinCategory;
use App\Models\PromoCode;
use App\Models\Sailing;
use App\Models\SailingCabinPrice;
use App\Services\AgeBandValidator;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Confirm booking" (booking.js). Re-checks on the server everything the template checks in the
 * browser (details.js guest limits, booking.js traveller and contact rules) plus availability.
 *
 * Payload (step 15): one or more cabins on the same sailing, each with its own guests and travellers:
 *
 *   { sailing, cabins: [ { cabin: "balcony", guests: {adult, child, infant}, travellers: {"adult-1": {...}} }, … ],
 *     contact: {email, dial, phone}, promoCode, agreed }
 *
 * The one-cabin payload of earlier versions ({ cabin, guests, travellers }) is still accepted and read as
 * a booking with one cabin, so a browser running a cached old booking.js can still book.
 */
class ConfirmBookingRequest extends FormRequest
{
    public const NAME_PATTERN = "/^[\p{L}][\p{L}\s'’\-]{0,48}$/u";

    public ?Sailing $sailing = null;

    public ?PromoCode $promo = null;

    /** @var list<array{price: SailingCabinPrice, guests: array{adult: int, child: int, infant: int}, travellers: array}> */
    public array $cabins = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('cabins') && $this->has('cabin')) {
            $this->merge(['cabins' => [[
                'cabin' => $this->input('cabin'),
                'guests' => $this->input('guests'),
                'travellers' => $this->input('travellers'),
            ]]]);
        }
    }

    public function rules(): array
    {
        $max = (int) config('cruise.max_cabin_guests');

        return [
            'sailing' => ['required', 'string', 'max:20'],
            'cabins' => ['required', 'array', 'min:1', 'max:'.config('cruise.max_cabins_per_booking')],
            'cabins.*.cabin' => ['required', 'string', 'max:40'],
            'cabins.*.guests' => ['required', 'array'],
            // "at least one adult in every cabin" is checked after, with a clearer message
            'cabins.*.guests.adult' => ['required', 'integer', 'min:0', "max:$max"],
            'cabins.*.guests.child' => ['required', 'integer', 'min:0', "max:$max"],
            'cabins.*.guests.infant' => ['required', 'integer', 'min:0', "max:$max"],
            'cabins.*.travellers' => ['required', 'array'],
            'cabins.*.travellers.*.first' => ['required', 'string', 'regex:'.self::NAME_PATTERN],
            'cabins.*.travellers.*.last' => ['required', 'string', 'regex:'.self::NAME_PATTERN],
            'cabins.*.travellers.*.dob' => ['required', 'date_format:Y-m-d'],
            'cabins.*.travellers.*.gender' => ['required', Rule::in(['Male', 'Female', 'male', 'female'])],
            'cabins.*.travellers.*.nationality' => ['required', Rule::in(config('cruise.nationalities'))],
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
        $e = fn (string $key, array $r = []) => __("site.booking.errors.$key", $r);

        return [
            'cabins.required' => $e('choose_cabin'),
            'cabins.max' => $e('too_many_cabins', ['max' => config('cruise.max_cabins_per_booking')]),
            'cabins.*.travellers.*.first.*' => $e('first_each'),
            'cabins.*.travellers.*.last.*' => $e('last_each'),
            'cabins.*.travellers.*.dob.*' => $e('dob_each'),
            'cabins.*.travellers.*.gender.*' => $e('gender_each'),
            'cabins.*.travellers.*.nationality.*' => $e('nationality_each'),
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

        $several = count($this->input('cabins')) > 1;
        foreach (array_values($this->input('cabins')) as $i => $row) {
            $this->checkCabin($validator, $i, $row, $several);
        }

        if ($error = PhoneNumber::error($this->input('contact.dial'), $this->input('contact.phone'))) {
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
     * One cabin: on this ship and priced on this sailing, not sold out, at least one adult (also for any infant),
     * everyone (infants included) within the cabin's limit, and the travellers of exactly its guest slots.
     * With several cabins, messages start with "Cabin 2: …".
     */
    private function checkCabin(Validator $validator, int $i, array $row, bool $several): void
    {
        $key = "cabins.$i";
        $say = fn (string $message) => $several ? __('site.booking.errors.in_cabin', ['n' => $i + 1, 'error' => $message]) : $message;

        $cabin = CabinCategory::where('ship_id', $this->sailing->cruise->ship_id)->where('slug', $row['cabin'])->first();
        $price = $cabin ? $this->sailing->cabinPrices()->with('cabinCategory')->where('cabin_category_id', $cabin->id)->first() : null;
        if (! $price) {
            $validator->errors()->add("$key.cabin", $say(__('site.booking.errors.choose_cabin')));

            return;
        }
        $cabinName = app()->getLocale() === 'ar' ? ($cabin->name_ar ?: $cabin->name_en) : $cabin->name_en;
        if ($price->is_sold_out) {
            $validator->errors()->add("$key.cabin", $say(__('site.booking.errors.cabin_sold_out', ['cabin' => $cabinName])));

            return;
        }

        $guests = [
            'adult' => (int) ($row['guests']['adult'] ?? 0),
            'child' => (int) ($row['guests']['child'] ?? 0),
            'infant' => (int) ($row['guests']['infant'] ?? 0),
        ];
        // Same rules as SC.guestError() in core.js: an adult in every cabin (and so with every infant), and
        // adults + children + infants together within the cabin's limit
        if ($guests['adult'] < 1) {
            $validator->errors()->add("$key.guests", $say(__(match (true) {
                $guests['infant'] > 0 => 'site.booking.errors.infant_needs_adult',
                $several => 'site.booking.errors.adult_per_cabin',
                default => 'site.booking.errors.adult_required',
            })));
        }
        $total = array_sum($guests);
        if ($total > $cabin->capacity()) {
            $validator->errors()->add("$key.guests", $say(__('site.booking.errors.cabin_too_small', ['cabin' => $cabinName, 'max' => $cabin->capacity(), 'count' => $total])));
        }

        $travellers = (array) ($row['travellers'] ?? []);
        if ($this->checkTravellers($validator, $key, $guests, $travellers, $say)) {
            $this->cabins[] = ['price' => $price, 'guests' => $guests, 'travellers' => $travellers];
        }
    }

    /**
     * Travellers must be exactly the cabin's slots (adult-1, adult-2, child-1, …) and each
     * date of birth must fit its age band on the departure date.
     */
    private function checkTravellers(Validator $validator, string $key, array $guests, array $travellers, \Closure $say): bool
    {
        $expected = [];
        foreach (TravellerBand::cases() as $band) {
            for ($n = 1; $n <= $guests[$band->value]; $n++) {
                $expected[] = "{$band->value}-{$n}";
            }
        }

        $given = array_keys($travellers);
        sort($given);
        $sorted = $expected;
        sort($sorted);
        if ($given !== $sorted) {
            $validator->errors()->add("$key.travellers", $say(__('site.booking.errors.travellers')));

            return false;
        }

        $ages = app(AgeBandValidator::class);
        // "Adult 1", "رضيع 1": band labels from Admin → Age bands, in the visitor's language
        $labels = AgeBand::all()->mapWithKeys(fn (AgeBand $b) => [$b->key->value => $b->tr('label')]);
        $ok = true;
        foreach ($expected as $slot) {
            [$band, $n] = explode('-', $slot);
            $error = $ages->check(TravellerBand::from($band), $travellers[$slot]['dob'] ?? null, $this->sailing->departure_date);
            if ($error) {
                $ok = false;
                $validator->errors()->add("$key.travellers.$slot.dob", $say(__('site.booking.errors.traveller', ['traveller' => ($labels[$band] ?? ucfirst($band))." $n", 'error' => $error])));
            }
        }

        return $ok;
    }

    /** Phone number as the template normalises it (no spaces, dashes or leading zeros). */
    public function normalisedPhone(): string
    {
        return PhoneNumber::normalise($this->input('contact.phone'));
    }
}
