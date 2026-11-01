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

  @if ($application->status === AgentRequestStatus::Accepted)
    {{-- Approved: the agent account made for it (App\Services\AgentAccounts) --}}
    <section class="card p-5 sm:p-6 mt-6">
      <h2 class="font-display font-semibold text-lg mb-4">Agent account</h2>
      @if ($application->user)
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-5 text-sm">
          <div><dt class="text-xs font-semibold text-muted">Signs in with</dt><dd class="font-bold mt-0.5 break-words">{{ $application->user->email }}</dd></div>
          <div><dt class="text-xs font-semibold text-muted">Account type</dt><dd class="font-bold mt-0.5">{{ $application->user->role->label() }}</dd></div>
          <div><dt class="text-xs font-semibold text-muted">Agent type</dt><dd class="font-bold mt-0.5">{{ $application->user->agentTier?->name ?? 'No type' }}</dd></div>
          <div><dt class="text-xs font-semibold text-muted">Approved</dt><dd class="font-bold mt-0.5">{{ $application->approved_at ? Format::date($application->approved_at, 'j M Y, H:i') : '—' }}</dd></div>
        </dl>
        <div class="flex flex-wrap gap-2 mt-5">
          @can('manage-users')
            <a href="{{ route('admin.agents.edit', $application->user) }}" class="btn btn-outline btn-sm">Edit account</a>
            @if ($application->user->isAgent())
              <form method="POST" action="{{ route('admin.agent-requests.credentials', $application) }}" onsubmit="return confirm('Make a new temporary password and email it to {{ addslashes($application->user->email) }}? The current password stops working.')">
                @csrf
                <button class="btn btn-outline btn-sm"><i data-icon="mail" class="w-4 h-4"></i> Send new password</button>
              </form>
            @endif
          @endcan
        </div>
        @unless ($application->user->isAgent())
          <p class="text-sm text-muted mt-3">This account is no longer an agent, so it sees the public prices.</p>
        @endunless
      @else
        <p class="text-sm text-muted">Approved, but the account was deleted since.</p>
      @endif
    </section>
  @else
    @can('manage-users')
      <section class="card p-5 sm:p-6 mt-6">
        <h2 class="font-display font-semibold text-lg mb-1">Approve</h2>
        <p class="text-sm text-muted mb-4">Makes a new website account for {{ $application->email }} with the role Agent and emails the sign-in details with a temporary password. Agents see the rates set under Agents → Agent rates.</p>
        {{-- Agent accounts need an email of their own (App\Services\AgentAccounts refuses it too) --}}
        @if ($existingAccount)
          <p class="mb-4 rounded-2xl border border-[#C42F2F]/30 bg-[#FDF0F0] p-4 text-sm font-semibold text-[#8E1F1F]" role="alert">
            {{ $application->email }} already has {{ $existingAccount->isStaff() ? 'a staff account' : ($existingAccount->isAgent() ? 'an agent account' : 'a customer account') }}, so it can't be approved. Ask the applicant to apply again with another email, then reject this application.
          </p>
        @endif
        <form method="POST" action="{{ route('admin.agent-requests.approve', $application) }}" class="flex flex-wrap items-end gap-3" onsubmit="return confirm('Approve {{ addslashes($application->company_name) }} and email the sign-in details to {{ addslashes($application->email) }}?')">
          @csrf
          <div>
            <label for="agent_tier_id" class="label">Agent type</label>
            <select id="agent_tier_id" name="agent_tier_id" class="field !w-auto min-w-48">
              <option value="">No type (rates for all agent types)</option>
              @foreach (\App\Models\AgentTier::options() as $id => $name)
                <option value="{{ $id }}" @selected((int) old('agent_tier_id') === $id)>{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <button class="btn btn-primary" @disabled($existingAccount)><i data-icon="check" class="w-4 h-4"></i> Approve and send sign-in details</button>
        </form>
        <p class="text-xs text-muted mt-2">Types and their rates: <a href="{{ route('admin.agent-tiers.index') }}" class="text-ocean-600 font-semibold hover:underline">Agent types</a>. You can change the type later under Agents.</p>
      </section>
    @endcan

    <section class="card p-5 sm:p-6 mt-6">
      <h2 class="font-display font-semibold text-lg mb-4">Status</h2>
      <form method="POST" action="{{ route('admin.agent-requests.update', $application) }}" class="flex flex-wrap items-end gap-3">
        @csrf @method('PATCH')
        <div>
          <label for="status" class="label">Move this application to</label>
          <select id="status" name="status" class="field !w-auto min-w-48">
            {{-- Accepted: the Approve button above --}}
            @foreach (AgentRequestStatus::cases() as $case)
              @continue($case === AgentRequestStatus::Accepted)
              <option value="{{ $case->value }}" @selected($application->status === $case)>{{ Format::badge($case)[1] }}</option>
            @endforeach
          </select>
        </div>
        <button class="btn btn-primary">Save status</button>
      </form>
    </section>
  @endif
@endsection
