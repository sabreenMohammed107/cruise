@use('App\Support\Format')
{{-- $sellers (AppServiceProvider): admins get a seller picker on each row; sellers just see the name. --}}
<div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-ocean-50 text-ocean-800">
      <tr>
        <th class="text-start font-semibold p-3">Reference</th>
        <th class="text-start font-semibold p-3">Sailing</th>
        <th class="text-start font-semibold p-3">Guests</th>
        <th class="text-end font-semibold p-3">Total</th>
        <th class="text-end font-semibold p-3">Due</th>
        <th class="text-start font-semibold p-3">Status</th>
        <th class="text-start font-semibold p-3">Seller</th>
        <th class="text-start font-semibold p-3">Booked</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($bookings as $b)
        <tr class="border-t border-line hover:bg-page">
          <td class="p-3"><a href="{{ route('admin.bookings.show', $b) }}" class="font-bold text-ocean-700 hover:underline tabular">{{ $b->reference }}</a><p class="text-xs text-muted">{{ $b->contact_email }}</p></td>
          <td class="p-3">{{ $b->sailing->cruise->name }}<p class="text-xs text-muted">{{ Format::date($b->sailing->departure_date) }} · {{ $b->cabinSummary() }}</p></td>
          <td class="p-3 whitespace-nowrap">{{ Format::guests($b->adults, $b->children, $b->infants) }}</td>
          <td class="p-3 text-end tabular">{{ Format::money($b->grand_total) }}</td>
          <td class="p-3 text-end tabular font-bold">{{ $b->status === \App\Enums\BookingStatus::Cancelled ? '—' : Format::money($b->amountDue()) }}</td>
          <td class="p-3"><div class="flex flex-wrap gap-1">@include('admin.partials.badge', ['status' => $b->status])@include('admin.partials.badge', ['status' => $b->payment_status])</div></td>
          <td class="p-3">
            @if ($sellers->isNotEmpty())
              <form method="POST" action="{{ route('admin.bookings.seller', $b) }}">
                @csrf @method('PATCH')
                <label for="seller-{{ $b->id }}" class="sr-only">Seller for {{ $b->reference }}</label>
                <select id="seller-{{ $b->id }}" name="seller_id" onchange="this.form.submit()" class="field !py-1.5 !px-2 !w-auto text-xs min-w-32">
                  <option value="">Unassigned</option>
                  @foreach ($sellers as $s)
                    <option value="{{ $s->id }}" @selected($b->seller_id === $s->id)>{{ $s->name }}</option>
                  @endforeach
                </select>
                <noscript><button class="btn btn-ghost btn-sm">Save</button></noscript>
              </form>
            @else
              <span class="{{ $b->seller ? 'font-semibold' : 'text-muted' }}">{{ $b->seller?->name ?? 'Unassigned' }}</span>
            @endif
          </td>
          <td class="p-3 whitespace-nowrap text-muted">{{ Format::date($b->confirmed_at ?? $b->created_at, 'j M, H:i') }}</td>
        </tr>
      @empty
        <tr><td colspan="8" class="p-8 text-center text-muted">{{ $empty ?? 'No bookings match.' }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
