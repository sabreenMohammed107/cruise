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

    /** The period shown without one in the URL (a ReportFilters::PRESETS key). */
    public static function defaultPreset(): string
    {
        return 'month';
    }

    /** The filters besides the period this report uses (ReportFilters::FILTERS); the form shows only these. */
    public static function filters(): array
    {
        return ReportFilters::FILTERS;
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

    /**
     * A "Key figures" block: one row per figure with this period, the comparison and the change. Each row is
     * [label, key, style]: an Xlsx style, or "pct" for a figure already in % (shown as a percentage, no change).
     */
    protected static function keyFigures(Xlsx $xlsx, array $current, ?array $previous, array $rows): Xlsx
    {
        $xlsx->row([['Key figures', 'section'], ['', 'section'], ['', 'section'], ['', 'section']])
            ->row([['', 'header'], ['This period', 'header'], [$previous ? 'Comparison' : '', 'header'], [$previous ? 'Change' : '', 'header']], 22);
        foreach ($rows as [$label, $key, $style]) {
            $cell = fn ($v) => match (true) {
                $v === null => ['—', 'muted'],
                $style === 'pct' => [(float) $v / 100, 'percent'],
                $style === 'int' => [(int) $v, 'int'],
                default => [(float) $v, $style],
            };
            $change = null;
            if ($previous && $style !== 'pct' && $current[$key] !== null) {
                $d = SalesSummaryReport::change((float) $current[$key], $previous[$key] === null ? null : (float) $previous[$key]);
                $change = $d === null ? ['—', 'muted'] : [$d / 100, 'percent'];
            }
            $xlsx->row([$label, $cell($current[$key]), $previous ? $cell($previous[$key]) : null, $change]);
        }

        return $xlsx->row();
    }

    /** A titled table on a summary sheet: a section row, a header row, the rows (or "None"), then a blank row. */
    protected static function table(Xlsx $xlsx, string $title, array $headers, iterable $rows): Xlsx
    {
        $xlsx->row(array_map(fn ($i) => [$i === 0 ? $title : '', 'section'], array_keys($headers)))
            ->row(array_map(fn ($h) => [$h, 'header'], $headers), 22);
        $any = false;
        foreach ($rows as $row) {
            $xlsx->row($row);
            $any = true;
        }
        if (! $any) {
            $xlsx->row([['None in this period.', 'subtitle']]);
        }

        return $xlsx->row();
    }

    /** @return array<string, class-string<Report>> */
    public static function all(): array
    {
        $reports = [
            SalesSummaryReport::class,
            TeamChannelsReport::class,
            CustomersReport::class,
            CancellationsReport::class,
            SeasonalityReport::class,
            InterestReport::class,
        ];

        return collect($reports)->mapWithKeys(fn ($r) => [$r::key() => $r])->all();
    }

    /** @return class-string<Report>|null */
    public static function find(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }
}
