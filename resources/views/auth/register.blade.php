@extends('layouts.app')

@section('title', 'Create an account | Safer Cruise')
@section('nav', 'dashboard')

@section('content')
  <main class="flex-1 w-full max-w-lg mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <h1 class="font-display font-bold text-3xl text-center">Create an account</h1>
    <p class="text-muted text-center mt-2">Keep your bookings and saved cruises in one place.</p>

    <form method="POST" action="{{ route('register') }}" class="card p-6 sm:p-7 mt-8 grid sm:grid-cols-2 gap-4" novalidate>
      @csrf
      @include('auth.partials.field', ['name' => 'first_name', 'label' => 'First name', 'autocomplete' => 'given-name'])
      @include('auth.partials.field', ['name' => 'last_name', 'label' => 'Last name', 'autocomplete' => 'family-name'])
      @include('auth.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'email', 'span' => 'sm:col-span-2'])
      @include('auth.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'At least 8 characters, including a number.'])
      @include('auth.partials.field', ['name' => 'password_confirmation', 'label' => 'Repeat password', 'type' => 'password', 'autocomplete' => 'new-password'])
      <button class="btn btn-primary btn-lg w-full mt-2 sm:col-span-2">Create account</button>
    </form>

    <p class="text-center text-sm text-muted mt-6">Already have an account? <a href="{{ route('login') }}" class="font-bold text-ocean-600 hover:underline">Sign in</a></p>
  </main>
@endsection
