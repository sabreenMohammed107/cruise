{{-- "Become an agent" (AgentController). Every heading, text, button and benefit card comes from $sections
     (Admin → Cruise pages → Become an agent, config/pages.php → agents); fixed words (form labels, buttons) from
     lang/{en,ar}/pages.php → agents. Applications are read in Admin → Agents → Agent requests. --}}
@extends('layouts.app')

@section('title', __('pages.agents.title'))
@section('description', __('pages.agents.description'))
@section('nav', 'agents')

@php
  ['head' => $head, 'program' => $program, 'vision' => $vision, 'benefits' => $benefits, 'form' => $form] = $sections->all();
@endphp

@section('content')
  <main class="flex-1">
    <!-- Page head -->
    <section class="bg-ocean-700 text-white">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-20">
        @include('partials.breadcrumb', ['tone' => 'light', 'items' => [[__('pages.breadcrumb.home'), route('home')], [__('pages.breadcrumb.agents'), null]]])
        @if ($head->filledBi('eyebrow'))<p class="font-semibold text-lagoon-400 uppercase tracking-wide text-sm">{{ $head->bi('eyebrow') }}</p>@endif
        <h1 class="font-display font-bold text-4xl sm:text-5xl mt-2">{{ $head->bi('title') }}</h1>
        @if ($head->filledBi('body'))<p class="mt-4 text-lg text-ocean-100 max-w-[60ch] leading-relaxed">{{ $head->bi('body') }}</p>@endif
        @if ($head->filledBi('primary_label'))
          <a href="{{ $head->primaryHref() }}" class="btn btn-accent btn-lg mt-8">{{ $head->bi('primary_label') }} <i data-icon="arrow-right" class="w-4 h-4 rtl:rotate-180"></i></a>
        @endif
      </div>
    </section>

    <!-- Program and vision -->
    @if ($program->is_visible || $vision->is_visible)
      <section class="max-w-site mx-auto px-4 sm:px-6 pt-12 sm:pt-16 grid md:grid-cols-2 gap-5">
        @foreach ([[$program, 'ship'], [$vision, 'globe']] as [$section, $icon])
          @continue (! $section->is_visible)
          <article class="card p-6 sm:p-8">
            <span class="grid place-items-center w-12 h-12 rounded-xl bg-ocean-50 text-ocean-600"><i data-icon="{{ $icon }}" class="w-6 h-6"></i></span>
            <h2 class="font-display font-bold text-2xl mt-4">{{ $section->bi('title') }}</h2>
            @if ($section->filledBi('body'))<p class="text-muted mt-3 leading-relaxed">{{ $section->bi('body') }}</p>@endif
          </article>
        @endforeach
      </section>
    @endif

    <!-- Benefits -->
    @if ($benefits->is_visible && $benefits->items->isNotEmpty())
      <section class="max-w-site mx-auto px-4 sm:px-6 pt-14 sm:pt-20">
        <h2 class="font-display font-bold text-3xl sm:text-4xl text-center">{{ $benefits->bi('title') }}</h2>
        <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
          @foreach ($benefits->items as $item)
            <div class="rounded-2xl bg-{{ $item->tone }}-50 p-6">
              <span class="grid place-items-center w-12 h-12 rounded-xl bg-white text-{{ $item->tone }}-600"><i data-icon="{{ $item->icon ?: 'check' }}" class="w-6 h-6"></i></span>
              <h3 class="font-display font-semibold text-lg mt-4">{{ $item->bi('title') }}</h3>
              @if ($item->filledBi('text'))<p class="text-muted mt-2 leading-relaxed">{{ $item->bi('text') }}</p>@endif
            </div>
          @endforeach
        </div>
      </section>
    @endif

    <!-- Application form -->
    <section id="apply" class="scroll-mt-24 max-w-site mx-auto px-4 sm:px-6 py-14 sm:py-20">
      <div class="rounded-[28px] bg-ocean-700 text-white p-6 sm:p-10 lg:p-12 grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] gap-8 lg:gap-12 items-center">
        <div>
          <h2 class="font-display font-bold text-3xl">{{ $form->bi('title') }}</h2>
          @if ($form->filledBi('body'))<p class="text-ocean-100 mt-3 leading-relaxed">{{ $form->bi('body') }}</p>@endif
        </div>

        <div class="card p-6 sm:p-8 text-ink">
          @if (session('agent_sent'))
            <div class="text-center py-6" role="status">
              <span class="mx-auto w-14 h-14 rounded-full bg-lagoon-50 text-lagoon-600 grid place-items-center"><i data-icon="check" class="w-7 h-7"></i></span>
              <h3 class="font-display font-bold text-2xl mt-5">{{ __('pages.agents.sent_title') }}</h3>
              <p class="text-muted mt-2">{{ __('pages.agents.sent_body') }}</p>
            </div>
          @else
            <form method="POST" action="{{ route('agents.store') }}#apply" class="grid sm:grid-cols-2 gap-4" novalidate>
              @csrf
              @foreach ([
                  ['company_name', 'text', 'organization'],
                  ['contact_name', 'text', 'name'],
                  ['email', 'email', 'email'],
              ] as [$name, $type, $autocomplete])
                @include('auth.partials.field', [
                    'name' => $name, 'type' => $type, 'autocomplete' => $autocomplete,
                    'label' => __("pages.agents.fields.$name"), 'placeholder' => __("pages.agents.placeholders.$name"),
                ])
              @endforeach
              @include('partials.phone-field', ['label' => __('pages.agents.fields.phone')])
              @include('partials.honeypot')
              <button class="btn btn-primary btn-lg sm:col-span-2">{{ __('pages.agents.send') }}</button>
            </form>
          @endif
        </div>
      </div>
    </section>
  </main>
@endsection

@push('scripts')
  @include('partials.phone-widget')
  <script src="@asset('assets/js/phone-fields.js')"></script>
@endpush
