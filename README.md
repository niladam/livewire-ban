# Livewire Ban

[![Latest Version on Packagist](https://img.shields.io/packagist/v/niladam/livewire-ban.svg?style=flat-square)](https://packagist.org/packages/niladam/livewire-ban)
[![Tests](https://img.shields.io/github/actions/workflow/status/niladam/livewire-ban/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/niladam/livewire-ban/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/niladam/livewire-ban.svg?style=flat-square)](https://packagist.org/packages/niladam/livewire-ban)

Automatically ban IP addresses that trigger Livewire exceptions a real browser never produces.

A bot that scrapes a component snapshot off your site and starts tampering with it throws exceptions no legitimate session throws. This catches them, counts strikes, bans the address, emails you, and gives you a signed link to undo it from your phone.

## Install

```bash
composer require niladam/livewire-ban
```

Publish the migration and see what is already in force:

```bash
php artisan livewire-ban:install
```

Create the table:

```bash
php artisan migrate
```

That is the whole setup. The middleware registers itself, and every setting has a working default:

| Behaviour | Out of the box |
|---|---|
| **Threshold** | 3 strikes in 10 minutes, then a 1 hour ban |
| **Escalation** | 1h → 6h → 24h → 7d for repeat offenders inside a day |
| **Alerts** | One queued email per ban, capped at 10/hour |
| **Undo** | A signed link in every alert, good for 7 days |

Point the alerts somewhere real and you are done:

```env
LIVEWIRE_BAN_ALERT_EMAIL=security@yourapp.com
LIVEWIRE_BAN_ALLOWLIST=203.0.113.5,10.0.0.0/8
```

Using Filament? One line gets you a read-only panel for reviewing and undoing bans:

```php
$panel->plugin(\Niladam\LivewireBan\Filament\LivewireBanPlugin::make());
```

## What it catches

Two exceptions, both of which require an attacker to have taken a real snapshot from your live site and altered it:

| Exception | What it means |
|---|---|
| `CorruptComponentPayloadException` | The snapshot's checksum does not match. Someone edited it in flight. |
| `CannotUpdateLockedPropertyException` | A `#[Locked]` property was targeted by an update. |

Both are configurable, and your own exceptions can join them.

## Documentation

| Page | Covers |
|---|---|
| [Configuration](docs/configuration.md) | Every setting: strikes, escalation, permanent bans, scope, exemptions, pruning |
| [Extending](docs/extending.md) | Hooks, events, your own model, banning by hand |
| [Reference](docs/reference.md) | The API, and what a ban row records |
| [Design notes](docs/design-notes.md) | Why these triggers, why this IP, why middleware |

## Requirements

Laravel 13, Livewire 4, PHP 8.3 or 8.4.

## Testing

```bash
composer test
```

## License

MIT. See [LICENSE.md](LICENSE.md).
