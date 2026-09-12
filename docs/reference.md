# Reference

## API

```php
use Niladam\LivewireBan\Facades\LivewireBan;

LivewireBan::banned($ip);                  // bool
LivewireBan::strike($request, $e);         // ?Ban — a strike, bans at the threshold
LivewireBan::query();                      // Builder on the configured model

LivewireBan::ban($request, $e);            // Ban — from a caught exception
LivewireBan::banIp($ip, $reason, $for);    // Ban — by hand
LivewireBan::banIpForever($ip, $reason);   // Ban — never expires

LivewireBan::unban($ban, $by);             // undo one
LivewireBan::unbanIp($ip);                 // undo every ban on an address
```

`strike()` always counts. Deciding *whether* an exception counts is the middleware's job, through `triggers` and `strikeUsing()`.

## The model

```php
$ban->isManual();              // no exception behind it — somebody banned it by hand
$ban->isPermanent();           // never expires
$ban->targetedProperty();      // the property the caller went after, if any
$ban->components();            // the Livewire components the request carried
$ban->url();                   // and the rest of the request context
$ban->userAgent();
$ban->cfRay();
$ban->connectingIp();
$ban->mismatchesCloudflareIp();

Ban::query()->active();        // not expired, not undone
```

## What a ban row records

Enough to tell an attack from a bug at a glance, and nothing that belongs to the user:

```json
{
  "request": {
    "url": "https://example.test/livewire/update",
    "user_agent": "python-requests/2.31.0",
    "cf_ray": "a39bd3782bdcee40-WAW",
    "cf_connecting_ip": null
  },
  "livewire": {
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
}
```

Two different things live here, for two different reasons:

- **What the caller sent** — `updates` and `calls` — is the evidence, kept verbatim.
- **What the component held** — the snapshot's data — is the legitimate user's own state: a half-typed message, an email address, whatever the screen was holding. That proves nothing, so only the property **names** survive in `properties`, never the values.

Also worth knowing:

- **`target`** is the property the caller went after, read by convention off the exception's public `$property` — so your own trigger classes get it for free.
- **`path`** is the page the attacker lifted the snapshot from.
- When a request carries several components, the `component` column names the one that carried the targeted update, not whichever happened to be first.

## Settings

Every setting is a typed property on `Niladam\LivewireBan\Settings`, sitting next to the config key it reads:

```php
#[Config('livewire-ban.strikes', 3)]
public int $strikes,
```

The container fills them in, so `$warden->settings->strikes` is type-checked and jumps straight to the key.

They are read once, when the container builds the `Warden`. Config files load long before any request, so this only matters if you mutate config at runtime — set it before the first call, or use a hook.

## Published assets

| Tag | What it publishes |
|---|---|
| `livewire-ban-migrations` | The migration, stamped with the current time. `livewire-ban:install` does this for you. |
| `livewire-ban-config` | `config/livewire-ban.php` |
| `livewire-ban-views` | The alert mail and the unban page |
| `livewire-ban-translations` | `lang/vendor/livewire-ban` |

The migration is published rather than loaded from the package, because your application owns its own schema — the table name is configurable, and `unbanned_by` has to hold whatever key type your users table uses.
