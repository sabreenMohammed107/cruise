{{-- Chat assistant (App\Http\Controllers\AssistantController, public/assets/js/assistant.js). Shown when
     App\Services\Assistant\Assistant::enabled(): an ANTHROPIC_API_KEY is set, ASSISTANT_ENABLED is not false, and
     it isn't switched off in Admin → Assistant chats, and (test mode) the visitor is a tester. Sits above the WhatsApp button (partials/footer). --}}
@if (\App\Services\Assistant\Assistant::available())
@php
  $chatUser = auth('web')->user();
  // Signed in: start with the reports (commissions for agents); guests: questions about cruises
  $starters = $chatUser
      ? collect(__('assistant.report_topics'))->except($chatUser->isAgent() ? [] : ['commissions'])->values()->all()
      : __('assistant.suggestions');
  $strings = [
      'confirm' => __('assistant.confirm_choices'),
      'total' => __('assistant.report.total'),
      'excel' => __('assistant.report.excel'),
      'pdf' => __('assistant.report.pdf'),
      'save' => __('assistant.report.save'),
      'savePrompt' => __('assistant.report.save_prompt'),
      'usd' => __('assistant.report.usd'),
      'empty' => __('assistant.report.empty'),
      'imageSent' => __('assistant.image_sent'),
      'imageInvalid' => __('assistant.image_invalid'),
  ];
  // Photos: the super admin and the accounts chosen in Admin → Assistant chats only
  $images = \App\Services\Assistant\Assistant::imagesAllowed();
@endphp
<div id="sc-assistant"
     data-url="{{ route('assistant.message') }}"
     data-reset-url="{{ route('assistant.reset') }}"
     data-chat="{{ \App\Http\Controllers\AssistantController::chatId(request()) }}"
     data-thinking="{{ __('assistant.thinking') }}"
     data-error="{{ __('assistant.error') }}"
     data-too-many="{{ __('assistant.too_many') }}"
     @if ($images) data-images="{{ \App\Services\Assistant\ImageInput::MAX_SIDE }}" @endif
     data-strings='@json($strings)'>

  <button type="button" data-assistant-open aria-expanded="false" aria-controls="sc-assistant-panel"
          class="fixed z-40 bottom-24 end-5 h-14 w-14 rounded-full bg-ocean-700 text-white shadow-lg shadow-black/20 inline-flex items-center justify-center hover:bg-ocean-800 transition"
          aria-label="{{ __('assistant.open') }}" title="{{ __('assistant.open') }}">
    <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 3l1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>
    </svg>
  </button>

  <section id="sc-assistant-panel" hidden role="dialog" aria-label="{{ __('assistant.title') }}"
           class="fixed z-50 inset-x-3 bottom-3 sm:inset-x-auto sm:end-5 sm:bottom-5 sm:w-[460px] h-[min(680px,calc(100dvh-1.5rem))] flex flex-col rounded-3xl bg-white shadow-2xl shadow-black/25 border border-ocean-100 overflow-hidden">
    <header class="bg-ocean-800 text-white px-4 py-3 flex items-center gap-3">
      <div class="min-w-0 flex-1" data-assistant-title>
        <p class="font-bold leading-tight">{{ __('assistant.title') }}</p>
        <p class="text-xs text-ocean-100 truncate">{{ __('assistant.subtitle') }}</p>
      </div>
      <button type="button" data-assistant-new class="text-xs font-semibold rounded-full border border-white/30 px-3 py-1 hover:bg-white/10">{{ __('assistant.new_chat') }}</button>
      <button type="button" data-assistant-min class="h-8 w-8 rounded-full hover:bg-white/10 inline-flex items-center justify-center" aria-label="{{ __('assistant.minimize') }}" title="{{ __('assistant.minimize') }}" aria-expanded="true">
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path data-min-icon d="M6 12h12"/><path data-restore-icon d="M6 15l6-6 6 6" style="display:none"/></svg>
      </button>
      <button type="button" data-assistant-close class="h-8 w-8 rounded-full hover:bg-white/10 inline-flex items-center justify-center" aria-label="{{ __('assistant.close') }}">
        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </header>

    <div data-assistant-log class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-ocean-50/40" aria-live="polite">
      <div class="sc-msg sc-msg-bot">{{ $chatUser ? __('assistant.welcome_user', ['name' => $chatUser->first_name ?: $chatUser->name]) : __('assistant.welcome') }}</div>
      <div data-assistant-suggestions class="flex flex-wrap gap-2">
        @foreach ($starters as $suggestion)
          <button type="button" class="text-start text-sm rounded-full border border-ocean-200 bg-white px-3 py-1.5 text-ocean-800 hover:bg-ocean-50">{{ $suggestion }}</button>
        @endforeach
      </div>
    </div>

    @if ($images)
      {{-- The chosen photo, before it is sent --}}
      <div data-assistant-preview hidden class="border-t border-ocean-100 px-3 pt-3 bg-white">
        <div class="relative inline-block">
          <img data-assistant-preview-img alt="" class="h-16 w-16 rounded-xl object-cover border border-ocean-100">
          <button type="button" data-assistant-remove-image class="absolute -top-2 -end-2 h-6 w-6 rounded-full bg-slate-800 text-white text-xs leading-none" aria-label="{{ __('assistant.remove_image') }}" title="{{ __('assistant.remove_image') }}">×</button>
        </div>
      </div>
    @endif
    <form data-assistant-form class="border-t border-ocean-100 p-3 flex items-end gap-2 bg-white">
      @if ($images)
        <input type="file" accept="image/jpeg,image/png,image/webp" data-assistant-file hidden>
        <button type="button" data-assistant-attach class="h-10 w-10 shrink-0 rounded-full border border-ocean-200 text-ocean-700 inline-flex items-center justify-center hover:bg-ocean-50"
                aria-label="{{ __('assistant.attach_image') }}" title="{{ __('assistant.attach_image') }}: {{ __('assistant.image_hint') }}">
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-8 8"/>
          </svg>
        </button>
      @endif
      <label for="sc-assistant-input" class="sr-only">{{ __('assistant.placeholder') }}</label>
      {{-- Not "required": a photo can be sent without words (assistant.js checks the message itself) --}}
      <textarea id="sc-assistant-input" rows="1" maxlength="{{ config('assistant.max_message_length') }}"
                class="flex-1 resize-none rounded-2xl border border-ocean-200 px-3 py-2 text-sm max-h-32 focus:outline-none focus:ring-2 focus:ring-ocean-300"
                placeholder="{{ __('assistant.placeholder') }}"></textarea>
      <button type="submit" class="h-10 rounded-full bg-ocean-700 text-white px-4 text-sm font-bold hover:bg-ocean-800 disabled:opacity-50">{{ __('assistant.send') }}</button>
    </form>
    <p class="px-4 pb-3 text-[11px] text-slate-500 bg-white">{{ __('assistant.disclaimer') }}</p>
  </section>
</div>

<style>
  #sc-assistant-panel.sc-min { height: auto; }
  #sc-assistant-panel.sc-min > :not(header) { display: none; }
  #sc-assistant-panel.sc-min [data-assistant-title], #sc-assistant-panel.sc-min [data-assistant-new] { cursor: pointer; }
  #sc-assistant-panel.sc-min [data-assistant-new] { display: none; }
  #sc-assistant-panel.sc-min [data-min-icon] { display: none; }
  #sc-assistant-panel.sc-min [data-restore-icon] { display: inline !important; }
  #sc-assistant .sc-msg { max-width: 88%; padding: .6rem .85rem; border-radius: 1.1rem; font-size: .9rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
  #sc-assistant .sc-msg-bot { background: #fff; border: 1px solid #dbeaf5; color: #0f2b3d; border-start-start-radius: .3rem; }
  #sc-assistant .sc-msg-user { background: #0e5a85; color: #fff; margin-inline-start: auto; border-start-end-radius: .3rem; }
  #sc-assistant .sc-msg a { text-decoration: underline; font-weight: 600; }
  #sc-assistant .sc-msg-bot a { color: #0e5a85; }
  #sc-assistant .sc-msg-error { background: #fff4f2; border-color: #f6c9c0; color: #8a2a1b; }
  #sc-assistant .sc-choices { display: flex; flex-wrap: wrap; gap: .45rem; }
  #sc-assistant .sc-choice { font-size: .85rem; border: 1px solid #b9d6ea; background: #fff; color: #0e4a6e; border-radius: 999px; padding: .35rem .8rem; }
  #sc-assistant .sc-choice:hover:not(:disabled) { background: #eaf3f9; }
  #sc-assistant .sc-choice:disabled { opacity: .55; cursor: default; }
  #sc-assistant .sc-check { display: flex; align-items: center; gap: .4rem; font-size: .85rem; border: 1px solid #dbeaf5; background: #fff; border-radius: .7rem; padding: .3rem .6rem; cursor: pointer; }
  #sc-assistant .sc-check input { accent-color: #0e5a85; }
  #sc-assistant .sc-report { background: #fff; border: 1px solid #dbeaf5; border-radius: 1rem; overflow: hidden; }
  #sc-assistant .sc-report-head { padding: .6rem .8rem; border-bottom: 1px solid #dbeaf5; }
  #sc-assistant .sc-report-scroll { max-height: 260px; overflow: auto; }
  #sc-assistant .sc-report table { border-collapse: collapse; font-size: .75rem; min-width: 100%; }
  #sc-assistant .sc-report th, #sc-assistant .sc-report td { padding: .35rem .55rem; border-bottom: 1px solid #eef3f7; text-align: start; white-space: nowrap; }
  #sc-assistant .sc-report th { position: sticky; top: 0; background: #eaf3f9; font-weight: 700; color: #0e4a6e; }
  #sc-assistant .sc-report td.num, #sc-assistant .sc-report th.num { text-align: end; font-variant-numeric: tabular-nums; }
  #sc-assistant .sc-report tr.total td { font-weight: 700; background: #f6fafc; position: sticky; bottom: 0; }
  #sc-assistant .sc-report-actions { display: flex; flex-wrap: wrap; gap: .4rem; padding: .6rem .8rem; border-top: 1px solid #dbeaf5; }
  #sc-assistant .sc-report-actions a, #sc-assistant .sc-report-actions button { font-size: .8rem; font-weight: 700; border-radius: 999px; padding: .35rem .8rem; border: 1px solid #0e5a85; color: #0e5a85; background: #fff; text-decoration: none; }
  #sc-assistant .sc-report-actions .primary { background: #0e5a85; color: #fff; }
</style>
@push('scripts')
  <script src="@asset('assets/js/assistant.js')" defer></script>
@endpush
@endif
