{{-- 403 in the site's style. In /admin it means the account signed in to the panel lost its admin role. --}}
@extends('layouts.app')

@section('title', 'No access | Safer Cruise')

@section('content')
  <main class="flex-1 w-full max-w-lg mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center">
    <span class="inline-grid place-items-center w-16 h-16 rounded-2xl bg-sun-50 text-sun-600"><i data-icon="lock" class="w-8 h-8"></i></span>
    <h1 class="font-display font-bold text-3xl mt-5">You don't have access to this page</h1>

    @if (request()->is('admin', 'admin/*') && auth('admin')->check())
      <p class="text-muted mt-3">The admin panel is signed in as <b class="text-ink">{{ auth('admin')->user()->email }}</b>, which no longer has admin access.</p>
      <form method="POST" action="{{ route('admin.logout') }}" class="mt-8">
        @csrf
        <button class="btn btn-primary">Sign out of the admin panel</button>
      </form>
    @else
      <p class="text-muted mt-3">{{ $exception->getMessage() ?: 'This page is not available.' }}</p>
      <a href="{{ route('home') }}" class="btn btn-primary mt-8">Back to home</a>
    @endif
  </main>
@endsection
