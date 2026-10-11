@extends('layouts.app')

@section('title', 'Sign in | Safer Cruise')
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-md mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <h1 class="font-display font-bold text-3xl text-center">Sign in</h1>
    <p class="text-muted text-center mt-2">See your bookings, saved cruises and profile.</p>

    <form method="POST" action="{{ route('login') }}" class="card p-6 sm:p-7 mt-8 grid gap-4" novalidate>
      @csrf
      @include('auth.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'email'])
      @include('auth.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password'])
      <label class="flex items-center gap-3 cursor-pointer text-sm">
        <input type="checkbox" name="remember" value="1" class="check"> Keep me signed in
      </label>
      <button class="btn btn-primary btn-lg w-full mt-2">Sign in</button>
    </form>

    <p class="text-center text-sm text-muted mt-6">New to Safer Cruise? <a href="{{ route('register') }}" class="font-bold text-ocean-600 hover:underline">Create an account</a></p>
  </main>
@endsection
