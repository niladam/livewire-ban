<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Niladam\LivewireBan\Models\Ban;

class Banned
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Ban $ban) {}
}
