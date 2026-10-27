@extends('layouts.admin')
@use('App\Support\Format')
@use('Illuminate\Support\Str')

@section('title', 'Contact messages')

@section('content')
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="font-display font-bold text-2xl sm:text-3xl">Contact messages</h1>
      <p class="text-muted mt-1">Sent from the website's "Contact us" page. Opening a message marks it as read.</p>
    </div>
    <div class="flex gap-2" role="tablist" aria-label="Show">
      <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-sm {{ $unreadOnly ? 'btn-ghost' : 'btn-primary' }}" @unless ($unreadOnly) aria-current="page" @endunless>All</a>
      <a href="{{ route('admin.contact-messages.index', ['unread' => 1]) }}" class="btn btn-sm {{ $unreadOnly ? 'btn-primary' : 'btn-ghost' }}" @if ($unreadOnly) aria-current="page" @endif>Unread ({{ $unreadCount }})</a>
    </div>
  </div>

  @include('admin.partials.flash')

  <div class="card overflow-hidden mt-5">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-ocean-50 text-ocean-800">
          <tr>
            <th class="text-start font-semibold p-3">From</th>
            <th class="text-start font-semibold p-3">Message</th>
            <th class="text-start font-semibold p-3">Received</th>
            <th class="p-3"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($messages as $m)
            <tr class="border-t border-line hover:bg-page {{ $m->is_read ? '' : 'bg-ocean-50/40' }}">
              <td class="p-3">
                <a href="{{ route('admin.contact-messages.show', $m) }}" class="{{ $m->is_read ? 'font-semibold' : 'font-bold' }} text-ocean-700 hover:underline">{{ $m->name }}</a>
                @unless ($m->is_read)<span class="tag tag-ocean ms-1">New</span>@endunless
                <p class="text-xs text-muted break-all">{{ $m->email }}</p>
              </td>
              <td class="p-3 max-w-md {{ $m->is_read ? 'text-muted' : '' }}">{{ Str::limit($m->message, 110) }}</td>
              <td class="p-3 whitespace-nowrap text-muted">{{ Format::date($m->created_at, 'j M, H:i') }}</td>
              <td class="p-3 text-end whitespace-nowrap">
                <a href="{{ route('admin.contact-messages.show', $m) }}" class="btn btn-ghost btn-sm">Open</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="p-8 text-center text-muted">{{ $unreadOnly ? 'No unread messages.' : 'No messages yet.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-5">{{ $messages->links() }}</div>
@endsection
