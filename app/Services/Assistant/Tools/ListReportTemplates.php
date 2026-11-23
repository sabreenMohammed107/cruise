<?php

namespace App\Services\Assistant\Tools;

use App\Models\AssistantReport;
use App\Models\User;

/** The report templates the signed-in user saved ("save as template" under a report). */
class ListReportTemplates extends Tool
{
    public function name(): string
    {
        return 'list_report_templates';
    }

    public function description(): string
    {
        return 'The report templates the signed-in user saved, with what each contains. Run one with run_report {template}.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function needsUser(): bool
    {
        return true;
    }

    public function handle(array $input, ?User $user): array
    {
        if (! $user) {
            return ['error' => 'Not signed in.'];
        }

        return [
            'templates' => AssistantReport::templates()->where('user_id', $user->id)->latest()->limit(20)->get()
                ->map(fn (AssistantReport $t) => [
                    'name' => $t->template_name,
                    'dataset' => $t->spec['dataset'] ?? null,
                    'columns' => $t->spec['columns'] ?? [],
                    'filters' => $t->spec['filters'] ?? [],
                    'period_when_saved' => array_filter(['from' => $t->spec['from'] ?? null, 'to' => $t->spec['to'] ?? null]),
                ])->all(),
        ];
    }
}
