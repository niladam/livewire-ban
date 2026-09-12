<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class Struck
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $ip,
        public readonly int $strikes,
        public readonly string $url,
        public readonly Throwable $exception,
    ) {}
}
