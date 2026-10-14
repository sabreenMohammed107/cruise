<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Sailing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Admins see every booking; a seller sees the figures and lists for the bookings assigned to them. */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $bookings = fn () => Booking::query()->when($user->isSeller(), fn ($q) => $q->where('seller_id', $user->id));

        $confirmed = $bookings()->where('status', BookingStatus::Confirmed);
        $awaiting = $bookings()->awaitingPayment();

        return view('admin.dashboard', [
            'stats' => [
                'newThisWeek' => (clone $confirmed)->where('confirmed_at', '>=', now()->subDays(7))->count(),
                'awaitingCount' => (clone $awaiting)->count(),
                'outstanding' => (float) (clone $awaiting)->selectRaw('SUM(grand_total - amount_paid) as due')->value('due'),
                'collectedThisMonth' => (float) (clone $confirmed)->where('paid_at', '>=', now()->startOfMonth())->sum('amount_paid'),
            ],
            'awaiting' => $awaiting->with(['sailing.cruise', 'cabinCategory', 'seller'])->oldest('confirmed_at')->limit(10)->get(),
            'recent' => $bookings()->with(['sailing.cruise', 'cabinCategory', 'seller'])->latest('confirmed_at')->limit(8)->get(),
            // Sailings (and their price screens) are for admins
            'sailings' => $user->isAdmin() ? Sailing::upcoming()->with('cruise')
                ->withCount(['bookings as booked_count' => fn ($q) => $q->where('status', BookingStatus::Confirmed)])
                ->withSum(['bookings as adults_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'adults')
                ->withSum(['bookings as children_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'children')
                ->withSum(['bookings as infants_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'infants')
                ->orderBy('departure_date')->limit(6)->get() : null,
        ]);
    }
}
