<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Niladam\LivewireBan\Warden;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Wraps the handler because everything lighter is bypassed: the routing pipeline
 * renders where the exception is thrown, and renderable()/reportable() run after
 * the exception's own render()/report(), which the triggers define.
 */
final readonly class DetectingExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private ExceptionHandler $handler,
        private Warden $warden,
    ) {}

    public function render($request, Throwable $e): Response
    {
        try {
            if ($this->warden->triggers($e, $request)) {
                $this->warden->strike($request, $e);
            }
        } catch (Throwable $failure) {
            // A strike that cannot be recorded — cache or database unreachable,
            // a listener throwing — must not cost the visitor the response the
            // application owes for the exception already unwinding.
            $this->reportQuietly($failure);
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

    private function reportQuietly(Throwable $e): void
    {
        try {
            $this->handler->report($e);
        } catch (Throwable) {
            // Nothing further is available from inside a handler.
        }
    }

    /**
     * Applications call what the contract does not carry, and a fluent method
     * hands back the handler it was called on rather than the wrapper.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->handler->{$method}(...$arguments);

        return $result === $this->handler ? $this : $result;
    }
}
