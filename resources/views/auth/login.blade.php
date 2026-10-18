@extends('layouts.app')

@section('title', __('site.auth.sign_in_title'))
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <h1 class="font-display font-bold text-3xl text-center">{{ __('site.auth.sign_in') }}</h1>
    <p class="text-muted text-center mt-2">{{ __('site.auth.sign_in_intro') }}</p>

    <form method="POST" action="{{ route('login') }}" class="card p-6 sm:p-7 mt-8 grid gap-4" novalidate>
      @csrf
      @include('auth.partials.field', ['name' => 'email', 'label' => __('site.auth.email'), 'type' => 'email', 'autocomplete' => 'email'])
      @include('auth.partials.field', ['name' => 'password', 'label' => __('site.auth.password'), 'type' => 'password', 'autocomplete' => 'current-password'])
      <label class="flex items-center gap-3 cursor-pointer text-sm">
        <input type="checkbox" name="remember" value="1" class="check"> {{ __('site.auth.remember') }}
      </label>
      <button class="btn btn-primary btn-lg w-full mt-2">{{ __('site.auth.sign_in') }}</button>
    </form>

    <p class="text-center text-sm text-muted mt-6">{{ __('site.auth.new_here') }} <a href="{{ route('register') }}" class="font-bold text-ocean-600 hover:underline">{{ __('site.auth.register') }}</a></p>
  </main>
@endsection
