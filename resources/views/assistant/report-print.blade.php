@use('App\Services\Assistant\Reports\ReportEngine')
@php($ar = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>{{ $report->title }} · Safer Cruise</title>
  @include('partials.favicon')
  {{-- Print page of a chat report (AssistantReportController@print): the browser's "Save as PDF" makes the PDF --}}
  <style>
    :root { --ink: #0f2b3d; --muted: #5b7083; --line: #dbe5ee; --brand: #0e5a85; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: var(--ink); background: #f4f8fb; }
    main { max-width: 1100px; margin: 24px auto; background: #fff; padding: 28px 32px; border-radius: 16px; box-shadow: 0 2px 16px rgba(15, 43, 61, .08); }
    header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 2px solid var(--brand); padding-bottom: 14px; }
    .brand { font-weight: 800; font-size: 20px; color: var(--brand); }
    .brand span { color: #1aa3b8; }
    h1 { font-size: 18px; margin: 14px 0 4px; }
    .meta { color: var(--muted); font-size: 12px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
    th, td { border: 1px solid var(--line); padding: 6px 8px; text-align: start; vertical-align: top; }
    th { background: #eaf3f9; font-weight: 700; }
    td.num, th.num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tr.total td { font-weight: 700; background: #f4f8fb; }
    .actions { display: flex; gap: 8px; }
    .btn { border: 1px solid var(--brand); background: var(--brand); color: #fff; padding: 8px 14px; border-radius: 999px; font-weight: 700; cursor: pointer; font: inherit; font-size: 13px; }
    .btn.ghost { background: #fff; color: var(--brand); text-decoration: none; }
    .empty { padding: 24px; text-align: center; color: var(--muted); }
    @media print {
      body { background: #fff; }
      main { margin: 0; box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
      .actions { display: none; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; }
    }
    @page { size: A4 landscape; margin: 12mm; }
  </style>
</head>
<body>
  <main>
    <header>
      <div>
        <div class="brand">Safer<span>Cruise</span></div>
        <h1>{{ $report->title }}</h1>
        <p class="meta">
          {{ $user->name }} · {{ $ar ? 'تاريخ الإنشاء' : 'Generated' }} {{ now()->format('Y-m-d H:i') }}
          · {{ $result['count'] }} {{ $ar ? 'صف' : ($result['count'] === 1 ? 'row' : 'rows') }}
          · {{ $ar ? 'المبالغ بالدولار الأمريكي' : 'Amounts in US dollars' }}
        </p>
      </div>
      <div class="actions">
        <button type="button" class="btn" onclick="window.print()">{{ __('assistant.report.print') }}</button>
        <a class="btn ghost" href="{{ route('assistant.reports.excel', $report) }}">{{ __('assistant.report.excel') }}</a>
      </div>
    </header>

    @if ($result['count'] === 0)
      <p class="empty">{{ __('assistant.report.empty') }}</p>
    @else
      <table>
        <thead>
          <tr>
            @foreach ($result['columns'] as $c)
              <th class="{{ in_array($c['type'], ['money', 'int']) ? 'num' : '' }}">{{ $c['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach ($result['rows'] as $row)
            <tr>
              @foreach ($result['columns'] as $c)
                <td class="{{ in_array($c['type'], ['money', 'int']) ? 'num' : '' }}" dir="auto">{{ ReportEngine::display($row[$c['key']] ?? null, $c['type']) }}</td>
              @endforeach
            </tr>
          @endforeach
          @if ($result['totals'])
            <tr class="total">
              @foreach ($result['columns'] as $i => $c)
                <td class="{{ isset($result['totals'][$c['key']]) ? 'num' : '' }}">{{ isset($result['totals'][$c['key']]) ? ReportEngine::display($result['totals'][$c['key']], 'money') : ($i === 0 ? ($ar ? 'الإجمالي' : 'Total') : '') }}</td>
              @endforeach
            </tr>
          @endif
        </tbody>
      </table>
    @endif
  </main>
  <script>
    // Opens the print dialog ("Save as PDF") once, when the page comes from the chat's PDF button
    if (new URLSearchParams(location.search).has('autoprint')) window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
  </script>
</body>
</html>
