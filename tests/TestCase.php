<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Shop-floor, delivery and reporting tests build orders without recording payments, so the
     * payment / credit gates are off by default here. PaymentGateEnforcementTest turns them on.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.enforce_payment_gates' => false]);
    }
}
