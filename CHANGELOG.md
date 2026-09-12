# Changelog

Every release is written up on the [releases page](https://github.com/niladam/livewire-ban/releases).
Publishing one there copies its notes into this file, so this is a record rather than something
anybody edits by hand.

## v1.1.0 - 2026-09-12

### Fixed

**Your application's own exception configuration was being thrown away.**

Everything set up in `withExceptions()` in `bootstrap/app.php` — error emails, custom reporting, `throttle()`, `dontReportDuplicates()`, `renderable()`, `dontReport()` — quietly stopped working in production once this package was installed. Nothing failed and nothing was logged; the configuration simply never reached the handler.

Laravel hands the error handler its instructions the first time something actually needs it, rather than up front. Detection was stepping in front of the handler before that moment, and Laravel no longer recognised what it found as the thing it was about to instruct, so it never handed the instructions over at all.

What made this so easy to miss: in development and in your test suite, Collision reaches the handler earlier and the instructions land safely. A green test suite told you nothing — the loss only happened in production, where Collision is not installed.

Detection now waits for Laravel to finish, then steps in front of whatever it finds. How it spots tampering, counts strikes and bans an address is unchanged.

Upgrading needs nothing from you. Do check that the error notifications you expect are actually arriving, because until now they may not have been.

### Changed

- Turning the package off with `LIVEWIRE_BAN_ENABLED=false` now leaves your error handler completely alone, rather than installing a layer with nothing to do. It is the clean way out for an application that wants its handler untouched.
- A handler your application bound itself is wrapped and stays the one doing the work — it is never replaced. The same holds for other packages that decorate the handler: they stack in either order and all of them keep working.
- Chained calls on the handler, such as `->ignore(...)->ignore(...)`, no longer slip past detection after the first one.
- Registering the service provider a second time no longer charges two strikes for a single exception.

## [Unreleased]
