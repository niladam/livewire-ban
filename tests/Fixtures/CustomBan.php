<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Niladam\LivewireBan\Models\Ban;

class CustomBan extends Ban
{
    public function shout(): string
    {
        return strtoupper($this->ip);
    }
}
