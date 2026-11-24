@extends('layouts.admin')
@use('App\Reports\ReportFilters')

{{-- Every report: header, filters, the report's own body ($report->view()), print layout. --}}
@section('title', $class::title())

@section('content')
  <style>
    .print-only { display: none; }
    @media print {
      @page { size: A4 landscape; margin: 12mm; }
      body { background: #fff !important; }
      body > header, #admin-sidebar, #admin-drawer, .no-print { display: none !important; }
      body > div { display: block !important; max-width: none !important; padding: 0 !important; }
      .print-only { display: block; }
      .card { box-shadow: none !important; break-inside: avoid; }
      a { color: inherit !important; text-decoration: none !important; }
      .report-table-wrap { overflow: visible !important; }
    }
  </style>

  @php($comparison = $filters->comparison())

  {{-- Printed / PDF header --}}
  <div class="print-only mb-4 pb-3 border-b border-line">
    <div class="flex items-center justify-between">
      @include('partials.logo')
      <p class="text-xs text-muted">Printed {{ now()->format('j M Y, H:i') }} by {{ auth()->user()->name }}</p>
    </div>
  </div>

  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <p class="text-sm text-muted no-print"><a href="{{ route('admin.reports.index') }}" class="hover:underline">Reports</a> /</p>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $class::title() }}</h1>
      <p class="text-muted mt-1 text-sm">
        <span class="font-semibold text-ink">{{ ReportFilters::label($filters->from, $filters->to) }}</span>
        @if ($comparison) · compared with {{ ReportFilters::label(...$comparison) }} @endif
        · amounts in USD
      </p>
      @if (auth()->user()->isSeller())
        <p class="mt-2"><span class="tag tag-ocean">Seller</span> <span class="text-muted text-sm">These figures cover the bookings assigned to you.</span></p>
      @endif
    </div>
    <div class="flex gap-2 no-print">
      <button type="button" onclick="window.print()" class="btn btn-outline btn-sm"><i data-icon="printer" class="w-4 h-4"></i> Print / PDF</button>
      <a href="{{ route('admin.reports.export', [$class::key()] + $filters->query()) }}" class="btn btn-primary btn-sm"><i data-icon="download" class="w-4 h-4"></i> Export to Excel</a>
    </div>
  </div>

  @include('admin.reports.partials.filters')

  @include($report->view())
@endsection
