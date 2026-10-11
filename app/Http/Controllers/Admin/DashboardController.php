<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Sailing;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $confirmed = Booking::where('status', BookingStatus::Confirmed);
        $awaiting = Booking::awaitingPayment();

        return view('admin.dashboard', [
            'stats' => [
                'newThisWeek' => (clone $confirmed)->where('confirmed_at', '>=', now()->subDays(7))->count(),
                'awaitingCount' => (clone $awaiting)->count(),
                'outstanding' => (float) (clone $awaiting)->selectRaw('SUM(grand_total - amount_paid) as due')->value('due'),
                'collectedThisMonth' => (float) (clone $confirmed)->where('paid_at', '>=', now()->startOfMonth())->sum('amount_paid'),
            ],
            'awaiting' => $awaiting->with(['sailing.cruise', 'cabinCategory'])->oldest('confirmed_at')->limit(10)->get(),
            'recent' => Booking::with(['sailing.cruise', 'cabinCategory'])->latest('confirmed_at')->limit(8)->get(),
            'sailings' => Sailing::upcoming()->with('cruise')
                ->withCount(['bookings as booked_count' => fn ($q) => $q->where('status', BookingStatus::Confirmed)])
                ->withSum(['bookings as adults_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'adults')
                ->withSum(['bookings as children_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'children')
                ->withSum(['bookings as infants_sum' => fn ($q) => $q->where('status', BookingStatus::Confirmed)], 'infants')
                ->orderBy('departure_date')->limit(6)->get(),
        ]);
    }
}
