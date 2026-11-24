@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Reports</h1>
  @if (auth()->user()->isSeller())
    <p class="mt-2"><span class="tag tag-ocean">Seller</span> <span class="text-muted">Reports cover the bookings assigned to you.</span></p>
  @else
    <p class="text-muted mt-1">Every report can be filtered by period, channel, cruise, sailing, seller and agent, exported to Excel or printed as a PDF.</p>
  @endif

  <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4 mt-6">
    @foreach ($reports as $key => $report)
      <a href="{{ route('admin.reports.show', $key) }}" class="card p-5 flex gap-4 items-start hover:border-ocean-400 transition-colors">
        <span class="grid place-items-center w-11 h-11 shrink-0 rounded-xl bg-ocean-50 text-ocean-600"><i data-icon="{{ $report::icon() }}" class="w-5 h-5"></i></span>
        <span>
          <span class="block font-display font-semibold text-lg text-ink">{{ $report::title() }}</span>
          <span class="block text-sm text-muted mt-1">{{ $report::description() }}</span>
        </span>
      </a>
    @endforeach
  </div>
@endsection
