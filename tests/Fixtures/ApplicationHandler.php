<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Illuminate\Foundation\Exceptions\Handler;
use Throwable;

class ApplicationHandler extends Handler
{
    /** @var list<class-string<Throwable>> */
    public static array $seen = [];

    public static function flush(): void
    {
        self::$seen = [];
    }

    public function report(Throwable $e)
    {
        self::$seen[] = $e::class;

        parent::report($e);
    }
}
