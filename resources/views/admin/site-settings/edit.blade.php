{{-- Admin → Website content → Footer & contact (Admin\SiteSettingController; fields in config/site.php). --}}
@extends('layouts.admin')
@use('App\Models\SiteSetting')

@php
  $inputType = ['url' => 'url', 'email' => 'email', 'phone' => 'tel', 'whatsapp' => 'tel'];
@endphp

@section('title', 'Footer & contact')

@section('content')
  <h1 class="font-display font-bold text-2xl sm:text-3xl">Footer & contact</h1>
  <p class="text-muted mt-2 max-w-[75ch]">What the footer shows on every page of the website, in English and Arabic.
    Leave a box empty to hide that line (an empty Arabic box shows the English text). Phone numbers, the email and
    links are the same in both languages.</p>

  <form method="POST" action="{{ route('admin.site-settings.update') }}" class="card p-5 sm:p-6 mt-6" novalidate>
    @csrf @method('PUT')

    @foreach ($groups as $group => $fields)
      <fieldset class="{{ $loop->first ? '' : 'mt-8 pt-6 border-t border-line' }}">
        <legend class="font-display font-semibold text-lg">{{ $group }}</legend>
        @if ($group === 'Social media')
          <p class="text-sm text-muted mt-1">Full page addresses (https://…). Only the networks with an address are shown in the footer.</p>
        @endif

        <div class="mt-4 grid sm:grid-cols-2 gap-5">
          @foreach ($fields as $key => $field)
            @php($type = $field['type'] ?? 'text')
            @if ($field['bi'] ?? false)
              <div class="sm:col-span-2 grid sm:grid-cols-2 gap-3">
                @foreach (['en' => [$key, ' (English)', null], 'ar' => [$key.'_ar', ' (عربي)', 'rtl']] as $lang => [$name, $suffix, $dir])
                  <div>
                    <label class="label" for="f-{{ $name }}">{{ $field['label'].$suffix }}</label>
                    @if ($type === 'textarea')
                      <textarea id="f-{{ $name }}" name="{{ $name }}" rows="4" class="field {{ $errors->has($name) ? 'is-invalid' : '' }}" @if ($dir) dir="rtl" lang="ar" @endif>{{ old($name, SiteSetting::value($key, $lang)) }}</textarea>
                    @else
                      <input id="f-{{ $name }}" name="{{ $name }}" class="field {{ $errors->has($name) ? 'is-invalid' : '' }}" value="{{ old($name, SiteSetting::value($key, $lang)) }}" @if ($dir) dir="rtl" lang="ar" @endif>
                    @endif
                    @if ($e = $errors->first($name))<p class="err show">{{ $e }}</p>@endif
                  </div>
                @endforeach
              </div>
            @else
              <div class="{{ $type === 'whatsapp' ? 'sm:col-span-2' : '' }}">
                <label class="label" for="f-{{ $key }}">{{ $field['label'] }}</label>
                <input id="f-{{ $key }}" name="{{ $key }}" type="{{ $inputType[$type] ?? 'text' }}" dir="ltr" class="field {{ $errors->has($key) ? 'is-invalid' : '' }}"
                       value="{{ old($key, SiteSetting::value($key)) }}"
                       @if ($type === 'url') placeholder="https://" @elseif ($type === 'phone') placeholder="+966 50 000 0000" @endif>
                @if ($e = $errors->first($key))
                  <p class="err show">{{ $e }}</p>
                @elseif ($field['help'] ?? null)
                  <p class="text-xs text-muted mt-1.5">{{ $field['help'] }}</p>
                @endif
              </div>
            @endif
          @endforeach
        </div>
      </fieldset>
    @endforeach

    <div class="mt-8 flex flex-wrap justify-end gap-3">
      <a href="{{ route('home') }}" target="_blank" class="btn btn-outline"><i data-icon="external" class="w-4 h-4"></i> View site</a>
      <button class="btn btn-primary">Save changes</button>
    </div>
  </form>
@endsection
