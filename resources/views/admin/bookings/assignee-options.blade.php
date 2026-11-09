{{--
  <option>s of the "Assigned to" pickers and filter, grouped by role (Admins, Supervisors, Sellers).
  $assignees: User::assignable() (AppServiceProvider). $selected: the chosen id (int|string|null).
  $assignedUser: the booking's assignee, shown even when they are no longer staff, so a row doesn't look unassigned.
  (Distinct names on purpose: @include passes the parent view's variables, and the list page has its own $current.)
--}}
@php
  $selected = (string) ($selected ?? '');
  $assignedNow = ($assignedUser ?? null) instanceof \App\Models\User ? $assignedUser : null;
  $groups = ['admin' => 'Admins', 'supervisor' => 'Supervisors', 'seller' => 'Sellers'];
@endphp
@foreach ($assignees->groupBy(fn ($u) => $u->role->value) as $role => $people)
  <optgroup label="{{ $groups[$role] ?? $role }}">
    @foreach ($people as $u)
      <option value="{{ $u->id }}" @selected($selected === (string) $u->id)>{{ $u->name }}</option>
    @endforeach
  </optgroup>
@endforeach
@if ($assignedNow && ! $assignees->contains('id', $assignedNow->id))
  <option value="{{ $assignedNow->id }}" selected disabled>{{ $assignedNow->name }} (no longer staff)</option>
@endif
