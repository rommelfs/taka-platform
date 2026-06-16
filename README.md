# TAKA Tour Website Builder

WordPress plugin for the TAKA European Tour 2026 website. It provides modular templates, central event configuration and GeneratePress-friendly shortcodes without Elementor, Divi or premium-plugin dependencies.

## Shortcodes

- `[taka_homepage]` renders the complete landing page.
- `[taka_tour_schedule]` renders the seminar overview and equal seminar cards.
- `[taka_tickets]` renders the standalone Konz Pretix ticket block.
- `[taka_sponsor]` renders the kanso sponsor section.
- `[taka_language_switcher]` renders the compact language selector.

## Multilingual support

Supported query-parameter languages: `?taka_lang=de`, `en`, `nl`, `fr`, `lb`, `fi`, `ja`. If no language is set, the plugin checks `HTTP_ACCEPT_LANGUAGE` and falls back to German.

Use `[taka_language_switcher]` to render language links. `[taka_homepage]` also includes the switcher in the hero.

German is the master language. Translations are static JSON files in `translations/`; there is no live translation API or runtime external-translation dependency. Missing keys fall back to `translations/de.json` and then to the template fallback.

## Event configuration

Organizers, venues and tour events are maintained in `config/tour-events.php`. The file returns one PHP array with `organizers`, `venues` and `events`, so templates and renderers do not need hard-coded event metadata.

Add an organizer by creating a new key under `organizers`, for example `my-dojo`, with `name`, `legal_name`, `website`, `logo`, `emails`, `contact_persons` and `social` fields.

Add a venue by creating a new key under `venues`, for example `my-venue`, with `name`, `address`, `timezone`, `website`, `parking`, `accessibility`, `notes` and `geo`.

Add an event by appending an item to `events` with fields such as `id`, `slug`, `title`, `date_start`, `date_end`, `organizer`, `venue`, `venues`, `format`, `audience`, `level`, `status`, `ticket_status`, `ticket_shop_url`, `ticket_provider` and `sort_order`. Setting `ticket_provider` to `pretix` and `ticket_shop_url` to a Pretix event URL automatically renders the embedded widget. Use `venues` when an event spans multiple places.

## Minimal admin prototype

Version 0.9.1 connects published WordPress admin events to the frontend while preserving the config fallback when no WordPress events exist. The TAKA Tour dashboard also reports WordPress/config event counts and the active data source.

## Changelog

### v0.9.1

- Completed WordPress data source flow so published admin-created events, organizers and venues render on the frontend with config fallback.

### v0.8.1

- Stabilized repository after merge conflict and added minimal WordPress admin dashboard prototype.

### v0.8.0

- Moved organizers, venues and events into central configuration file and rendered seminar cards from structured event data.

### v0.7.4

- Refined gallery layout, removed duplicate image sections, fixed language dropdown click behavior, updated Taka portrait and completed seminar-card translations.

### v0.7.3

- Replaced country-name language selector with compact icon/flag language bar and dropdowns for Belgium and Luxembourg.

### v0.7.1

- Replaced runtime translation concept with static JSON translations, added Dutch, French, Luxembourgish, Finnish and Japanese, and improved language switcher labels.

### v0.7.0

- Added internal multilingual architecture with language switcher, translation keys and country-based language suggestions.

### v0.6.7

- Fixed visible embedded Pretix widgets by separating seminar widgets from legacy panel styling.

### v0.6.6

- Restored working embedded Pretix widgets in seminar cards.

### v0.6.5

- Added editorial real-image gallery, removed empty placeholders, refined homepage flow and image handling.

### v0.6.4

- Added real seminar image grid and reworked hero as full tour overview with station links.

### v0.6.3

- Fixed seminar data, removed bad map/caption, embedded Pretix widgets directly in seminar cards, corrected kanso sponsor link, reordered host/sponsor sections.

### v0.6.2

- Refactor to modular plugin structure, equal seminar cards, per-card pretix integration, Europe map, kanso sponsor section.
