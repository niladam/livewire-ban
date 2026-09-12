<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

class AllowNothingPolicy
{
    public function viewAny(mixed $user): bool
    {
        return false;
    }
}
