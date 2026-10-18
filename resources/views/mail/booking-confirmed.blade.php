@php
  $sailing = $booking->sailing;
  $cruise = $sailing->cruise;
  $money = fn ($n) => config('cruise.currency_symbol').number_format((float) $n);
@endphp
<x-mail::message>
# Your cruise is booked

Booking reference: **{{ $booking->reference }}**

Our team will contact you within {{ config('cruise.payment_contact_hours') }} hours to arrange payment. There is nothing to pay online.

<x-mail::table>
| | |
|:--|:--|
| Cruise | {{ $cruise->name }} |
| Ship | {{ $cruise->ship->name }} |
| Departs | {{ $sailing->departure_date->format('j M Y') }}, {{ $cruise->departure_port }} |
| Returns | {{ $sailing->return_date->format('j M Y') }}, {{ $cruise->arrival_port }} |
| {{ $booking->cabins->count() > 1 ? 'Cabins' : 'Cabin' }} | {{ $booking->cabinSummary() }} |
| Travellers | {{ $booking->travellers->map(fn ($t) => "$t->first_name $t->last_name")->join(', ') }} |
| **Total** | **{{ $money($booking->grand_total) }}** |
</x-mail::table>

<x-mail::button :url="\App\Http\Controllers\BookingController::signedConfirmationUrl($booking)">
View your booking
</x-mail::button>

Bring a passport valid for at least six months, or a Saudi national ID for domestic-only sailings, to check-in.

Safer Cruise
</x-mail::message>
