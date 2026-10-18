{{-- Converted from legacy-html/dashboard.html. Words: lang/{en,ar}/site.php → account (dashboard.js reads the same keys). --}}
@extends('layouts.app')

@section('title', __('site.account.title'))
@section('nav', 'dashboard')

@section('content')

  {{-- grid-cols-1 = minmax(0, 1fr) and min-w-0 on the sidebar: on phones the tab bar scrolls inside its own strip
       instead of stretching the column (and the whole page) to its full width --}}
  <main class="flex-1 max-w-site w-full mx-auto px-4 sm:px-6 py-8 grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-8 items-start">

    <!-- =========================================================
         SIDEBAR — column on desktop, scrollable tab bar on mobile
         ========================================================= -->
    <aside class="min-w-0 lg:sticky lg:top-24">
      <div class="hidden lg:flex items-center gap-3 card p-4 mb-4">
        <span class="grid place-items-center w-12 h-12 rounded-full bg-ocean-600 text-white font-display font-bold" id="u-initials">{{ mb_substr($user->first_name ?? '', 0, 1) }}{{ mb_substr($user->last_name ?? '', 0, 1) }}</span>
        <div class="min-w-0"><p class="font-bold truncate" id="u-name">{{ $user->name }}</p><p class="text-sm text-muted truncate" id="u-email" dir="ltr">{{ $user->email }}</p></div>
      </div>
      <nav class="flex lg:flex-col gap-1 overflow-x-auto no-scrollbar -mx-4 px-4 lg:mx-0 lg:px-0 lg:card lg:p-2" aria-label="{{ __('site.account.nav_label') }}" id="dash-nav">
        <a href="#overview" class="side-link shrink-0" data-section="overview"><i data-icon="home" class="w-5 h-5"></i> {{ __('site.account.overview') }}</a>
        <a href="#bookings" class="side-link shrink-0" data-section="bookings"><i data-icon="ticket" class="w-5 h-5"></i> {{ __('site.account.bookings') }}</a>
        <a href="#favorites" class="side-link shrink-0" data-section="favorites"><i data-icon="heart" class="w-5 h-5"></i> {{ __('site.account.favorites') }}</a>
        <a href="#profile" class="side-link shrink-0" data-section="profile"><i data-icon="sliders" class="w-5 h-5"></i> {{ __('site.account.profile') }}</a>
        <button class="side-link shrink-0 lg:mt-2 lg:border-t lg:border-line lg:rounded-none lg:pt-3" id="sign-out"><i data-icon="logout" class="w-5 h-5 rtl:rotate-180"></i> {{ __('site.account.sign_out') }}</button>
      </nav>
    </aside>

    <div class="min-w-0">
      <!-- =========================================================
           OVERVIEW
           ========================================================= -->
      <section data-dash="overview" hidden>
        <h1 class="font-display font-bold text-2xl sm:text-3xl" id="greeting">{{ __('site.account.welcome') }}</h1>
        <p class="text-muted mt-1">{{ __('site.account.intro') }}</p>

        <div id="next-trip" class="mt-6"></div>

        <div class="grid sm:grid-cols-3 gap-4 mt-6" id="stats"></div>

        <div class="mt-8 grid md:grid-cols-2 gap-4">
          <a href="{{ route('cruises.index') }}" class="card card-hover p-5 flex items-center gap-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-sun-50 text-sun-600"><i data-icon="search" class="w-6 h-6"></i></span>
            <span><span class="block font-bold">{{ __('site.account.find_next') }}</span><span class="text-sm text-muted">{{ __('site.account.find_next_text') }}</span></span>
          </a>
          <a href="https://wa.me/905016451111" target="_blank" rel="noopener" class="card card-hover p-5 flex items-center gap-4">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-[#E3F5EE] text-[#12805C]"><i data-icon="chat" class="w-6 h-6"></i></span>
            <span><span class="block font-bold">{{ __('site.account.change_booking') }}</span><span class="text-sm text-muted">{{ __('site.account.change_booking_text') }}</span></span>
          </a>
        </div>
      </section>

      <!-- =========================================================
           MY BOOKINGS
           ========================================================= -->
      <section data-dash="bookings" hidden>
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ __('site.account.bookings') }}</h1>
        <div data-tabs class="mt-6">
          <div class="flex gap-2 overflow-x-auto no-scrollbar" role="tablist" aria-label="{{ __('site.account.status_label') }}">
            <button class="chip is-active" role="tab" data-tab="upcoming" aria-selected="true">{{ __('site.account.upcoming') }} <span class="tabular" id="n-upcoming"></span></button>
            <button class="chip" role="tab" data-tab="past" aria-selected="false">{{ __('site.account.past') }} <span class="tabular" id="n-past"></span></button>
            <button class="chip" role="tab" data-tab="cancelled" aria-selected="false">{{ __('site.account.cancelled') }} <span class="tabular" id="n-cancelled"></span></button>
          </div>
          <div class="mt-6 grid gap-5" data-panel="upcoming" id="list-upcoming"></div>
          <div class="mt-6 grid gap-5" data-panel="past" id="list-past" hidden></div>
          <div class="mt-6 grid gap-5" data-panel="cancelled" id="list-cancelled" hidden></div>
        </div>
      </section>

      <!-- =========================================================
           FAVOURITES
           ========================================================= -->
      <section data-dash="favorites" hidden>
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ __('site.account.favorites') }}</h1>
        <p class="text-muted mt-1">{{ __('site.account.favorites_intro') }}</p>
        <div id="fav-grid" class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3"></div>
      </section>

      <!-- =========================================================
           PROFILE & SETTINGS
           ========================================================= -->
      <section data-dash="profile" hidden>
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ __('site.account.profile') }}</h1>
        <p class="text-muted mt-1">{{ __('site.account.profile_intro') }}</p>

        <form id="profile-form" class="card p-5 sm:p-6 mt-6 grid sm:grid-cols-2 gap-4" novalidate>
          <h2 class="sm:col-span-2 font-display font-semibold text-lg">{{ __('site.account.personal') }}</h2>
          <div><label class="label" for="p-first">{{ __('site.booking.first_name') }}</label><input id="p-first" class="field" autocomplete="given-name"><p class="err">{{ __('site.account.first_required') }}</p></div>
          <div><label class="label" for="p-last">{{ __('site.booking.last_name') }}</label><input id="p-last" class="field" autocomplete="family-name"><p class="err">{{ __('site.account.last_required') }}</p></div>
          <div><label class="label" for="p-email">{{ __('site.booking.email') }}</label><input id="p-email" type="email" class="field" dir="ltr" autocomplete="email"><p class="err">{{ __('site.booking.errors.email_invalid') }}</p></div>
          <div><label class="label" for="p-phone">{{ __('site.booking.mobile') }}</label>
            <div class="flex gap-2" dir="ltr"><select id="p-dial" class="field !w-28 shrink-0" aria-label="{{ __('site.booking.country_code') }}"></select><input id="p-phone" type="tel" class="field tabular" autocomplete="tel-national"></div>
            <p class="err">{{ __('site.booking.errors.phone_length') }}</p></div>
          <div><label class="label" for="p-dob">{{ __('site.booking.dob') }}</label><input id="p-dob" type="date" class="field"></div>
          <div><label class="label" for="p-nat">{{ __('site.booking.nationality') }}</label><select id="p-nat" class="field"></select></div>
          <div class="sm:col-span-2 flex justify-end"><button class="btn btn-primary">{{ __('site.account.save') }}</button></div>
        </form>

        <div class="card p-5 sm:p-6 mt-5">
          <h2 class="font-display font-semibold text-lg">{{ __('site.account.notifications') }}</h2>
          <ul class="mt-4 divide-y divide-line" id="notif"></ul>
        </div>

        <form id="pw-form" class="card p-5 sm:p-6 mt-5 grid sm:grid-cols-2 gap-4" novalidate>
          <h2 class="sm:col-span-2 font-display font-semibold text-lg">{{ __('site.account.change_password') }}</h2>
          <div class="sm:col-span-2"><label class="label" for="pw-old">{{ __('site.account.current_password') }}</label><input id="pw-old" type="password" class="field" dir="ltr" autocomplete="current-password"><p class="err">{{ __('site.account.current_password_required') }}</p></div>
          <div><label class="label" for="pw-new">{{ __('site.account.new_password') }}</label><input id="pw-new" type="password" class="field" dir="ltr" autocomplete="new-password"><p class="err">{{ __('site.account.password_rule') }}</p></div>
          <div><label class="label" for="pw-rep">{{ __('site.account.repeat_password') }}</label><input id="pw-rep" type="password" class="field" dir="ltr" autocomplete="new-password"><p class="err">{{ __('site.account.password_mismatch') }}</p></div>
          <div class="sm:col-span-2 flex justify-end"><button class="btn btn-outline">{{ __('site.account.update_password') }}</button></div>
        </form>
      </section>
    </div>
  </main>
@endsection

@push('scripts')
  <script src="@asset('assets/js/pages/dashboard.js')"></script>
@endpush
