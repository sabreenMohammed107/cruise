{{-- "EGP" tag of a booking made with the website in EGP (bookings.display_currency): the guest's voucher and email
     show that currency, so the team quotes the same figure. Nothing for USD bookings. --}}
@if ($booking->inDisplayCurrency())
  <span class="tag bg-[#F1E9FB] text-[#5B2A86]" title="Guest booked in {{ $booking->display_currency }} at 1 USD = {{ number_format((float) $booking->exchange_rate, 2) }} {{ $booking->display_currency }}">Booked in {{ $booking->display_currency }}</span>
@endif
