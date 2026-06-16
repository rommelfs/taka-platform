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

## WordPress admin CMS

Version 0.9.0 adds native WordPress editing screens under **TAKA Tour**. Administrators can maintain organizers (`taka_organizer`), venues (`taka_venue`) and events (`taka_event`) with regular custom post type screens instead of editing JSON or PHP by hand.

Organizer records support legal name, website, logo media ID, e-mail lines, contact-person lines, social links, description and active status. Venue records support address fields, timezone, website, parking, accessibility, notes and optional geo coordinates. Event records support subtitle, country/city data, dates/times, format, audience, level, organizer/venue relations, ticket provider/status/URL, image media ID, photo credit, notes, parking and sort order.

Global images are managed at **TAKA Tour → Einstellungen** with WordPress Media Picker fields for hero, portrait, community, Kobudo, Soft Blocking, together-practice, kids seminar, Kleiner Wald logo and optional sponsor logo. The plugin stores media attachment IDs and resolves them with `wp_get_attachment_image_url()`, falling back to the built-in URLs when an image is not selected.

Use **TAKA Tour → Import → Konfiguration importieren** to seed or update CPT data from `config/tour-events.php`. The import stores `config_id` metadata and updates existing imported records instead of creating duplicates. Rendering prefers CPT data when events exist and falls back to `config/tour-events.php` when no event CPT data is available, so existing pages remain populated immediately after update.

Prepared capabilities: `manage_taka_tour`, `edit_taka_events`, `edit_taka_organizers` and `edit_taka_venues`. Administrators receive these capabilities automatically; organizer-specific restrictions are intentionally left for the future self-service milestone.

## Changelog

### v0.9.0

- Added WordPress admin event CMS with organizers, venues, events, media picker settings and config import fallback.

## Changelog

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
