<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Totals in the tests (and the seeded demo bookings) are fares, offers and promo codes: no port & departure
        // taxes unless a test turns them on (PortTaxTest). Set here so it applies before the database is seeded.
        $this->app['config']->set('cruise.port_tax_per_guest', 0);
    }
}
