@if (session('status'))
  <p class="mb-5 rounded-2xl border border-[#12805C]/25 bg-[#E3F5EE] p-4 text-sm font-semibold text-[#0E6B4C]" role="status">{{ session('status') }}</p>
@endif
@if (session('error'))
  <p class="mb-5 rounded-2xl border border-[#C42F2F]/30 bg-[#FDF0F0] p-4 text-sm font-semibold text-[#8E1F1F]" role="alert">{{ session('error') }}</p>
@endif
@if ($errors->any())
  <div class="mb-5 rounded-2xl border border-[#C42F2F]/30 bg-[#FDF0F0] p-4 text-sm text-[#8E1F1F]" role="alert">
    <p class="font-bold">{{ $errors->count() === 1 ? '1 field needs attention.' : $errors->count().' fields need attention.' }}</p>
    <ul class="mt-1 list-disc ps-5">
      @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif
