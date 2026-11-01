@extends('layouts.admin')

@section('title', 'Bookings')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ auth()->user()->isSeller() ? 'My bookings' : 'Bookings' }}</h1>
  @if (auth()->user()->isSeller())
    <p class="text-muted mt-1">The bookings assigned to you.</p>
  @endif

  {{-- Channel: B2C (customers and guest checkout) or agent bookings; the counts follow the other filters --}}
  <nav class="mt-5 flex flex-wrap gap-2 text-sm" aria-label="Booking channel">
    @foreach (['' => 'All bookings', 'b2c' => 'B2C customers', 'agent' => 'Agents'] as $value => $label)
      @php($current = ($filters['channel'] ?? '') === $value)
      <a href="{{ route('admin.bookings.index', array_filter(['channel' => $value ?: null] + ($value === 'b2c' ? \Illuminate\Support\Arr::except($filters, ['channel', 'agent_tier', 'agent']) : \Illuminate\Support\Arr::except($filters, ['channel'])))) }}"
         @if ($current) aria-current="page" @endif
         class="rounded-full px-4 py-1.5 font-semibold border {{ $current ? 'bg-ocean-600 border-ocean-600 text-white' : 'border-line bg-white text-ink hover:border-ocean-600' }}">
        {{ $label }} <span class="{{ $current ? 'text-ocean-100' : 'text-muted' }} font-normal tabular">({{ $counts[$value ?: 'all'] }})</span>
      </a>
    @endforeach
  </nav>

  <form method="GET" class="mt-4 flex flex-wrap gap-2 items-end">
    <input type="hidden" name="channel" value="{{ $filters['channel'] ?? '' }}">
    <div class="w-full sm:w-72">
      <label for="q" class="label">Search</label>
      <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="field !py-2" placeholder="Reference, email, phone or name">
    </div>
    <div>
      <label for="status" class="label">Booking</label>
      <select id="status" name="status" class="field !py-2 !w-auto">
        <option value="">All</option>
        @foreach (\App\Enums\BookingStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ ucfirst($s->value) }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label for="payment" class="label">Payment</label>
      <select id="payment" name="payment" class="field !py-2 !w-auto">
        <option value="">All</option>
        @foreach (\App\Enums\PaymentStatus::cases() as $s)
          <option value="{{ $s->value }}" @selected(($filters['payment'] ?? '') === $s->value)>{{ \App\Support\Format::badge($s)[1] }}</option>
        @endforeach
      </select>
    </div>
    @if ($sellers->isNotEmpty())
      <div>
        <label for="seller" class="label">Seller</label>
        <select id="seller" name="seller" class="field !py-2 !w-auto">
          <option value="">All</option>
          <option value="none" @selected(($filters['seller'] ?? '') === 'none')>Unassigned</option>
          @foreach ($sellers as $s)
            <option value="{{ $s->id }}" @selected(($filters['seller'] ?? '') === (string) $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
    @endif
    {{-- Agent filters: not for B2C --}}
    @if (($filters['channel'] ?? '') !== 'b2c' && ($agentTiers || $agents->isNotEmpty()))
      @if ($agentTiers)
        <div>
          <label for="agent_tier" class="label">Agent type</label>
          <select id="agent_tier" name="agent_tier" class="field !py-2 !w-auto">
            <option value="">All</option>
            @foreach ($agentTiers as $id => $name)
              <option value="{{ $id }}" @selected(($filters['agent_tier'] ?? '') === (string) $id)>{{ $name }}</option>
            @endforeach
            <option value="none" @selected(($filters['agent_tier'] ?? '') === 'none')>No type</option>
          </select>
        </div>
      @endif
      @if ($agents->isNotEmpty())
        <div>
          <label for="agent" class="label">Agent</label>
          <select id="agent" name="agent" class="field !py-2 !w-auto max-w-56">
            <option value="">All</option>
            @foreach ($agents as $a)
              <option value="{{ $a->id }}" @selected((string) ($filters['agent'] ?? '') === (string) $a->id)>{{ $a->company_name ?: $a->name }}</option>
            @endforeach
          </select>
        </div>
      @endif
    @endif
    <button class="btn btn-primary btn-sm">Filter</button>
    @if (array_filter($filters))
      <a href="{{ route('admin.bookings.index') }}" class="btn btn-ghost btn-sm">Clear</a>
    @endif
  </form>

  <div class="card overflow-hidden mt-5">
    @include('admin.bookings.table', ['bookings' => $bookings])
  </div>
  <div class="mt-5">{{ $bookings->links() }}</div>
@endsection
