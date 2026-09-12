<?php

declare(strict_types=1);

namespace Niladam\LivewireBan\Facades;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Niladam\LivewireBan\Models\Ban;
use Niladam\LivewireBan\Warden;
use Throwable;

/**
 * @method static bool banned(string $ip)
 * @method static bool triggers(Throwable $e, ?Request $request = null)
 * @method static bool guards(Request $request)
 * @method static string ip(Request $request)
 * @method static Ban|null strike(Request $request, Throwable $e)
 * @method static Ban ban(Request $request, Throwable $e, int $strikes = 1)
 * @method static Ban banIp(string $ip, ?string $reason = null, ?\DateInterval $for = null)
 * @method static Ban banIpForever(string $ip, ?string $reason = null)
 * @method static void unban(Ban $ban, mixed $by = null)
 * @method static void unbanIp(string $ip, mixed $by = null)
 * @method static \DateInterval|null banDuration(int $offence)
 * @method static Builder<Ban> query()
 *
 * @see Warden
 */
class LivewireBan extends Facade
{
    /**
     * Defaults to $request->ip() — the connecting address, which cannot be
     * forged. Override only if your proxy setup genuinely needs a header.
     */
    public static function resolveIpUsing(Closure $callback): void
    {
        Warden::$resolveIpUsing = $callback;
    }

    /** Return true to let a request pass unstruck. */
    public static function exemptUsing(Closure $callback): void
    {
        Warden::$exemptUsing = $callback;
    }

    /** Return null to defer to the configured trigger list. */
    public static function strikeUsing(Closure $callback): void
    {
        Warden::$strikeUsing = $callback;
    }

    /** Replaces what happens at the threshold; the Warden is handed in to delegate back. */
    public static function banUsing(Closure $callback): void
    {
        Warden::$banUsing = $callback;
    }

    /** @param  class-string<Ban>  $model */
    public static function useModel(string $model): void
    {
        Warden::$model = $model;
    }

    /** @return class-string<Ban> */
    public static function model(): string
    {
        return static::getFacadeRoot()->model();
    }

    public static function flushHooks(): void
    {
        Warden::flushHooks();
    }

    protected static function getFacadeAccessor(): string
    {
        return Warden::class;
    }
}
