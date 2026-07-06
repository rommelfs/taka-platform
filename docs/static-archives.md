# Static Archives

TAKA Platform can export public tour/event pages as a static ZIP archive for
hosting after a tour has ended.

## Purpose

Static archives are read-only public snapshots. They are intended for domains
such as `2026.example.org` or plain static hosting where WordPress, PHP,
checkout, PayPal and private admin functionality are not available.

## Rendering Mode

The exporter defines `TAKA_ARCHIVE_MODE` for the export request. Templates should
check `taka_platform_is_archive_mode()` before rendering live functionality.

In archive mode:

- booking widgets are replaced by an archive notice
- native checkout, carts and payment provider code are not rendered
- API-dependent frontend code should not be included
- private, admin and user-specific data must not be rendered

## Export Scope

The first archive exporter creates:

- `index.html`
- `events.html`
- `tickets.html`
- one static page per exported event
- public organizer pages referenced by exported events
- public venue pages referenced by exported events
- local copies of referenced plugin assets and local media

The ZIP is generated from existing TAKA data models and frontend templates where
practical. Do not duplicate public display logic unless archive hosting requires
a static-safe wrapper.

## Future Extensions

Future archive work can add more public page types, richer tour grouping, custom
archive themes and multi-language ZIPs. Any extension must keep booking disabled
and must not expose private planning, ticketing, order, finance or people data.
