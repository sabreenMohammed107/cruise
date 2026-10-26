{{-- Converted from legacy-html/booking.html. Words: lang/{en,ar}/site.php → booking (booking.js reads the same keys). --}}
@extends('layouts.app')

@section('title', __('site.booking.title'))
@section('nav', 'catalog')

@section('content')

  <main class="flex-1">
    <!-- =========================================================
         STEPPER
         ========================================================= -->
    <div class="bg-white border-b border-line">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-5 flex flex-wrap items-center justify-between gap-4">
        <a href="#" id="back-to-details" class="btn btn-ghost btn-sm -ms-3"><i data-icon="arrow-left" class="w-4 h-4 rtl:rotate-180"></i> {{ __('site.booking.back_to_cabins') }}</a>
        <ol class="flex items-center gap-2 sm:gap-3 text-sm font-semibold" aria-label="{{ __('site.booking.steps') }}" id="stepper">
          <li class="step flex items-center gap-2" data-step="guests"><span class="step-dot">1</span><span class="hidden sm:inline">{{ __('site.booking.step_guests') }}</span></li>
          <li class="w-6 sm:w-10 h-0.5 bg-line" aria-hidden="true"></li>
          <li class="step flex items-center gap-2" data-step="review"><span class="step-dot">2</span><span class="hidden sm:inline">{{ __('site.booking.step_review') }}</span></li>
        </ol>
      </div>
    </div>

    <!-- Empty state when no sailing is in the booking session -->
    <section id="no-booking" class="hidden max-w-xl mx-auto px-4 py-20 text-center">
      <span class="inline-grid place-items-center w-16 h-16 rounded-2xl bg-ocean-50 text-ocean-600"><i data-icon="ship" class="w-8 h-8"></i></span>
      <h1 class="font-display font-bold text-2xl mt-5">{{ __('site.booking.empty_title') }}</h1>
      <p class="text-muted mt-2">{{ __('site.booking.empty_text') }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('cruises.index') }}" class="btn btn-primary">{{ __('site.booking.browse') }}</a>
        <button id="load-demo" class="btn btn-outline">{{ __('site.booking.load_demo') }}</button>
      </div>
    </section>

    <div id="flow" class="hidden max-w-site mx-auto px-4 sm:px-6 py-8 grid lg:grid-cols-[1fr_370px] gap-8 items-start">
      <div class="min-w-0">

        <!-- =========================================================
             STEP 1 — GUEST DETAILS
             ========================================================= -->
        <section data-view="guests" hidden>
          <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ __('site.booking.whos_sailing') }}</h1>
          <p class="text-muted mt-2 max-w-[62ch]">{{ __('site.booking.whos_sailing_intro') }}</p>
          <form id="guests-form" class="mt-6 grid gap-5" novalidate>
            <div id="traveller-forms" class="grid gap-5"></div>
            <div id="form-error" class="hidden rounded-2xl border border-[#C42F2F]/30 bg-[#FDF0F0] p-4 text-sm text-[#8E1F1F] font-semibold" role="alert"></div>
            <div class="flex flex-wrap justify-between gap-3">
              <a href="#" class="btn btn-outline" data-back-details>{{ __('site.booking.back') }}</a>
              <button type="submit" class="btn btn-accent btn-lg">{{ __('site.booking.review_booking') }}</button>
            </div>
          </form>
        </section>

        <!-- =========================================================
             STEP 2 — REVIEW & CONFIRM (no online payment: confirming completes the booking)
             ========================================================= -->
        <section data-view="review" hidden>
          <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ __('site.booking.review_title') }}</h1>
          <p class="text-muted mt-2">{{ __('site.booking.review_intro') }}</p>
          <div id="review" class="mt-6 grid gap-5"></div>

          <div class="card p-5 sm:p-6 mt-5">
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" id="agree" class="check mt-0.5">
              <span class="text-[0.95rem]">{!! __('site.booking.agree', [
                  'terms' => '<a href="#" class="font-bold text-ocean-600 underline" data-terms="terms">'.e(__('site.booking.terms_link')).'</a>',
                  'cancellation' => '<a href="#" class="font-bold text-ocean-600 underline" data-terms="cancellation">'.e(__('site.booking.cancellation_link')).'</a>',
              ]) !!}</span>
            </label>
            <p class="err" id="agree-err">{{ __('site.booking.errors.agree') }}</p>
          </div>

          <div class="mt-6 flex flex-wrap justify-between gap-3">
            <button class="btn btn-outline" data-goto="guests"><i data-icon="edit" class="w-4 h-4"></i> {{ __('site.booking.back_edit') }}</button>
            <button class="btn btn-accent btn-lg" id="confirm-btn"><i data-icon="check" class="w-5 h-5"></i> <span>{{ __('site.booking.confirm') }}</span></button>
          </div>
        </section>

      </div>

      <!-- =========================================================
           BOOKING SUMMARY (sticky)
           ========================================================= -->
      <aside class="lg:sticky lg:top-24 order-first lg:order-none" aria-label="{{ __('site.booking.summary') }}">
        <details class="lg:hidden card mb-2 group" id="m-summary-toggle">
          <summary class="list-none flex items-center justify-between p-4 cursor-pointer">
            <span class="font-bold">{{ __('site.booking.summary') }}</span>
            <span class="flex items-center gap-2 font-display font-bold text-ocean-800 tabular"><span id="m-total"></span><i data-icon="chevron-down" class="w-4 h-4 transition group-open:rotate-180"></i></span>
          </summary>
          <div id="summary-mobile" class="border-t border-line"></div>
        </details>
        <div class="hidden lg:block card pass overflow-hidden" style="--perf-bg:#F5F8FB" id="summary"></div>
      </aside>
    </div>
  </main>
@endsection

@push('scripts')
  @include('partials.phone-widget')
  <script src="@asset('assets/js/pages/booking.js')"></script>
@endpush
