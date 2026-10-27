@extends('layouts.app')

@section('title', __('site.auth.forgot_title'))
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
    @if ($sentTo = session('reset_sent'))
      {{-- After sending: no form again, just where the link went and a way to resend --}}
      <div class="card p-6 sm:p-8 text-center">
        <div class="mx-auto grid place-items-center w-14 h-14 rounded-full bg-ocean-50 text-ocean-600">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </div>
        <h1 class="font-display font-bold text-2xl mt-4">{{ __('site.auth.check_inbox') }}</h1>
        <p class="text-muted mt-2">{{ __('site.auth.reset_link_sent_to') }}</p>
        <p class="font-bold mt-1 break-all" dir="ltr">{{ $sentTo }}</p>
        <p class="text-sm text-muted mt-4">{{ __('site.auth.reset_link_spam') }}</p>

        <form method="POST" action="{{ route('password.email') }}" class="mt-6">
          @csrf
          <input type="hidden" name="email" value="{{ $sentTo }}">
          <button class="btn btn-primary btn-lg w-full">{{ __('site.auth.resend_link') }}</button>
        </form>
        <a href="{{ route('password.request') }}" class="inline-block text-sm font-bold text-ocean-600 hover:underline mt-4">{{ __('site.auth.other_email') }}</a>
      </div>
    @else
      <h1 class="font-display font-bold text-3xl text-center">{{ __('site.auth.forgot') }}</h1>
      <p class="text-muted text-center mt-2">{{ __('site.auth.forgot_intro') }}</p>

      <form method="POST" action="{{ route('password.email') }}" class="card p-6 sm:p-7 mt-8 grid gap-4" novalidate>
        @csrf
        @include('auth.partials.field', ['name' => 'email', 'label' => __('site.auth.email'), 'type' => 'email', 'autocomplete' => 'email'])
        <button class="btn btn-primary btn-lg w-full mt-2">{{ __('site.auth.send_link') }}</button>
      </form>
    @endif

    <p class="text-center text-sm text-muted mt-6">{{ __('site.auth.remembered') }} <a href="{{ route('login') }}" class="font-bold text-ocean-600 hover:underline">{{ __('site.auth.sign_in') }}</a></p>
  </main>
@endsection
