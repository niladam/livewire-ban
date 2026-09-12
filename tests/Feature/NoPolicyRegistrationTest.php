<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Niladam\LivewireBan\Models\Ban;

it('registers nothing when no policy is configured', function () {
    expect(Gate::getPolicyFor(Ban::class))->toBeNull();
});
