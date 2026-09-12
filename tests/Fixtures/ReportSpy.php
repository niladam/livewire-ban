<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Throwable;

final class ReportSpy
{
    /** @var list<Throwable> */
    public static array $reported = [];

    public static function flush(): void
    {
        self::$reported = [];
    }

    public static function record(Throwable $e): void
    {
        self::$reported[] = $e;
    }

    /** @return list<class-string<Throwable>> */
    public static function classes(): array
    {
        return array_map(static fn (Throwable $e): string => $e::class, self::$reported);
    }
}
