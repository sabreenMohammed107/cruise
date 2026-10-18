@php
  $sailing = $booking->sailing;
  $money = fn ($n) => config('cruise.currency_symbol').number_format((float) $n);
@endphp
<x-mail::message>
# New booking {{ $booking->reference }}

Contact the guest within {{ config('cruise.payment_contact_hours') }} hours to arrange payment of **{{ $money($booking->grand_total) }}**.

<x-mail::table>
| | |
|:--|:--|
| Sailing | {{ $sailing->cruise->name }} ({{ $sailing->code }}), {{ $sailing->departure_date->format('j M Y') }} |
| {{ $booking->cabins->count() > 1 ? 'Cabins' : 'Cabin' }} | {{ $booking->cabinSummary() }} |
| Guests | {{ $booking->adults }} adults, {{ $booking->children }} children, {{ $booking->infants }} infants |
| Contact | {{ $booking->contact_email }}, {{ $booking->contact_dial }} {{ $booking->contact_phone }} |
</x-mail::table>

<x-mail::button :url="route('admin.bookings.show', $booking)">
Open in admin
</x-mail::button>
</x-mail::message>
