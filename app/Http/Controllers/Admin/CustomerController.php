<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resources\CustomerResource;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers' details page and the spreadsheet export. The list, edit and delete screens are the
 * generic CustomerResource ones.
 */
class CustomerController extends Controller
{
    public function show(int $customer): View
    {
        $customer = CustomerResource::scope()->withCount(['bookings', 'favorites'])->findOrFail($customer);

        return view('admin.customers.show', [
            'customer' => $customer,
            'bookings' => $customer->bookings()->with(['sailing.cruise', 'cabinCategory', 'cabins.cabinCategory', 'seller'])->latest()->get(),
            'spent' => (float) $customer->bookings()->where('status', BookingStatus::Confirmed)->sum('amount_paid'),
        ]);
    }

    /**
     * The customers list as it is filtered and searched on screen, as a CSV file Excel opens directly:
     * UTF-8 with a byte order mark (so Arabic names show correctly), one row per customer.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = CustomerResource::indexQuery($request)
            ->withCount(['bookings as confirmed_bookings_count' => fn ($q) => $q->where('status', BookingStatus::Confirmed)])
            ->withMax('bookings as last_booked_at', 'created_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'First name', 'Last name', 'Email', 'Phone', 'Nationality', 'Registered', 'Email verified', 'Bookings', 'Confirmed bookings', 'Last booking', 'Status']);

            $query->chunk(500, function ($customers) use ($out) {
                foreach ($customers as $c) {
                    fputcsv($out, array_map([$this, 'cell'], [
                        $c->id,
                        $c->first_name,
                        $c->last_name,
                        $c->email,
                        // "(+966) 501234567": a leading "+" would make Excel read it as a formula
                        $c->phone ? "({$c->phone_dial}) {$c->phone}" : '',
                        $c->nationality,
                        $c->created_at?->format('Y-m-d H:i'),
                        $c->email_verified_at ? 'Yes' : 'No',
                        $c->bookings_count,
                        $c->confirmed_bookings_count,
                        $c->last_booked_at ? substr((string) $c->last_booked_at, 0, 10) : '',
                        $c->bookings_count ? 'Has bookings' : 'No bookings yet',
                    ]));
                }
            });

            fclose($out);
        }, 'customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Text starting with = + - @ would run as a formula in Excel; prefix it with a quote. */
    private function cell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
