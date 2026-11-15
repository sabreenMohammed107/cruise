<?php

namespace App\Services\Assistant\Tools;

use App\Models\SiteSetting;
use App\Models\User;

/**
 * The team's contact details for the visitor to reach them: WhatsApp link with their request filled in, phones, email
 * and the contact page. Only gives the details: nothing is sent to the team.
 */
class ContactTeam extends Tool
{
    public function name(): string
    {
        return 'contact_team';
    }

    public function description(): string
    {
        return 'The Safer Cruise team\'s contact details (WhatsApp link, phone, email, contact page) for the visitor to '
            .'reach them. It does not send anything to the team. Use it when the visitor asks for a person, wants to '
            .'change or cancel a booking, asks about payment, or when you cannot answer.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string', 'description' => 'One short sentence of what the visitor needs, in their language, prefilled in the WhatsApp message the visitor can send.'],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $summary = $this->str($input, 'summary') ?? __('site.footer.whatsapp_message');
        $lang = $this->isArabic() ? 'ar' : 'en';

        return [
            'whatsapp_url' => 'https://wa.me/'.SiteSetting::whatsapp().'?text='.rawurlencode($summary),
            'phones' => collect([1, 2])->map(fn ($n) => SiteSetting::value("phone_{$n}", $lang) ?: SiteSetting::value("phone_{$n}"))->filter()->values()->all(),
            'email' => SiteSetting::value('email') ?: config('cruise.team_email'),
            'contact_page' => route('contact'),
            'note' => 'Nothing was sent to the team: give these details so the visitor can contact them.',
        ];
    }
}
