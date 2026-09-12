<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Tests\TestCase;

class NoPolicyRegistrationTest extends TestCase
{
    public function test_nothing_is_registered_when_no_policy_is_configured(): void
    {
        $this->assertNull(Gate::getPolicyFor(Ban::class));
    }
}
