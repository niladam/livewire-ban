<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Niladam\LivewireBan\Warden;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Wraps the application's handler because every lighter hook is bypassed: the
 * routing pipeline renders an exception where it is thrown so middleware never
 * sees it, and renderable()/reportable() both run after the exception's own
 * render()/report(), which these triggers define.
 */
final readonly class DetectingExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private ExceptionHandler $handler,
        private Warden $warden,
    ) {}

    public function render($request, Throwable $e): Response
    {
        if ($this->warden->triggers($e, $request)) {
            $this->warden->strike($request, $e);
        }

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

    /**
     * The concrete handler carries more than the contract — renderable(), map(),
     * dontReport() — and applications call those.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->handler->{$method}(...$arguments);
    }
}
