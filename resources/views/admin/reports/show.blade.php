@extends('layouts.admin')
@use('App\Reports\ReportFilters')

{{-- Every report: header, filters, the report's own body ($report->view()), and the printed / PDF version
     (partials/print: A4 sheets between the Menatours · Safer banners, like the booking voucher). --}}
@section('title', $class::title())

@section('content')
  @php($comparison = $filters->comparison())

  <div id="report-content">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-sm text-muted no-print"><a href="{{ route('admin.reports.index') }}" class="hover:underline">Reports</a> /</p>
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $class::title() }}</h1>
        <p class="text-muted mt-1 text-sm">
          <span class="font-semibold text-ink">{{ ReportFilters::label($filters->from, $filters->to) }}</span>
          @if ($comparison) · compared with {{ ReportFilters::label(...$comparison) }} @endif
          · amounts in USD
        </p>
        {{-- Printed only: what the figures cover and who printed them --}}
        <p class="print-only text-xs text-muted mt-1">
          Filters: {{ implode(' · ', $filters->describe()) ?: 'none' }} · printed {{ now()->format('j M Y, H:i') }} by {{ auth()->user()->name }}
        </p>
        @if (auth()->user()->isSeller())
          <p class="mt-2 no-print"><span class="tag tag-ocean">Seller</span> <span class="text-muted text-sm">These figures cover the bookings assigned to you.</span></p>
        @endif
      </div>
      <div class="flex gap-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-outline btn-sm"><i data-icon="printer" class="w-4 h-4"></i> Print / PDF</button>
        <a href="{{ route('admin.reports.export', [$class::key()] + $filters->query()) }}" class="btn btn-primary btn-sm"><i data-icon="download" class="w-4 h-4"></i> Export to Excel</a>
      </div>
    </div>

    @include('admin.reports.partials.filters')

    @include($report->view())
  </div>

  @include('admin.reports.partials.print', ['printTitle' => $class::title().' · '.ReportFilters::label($filters->from, $filters->to)])
@endsection
