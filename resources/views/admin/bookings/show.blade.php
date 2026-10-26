@extends('layouts.admin')
@use('App\Support\Format')

@php
  $sailing = $booking->sailing;
  $cruise = $sailing->cruise;
  $quote = $booking->price_snapshot ?? [];
  $cancelled = $booking->status === \App\Enums\BookingStatus::Cancelled;
@endphp

@section('title', 'Booking '.$booking->reference)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.bookings.index') }}" class="hover:text-ocean-600">Bookings</a> <span class="mx-1">/</span> <span class="text-ink">{{ $booking->reference }}</span></nav>

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl tabular">{{ $booking->reference }}</h1>
      <div class="mt-2 flex flex-wrap gap-1.5">@include('admin.partials.badge', ['status' => $booking->status])@include('admin.partials.badge', ['status' => $booking->payment_status])</div>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('booking.confirmation', $booking) }}" target="_blank" class="btn btn-outline btn-sm"><i data-icon="download" class="w-4 h-4"></i> Guest voucher</a>
      <a href="https://wa.me/{{ ltrim($booking->contact_dial, '+') }}{{ $booking->contact_phone }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i data-icon="chat" class="w-4 h-4"></i> WhatsApp guest</a>
      @if (! $cancelled && auth()->user()->can('admin'))
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel booking {{ $booking->reference }}? The guest is not notified automatically.')">
          @csrf
          <button class="btn btn-ghost btn-sm text-[#C42F2F]"><i data-icon="x" class="w-4 h-4"></i> Cancel booking</button>
        </form>
      @endif
    </div>
  </div>

  <div class="grid xl:grid-cols-[1fr_380px] gap-6 mt-6 items-start">
    <div class="grid gap-6 min-w-0">
      {{-- Trip --}}
      <section class="card p-5 sm:p-6">
        <h2 class="font-display font-semibold text-lg mb-4">Trip</h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-5 text-sm">
          @foreach ([
              'Cruise' => $cruise->name,
              'Sailing' => $sailing->code,
              'Ship' => $cruise->ship->name,
              'Departs' => Format::date($sailing->departure_date, 'D j M Y').', '.$cruise->departure_port,
              'Returns' => Format::date($sailing->return_date, 'D j M Y').', '.$cruise->arrival_port,
              ($booking->cabins->count() > 1 ? 'Cabins ('.$booking->cabins->count().')' : 'Cabin') => $booking->cabinSummary(),
              'Guests' => Format::guests($booking->adults, $booking->children, $booking->infants),
              'Booked' => Format::date($booking->confirmed_at, 'j M Y, H:i'),
              'Account' => $booking->user?->email ?? 'Guest checkout',
          ] as $label => $value)
            <div><dt class="text-xs font-semibold text-muted">{{ $label }}</dt><dd class="font-bold mt-0.5">{{ $value }}</dd></div>
          @endforeach
        </dl>
      </section>

      {{-- Travellers --}}
      <section class="card p-5 sm:p-6">
        <h2 class="font-display font-semibold text-lg mb-4">Travellers</h2>
        <ul class="divide-y divide-line text-sm">
          @foreach ($booking->travellers as $t)
            {{-- several cabins (step 15): a heading before each cabin's travellers --}}
            @if ($booking->cabins->count() > 1 && ($loop->first || $booking->travellers[$loop->index - 1]->booking_cabin_id !== $t->booking_cabin_id))
              @php($c = $booking->cabins->firstWhere('id', $t->booking_cabin_id))
              <li class="pt-4 pb-1 first:pt-0 text-xs font-bold text-muted">Cabin {{ $c?->position }} · {{ $c?->cabinCategory->name_en }} ({{ $c ? Format::guests($c->adults, $c->children, $c->infants) : '' }})</li>
            @endif
            <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold">{{ $t->first_name }} {{ $t->last_name }} @if ($t->is_primary)<span class="text-xs font-semibold text-lagoon-600 ms-1">Primary guest</span>@endif</p>
                <p class="text-muted">Born {{ Format::date($t->date_of_birth) }} ({{ intdiv(app(\App\Services\AgeBandValidator::class)->monthsAt($t->date_of_birth, $sailing->departure_date), 12) }} on departure), {{ ucfirst($t->gender->value) }}, {{ $t->nationality }}</p>
              </div>
              <span class="tag tag-ocean">{{ ucfirst($t->band->value) }} {{ $t->slot }}</span>
            </li>
          @endforeach
        </ul>
        <div class="mt-5 pt-4 border-t border-line text-sm flex flex-wrap gap-x-6 gap-y-1">
          <span class="inline-flex items-center gap-1.5"><i data-icon="mail" class="w-4 h-4 text-muted"></i><a href="mailto:{{ $booking->contact_email }}" class="hover:underline">{{ $booking->contact_email }}</a></span>
          <span class="inline-flex items-center gap-1.5 tabular" dir="ltr"><i data-icon="phone" class="w-4 h-4 text-muted"></i><a href="tel:{{ $booking->contact_dial }}{{ $booking->contact_phone }}" class="hover:underline">{{ $booking->contact_dial }} {{ $booking->contact_phone }}</a></span>
        </div>
      </section>

      {{-- Price --}}
      <section class="card p-5 sm:p-6">
        <h2 class="font-display font-semibold text-lg mb-4">Price at booking</h2>
        <table class="w-full text-sm tabular">
          <tbody>
            @foreach ($quote['lines'] ?? [] as $line)
              @if (count($quote['cabins'] ?? []) > 1 && ($loop->first || ($quote['lines'][$loop->index - 1]['cabin'] ?? null) !== ($line['cabin'] ?? null)))
                @php($part = collect($quote['cabins'])->firstWhere('position', $line['cabin'] ?? 1))
                <tr class="border-b border-line bg-page"><td class="py-2 font-bold" colspan="3">Cabin {{ $part['position'] ?? '' }} · {{ $part['name'] ?? '' }} <span class="font-normal text-muted">({{ Format::guests($part['guests']['adult'] ?? 0, $part['guests']['child'] ?? 0, $part['guests']['infant'] ?? 0) }}@if ((float) ($part['fees'] ?? 0) > 0), fees {{ Format::money($part['fees']) }}@endif)</span></td></tr>
              @endif
              <tr class="border-b border-line"><td class="py-2">{{ $line['label'] ?? ucfirst($line['band']) }}</td><td class="text-end">{{ Format::money($line['unit']) }} × {{ $line['qty'] }}</td><td class="text-end font-bold">{{ Format::money($line['total']) }}</td></tr>
            @endforeach
            {{-- Prices include tax since step 21; only older bookings have separate taxes and port fees --}}
            @if ((float) $booking->fees_total > 0)
              <tr class="border-b border-line"><td class="py-2">Taxes, port charges & fees</td><td></td><td class="text-end font-bold">{{ Format::money($booking->fees_total) }}</td></tr>
            @endif
            @if ((float) $booking->discount_total)
              <tr class="border-b border-line text-[#12805C]"><td class="py-2">Discount ({{ $quote['promo']['code'] ?? $booking->promoCode?->code }})</td><td></td><td class="text-end font-bold">− {{ Format::money($booking->discount_total) }}</td></tr>
            @endif
            <tr><td class="pt-3 font-bold text-base">Total @if ((float) $booking->fees_total <= 0)<span class="block text-xs font-semibold text-lagoon-600">Tax included</span>@endif</td><td></td><td class="pt-3 text-end font-display font-bold text-xl text-ocean-800">{{ Format::money($booking->grand_total) }}</td></tr>
          </tbody>
        </table>
      </section>

      {{-- Notes --}}
      <form method="POST" action="{{ route('admin.bookings.update', $booking) }}" class="card p-5 sm:p-6">
        @csrf @method('PATCH')
        <label for="admin_notes" class="font-display font-semibold text-lg">Team notes</label>
        <p class="text-sm text-muted">Only visible to admins.</p>
        <textarea id="admin_notes" name="admin_notes" rows="3" class="field mt-3">{{ old('admin_notes', $booking->admin_notes) }}</textarea>
        <div class="mt-3 flex justify-end"><button class="btn btn-outline btn-sm">Save notes</button></div>
      </form>
    </div>

    {{-- Payments --}}
    <aside class="grid gap-6 xl:sticky xl:top-[88px]">
      {{-- Assigned seller: admins choose, the seller sees it --}}
      <section class="card p-5">
        <h2 class="font-display font-semibold text-lg">Assigned seller</h2>
        @can('admin')
          <form method="POST" action="{{ route('admin.bookings.seller', $booking) }}" class="mt-3 flex gap-2">
            @csrf @method('PATCH')
            <label for="seller_id" class="sr-only">Seller</label>
            <select id="seller_id" name="seller_id" class="field !py-2">
              <option value="">Unassigned</option>
              @foreach ($sellers as $s)
                <option value="{{ $s->id }}" @selected($booking->seller_id === $s->id)>{{ $s->name }}</option>
              @endforeach
            </select>
            <button class="btn btn-primary btn-sm shrink-0">Save</button>
          </form>
          @error('seller_id')<p class="err show">{{ $message }}</p>@enderror
          @if ($sellers->isEmpty())
            <p class="text-xs text-muted mt-2">No sellers yet. Add one in <a href="{{ route('admin.users.create') }}" class="underline">Admins &amp; staff</a> with the Seller role.</p>
          @endif
        @else
          <p class="mt-2 font-bold">{{ $booking->seller?->name ?? 'Unassigned' }}</p>
        @endcan
      </section>

      <section class="card overflow-hidden">
        <div class="p-5">
          <h2 class="font-display font-semibold text-lg">Payment</h2>
          <dl class="mt-4 grid gap-2 text-sm tabular">
            <div class="flex justify-between"><dt class="text-muted">Total</dt><dd class="font-bold">{{ Format::money($booking->grand_total, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">Received (net of refunds)</dt><dd class="font-bold">{{ Format::money($booking->amount_paid, 2) }}</dd></div>
            @unless ($cancelled)
              <div class="flex justify-between pt-2 border-t border-line"><dt class="font-bold">Still due</dt><dd class="font-display font-bold text-lg text-ocean-800">{{ Format::money($booking->amountDue(), 2) }}</dd></div>
            @endunless
          </dl>
        </div>

        @if ($booking->payments->isNotEmpty())
          <ul class="border-t border-line divide-y divide-line text-sm">
            @foreach ($booking->payments as $p)
              @php($refund = $p->type === \App\Enums\PaymentType::Refund)
              <li class="px-5 py-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="font-bold {{ $refund ? 'text-[#C42F2F]' : '' }}">{{ $refund ? 'Refund' : 'Payment' }} · {{ Str::headline($p->method->value) }}</p>
                  <p class="text-xs text-muted">{{ Format::date($p->received_at, 'j M Y') }}@if ($p->reference) · {{ $p->reference }}@endif @if ($p->recordedBy) · by {{ $p->recordedBy->first_name ?: $p->recordedBy->name }}@endif</p>
                  @if ($p->notes)<p class="text-xs text-muted mt-0.5">{{ $p->notes }}</p>@endif
                </div>
                <div class="text-end shrink-0">
                  <p class="font-bold tabular">{{ $refund ? '−' : '' }}{{ Format::money($p->amount, 2) }}</p>
                  @can('admin')
                    <form method="POST" action="{{ route('admin.bookings.payments.destroy', [$booking, $p]) }}" onsubmit="return confirm('Remove this entry?')">
                      @csrf @method('DELETE')
                      <button class="text-xs text-muted hover:text-[#C42F2F] underline">Remove</button>
                    </form>
                  @endcan
                </div>
              </li>
            @endforeach
          </ul>
        @endif

        <form method="POST" action="{{ route('admin.bookings.payments.store', $booking) }}" class="border-t border-line p-5 grid gap-3 bg-page/60">
          @csrf
          <h3 class="font-bold">Record a payment or refund</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="label" for="type">Type</label>
              <select id="type" name="type" class="field !py-2">
                <option value="payment" @selected(old('type', $cancelled ? 'refund' : 'payment') === 'payment')>Payment received</option>
                <option value="refund" @selected(old('type', $cancelled ? 'refund' : 'payment') === 'refund')>Refund sent</option>
              </select>
            </div>
            <div>
              <label class="label" for="method">Method</label>
              <select id="method" name="method" class="field !py-2">
                @foreach (\App\Enums\PaymentMethod::cases() as $m)
                  <option value="{{ $m->value }}" @selected(old('method') === $m->value)>{{ Str::headline($m->value) }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="label" for="amount">Amount ($)</label>
              <input id="amount" name="amount" type="number" step="0.01" min="0.01" class="field !py-2 tabular" value="{{ old('amount', $cancelled ? '' : ($booking->amountDue() ?: '')) }}">
            </div>
            <div>
              <label class="label" for="received_at">Date</label>
              <input id="received_at" name="received_at" type="date" class="field !py-2" value="{{ old('received_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}">
            </div>
          </div>
          <div>
            <label class="label" for="reference">Reference</label>
            <input id="reference" name="reference" class="field !py-2" value="{{ old('reference') }}" placeholder="Transfer or receipt number">
          </div>
          <div>
            <label class="label" for="notes">Note</label>
            <input id="notes" name="notes" class="field !py-2" value="{{ old('notes') }}">
          </div>
          <button class="btn btn-primary btn-sm">Record</button>
        </form>
      </section>

      <section class="card p-5 text-sm">
        <h2 class="font-bold">Cancellation policy today</h2>
        <p class="text-muted mt-1">{{ $policy['days'] >= 0 ? $policy['days'].' days before departure' : 'Already departed' }}: <b class="text-ink">{{ $policy['when'] }}</b> → <b class="text-ink">{{ $policy['penalty'] }}</b>.</p>
      </section>
    </aside>
  </div>
@endsection
