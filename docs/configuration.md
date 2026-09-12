# Configuration

Every setting has a working default, so publish the config only when you need to change one:

```bash
php artisan vendor:publish --tag=livewire-ban-config
```

Laravel merges a published config over the package defaults at the **top level only**, so keep the file whole and edit it in place. A partial file silently drops the siblings of any nested key it names.

## The settings you are most likely to touch

```php
use Niladam\LivewireBan\Enums\BlockScope;

use function Illuminate\Support\{days, hours, minutes, months, weeks};

'strikes'    => 3,                    // 1 bans on sight
'window'     => minutes(10),
'escalation' => [hours(1), hours(6), days(1), weeks(1)],

'block'     => BlockScope::Site,      // or BlockScope::Livewire
'allowlist' => ['10.0.0.0/8'],        // office, monitoring, load tests
'enabled'   => true,                  // kill switch

'prune_after' => months(6),           // null keeps everything
```

## Durations

Written with Laravel's own helpers, so they read as what they are. They are `CarbonInterval`s and survive `config:cache`. A bare number is read as hours.

## Triggers

An exception listed here is taken as evidence. Matching uses `instanceof`, so a base class covers its subclasses:

```php
'triggers' => [
    CorruptComponentPayloadException::class,
    CannotUpdateLockedPropertyException::class,
    App\Exceptions\SignatureTampered::class,
],
```

Anything that propagates out of your middleware stack can be listed. Exceptions caught inside a controller never reach it.

Take care with exceptions a real visitor can cause — `ValidationException` would ban your own users. For a decision the list cannot express, use [`strikeUsing()`](extending.md#hooks).

## Permanent bans

A `null` rung on the escalation ladder never expires:

```php
'escalation' => [hours(1), hours(6), days(1), null],   // fourth offence is permanent
'escalation' => [null],                                // never forgive anything
```

Permanent bans still count as active, still appear in the panel, and are undone the same way.

## Scope

```php
'block' => BlockScope::Site,       // refuses every request (default)
'block' => BlockScope::Livewire,   // refuses only the Livewire endpoints
```

`BlockScope::Livewire` leaves the rest of your application reachable, which matters when one shared or carrier-NAT address stands in for many people.

## Exempting your own team

So a bad deployment cannot lock you out of your own admin:

```php
'exempt_roles'     => ['admin', 'owner'],
'exempt_roles_via' => 'hasAnyRole',
```

The two are read together: the second names the method to ask, the first is handed to it. The example above decides every request with:

```php
$user->hasAnyRole(['admin', 'owner'])
```

Naming the method rather than calling one directly is what keeps any particular permissions package from being a requirement. The default suits `spatie/laravel-permission`; name your own if you use a different convention.

Nothing is asked when the role list is empty, when nobody is logged in, or when your user model has no such method.

For anything a role list cannot express, name a callable:

```php
'exempt_using' => [App\Security\BanExemptions::class, 'decide'],
```

It receives the request and returns true to let it pass unstruck. A closure cannot be written here — config caching cannot hold one — so register those with [`exemptUsing()`](extending.md#hooks).

## Authorization

Laravel cannot discover a policy for a model that lives inside a package, so name yours and it gets registered for you:

```php
'policy' => App\Policies\LivewireBanPolicy::class,
```

It is bound to the base model, which means a replacement model inherits it.

## Pruning

Bans outlive the block itself, as a record of what happened. `prune_after` feeds Laravel's `model:prune`, which you schedule:

```php
Schedule::command('model:prune', [
    '--model' => [Niladam\LivewireBan\Models\Ban::class],
])->daily();
```

## Upgrading

Because the merge is top-level only, a published config has to stay complete. When a new version adds a key, republish and re-apply your overrides:

```bash
php artisan vendor:publish --tag=livewire-ban-config --force
```
