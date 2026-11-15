<?php

namespace App\Http\Controllers;

use App\Models\AssistantReport;
use App\Services\Assistant\Reports\ReportEngine;
use App\Services\Assistant\Reports\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads of a chat report (Tools\RunReport): Excel (.xlsx) and a print page to save as PDF, and "save as template".
 * Only the signed-in user who asked for the report; the spec runs again, so a download has today's data.
 */
class AssistantReportController extends Controller
{
    public function excel(Request $request, AssistantReport $report, ReportEngine $engine): BinaryFileResponse
    {
        $result = $this->run($request, $report, $engine);
        $headers = array_column($result['columns'], 'label');
        $rows = array_map(fn ($row) => array_map(
            fn ($c) => $this->cell($row[$c['key']] ?? null, $c['type']),
            $result['columns'],
        ), $result['rows']);
        if ($result['totals']) {
            $rows[] = array_map(fn ($c, $i) => $result['totals'][$c['key']] ?? ($i === 0 ? (app()->getLocale() === 'ar' ? 'الإجمالي' : 'Total') : null), $result['columns'], array_keys($result['columns']));
        }

        $path = XlsxWriter::write($report->title, $headers, $rows, app()->getLocale() === 'ar');

        return response()->download($path, $this->filename($report).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** A clean page that opens the print dialog: "Save as PDF" keeps Arabic text right, unlike PDF libraries. */
    public function print(Request $request, AssistantReport $report, ReportEngine $engine): View
    {
        return view('assistant.report-print', [
            'report' => $report,
            'result' => $this->run($request, $report, $engine),
            'user' => $request->user('web'),
        ]);
    }

    public function template(Request $request, AssistantReport $report): JsonResponse
    {
        $this->authorizeOwner($request, $report);
        $name = trim($request->validate(['name' => ['required', 'string', 'max:80']])['name']);

        // Same name again: the template is replaced
        AssistantReport::updateOrCreate(
            ['user_id' => $report->user_id, 'template_name' => $name],
            ['title' => $report->title, 'spec' => $report->spec],
        );

        return response()->json(['ok' => true, 'message' => __('assistant.report.saved', ['name' => $name])]);
    }

    private function run(Request $request, AssistantReport $report, ReportEngine $engine): array
    {
        $this->authorizeOwner($request, $report);

        // Normalized again: a spec saved before a change of columns or filters still runs
        return $engine->run($engine->normalize($report->spec, $request->user('web')), $request->user('web'));
    }

    private function authorizeOwner(Request $request, AssistantReport $report): void
    {
        abort_unless($request->user('web')?->id === $report->user_id, 404);
    }

    private function cell(mixed $value, string $type): string|int|float|null
    {
        return match (true) {
            $value === null => null,
            $type === 'money' => (float) $value,
            $type === 'int' => (int) $value,
            default => (string) $value,
        };
    }

    private function filename(AssistantReport $report): string
    {
        $slug = Str::slug(Str::before($report->title, ' · ')) ?: 'report';

        return $slug.'-'.now()->format('Y-m-d');
    }
}
