@extends('layouts.admin')

@php
  $title = $record ? $resource::$singular.': '.$record->getAttribute($resource::$title) : 'New '.strtolower($resource::$singular);
@endphp

@section('title', $title)

@section('content')
  <nav class="text-sm text-muted mb-3" aria-label="Breadcrumb">
    <a href="{{ route($resource::routeName('index')) }}" class="hover:text-ocean-600">{{ $resource::$label }}</a> <span class="mx-1">/</span>
    <span class="text-ink">{{ $record ? 'Edit' : 'New' }}</span>
  </nav>
  <h1 class="font-display font-bold text-2xl sm:text-3xl">{{ $title }}</h1>

  <form method="POST" action="{{ $record ? route($resource::routeName('update'), $record->getKey()) : route($resource::routeName('store')) }}" enctype="multipart/form-data" class="card p-5 sm:p-6 mt-6" novalidate>
    @csrf
    @if ($record) @method('PUT') @endif

    <div class="grid sm:grid-cols-2 gap-5">
      @foreach ($resource::formFields() as $field)
        @include('admin.resources.field', ['field' => $field, 'record' => $record])
      @endforeach
    </div>

    @if ($extra = $resource::formExtra())
      <div class="mt-8 pt-6 border-t border-line">@include($extra, ['record' => $record])</div>
    @endif

    <div class="mt-8 flex flex-wrap justify-between gap-3">
      <a href="{{ route($resource::routeName('index')) }}" class="btn btn-outline">Cancel</a>
      <button class="btn btn-primary">{{ $record ? 'Save changes' : 'Create '.strtolower($resource::$singular) }}</button>
    </div>
  </form>

  {{-- Field::showWhen(): a field shows only while another field (select, radio or checkbox) has one of the listed
       values. Hidden fields are disabled, so they aren't sent and save empty. Fields can depend on fields that are
       themselves hidden: a hidden switch counts as off. --}}
  <script>
    (() => {
      const form = document.currentScript.previousElementSibling;
      const deps = [...form.querySelectorAll('[data-show-when]')];
      if (!deps.length) return;
      const box = (name) => form.querySelector(`[name="${name}"]:not([type=hidden])`)?.closest('[data-show-when], .grid > div');
      const valueOf = (name) => {
        const holder = box(name);
        if (holder && holder.hidden) return '';
        const inputs = [...form.querySelectorAll(`[name="${name}"]`)];
        const box1 = inputs.find((i) => i.type === 'checkbox');
        if (box1) return box1.checked ? '1' : '0';
        return inputs.find((i) => i.type !== 'hidden')?.value ?? '';
      };
      const apply = () => {
        // Twice, so a field that depends on a field that just appeared or disappeared follows it
        for (let pass = 0; pass < 2; pass++) deps.forEach((el) => {
          const show = el.dataset.showValues.split(',').includes(valueOf(el.dataset.showWhen));
          el.hidden = !show;
          el.querySelectorAll('input, select, textarea').forEach((i) => { i.disabled = !show; });
        });
      };
      form.addEventListener('change', apply);
      apply();
    })();
  </script>
@endsection
