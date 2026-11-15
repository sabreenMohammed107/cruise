<?php

namespace App\Services\Assistant\Tools;

use App\Models\CabinCategory;
use App\Models\Sailing;
use App\Models\User;
use App\Services\AgentPricing;
use App\Services\Assistant\BookingDraft;
use App\Services\OfferService;
use App\Services\PricingService;
use Illuminate\Validation\ValidationException;

/**
 * Books a cruise from the chat, in two steps: this tool checks the travellers' details with the booking page's rules
 * and shows the visitor a summary with a "Confirm booking" button; only their click books
 * (AssistantController::book(), BookingService like the booking page). The turn ends here.
 */
class PrepareBooking extends Tool
{
    public function name(): string
    {
        return 'prepare_booking';
    }

    public function description(): string
    {
        return 'Prepare a booking once you have every detail. Checks them exactly like the booking page and shows the '
            .'visitor a summary with the total and a "Confirm booking" button. Nothing is booked until the visitor presses '
            .'it; your turn ends after this call. If it returns errors, ask the visitor only for what is wrong and call it '
            .'again. Each traveller: type (adult 18+, child 2-17, infant under 2 on the departure date), first and last '
            .'name as in the passport, date of birth, gender, nationality (English country name). Every cabin needs an adult.';
    }

    public function inputSchema(): array
    {
        $traveller = [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => ['adult', 'child', 'infant']],
                'first_name' => ['type' => 'string'],
                'last_name' => ['type' => 'string'],
                'date_of_birth' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'gender' => ['type' => 'string', 'enum' => ['male', 'female']],
                'nationality' => ['type' => 'string', 'description' => 'Country in English, e.g. "Egypt", "Saudi Arabia".'],
            ],
            'required' => ['type', 'first_name', 'last_name', 'date_of_birth', 'gender', 'nationality'],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'sailing_code' => ['type' => 'string', 'description' => 'e.g. "RSD-261213"'],
                'cabins' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'cabin' => ['type' => 'string', 'description' => 'Cabin type slug or name, e.g. "balcony".'],
                            'travellers' => ['type' => 'array', 'minItems' => 1, 'items' => $traveller],
                        ],
                        'required' => ['cabin', 'travellers'],
                    ],
                ],
                'contact_email' => ['type' => 'string'],
                'contact_dial_code' => ['type' => 'string', 'description' => 'Country calling code of the mobile, e.g. "+20".'],
                'contact_phone' => ['type' => 'string', 'description' => 'Mobile number without the country code.'],
                'promo_code' => ['type' => 'string', 'description' => 'Only if the visitor gave one.'],
            ],
            'required' => ['sailing_code', 'cabins', 'contact_email', 'contact_dial_code', 'contact_phone'],
        ];
    }

    public function stopsTurn(): bool
    {
        return true;
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $this->conversation) {
            return ['error' => 'Booking is not available in this chat.'];
        }

        $sailing = Sailing::with('cruise', 'cabinPrices.cabinCategory')->where('code', (string) $this->str($input, 'sailing_code'))->first();
        if (! $sailing || ! $sailing->isBookable()) {
            return ['error' => 'This sailing cannot be booked (unknown code, closed or already departed).'];
        }

        $cabins = [];
        $problems = [];
        foreach (array_values(array_filter((array) ($input['cabins'] ?? []), 'is_array')) as $i => $row) {
            $wanted = mb_strtolower(trim((string) ($row['cabin'] ?? '')));
            $price = $sailing->cabinPrices->first(fn ($p) => $p->cabinCategory && $this->cabinMatches($p->cabinCategory, $wanted));
            if (! $price) {
                $problems[] = 'Cabin '.($i + 1).': not offered on this sailing. Cabins: '.$sailing->cabinPrices->map(fn ($p) => $p->cabinCategory?->slug)->filter()->implode(', ');

                continue;
            }

            $guests = ['adult' => 0, 'child' => 0, 'infant' => 0];
            $travellers = [];
            foreach (array_filter((array) ($row['travellers'] ?? []), 'is_array') as $t) {
                $band = in_array($t['type'] ?? null, ['adult', 'child', 'infant'], true) ? $t['type'] : 'adult';
                $nationality = $this->nationality((string) ($t['nationality'] ?? ''));
                if ($nationality === null) {
                    $problems[] = 'Nationality "'.mb_substr((string) ($t['nationality'] ?? ''), 0, 40).'" is not in the list; use the English country name, e.g. '.implode(', ', array_slice(config('cruise.nationalities'), 0, 8)).'.';
                }
                $travellers[$band.'-'.(++$guests[$band])] = [
                    'first' => trim((string) ($t['first_name'] ?? '')),
                    'last' => trim((string) ($t['last_name'] ?? '')),
                    'dob' => trim((string) ($t['date_of_birth'] ?? '')),
                    'gender' => strtolower(trim((string) ($t['gender'] ?? ''))),
                    'nationality' => $nationality ?? '',
                ];
            }
            $cabins[] = ['cabin' => $price->cabinCategory->slug, 'guests' => $guests, 'travellers' => $travellers];
        }
        if ($problems !== []) {
            return ['error' => 'Some details need fixing.', 'problems' => $problems];
        }

        $payload = [
            'sailing' => $sailing->code,
            'cabins' => $cabins,
            'contact' => [
                'email' => trim((string) ($input['contact_email'] ?? '')),
                'dial' => '+'.ltrim(preg_replace('/[^\d]/', '', (string) ($input['contact_dial_code'] ?? '')), '0'),
                'phone' => trim((string) ($input['contact_phone'] ?? '')),
            ],
            'promoCode' => $this->str($input, 'promo_code'),
            'agreed' => true,
        ];

        try {
            $request = BookingDraft::validate($payload, $user);
        } catch (ValidationException $e) {
            return ['error' => 'The booking page would refuse these details.', 'problems' => array_values(array_unique($e->validator->errors()->all()))];
        }

        $quote = $this->quote($request->cabins, $request, $sailing, $user);
        $id = BookingDraft::put($this->conversation, $payload, $user);

        return [
            'shown' => true,
            'total' => $this->money($quote['total']),
            'note' => 'The visitor sees the summary and a "Confirm booking" button. Nothing is booked until they press it: '
                .'never say it is booked. If they want to change something, call prepare_booking again.',
            '_ui' => $this->card($id, $sailing, $request, $quote, $user),
        ];
    }

    /** The same total BookingService will save (offers, promo code, taxes, agent rate). */
    private function quote(array $cabins, $request, Sailing $sailing, ?User $user): array
    {
        $agents = app(AgentPricing::class);
        $agent = $agents->appliesTo($user) ? $user : null;
        if ($agent) {
            $cabins = array_map(fn ($c) => $c + ['agentPercent' => $agents->percentFor($c['price'], $agent)], $cabins);
        }
        $offers = app(OfferService::class)->forSailing($sailing, $user);

        return app(PricingService::class)->quoteBooking($cabins, $request->promo, $offers, $sailing->departure_date, $agent);
    }

    private function card(string $id, Sailing $sailing, $request, array $quote, ?User $user): array
    {
        $b = fn (string $key, array $r = []) => __("assistant.booking.$key", $r);
        $lines = [$b('sailing', ['date' => $sailing->departure_date->toDateString(), 'code' => $sailing->code])];
        foreach ($request->cabins as $i => $cabin) {
            $category = $cabin['price']->cabinCategory;
            $name = $this->isArabic() && filled($category->name_ar) ? $category->name_ar : $category->name_en;
            $people = [];
            foreach ($cabin['travellers'] as $slot => $t) {
                [$band] = explode('-', $slot);
                $people[] = trim($t['first'].' '.$t['last']).' ('.$b($band).', '.$t['dob'].')';
            }
            $lines[] = $b('cabin', ['n' => $i + 1, 'cabin' => $name]).': '.implode('، ', $people);
        }
        $contact = $request->input('contact');
        $lines[] = $b('contact').': '.$contact['email'].' · '.$contact['dial'].' '.$request->normalisedPhone();
        if ($request->promo) {
            $lines[] = $b('promo').': '.$request->promo->code;
        }

        return [
            'type' => 'booking',
            'id' => $id,
            'confirm_url' => route('assistant.booking'),
            'title' => $b('title').' · '.$sailing->cruise?->tr('name'),
            'lines' => $lines,
            'total' => $b('total').': '.$this->money($quote['total'])['display'].($this->agentApplies($user) ? ' ('.$b('agent_rate').')' : ''),
            'terms' => $b('terms', ['hours' => config('cruise.payment_contact_hours')]),
            'confirm' => $b('confirm'),
        ];
    }

    private function agentApplies(?User $user): bool
    {
        return app(AgentPricing::class)->appliesTo($user);
    }

    /** "egypt", "Egyptian" → "Egypt" (config cruise.nationalities), or null. */
    private function nationality(string $given): ?string
    {
        $given = mb_strtolower(trim($given));
        if ($given === '') {
            return null;
        }
        $list = collect(config('cruise.nationalities'));
        $rules = [
            fn (string $c) => $c === $given,
            fn (string $c) => str_contains($given, $c),             // "united states of america"
            fn (string $c) => strlen($given) >= 4 && str_contains($c, $given),
            fn (string $c) => strlen($given) >= 5 && str_starts_with($given, substr($c, 0, 5)), // "egyptian", "saudi"
        ];
        foreach ($rules as $rule) {
            $found = $list->filter(fn ($country) => $rule(mb_strtolower($country)));
            if ($found->count() === 1) {
                return $found->first();
            }
        }

        return null;
    }

    private function cabinMatches(CabinCategory $cabin, string $wanted): bool
    {
        if ($wanted === '') {
            return false;
        }
        foreach ([$cabin->slug, $cabin->name_en, $cabin->name_ar] as $name) {
            $name = mb_strtolower((string) $name);
            if ($name !== '' && ($name === $wanted || str_contains($name, $wanted))) {
                return true;
            }
        }

        return false;
    }
}
