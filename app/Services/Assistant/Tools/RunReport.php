<?php

namespace App\Services\Assistant\Tools;

use App\Models\AssistantReport;
use App\Models\User;
use App\Services\Assistant\Reports\ReportEngine;

/**
 * Builds a report of the signed-in user's own data (ReportEngine) and shows it in the chat as a table with Excel,
 * PDF and "save as template" buttons. Claude gets the count, totals and the first rows to sum it up.
 */
class RunReport extends Tool
{
    /** Rows shown in the chat; the downloads have them all. */
    public const CHAT_ROWS = 100;

    public function name(): string
    {
        return 'run_report';
    }

    public function description(): string
    {
        return 'Build a report of the signed-in user\'s own data and show it as a table with Excel / PDF / save buttons. '
            .'Datasets and their column keys (omit "columns" for sensible defaults):'
            .' bookings: reference, customer, cruise, sailing, departure, return, cabins, guests, status, payment_status, '
            .'original, commission (agents only), total, paid, due, booked_at.'
            .' payments: date, reference, customer, cruise, type, method, amount, receipt.'
            .' sailings (upcoming sailings with cabin prices; agents see their agent rate): cruise, sailing, departure, return, '
            .'nights, cabin, single, double, extra, per_person, available.'
            .' Dates are YYYY-MM-DD; turn "this month", "December", "last 3 months" into from/to using today\'s date. '
            .'Bookings period is on departure date unless date_field="booked". Filters: status (confirmed|cancelled|all, '
            .'default confirmed), payment (due = unpaid or partly paid | unpaid | partial | paid | refunded), cruise (name), '
            .'type (payment|refund), cabin, available_only. Or give "template" to run a saved template.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dataset' => ['type' => 'string', 'enum' => ReportEngine::DATASETS],
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'date_field' => ['type' => 'string', 'enum' => ['departure', 'booked', 'received']],
                'columns' => ['type' => 'array', 'items' => ['type' => 'string']],
                'filters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['confirmed', 'cancelled', 'all']],
                        'payment' => ['type' => 'string', 'enum' => ['due', 'unpaid', 'partial', 'paid', 'refunded']],
                        'type' => ['type' => 'string', 'enum' => ['payment', 'refund']],
                        'cruise' => ['type' => 'string'],
                        'cabin' => ['type' => 'string'],
                        'available_only' => ['type' => 'boolean'],
                    ],
                ],
                'sort' => [
                    'type' => 'object',
                    'properties' => [
                        'by' => ['type' => 'string', 'description' => 'A column key.'],
                        'dir' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                    ],
                ],
                'template' => ['type' => 'string', 'description' => 'Name of a saved template to run (list_report_templates).'],
            ],
        ];
    }

    public function needsUser(): bool
    {
        return true;
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'Reports need the visitor to sign in first: '.route('login')];
        }
        $engine = app(ReportEngine::class);

        if ($name = $this->str($input, 'template')) {
            $template = AssistantReport::templates()->where('user_id', $user->id)->where('template_name', $name)->first();
            if (! $template) {
                return ['error' => 'No saved template with this name.', 'templates' => AssistantReport::templates()->where('user_id', $user->id)->pluck('template_name')->all()];
            }
            // The template's columns, filters and sort; a new period if one was given
            $input = array_filter(['from' => $input['from'] ?? null, 'to' => $input['to'] ?? null]) + $template->spec;
        }

        $spec = $engine->normalize($input, $user);
        $result = $engine->run($spec, $user);
        $report = AssistantReport::create([
            'user_id' => $user->id,
            'assistant_conversation_id' => $this->conversation?->id,
            'title' => $engine->title($spec),
            'spec' => $spec,
        ]);

        $show = fn (array $row) => array_map(fn ($c) => ReportEngine::display($row[$c['key']] ?? null, $c['type']), $result['columns']);
        $totals = array_map(fn ($v) => ReportEngine::display($v, 'money'), $result['totals']);

        return [
            'report_id' => $report->id,
            'title' => $report->title,
            'rows' => $result['count'],
            'totals' => $totals,
            'columns' => array_column($result['columns'], 'label'),
            'first_rows' => array_map($show, array_slice($result['rows'], 0, 5)),
            'note' => 'The table and its Excel / PDF / save-as-template buttons are already shown to the visitor. '
                .'Reply with a one or two line summary (count and totals); do not repeat the table.',
            '_ui' => [
                'type' => 'report',
                'id' => $report->id,
                'title' => $report->title,
                'columns' => $result['columns'],
                'rows' => array_map($show, array_slice($result['rows'], 0, self::CHAT_ROWS)),
                'count' => $result['count'],
                'count_label' => trans_choice('assistant.report.rows', $result['count'], ['count' => $result['count']]),
                'note' => implode(' ', array_filter([
                    $result['count'] > self::CHAT_ROWS ? __('assistant.report.more', ['shown' => self::CHAT_ROWS, 'count' => $result['count']]) : null,
                    $result['truncated'] ? __('assistant.report.truncated', ['max' => ReportEngine::MAX_ROWS]) : null,
                ])),
                'totals' => array_map(fn ($c) => $totals[$c['key']] ?? '', $result['columns']),
                'excel_url' => route('assistant.reports.excel', $report),
                'print_url' => route('assistant.reports.print', $report),
                'template_url' => route('assistant.reports.template', $report),
            ],
        ];
    }
}
