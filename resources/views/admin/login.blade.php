{{-- Admin panel sign-in (guard "admin"), separate from the website's /login. --}}
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex">
  <title>Admin sign in | Safer Cruise</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="{{ asset('assets/js/tailwind.config.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
</head>
<body class="min-h-screen flex flex-col items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <div class="flex items-center justify-center gap-3">
      @include('partials.logo')
      <span class="tag tag-ocean">Admin</span>
    </div>

    <form method="POST" action="{{ route('admin.login') }}" class="card p-6 sm:p-7 mt-8 grid gap-4" novalidate>
      @csrf
      <h1 class="font-display font-bold text-2xl">Sign in to the admin panel</h1>
      @include('auth.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'autocomplete' => 'username'])
      @include('auth.partials.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password'])
      <label class="flex items-center gap-3 cursor-pointer text-sm">
        <input type="checkbox" name="remember" value="1" class="check"> Keep me signed in
      </label>
      <button class="btn btn-primary btn-lg w-full mt-2">Sign in</button>
    </form>

    <p class="text-center text-sm text-muted mt-6">This sign-in is separate from customer accounts on the website. <a href="{{ route('home') }}" class="font-bold text-ocean-600 hover:underline">Go to the website</a></p>
  </div>

  <script>window.SC_DATA = @json(app(\App\Support\ScDataPresenter::class)->minimal());</script>
  <script src="{{ asset('assets/js/core.js') }}"></script>
</body>
</html>
