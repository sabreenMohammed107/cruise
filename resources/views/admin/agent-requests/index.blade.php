@extends('layouts.admin')
@use('App\Enums\AgentRequestStatus')
@use('App\Support\Format')

@section('title', 'Agent requests')

@section('content')
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Agent requests</h1>
      <p class="text-muted mt-1">Applications from the website's "Become our agent" page.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('agents') }}" target="_blank" class="btn btn-outline btn-sm"><i data-icon="external" class="w-4 h-4"></i> View page</a>
      <a href="{{ route('admin.pages.index') }}#page-agents" class="btn btn-outline btn-sm"><i data-icon="edit" class="w-4 h-4"></i> Edit page texts</a>
    </div>
  </div>

  {{-- Status filter, with how many applications are in each --}}
  <nav class="mt-5 flex flex-wrap gap-2" aria-label="Status">
    <a href="{{ route('admin.agent-requests.index') }}" class="btn btn-sm {{ $status ? 'btn-ghost' : 'btn-primary' }}" @unless ($status) aria-current="page" @endunless>All ({{ $counts->sum() }})</a>
    @foreach (AgentRequestStatus::cases() as $case)
      <a href="{{ route('admin.agent-requests.index', ['status' => $case->value]) }}" class="btn btn-sm {{ $status === $case ? 'btn-primary' : 'btn-ghost' }}" @if ($status === $case) aria-current="page" @endif>
        {{ Format::badge($case)[1] }} ({{ $counts[$case->value] ?? 0 }})
      </a>
    @endforeach
  </nav>

  @include('admin.partials.flash')

  <div class="card overflow-hidden mt-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">Company</th>
            <th class="text-start font-semibold p-3">Contact</th>
            <th class="text-start font-semibold p-3">Status</th>
            <th class="text-start font-semibold p-3">Received</th>
            <th class="p-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($applications as $a)
            <tr class="border-t border-line hover:bg-page">
              <td class="p-3"><a href="{{ route('admin.agent-requests.show', $a) }}" class="font-bold text-ocean-700 hover:underline">{{ $a->company_name }}</a></td>
              <td class="p-3">{{ $a->contact_name }}<p class="text-xs text-muted break-all">{{ $a->email }} · <span dir="ltr">{{ $a->full_phone }}</span></p></td>
              <td class="p-3">@include('admin.partials.badge', ['status' => $a->status])</td>
              <td class="p-3 whitespace-nowrap text-muted">{{ Format::date($a->created_at, 'j M, H:i') }}</td>
              <td class="p-3 text-end"><a href="{{ route('admin.agent-requests.show', $a) }}" class="btn btn-ghost btn-sm">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="p-8 text-center text-muted">No applications{{ $status ? ' with this status' : ' yet' }}.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-5">{{ $applications->links() }}</div>
@endsection
