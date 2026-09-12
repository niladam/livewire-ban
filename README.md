# Livewire Ban

[![Latest Version on Packagist](https://img.shields.io/packagist/v/niladam/livewire-ban.svg?style=flat-square)](https://packagist.org/packages/niladam/livewire-ban)
[![Tests](https://img.shields.io/github/actions/workflow/status/niladam/livewire-ban/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/niladam/livewire-ban/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/niladam/livewire-ban.svg?style=flat-square)](https://packagist.org/packages/niladam/livewire-ban)

Automatically ban IP addresses that trigger Livewire exceptions a real browser never produces.

Built for **Laravel 13** and **Livewire 4**.

A bot that scrapes a component snapshot off your site and starts tampering with it throws exceptions no legitimate session throws. This catches them, counts strikes, bans the address, emails you, and gives you a signed link to undo it from your phone.

---

## Quick start

```bash
composer require niladam/livewire-ban
php artisan livewire-ban:install
```

That publishes the migration — your application owns its own schema — and prints what is already in force. Run your migrations and you are done:

| | |
|---|---|
| **Detection** | Middleware registered at the front of the global stack |
| **Threshold** | 3 strikes in 10 minutes, then a 1 hour ban |
| **Escalation** | 1h → 6h → 24h → 7d for repeat offenders inside a day |
| **Alerts** | One email per ban to your `mail.from` address, capped at 10/hour |
| **Undo** | A signed link in every alert, good for 7 days |

No config file is created; every setting has a working default. Point the alerts somewhere real:

```env
LIVEWIRE_BAN_ALERT_EMAIL=security@yourapp.com
LIVEWIRE_BAN_ALLOWLIST=203.0.113.5,10.0.0.0/8
```

### Using Filament?

```php
use Niladam\LivewireBan\Filament\LivewireBanPlugin;

$panel->plugin(LivewireBanPlugin::make());
```

A read-only resource: browse bans, inspect what the attacker sent, unban one. Creating and editing are closed off — bans are written by the detector.

---

## What it catches

Two exceptions, both of which require an attacker to have taken a real snapshot from your live site and altered it:

| Exception | What it means |
|---|---|
| `CorruptComponentPayloadException` | The snapshot's checksum does not match. Someone edited it in flight. |
| `CannotUpdateLockedPropertyException` | A `#[Locked]` property was targeted by an update. |

Add your own — `triggers` is matched with `instanceof`, so a base class covers its subclasses:

```php
'triggers' => [
    CorruptComponentPayloadException::class,
    CannotUpdateLockedPropertyException::class,
    App\Exceptions\SignatureTampered::class,
],
```

Anything that propagates out of your middleware stack works. Exceptions caught inside a controller never reach it. Take care with framework exceptions a real user can cause — `ValidationException` would ban your own users.

> **Why `ComponentNotFoundException` is not on this list** — see [Notes](#notes). Short version: on Livewire 4 it can no longer reach you, and if it ever fires it punishes the wrong person.

---

## Tuning

Publish the config only when you need to change something:

```bash
php artisan vendor:publish --tag=livewire-ban-config
```

Laravel merges a published config over the defaults at the **top level only**, so keep the file whole and edit it in place. A partial file silently drops the siblings of any nested key it names.

### The settings you are most likely to touch

```php
use Niladam\LivewireBan\Enums\BlockScope;

use function Illuminate\Support\{days, hours, minutes, weeks};

'strikes'    => 3,                 // 1 bans on sight
'window'     => minutes(10),
'escalation' => [hours(1), hours(6), days(1), weeks(1)],

'block'     => BlockScope::Site,      // or BlockScope::Livewire
'allowlist' => ['10.0.0.0/8'],        // office, monitoring, load tests
'enabled'   => true,                  // kill switch

'prune_after' => months(6),           // null keeps everything
```

**Exempting your own team** — so a bad deploy can never lock you out of your own admin:

```php
'exempt_roles'     => ['admin'],
'exempt_roles_via' => 'hasAnyRole',   // called as $user->hasAnyRole($roles)
```

The default matches spatie/laravel-permission. Name your own method if you use a different convention — nothing about the user model is assumed, and a model without that method simply fails the check. For anything a role list cannot express, use `exemptUsing()`.

**Pruning** — bans outlive the block itself, as an audit trail. `prune_after` feeds Laravel's `model:prune`, so schedule it:

```php
Schedule::command('model:prune', ['--model' => [Niladam\LivewireBan\Models\Ban::class]])->daily();
```

**Durations** use Laravel's helpers, so they read as what they are. They are `CarbonInterval`s and survive `config:cache`. A bare number is read as hours.

**Permanent bans** — a `null` rung never expires:

```php
'escalation' => [hours(1), hours(6), days(1), null],   // fourth offence is permanent
'escalation' => [null],                                // never forgive anything
```

**Scope** — `BlockScope::Site` refuses every request. `BlockScope::Livewire` refuses only the Livewire endpoints, leaving the rest of your application reachable, which matters when one shared or carrier-NAT address stands in for many people.

**Authorization** — Laravel cannot discover a policy for a model inside a package, so hand it over and it gets registered for you:

```php
'policy' => App\Policies\LivewireBanPolicy::class,
```

---

## Extending

### Hooks

Closures cannot survive `config:cache`, so register these from a service provider's `boot()`:

```php
use Niladam\LivewireBan\Facades\LivewireBan;

LivewireBan::resolveIpUsing(fn (Request $r) => $r->ip());

LivewireBan::exemptUsing(fn (Request $r) => $r->user()?->isStaff() ?? false);

// Return null to defer to the trigger list.
LivewireBan::strikeUsing(fn (Throwable $e, ?Request $r) => $e instanceof MyException ?: null);

// Replace what banning means — firewall, account lock, anything.
LivewireBan::banUsing(function (Request $r, Throwable $e, int $strikes, Warden $warden) {
    Cloudflare::block($r->ip());

    return $warden->ban($r, $e, $strikes);   // still want the row and the email
});
```

### Events

For adding behaviour rather than replacing it:

```php
Niladam\LivewireBan\Events\Struck    // every strike: $ip, $strikes, $url, $exception
Niladam\LivewireBan\Events\Banned    // the ban itself: $ban
```

### Your own model

```php
LivewireBan::useModel(App\Models\LivewireBan::class);   // must extend Niladam\LivewireBan\Models\Ban
```

Or set `'model'` in the config. The Filament resource and the policy both follow it.

---

## Reference

### API

```php
LivewireBan::banned($ip);                          // bool
LivewireBan::strike($request, $e);                 // ?Ban — a strike, bans at the threshold
LivewireBan::query();                              // Builder on the configured model

// Ban by hand — no request, no exception needed
LivewireBan::banIp($ip, 'Scraping');                           // follows the escalation ladder
LivewireBan::banIp($ip, 'Scraping', for: days(30));            // explicit duration
LivewireBan::banIpForever($ip, 'Known bad actor');            // never expires

LivewireBan::unban($ban, $by);                     // undo one
LivewireBan::unbanIp($ip);                         // undo every ban on an address
```

A manual ban is recorded the same way as a detected one — it alerts, fires `Banned`, and shows in the panel — but carries no `exception_class`. `$ban->isManual()` tells the two apart.

`strike()` always counts. Deciding *whether* an exception counts is the middleware's job, through `triggers` and `strikeUsing()`.

### What a ban row captures

Enough to tell an attack from a bug at a glance, and nothing that belongs to the user:

```json
{
  "target": "error",
  "components": [
    {
      "name": "support-chat",
      "id": "aBc12345",
      "path": "contact",
      "properties": ["messages", "prompt", "error"],
      "updates": {"error": []},
      "calls": []
    }
  ]
}
```

- **`target`** — the property the caller went after. Read by convention off the exception's public `$property`, so your own trigger classes get it for free.
- **`path`** — which page the attacker lifted the snapshot from.
- **`properties`** — the snapshot's keys only. **Values are dropped**; that is the real user's state and has no place in an audit row. `updates` and `calls` are kept in full — that is what the caller sent.
- When a request carries several components, the `component` column names the one that carried the targeted update, not whichever was first.

### Settings

Every setting is a typed property on `Niladam\LivewireBan\Settings`, sitting next to the config key it reads:

```php
#[Config('livewire-ban.strikes', 3)]
public int $strikes,
```

The container fills them in, so `$warden->settings->strikes` is type-checked and jumps straight to the key. They are read once, when the container builds the Warden — config files load long before any request, so this only matters if you mutate config at runtime.

---

## Notes

**Why `ComponentNotFoundException` is excluded.** It was the obvious candidate, and on Livewire 4 it no longer works. `SupportReleaseTokens` verifies the release token *before* the checksum and rewrites a missing component into a `LivewireReleaseTokenMismatchException`, so it never escapes the update endpoint. When it does fire it comes from a broken `@livewire()` include in a Blade view — which would strike the innocent visitor who loaded that page. `LivewireReleaseTokenMismatchException` is excluded for the mirror-image reason: every stale browser tab throws it after a deploy.

**Why the ban key is `$request->ip()`.** That is the connecting address and cannot be forged. A `CF-Connecting-IP` header can be, by anyone who finds your origin — they could evade their own ban, or get an innocent address banned. The alert flags a mismatch between the two, because that itself means someone reached your origin off-Cloudflare. Override with `resolveIpUsing()` if your setup genuinely needs a header.

**Why detection lives in middleware.** Applications commonly throttle exception reporting per class and message. A bot spraying the same component would be throttled out of the reporter and never earn a strike.

---

## Testing

```bash
composer test
```

## License

MIT.
