# Design notes

Three decisions that look wrong until you know why.

## Why `ComponentNotFoundException` is excluded

It is the obvious candidate — a request naming a component that does not exist is plainly not a browser — and on Livewire 4 it does not work.

`SupportReleaseTokens` verifies the release token *before* the checksum, and its own comment says so:

```php
// Verify the release token before verifying the snapshot checksum, as we don't want
// to trigger a checksum failure if the release token doesn't match...
on('checksum.verify', fn ($checksum, $snapshot) => ReleaseToken::verify($snapshot));
```

And `ReleaseToken::verify()` swallows it:

```php
try {
    $componentClass = app('livewire.factory')->resolveComponentClass($snapshot['memo']['name']);
} catch (ComponentNotFoundException) {
    throw new LivewireReleaseTokenMismatchException;
}
```

So a bot naming a component that does not exist gets a 419 release-token mismatch. `ComponentNotFoundException` never escapes the update endpoint at all.

Worse, when it *does* fire it comes from a broken `@livewire()` include in a Blade view — which would strike whichever innocent visitor happened to load that page.

`LivewireReleaseTokenMismatchException` cannot stand in for it either, for the mirror-image reason: every stale browser tab throws it after a deploy.

Livewire 4.4 also added `RequireLivewireHeaders`, which 404s anything without both `X-Livewire` and `Content-Type: application/json`, so naive scanners never reach the component layer.

That leaves the two triggers this package ships with, both of which require an attacker to have scraped a real snapshot from your live site first.

## Why the ban key is the connecting address

`$request->ip()` is where the TCP connection actually came from, and cannot be forged.

`CF-Connecting-IP` can be, by anyone who finds your origin:

```bash
curl -H 'Host: yourapp.com' -H 'CF-Connecting-IP: 8.8.8.8' https://<origin-ip>/livewire/update
```

For a log line that is cosmetic. For a ban key it means an attacker evades their own ban and can get an innocent address banned instead.

The alert flags a mismatch between the header and the connecting address, because that itself tells you someone reached your origin without going through Cloudflare.

If your proxy setup genuinely needs a header, override it with [`resolveIpUsing()`](extending.md#hooks) — but make sure the origin only accepts traffic from your proxy first, or you are handing out the forgery above.

## Why detection lives in middleware

Applications commonly throttle exception reporting per class and message, for instance:

```php
$exceptions->throttle(fn (Throwable $e) => Limit::perHour(1)->by(
    md5(get_class($e).$e->getMessage())
));
```

A bot spraying the same component would be throttled out of the reporter after the first hit, and never earn a second strike. Catching in middleware sidesteps the reporting pipeline entirely.

The middleware always rethrows, so your normal error handling is unaffected.

## Why three strikes rather than one

One exception is not proof of malice. A stale browser tab, a bad deployment, or a half-rolled-out asset can each produce one. Three inside ten minutes cannot plausibly be an accident, and a bot clears that in seconds.

Set `'strikes' => 1` if you would rather ban on sight.
