# Extending

## Hooks

Closures cannot survive `config:cache`, so register these from a service provider's `boot()`:

```php
use Niladam\LivewireBan\Facades\LivewireBan;

// Read the client address differently.
LivewireBan::resolveIpUsing(fn (Request $request) => $request->ip());

// Let a request through untouched.
LivewireBan::exemptUsing(fn (Request $request) => $request->user()?->isStaff() ?? false);

// Decide whether an exception counts. Return null to defer to the trigger list.
LivewireBan::strikeUsing(fn (Throwable $e, ?Request $request) => $e instanceof MyException ?: null);

// Replace what banning means — firewall, account lock, anything.
LivewireBan::banUsing(function (Request $request, Throwable $e, int $strikes, Warden $warden) {
    Cloudflare::block($request->ip());

    return $warden->ban($request, $e, $strikes); // still want the row and the email
});
```

`resolveIpUsing` defaults to `$request->ip()` — the connecting address, which cannot be forged. Override it only if your proxy setup genuinely needs a header, and read the [design notes](design-notes.md#why-the-ban-key-is-the-connecting-address) first.

## Banning by hand

No request and no exception required:

```php
LivewireBan::banIp($ip, 'Scraping');                 // follows the escalation ladder
LivewireBan::banIp($ip, 'Scraping', for: days(30));  // explicit duration
LivewireBan::banIpForever($ip, 'Known bad actor');   // never expires

LivewireBan::unbanIp($ip);                           // undo every ban on an address
```

A manual ban is recorded the same way as a detected one — it alerts, fires `Banned`, escalates, and shows in the panel — but carries no `exception_class`. `$ban->isManual()` tells the two apart.

## Events

For adding behaviour rather than replacing it:

```php
Niladam\LivewireBan\Events\Struck    // every strike: $ip, $strikes, $url, $exception
Niladam\LivewireBan\Events\Banned    // the ban itself: $ban
```

Both use `Dispatchable` and `SerializesModels`, so queued listeners work.

## Your own model

```php
LivewireBan::useModel(App\Models\LivewireBan::class);
```

It must extend `Niladam\LivewireBan\Models\Ban`. Or set `'model'` in the config, which is the cacheable way. The Filament resource, the policy binding and the unban route all follow it.

## Your own views

```bash
php artisan vendor:publish --tag=livewire-ban-views
php artisan vendor:publish --tag=livewire-ban-translations
```

Two views ship: `mail/banned.blade.php` is the alert, and `unbanned.blade.php` is the page the signed link lands on.
