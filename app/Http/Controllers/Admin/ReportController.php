<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cruise;
use App\Models\Sailing;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin → Reports (docs/reports-plan.pdf). Every report is a class in app/Reports with the same filters, page and
 * Excel export. Staff can open them; a seller's figures cover only the bookings assigned to them (ReportFilters).
 */
class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports.index', [
            'reports' => collect(Report::all())->filter(fn ($r) => ! $r::ability() || Gate::allows($r::ability())),
        ]);
    }

    public function show(Request $request, string $key): View
    {
        $class = $this->report($key);
        $filters = ReportFilters::fromRequest($request);
        $report = new $class;

        return view('admin.reports.show', [
            'report' => $report,
            'class' => $class,
            'filters' => $filters,
            'options' => $this->options($request->user()),
        ] + $report->data($filters, $request));
    }

    /** The report as a formatted Excel workbook (App\Support\Xlsx), with the same filters as the page. */
    public function export(Request $request, string $key): BinaryFileResponse
    {
        $class = $this->report($key);
        $filters = ReportFilters::fromRequest($request);

        $filename = $class::key().'-'.$filters->from->toDateString().'_'.$filters->to->toDateString().'.xlsx';

        return response()->download((new $class)->workbook($filters)->save(), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @return class-string<Report> */
    private function report(string $key): string
    {
        $class = Report::find($key) ?? abort(404);
        if ($class::ability()) {
            Gate::authorize($class::ability());
        }

        return $class;
    }

    /** Choices for the filter bar. */
    private function options(User $viewer): array
    {
        return [
            'cruises' => Cruise::withTrashed()->orderBy('name')->get(['id', 'name']),
            'sailings' => Sailing::with('cruise:id,name')
                ->whereIn('id', Booking::query()->visibleTo($viewer)->select('sailing_id'))
                ->orderByDesc('departure_date')->get(['id', 'cruise_id', 'departure_date', 'code']),
            // Sellers always see their own bookings: no staff picker for them
            'staff' => $viewer->isSeller() ? collect() : User::assignable()->visibleTo($viewer)->get(['id', 'name', 'role']),
            'agents' => User::visibleTo($viewer)->whereIn('id', Booking::query()->whereNotNull('agent_id')->select('agent_id'))
                ->orderBy('company_name')->orderBy('name')->get(['id', 'name', 'company_name']),
        ];
    }
}
