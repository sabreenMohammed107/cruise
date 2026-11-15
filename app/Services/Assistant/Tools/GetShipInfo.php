<?php

namespace App\Services\Assistant\Tools;

use App\Models\Ship;
use App\Models\User;

/** The ship page: facts, facilities, life on board, good to know and the FAQs. */
class GetShipInfo extends Tool
{
    private const TOPICS = ['overview', 'specs', 'facilities', 'life_on_board', 'good_to_know', 'faqs'];

    public function name(): string
    {
        return 'get_ship_info';
    }

    public function description(): string
    {
        return 'Information about the ship: overview, specs (size, decks, guests…), facilities (pools, restaurants…), '
            .'life on board, good to know (dress code, currency on board, Wi-Fi…) and FAQs. Ask for the topics you need.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topics' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => self::TOPICS],
                    'description' => 'Leave empty for everything.',
                ],
            ],
        ];
    }

    public function handle(array $input, ?User $user): array
    {
        $topics = array_values(array_intersect(self::TOPICS, (array) ($input['topics'] ?? [])));
        $topics = $topics === [] ? self::TOPICS : $topics;
        $want = fn (string $t) => in_array($t, $topics, true);

        $ships = Ship::with(['specs', 'facilities', 'zones', 'essentials', 'faqs'])->get()->map(fn (Ship $s) => array_filter([
            'name' => $s->tr('name'),
            'url' => route('ship'),
            'overview' => $want('overview') ? [
                'tagline' => $s->tr('tagline'),
                'flag' => $s->tr('flag'),
                'home_port' => $s->tr('home_port'),
                'description' => $s->tr('description'),
            ] : null,
            'specs' => $want('specs') ? $s->specs->map(fn ($x) => trim($x->tr('label').': '.$x->tr('value').' '.$x->tr('unit')))->all() : null,
            'facilities' => $want('facilities') ? $s->facilities->map(fn ($x) => ['name' => $x->tr('label'), 'value' => $x->tr('value'), 'about' => $x->tr('description')])->all() : null,
            'life_on_board' => $want('life_on_board') ? $s->zones->map(fn ($x) => ['title' => $x->tr('title'), 'text' => $x->tr('text')])->all() : null,
            'good_to_know' => $want('good_to_know') ? $s->essentials->map(fn ($x) => ['title' => $x->tr('title'), 'text' => $x->tr('text')])->all() : null,
            'faqs' => $want('faqs') ? $s->faqs->map(fn ($x) => ['q' => $x->tr('question'), 'a' => $x->tr('answer')])->all() : null,
        ], fn ($v) => $v !== null))->all();

        return ['ships' => $ships];
    }
}
