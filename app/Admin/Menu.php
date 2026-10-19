<?php

namespace App\Admin;

use App\Models\Booking;

/**
 * The admin sidebar (admin.partials.nav), laid out like the safeer dashboard: single links with an icon,
 * and collapsible groups whose links have a bullet. The group holding the current page starts open.
 * Admins get the whole panel; sellers their dashboard and the bookings assigned to them.
 *
 * Node: ['label', 'icon', 'href', 'active', 'badge'?] or ['label', 'icon', 'children' => [...], 'active'].
 */
final class Menu
{
    /** @return list<array> */
    public static function build(): array
    {
        $user = auth('admin')->user();
        $nodes = $user?->isAdmin() ? self::admin() : self::seller((int) $user?->id);

        return array_map(fn ($node) => isset($node['children'])
            ? $node + ['active' => collect($node['children'])->contains('active', true)]
            : $node, $nodes);
    }

    private static function seller(int $id): array
    {
        $awaiting = Booking::awaitingPayment()->where('seller_id', $id)->count();

        return [
            self::link('Dashboard', 'grid', 'admin.dashboard', ['admin.dashboard']),
            self::link('My bookings', 'ticket', 'admin.bookings.index', ['admin.bookings.*']) + ['badge' => $awaiting ?: null],
        ];
    }

    private static function admin(): array
    {
        $awaiting = Booking::awaitingPayment()->count();

        return [
            self::link('Dashboard', 'grid', 'admin.dashboard', ['admin.dashboard']),
            self::link('Bookings', 'ticket', 'admin.bookings.index', ['admin.bookings.*']) + ['badge' => $awaiting ?: null],

            self::group('Cruises', 'ship', [
                self::link('Cruises list', null, 'admin.cruises.index', ['admin.cruises.index', 'admin.cruises.edit', 'admin.cruises.sailings.*', 'admin.sailings.*']),
                self::link('Add cruise', null, 'admin.cruises.create', ['admin.cruises.create']),
                self::resource(Resources\PortResource::class),
                self::resource(Resources\CabinCategoryResource::class),
                self::resource(Resources\DestinationResource::class),
            ]),

            self::group('Ship', 'anchor', [
                self::resource(Resources\ShipResource::class),
                self::resource(Resources\ShipSpecResource::class),
                self::resource(Resources\ShipZoneResource::class),
                self::resource(Resources\FacilityResource::class),
                self::resource(Resources\ShipEssentialResource::class),
                self::resource(Resources\ShipFaqResource::class),
            ]),

            self::group('Website content', 'edit', [
                self::link('Homepage', null, 'admin.home-sections.index', ['admin.home-sections.*']),
                self::link('Cruise pages', null, 'admin.pages.index', ['admin.pages.*']),
                self::resource(Resources\TestimonialResource::class),
            ]),

            self::group('Pricing & offers', 'tag', [
                self::resource(Resources\PromoCodeResource::class),
                self::resource(Resources\AgeBandResource::class),
            ]),

            self::group('Customers', 'user', [
                self::link('Customers list', null, 'admin.customers.index', ['admin.customers.index', 'admin.customers.edit', 'admin.customers.show']),
                self::link('Add customer', null, 'admin.customers.create', ['admin.customers.create']),
            ]),

            self::group('Admins & staff', 'shield', [
                self::link('Staff list', null, 'admin.users.index', ['admin.users.index', 'admin.users.edit']),
                self::link('Add staff member', null, 'admin.users.create', ['admin.users.create']),
            ]),

            self::resource(Resources\NewsletterSubscriberResource::class, 'mail'),
        ];
    }

    private static function link(string $label, ?string $icon, string $route, array $active): array
    {
        return ['label' => $label, 'icon' => $icon, 'href' => route($route), 'active' => request()->routeIs(...$active)];
    }

    /** @param  class-string<resource>  $resource */
    private static function resource(string $resource, ?string $icon = null): array
    {
        return self::link($resource::$label, $icon, $resource::routeName('index'), ['admin.'.$resource::$slug.'.*']);
    }

    private static function group(string $label, string $icon, array $children): array
    {
        return ['label' => $label, 'icon' => $icon, 'children' => $children];
    }
}
