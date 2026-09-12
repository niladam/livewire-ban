<?php

declare(strict_types=1);

namespace Niladam\LivewireBan;

use Carbon\CarbonInterface;
use Closure;
use DateInterval;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Niladam\LivewireBan\Enums\BlockScope;
use Niladam\LivewireBan\Events\Banned;
use Niladam\LivewireBan\Events\Struck;
use Niladam\LivewireBan\Mail\IpBanned;
use Niladam\LivewireBan\Models\Ban;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

use function Illuminate\Support\hours;

class Warden
{
    private const string BAN = 'livewire-ban:';

    private const string STRIKES = 'livewire-ban-strikes:';

    private const string ALERTS = 'livewire-ban-alerts';

    /** Closures cannot survive config:cache, so hooks live here, not in config. */
    public static ?Closure $resolveIpUsing = null;

    public static ?Closure $exemptUsing = null;

    public static ?Closure $strikeUsing = null;

    public static ?Closure $banUsing = null;

    /** @var class-string<Ban>|null */
    public static ?string $model = null;

    public function __construct(public readonly Settings $settings) {}

    public static function flushHooks(): void
    {
        static::$resolveIpUsing = null;
        static::$exemptUsing = null;
        static::$strikeUsing = null;
        static::$banUsing = null;
        static::$model = null;
    }

    /** @return class-string<Ban> */
    public function model(): string
    {
        $model = static::$model ?? $this->settings->model;

        if ($model !== Ban::class && ! is_subclass_of($model, Ban::class)) {
            throw new InvalidArgumentException("[{$model}] must extend ".Ban::class.'.');
        }

        return $model;
    }

    /** @return Builder<Ban> */
    public function query(): Builder
    {
        return $this->model()::query();
    }

    public function ip(Request $request): string
    {
        if (static::$resolveIpUsing) {
            return (string) (static::$resolveIpUsing)($request);
        }

        return (string) $request->ip();
    }

    public function banned(string $ip): bool
    {
        return $this->settings->enabled && $this->cache()->has(self::BAN.$ip);
    }

    /** A strikeUsing hook returning null defers to the configured list. */
    public function triggers(Throwable $e, ?Request $request = null): bool
    {
        if (static::$strikeUsing) {
            $decision = (static::$strikeUsing)($e, $request);

            if (! is_null($decision)) {
                return (bool) $decision;
            }
        }

        foreach ($this->settings->triggers as $trigger) {
            if ($e instanceof $trigger) {
                return true;
            }
        }

        return false;
    }

    public function guards(Request $request): bool
    {
        return $this->settings->block->covers($request);
    }

    public function scope(): BlockScope
    {
        return $this->settings->block;
    }

    public function strike(Request $request, Throwable $e): ?Ban
    {
        $ip = $this->ip($request);

        if (! $this->settings->enabled || $this->exempt($request, $ip)) {
            return null;
        }

        $key = self::STRIKES.$ip;

        $this->cache()->add($key, 0, $this->settings->window);

        $strikes = (int) $this->cache()->increment($key);

        Event::dispatch(new Struck($ip, $strikes, $request->fullUrl(), $e));

        if ($strikes < $this->settings->strikes) {
            return null;
        }

        $this->cache()->forget($key);

        if (static::$banUsing) {
            return (static::$banUsing)($request, $e, $strikes, $this);
        }

        return $this->ban($request, $e, $strikes);
    }

    /** Public so a banUsing hook can delegate back to it. */
    public function ban(Request $request, Throwable $e, int $strikes = 1): Ban
    {
        $context = $this->context($request, $e);

        return $this->record($this->ip($request), [
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
            'component' => $this->blame($context),
            'cf_country' => $request->header('CF-IPCountry'),
            'context' => $context,
            'strikes' => $strikes,
        ]);
    }

    /**
     * Ban an address by hand, with no request and no exception behind it. The
     * duration falls back to the escalation ladder.
     */
    public function banIp(string $ip, ?string $reason = null, ?DateInterval $for = null): Ban
    {
        return $this->record($ip, ['exception_message' => $reason, 'strikes' => 0], $for);
    }

    public function banIpForever(string $ip, ?string $reason = null): Ban
    {
        return $this->record($ip, ['exception_message' => $reason, 'strikes' => 0], permanent: true);
    }

    public function unban(Ban $ban, mixed $by = null): void
    {
        $ban->forceFill([
            'unbanned_at' => now(),
            'unbanned_by' => is_object($by) && method_exists($by, 'getKey') ? $by->getKey() : $by,
        ])->save();

        $this->cache()->forget(self::BAN.$ban->ip);
        $this->cache()->forget(self::STRIKES.$ban->ip);
    }

    /** Undo every ban standing against an address. */
    public function unbanIp(string $ip, mixed $by = null): void
    {
        $this->query()->active()->where('ip', $ip)->each(fn (Ban $ban) => $this->unban($ban, $by));

        $this->cache()->forget(self::BAN.$ip);
        $this->cache()->forget(self::STRIKES.$ip);
    }

    /** @param  array<string, mixed>  $attributes */
    private function record(string $ip, array $attributes, ?DateInterval $for = null, bool $permanent = false): Ban
    {
        $offence = $this->recentOffences($ip) + 1;

        $expiresAt = match (true) {
            $permanent => null,
            $for instanceof DateInterval => now()->add($for),
            default => $this->expiryFor($offence),
        };

        $ban = $this->model()::create([
            ...$attributes,
            'ip' => $ip,
            'offence' => $offence,
            'banned_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $ban->expires_at
            ? $this->cache()->put(self::BAN.$ip, true, $ban->expires_at)
            : $this->cache()->forever(self::BAN.$ip, true);

        $this->alert($ban);

        Event::dispatch(new Banned($ban));

        return $ban;
    }

    /** Rate limited: a distributed spray must not bury the inbox warning you about it. */
    private function alert(Ban $ban): void
    {
        if (! $this->settings->alertsEnabled) {
            return;
        }

        $recipient = $this->settings->alertsTo ?? Config::string('mail.from.address', '');

        if (blank($recipient)) {
            return;
        }

        if (RateLimiter::tooManyAttempts(self::ALERTS, $this->settings->alertsPerHour)) {
            return;
        }

        RateLimiter::hit(self::ALERTS, (int) hours(1)->totalSeconds);

        Mail::to($recipient)->queue(new IpBanned($ban));
    }

    private function exempt(Request $request, string $ip): bool
    {
        if (IpUtils::checkIp($ip, $this->settings->allowlist)) {
            return true;
        }

        if ($this->hasExemptRole()) {
            return true;
        }

        $callback = static::$exemptUsing ?? $this->settings->exemptUsing;

        if (blank($callback)) {
            return false;
        }

        try {
            return (bool) app()->call($callback, ['request' => $request]);
        } catch (Throwable) {
            // This runs while an exception unwinds; a broken exemption must not mask it.
            return false;
        }
    }

    /**
     * The role check is a method name rather than a hard-coded call, so this
     * works with spatie/laravel-permission, with your own convention, or not
     * at all — nothing about the user model is assumed.
     */
    private function hasExemptRole(): bool
    {
        if ($this->settings->exemptRoles === []) {
            return false;
        }

        try {
            $user = Auth::user();
            $method = $this->settings->exemptRolesVia;

            if (is_null($user) || ! method_exists($user, $method)) {
                return false;
            }

            return (bool) $user->{$method}($this->settings->exemptRoles);
        } catch (Throwable) {
            // Runs while an exception unwinds; a broken check must not mask it.
            return false;
        }
    }

    private function recentOffences(string $ip): int
    {
        return $this->query()
            ->where('ip', $ip)
            ->where('banned_at', '>=', now()->subDay())
            ->count();
    }

    /** The final rung repeats once exhausted; a null rung never expires. */
    public function banDuration(int $offence): ?DateInterval
    {
        $ladder = $this->settings->escalation ?: [hours(1)];
        $rung = $ladder[min($offence, count($ladder)) - 1];

        return $rung instanceof DateInterval ? $rung : null;
    }

    private function expiryFor(int $offence): ?CarbonInterface
    {
        $rung = $this->banDuration($offence);

        return $rung instanceof DateInterval ? now()->add($rung) : null;
    }

    /**
     * Two different things go in here, for two different reasons.
     *
     * What the caller SENT — the updates and the method calls — is the evidence,
     * so it is kept verbatim. What the component HELD is the legitimate user's
     * own state: a half-typed message, an email address, whatever the screen
     * was holding. That proves nothing, so only the property names survive,
     * never the values.
     *
     * Decoding stays lenient because this runs while an exception unwinds.
     *
     * @return array<string, mixed>
     */
    private function context(Request $request, Throwable $e): array
    {
        return [
            'request' => [
                'url' => $request->fullUrl(),
                'user_agent' => $request->userAgent(),
                'cf_ray' => $request->header('CF-Ray'),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
            ],
            'livewire' => [
                'target' => $this->targetedProperty($e),
                'components' => $this->components($request),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function components(Request $request): array
    {
        $components = array_values((array) $request->input('components', []));

        return array_map(static function (mixed $component): array {
            $snapshot = Arr::get($component, 'snapshot');
            $snapshot = is_string($snapshot) ? (array) json_decode($snapshot, true) : [];

            return [
                'name' => Arr::get($snapshot, 'memo.name'),
                'id' => Arr::get($snapshot, 'memo.id'),
                'path' => Arr::get($snapshot, 'memo.path'),
                'properties' => array_keys((array) Arr::get($snapshot, 'data', [])),
                'updates' => Arr::get($component, 'updates'),
                'calls' => Arr::get($component, 'calls'),
            ];
        }, $components);
    }

    /** Read by convention, so an application's own trigger classes get it too. */
    private function targetedProperty(Throwable $e): ?string
    {
        return property_exists($e, 'property') && is_string($e->property)
            ? $e->property
            : null;
    }

    /**
     * Blame the component that carried the targeted update, not merely the first.
     *
     * @param  array<string, mixed>  $context
     */
    private function blame(array $context): ?string
    {
        $components = Arr::get($context, 'livewire.components', []);

        if ($components === []) {
            return null;
        }

        $target = Arr::get($context, 'livewire.target');

        if (filled($target)) {
            foreach ($components as $component) {
                if (array_key_exists($target, (array) ($component['updates'] ?? []))) {
                    return $component['name'];
                }
            }
        }

        return $components[0]['name'];
    }

    private function cache(): Repository
    {
        return Cache::store($this->settings->cacheStore);
    }
}
