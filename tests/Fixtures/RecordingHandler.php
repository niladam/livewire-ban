<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Tests\Fixtures;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Another package's decorator, shaped the way ours is, so a test can tell
 * whether two of them can sit on the same handler.
 */
final class RecordingHandler implements ExceptionHandler
{
    /** @var list<class-string<Throwable>> */
    public static array $rendered = [];

    public function __construct(private readonly ExceptionHandler $handler) {}

    public static function flush(): void
    {
        self::$rendered = [];
    }

    public function render($request, Throwable $e): Response
    {
        self::$rendered[] = $e::class;

        return $this->handler->render($request, $e);
    }

    public function report(Throwable $e): void
    {
        $this->handler->report($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return $this->handler->shouldReport($e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->handler->renderForConsole($output, $e);
    }
}
