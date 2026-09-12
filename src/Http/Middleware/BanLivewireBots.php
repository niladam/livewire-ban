<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Niladam\LivewireBan\Warden;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforcement only. Detection cannot live here: Illuminate\Routing\Pipeline
 * turns an exception into a response where it is thrown, so nothing ever
 * propagates out to a catch in outer middleware. See DetectingExceptionHandler.
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

        return $next($request);
    }
}
