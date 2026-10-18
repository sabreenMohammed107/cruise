@extends('layouts.app')

@section('title', __('site.auth.register_title'))
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-lg mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <h1 class="font-display font-bold text-3xl text-center">{{ __('site.auth.register') }}</h1>
    <p class="text-muted text-center mt-2">{{ __('site.auth.register_intro') }}</p>

    <form method="POST" action="{{ route('register') }}" class="card p-6 sm:p-7 mt-8 grid sm:grid-cols-2 gap-4" novalidate>
      @csrf
      @include('auth.partials.field', ['name' => 'first_name', 'label' => __('site.auth.first_name'), 'autocomplete' => 'given-name'])
      @include('auth.partials.field', ['name' => 'last_name', 'label' => __('site.auth.last_name'), 'autocomplete' => 'family-name'])
      @include('auth.partials.field', ['name' => 'email', 'label' => __('site.auth.email'), 'type' => 'email', 'autocomplete' => 'email', 'span' => 'sm:col-span-2'])
      @include('auth.partials.field', ['name' => 'password', 'label' => __('site.auth.password'), 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => __('site.auth.password_hint')])
      @include('auth.partials.field', ['name' => 'password_confirmation', 'label' => __('site.auth.password_repeat'), 'type' => 'password', 'autocomplete' => 'new-password'])
      <button class="btn btn-primary btn-lg w-full mt-2 sm:col-span-2">{{ __('site.auth.create_account') }}</button>
    </form>

    <p class="text-center text-sm text-muted mt-6">{{ __('site.auth.have_account') }} <a href="{{ route('login') }}" class="font-bold text-ocean-600 hover:underline">{{ __('site.auth.sign_in') }}</a></p>
  </main>
@endsection
