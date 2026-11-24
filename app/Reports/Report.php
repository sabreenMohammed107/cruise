<?php

namespace App\Reports;

use App\Support\Xlsx;
use Illuminate\Http\Request;

/**
 * One admin report (Admin → Reports, docs/reports-plan.pdf): its page data and its Excel file, both built from the
 * shared ReportFilters. Admin\ReportController serves every report the same way, like the generic resources (D9).
 * Register a new report in self::all().
 */
abstract class Report
{
    /** URL key: /admin/reports/{key} */
    abstract public static function key(): string;

    abstract public static function title(): string;

    /** One line for the reports list. */
    abstract public static function description(): string;

    /** Icon name from core.js. */
    public static function icon(): string
    {
        return 'chart';
    }

    /** Gate needed to open the report; null: every staff member (a seller sees their own bookings only). */
    public static function ability(): ?string
    {
        return null;
    }

    /** Blade view for the page body (inside admin.reports.show). */
    abstract public function view(): string;

    /** Everything the page shows. */
    abstract public function data(ReportFilters $filters, Request $request): array;

    /** The Excel export: the same figures and filters as the page. */
    abstract public function workbook(ReportFilters $filters): Xlsx;

    /** The top of an export's first sheet: title, period and comparison, filters, who exported it, then a blank row. */
    protected function heading(Xlsx $xlsx, ReportFilters $filters): Xlsx
    {
        $comparison = $filters->comparison();

        return $xlsx->row([[static::title(), 'title']], 30)
            ->row([['Safer Cruise · '.ReportFilters::label($filters->from, $filters->to)
                .($comparison ? ' · compared with '.ReportFilters::label(...$comparison) : '').' · amounts in USD', 'subtitle']])
            ->row([['Filters: '.(implode(' · ', $filters->describe()) ?: 'none'), 'subtitle']])
            ->row([['Exported '.now()->format('j M Y, H:i').' by '.$filters->viewer->name, 'subtitle']])
            ->row();
    }

    /** @return array<string, class-string<Report>> */
    public static function all(): array
    {
        $reports = [
            SalesSummaryReport::class,
            TeamChannelsReport::class,
        ];

        return collect($reports)->mapWithKeys(fn ($r) => [$r::key() => $r])->all();
    }

    /** @return class-string<Report>|null */
    public static function find(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }
}
