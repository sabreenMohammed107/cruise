@extends('layouts.admin')
@use('App\Enums\AgentRequestStatus')
@use('App\Support\Format')

@section('title', 'Agent request: '.$application->company_name)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb"><a href="{{ route('admin.agent-requests.index') }}" class="hover:text-ocean-600">Agent requests</a> <span class="mx-1">/</span> <span class="text-ink">{{ $application->company_name }}</span></nav>

  @include('admin.partials.flash')

  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $application->company_name }}</h1>
        @include('admin.partials.badge', ['status' => $application->status])
      </div>
      <p class="text-muted mt-1">Received {{ Format::date($application->created_at, 'j M Y, H:i') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="https://wa.me/{{ preg_replace('/\D/', '', $application->full_phone) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm"><i data-icon="chat" class="w-4 h-4"></i> WhatsApp</a>
      <a href="tel:{{ preg_replace('/[^0-9+]/', '', $application->full_phone) }}" class="btn btn-outline btn-sm"><i data-icon="phone" class="w-4 h-4"></i> Call</a>
      <a href="mailto:{{ $application->email }}?subject={{ rawurlencode('Your Safer agent application') }}" class="btn btn-primary btn-sm"><i data-icon="mail" class="w-4 h-4"></i> Email</a>
    </div>
  </div>

  <section class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg mb-4">Application</h2>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-5 text-sm">
      @foreach ([
          'Company' => $application->company_name,
          'Contact person' => $application->contact_name,
          'Email' => $application->email,
          'Mobile' => $application->full_phone,
      ] as $label => $value)
        <div><dt class="text-xs font-semibold text-muted">{{ $label }}</dt><dd class="font-bold mt-0.5 break-words" @if ($label === 'Mobile') dir="ltr" @endif>{{ $value }}</dd></div>
      @endforeach
    </dl>
  </section>

  <section class="card p-5 sm:p-6 mt-6">
    <h2 class="font-display font-semibold text-lg mb-4">Status</h2>
    <form method="POST" action="{{ route('admin.agent-requests.update', $application) }}" class="flex flex-wrap items-end gap-3">
      @csrf @method('PATCH')
      <div>
        <label for="status" class="label">Move this application to</label>
        <select id="status" name="status" class="field !w-auto min-w-48">
          @foreach (AgentRequestStatus::cases() as $case)
            <option value="{{ $case->value }}" @selected($application->status === $case)>{{ Format::badge($case)[1] }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary">Save status</button>
    </form>
  </section>
@endsection
