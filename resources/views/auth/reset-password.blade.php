@extends('layouts.app')

@section('title', __('site.auth.reset_title'))
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <h1 class="font-display font-bold text-3xl text-center">{{ __('site.auth.reset') }}</h1>
    <p class="text-muted text-center mt-2">{{ __('site.auth.reset_intro') }}</p>

    <form method="POST" action="{{ route('password.update') }}" class="card p-6 sm:p-7 mt-8 grid gap-4" novalidate>
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      @include('auth.partials.field', ['name' => 'email', 'label' => __('site.auth.email'), 'type' => 'email', 'autocomplete' => 'username', 'value' => $email])
      @include('auth.partials.field', ['name' => 'password', 'label' => __('site.auth.new_password'), 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => __('site.auth.password_hint')])
      @include('auth.partials.field', ['name' => 'password_confirmation', 'label' => __('site.auth.password_repeat'), 'type' => 'password', 'autocomplete' => 'new-password'])
      <button class="btn btn-primary btn-lg w-full mt-2">{{ __('site.auth.save_password') }}</button>
    </form>

    <p class="text-center text-sm text-muted mt-6"><a href="{{ route('password.request') }}" class="font-bold text-ocean-600 hover:underline">{{ __('site.auth.new_link') }}</a></p>
  </main>
@endsection
