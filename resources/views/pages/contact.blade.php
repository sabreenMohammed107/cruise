{{-- "Contact us" (ContactController). Messages are read in Admin → Contact messages; phones, email, address and
     WhatsApp come from Admin → Footer & contact (App\Models\SiteSetting). Words: lang/{en,ar}/pages.php → contact. --}}
@extends('layouts.app')
@use('App\Models\SiteSetting')

@section('title', __('pages.contact.title'))
@section('description', __('pages.contact.description'))
@section('nav', 'contact')

@php
  $phones = collect([1, 2])->map(fn ($n) => ['label' => SiteSetting::text("phone_{$n}_label"), 'number' => SiteSetting::value("phone_{$n}")])
      ->filter(fn ($p) => $p['number'] !== '');
  $email = SiteSetting::value('email');
  $address = SiteSetting::text('address');
  $wa = 'https://wa.me/'.SiteSetting::whatsapp().'?text='.rawurlencode(__('site.footer.whatsapp_message'));
  $messageError = $errors->first('message');
@endphp

@section('content')
  <main class="flex-1">
    <section class="bg-ocean-700 text-white">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16">
        @include('partials.breadcrumb', ['tone' => 'light', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.contact'), null]]])
        <h1 class="font-display font-bold text-4xl sm:text-5xl">{{ __('pages.contact.heading') }}</h1>
        <p class="mt-3 text-ocean-100 max-w-[60ch]">{{ __('pages.contact.intro') }}</p>
      </div>
    </section>

    <section class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16 grid lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] gap-8 lg:gap-12 items-start">
      <div class="card p-6 sm:p-8">
        @if (session('contact_sent'))
          <div class="text-center py-8" role="status">
            <span class="mx-auto w-14 h-14 rounded-full bg-lagoon-50 text-lagoon-600 grid place-items-center"><i data-icon="check" class="w-7 h-7"></i></span>
            <h2 class="font-display font-bold text-2xl mt-5">{{ __('pages.contact.sent_title') }}</h2>
            <p class="text-muted mt-2 max-w-[46ch] mx-auto">{{ __('pages.contact.sent_body') }}</p>
            <a href="{{ route('cruises.index') }}" class="btn btn-outline mt-6">@bi('site.footer.all_cruises') <i data-icon="arrow-right" class="w-4 h-4 rtl:rotate-180"></i></a>
          </div>
        @else
          <h2 class="font-display font-bold text-2xl">{{ __('pages.contact.form_title') }}</h2>
          <form method="POST" action="{{ route('contact.store') }}" class="mt-6 grid sm:grid-cols-2 gap-4" novalidate>
            @csrf
            @include('auth.partials.field', ['name' => 'name', 'label' => __('pages.contact.fields.name'), 'placeholder' => __('pages.contact.placeholders.name'), 'autocomplete' => 'name', 'span' => 'sm:col-span-2'])
            @include('auth.partials.field', ['name' => 'email', 'label' => __('pages.contact.fields.email'), 'placeholder' => __('pages.contact.placeholders.email'), 'type' => 'email', 'autocomplete' => 'email'])
            @include('partials.phone-field', ['label' => __('pages.contact.fields.phone')])

            <div class="sm:col-span-2">
              <label class="label" for="message">{{ __('pages.contact.fields.message') }}</label>
              <textarea id="message" name="message" rows="6" maxlength="2000" class="field {{ $messageError ? 'is-invalid' : '' }}"
                        placeholder="{{ __('pages.contact.placeholders.message') }}" aria-describedby="message-{{ $messageError ? 'err' : 'hint' }}" @if ($messageError) aria-invalid="true" @endif>{{ old('message') }}</textarea>
              @if ($messageError)
                <p class="err show" id="message-err">{{ $messageError }}</p>
              @else
                <p class="text-xs text-muted mt-1.5" id="message-hint">{{ __('pages.contact.message_hint') }}</p>
              @endif
            </div>

            @include('partials.honeypot')

            <button class="btn btn-primary btn-lg sm:col-span-2 sm:justify-self-start"><i data-icon="mail" class="w-5 h-5"></i> {{ __('pages.contact.send') }}</button>
          </form>
        @endif
      </div>

      <aside class="grid gap-4">
        <h2 class="font-display font-semibold text-xl">{{ __('pages.contact.other_ways') }}</h2>
        <a href="{{ $wa }}" target="_blank" rel="noopener" class="card card-hover p-5 flex items-center gap-4 hover:border-ocean-400 transition">
          <span class="w-12 h-12 rounded-full bg-[#1FAF5A] text-white grid place-items-center shrink-0"><i data-icon="chat" class="w-6 h-6"></i></span>
          <span><span class="block font-bold">{{ __('pages.contact.whatsapp') }}</span><span class="text-sm text-muted">{{ __('pages.contact.whatsapp_body') }}</span></span>
        </a>
        @if ($phones->isNotEmpty())
          <div class="card p-5 flex gap-4">
            <span class="w-12 h-12 rounded-full bg-ocean-50 text-ocean-600 grid place-items-center shrink-0"><i data-icon="phone" class="w-6 h-6"></i></span>
            <div class="min-w-0">
              <p class="font-bold">{{ __('pages.contact.call') }}</p>
              <ul class="mt-1 grid gap-1 text-sm">
                {{-- dir="ltr" keeps "+966 …" in the right order on the Arabic layout --}}
                @foreach ($phones as $phone)
                  <li class="text-muted">{{ $phone['label'] }} <a href="{{ SiteSetting::tel($phone['number']) }}" class="font-semibold text-ink hover:text-ocean-600 inline-block" dir="ltr">{{ $phone['number'] }}</a></li>
                @endforeach
              </ul>
            </div>
          </div>
        @endif
        @if ($email !== '')
          <a href="mailto:{{ $email }}" class="card card-hover p-5 flex items-center gap-4 hover:border-ocean-400 transition">
            <span class="w-12 h-12 rounded-full bg-ocean-50 text-ocean-600 grid place-items-center shrink-0"><i data-icon="mail" class="w-6 h-6"></i></span>
            <span class="min-w-0"><span class="block font-bold">{{ __('pages.contact.email_us') }}</span><span class="text-sm text-muted break-all" dir="ltr">{{ $email }}</span></span>
          </a>
        @endif
        @if (filled((string) $address))
          <div class="card p-5 flex gap-4">
            <span class="w-12 h-12 rounded-full bg-ocean-50 text-ocean-600 grid place-items-center shrink-0"><i data-icon="pin" class="w-6 h-6"></i></span>
            <div><p class="font-bold">{{ __('pages.contact.visit') }}</p><p class="text-sm text-muted mt-1">{{ $address }}</p></div>
          </div>
        @endif
      </aside>
    </section>
  </main>
@endsection

@push('scripts')
  @include('partials.phone-widget')
  <script src="@asset('assets/js/phone-fields.js')"></script>
@endpush
