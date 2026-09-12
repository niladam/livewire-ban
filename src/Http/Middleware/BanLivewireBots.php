<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Niladam\LivewireBan\Warden;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Detection lives here, not in the exception reporter: applications commonly
 * throttle reporting per exception class and message, which would throttle a
 * spraying bot out of the reporter before it ever earned a strike.
 */
class BanLivewireBots
{
    public function __construct(private readonly Warden $warden) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $this->warden->guards($request) && $this->warden->banned($this->warden->ip($request)),
            403,
        );

        try {
            return $next($request);
        } catch (Throwable $e) {
            if ($this->warden->triggers($e, $request)) {
                $this->warden->strike($request, $e);
            }

            throw $e;
        }
    }
}
