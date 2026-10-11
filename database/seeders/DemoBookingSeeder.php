<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\SailingStatus;
use App\Models\Booking;
use App\Models\CabinCategory;
use App\Models\Cruise;
use App\Models\Sailing;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The three sample trips on dashboard.html (upcoming / past / cancelled) for reem@example.com.
 * The past and cancelled trips depart on dates that aren't in SC_DATA, so their sailings are
 * created here as closed departures.
 */
class DemoBookingSeeder extends Seeder
{
    public function run(PricingService $pricing): void
    {
        $user = User::where('email', 'reem@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@safercruise.test')->first();

        $primary = ['first_name' => 'Reem', 'last_name' => 'Alharbi', 'date_of_birth' => '1990-04-12', 'gender' => 'female', 'nationality' => 'Saudi Arabia'];
        $spouse = ['first_name' => 'Faisal', 'last_name' => 'Alharbi', 'date_of_birth' => '1987-08-03', 'gender' => 'male', 'nationality' => 'Saudi Arabia'];

        $trips = [
            [
                'ref' => 'SC2611-K4P2Q', 'cruise' => 'saudi-coast-weekend', 'sailing' => 'SCW-261105', 'departure' => '2026-11-05',
                'cabin' => 'balcony', 'status' => BookingStatus::Confirmed, 'paid' => false,
                'travellers' => [
                    ['adult', 1, $primary], ['adult', 2, $spouse],
                    ['child', 1, ['first_name' => 'Lana', 'last_name' => 'Alharbi', 'date_of_birth' => '2019-06-20', 'gender' => 'female', 'nationality' => 'Saudi Arabia']],
                ],
            ],
            [
                'ref' => 'SC2603-H7M1Z', 'cruise' => 'red-sea-discovery', 'sailing' => 'RSD-260315', 'departure' => '2026-03-15',
                'cabin' => 'interior', 'status' => BookingStatus::Confirmed, 'paid' => true,
                'travellers' => [['adult', 1, $primary], ['adult', 2, $spouse]],
            ],
            [
                'ref' => 'SC2605-T9R3B', 'cruise' => 'egypt-jordan-voyage', 'sailing' => 'EJV-260522', 'departure' => '2026-05-22',
                'cabin' => 'suite', 'status' => BookingStatus::Cancelled, 'paid' => true,
                'travellers' => [
                    ['adult', 1, $primary], ['adult', 2, $spouse],
                    ['child', 1, ['first_name' => 'Yousef', 'last_name' => 'Alharbi', 'date_of_birth' => '2016-02-10', 'gender' => 'male', 'nationality' => 'Saudi Arabia']],
                    ['child', 2, ['first_name' => 'Lana', 'last_name' => 'Alharbi', 'date_of_birth' => '2019-06-20', 'gender' => 'female', 'nationality' => 'Saudi Arabia']],
                ],
            ],
        ];

        foreach ($trips as $trip) {
            $cruise = Cruise::where('slug', $trip['cruise'])->firstOrFail();
            $departure = Carbon::parse($trip['departure']);

            $sailing = Sailing::firstOrCreate(['code' => $trip['sailing']], [
                'cruise_id' => $cruise->id,
                'departure_date' => $departure,
                'return_date' => $departure->copy()->addDays($cruise->nights),
                'price_factor' => 1.0,
                'status' => $departure->isPast() ? SailingStatus::Closed : SailingStatus::Open,
            ]);
            if ($sailing->wasRecentlyCreated) {
                $pricing->syncSailingPrices($sailing);
            }

            $cabin = CabinCategory::where('ship_id', $cruise->ship_id)->where('slug', $trip['cabin'])->firstOrFail();
            $price = $sailing->cabinPrices()->where('cabin_category_id', $cabin->id)->firstOrFail();

            $guests = ['adult' => 0, 'child' => 0, 'infant' => 0];
            foreach ($trip['travellers'] as [$band]) {
                $guests[$band]++;
            }
            $quote = $pricing->quote($price, $guests);

            $bookedAt = $departure->copy()->subDays(45)->setTime(10, 30);
            $cancelled = $trip['status'] === BookingStatus::Cancelled;

            $booking = Booking::updateOrCreate(['reference' => $trip['ref']], [
                'user_id' => $user->id,
                'sailing_id' => $sailing->id,
                'cabin_category_id' => $cabin->id,
                'status' => $trip['status'],
                'adults' => $guests['adult'],
                'children' => $guests['child'],
                'infants' => $guests['infant'],
                'contact_email' => $user->email,
                'contact_dial' => $user->phone_dial,
                'contact_phone' => $user->phone,
                'fare_total' => $quote['fare'],
                'fees_total' => $quote['fees'],
                'discount_total' => $quote['discount'],
                'grand_total' => $quote['total'],
                'currency' => config('cruise.currency'),
                'price_snapshot' => $quote,
                'confirmed_at' => $bookedAt,
                'cancelled_at' => $cancelled ? $bookedAt->copy()->addDays(10) : null,
            ]);

            $booking->travellers()->delete();
            foreach ($trip['travellers'] as [$band, $slot, $person]) {
                $booking->travellers()->create($person + [
                    'band' => $band, 'slot' => $slot, 'is_primary' => $band === 'adult' && $slot === 1,
                ]);
            }

            // Offline payments recorded by the team. The upcoming trip is left unpaid so the
            // admin "awaiting payment" list has something in it.
            $booking->payments()->delete();
            if ($trip['paid']) {
                $booking->payments()->create([
                    'type' => PaymentType::Payment,
                    'method' => PaymentMethod::BankTransfer,
                    'amount' => $quote['total'],
                    'currency' => config('cruise.currency'),
                    'reference' => 'DEMO-'.$trip['ref'],
                    'received_at' => $bookedAt->copy()->addDay(),
                    'recorded_by' => $admin?->id,
                ]);
            }
            if ($cancelled && $trip['paid']) {
                $booking->payments()->create([
                    'type' => PaymentType::Refund,
                    'method' => PaymentMethod::BankTransfer,
                    'amount' => $quote['total'] / 2,
                    'currency' => config('cruise.currency'),
                    'reference' => 'DEMO-REFUND-'.$trip['ref'],
                    'notes' => 'Cancelled 35 days before departure: 50% penalty under the cancellation policy (15–60 days).',
                    'received_at' => $booking->cancelled_at,
                    'recorded_by' => $admin?->id,
                ]);
            }
            $booking->syncPaymentTotals();
        }
    }
}
