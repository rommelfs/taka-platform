# TAKA Tour Website Builder

Reusable WordPress plugin for international seminar and event tours. The TAKA European Tour 2026 is the first reference implementation, but the plugin is now structured as a WordPress-first event-tour manager with native admin screens, static multilingual labels, config fallback data and Pretix ticket widgets.

## Public shortcodes

- `[taka_homepage]` renders the full landing page.
- `[taka_tour_schedule]` renders the tour/event schedule and cards.
- `[taka_tickets]` renders a standalone ticket-widget block for ticketed events.
- `[taka_sponsor]` renders the sponsor section.
- `[taka_language_switcher]` renders the compact language selector.

Existing pages using `[taka_homepage]` remain compatible.

## WordPress-first data flow

WordPress is the primary live data source. If at least one `taka_event` post exists, the frontend renders published WordPress events. If no WordPress events exist, the plugin falls back to `config/tour-events.php` so a fresh install still renders the reference TAKA Tour.

`config/tour-events.php` remains the seed, demo, fallback, import/export and backup format. It is no longer the live source of truth once WordPress event data exists.

## Admin CMS

The WordPress admin menu `TAKA Tour` contains:

- Dashboard: version, config status, WordPress/config event counts and active frontend source.
- Events: native CPT `taka_event`.
- Organizers: native CPT `taka_organizer`.
- Venues: native CPT `taka_venue`.
- Media: global image settings using WordPress attachment IDs.
- Import / Export: config import modes and portable PHP/JSON export.
- Settings: architecture notes and future settings.

The admin UI uses native WordPress posts, meta boxes, nonces, capability checks, sanitization and escaping.

## Event model

Events support: `id`, `slug`, `title`, `subtitle`, `description`, `country`, `country_code`, `flag`, `city`, `date_start`, `date_end`, `time_start`, `time_end`, `doors_open`, `timezone`, `organizer`, `venue`, `venues`, `format`, `audience`, `level`, `status`, `ticket_status`, `ticket_provider`, `ticket_shop_url`, `image_id`, `image_url`, `group_image_id`, `group_image_url`, `gallery_image_ids`, `gallery_urls`, `photo_credit`, `languages`, `notes`, `parking` and `sort_order`.

Only published `taka_event` posts appear on the frontend. Events are sorted by `sort_order`, `date_start`, `time_start` and title. Optional missing fields are skipped instead of producing warnings.

## Organizer and venue model

Organizers support `name`, `legal_name`, `website`, `logo_id`, `logo_url`, `emails`, `contact_persons`, `social_links`, `description` and `active`.

Venues support address fields, `timezone`, `website`, `parking`, `accessibility`, `notes`, `geo.lat`, `geo.lng`, `image_id`, `image_url`, `parking_image_id`, `parking_image_url` and `gallery_image_ids`.

Events can reference one organizer, one primary venue and additional venue IDs.

## Import / Export

The Import / Export screen can import from the bundled `config/tour-events.php`, an uploaded compatible PHP config file or pasted JSON with `organizers`, `venues` and `events`. All sources support:

- dry run / preview
- import missing only
- update existing
- overwrite existing
- optionally delete existing plugin data before import

Imports are idempotent and use stable `_taka_config_id` identifiers to avoid duplicates. The result summary reports created, updated and skipped organizers, venues and events.

Export provides the current WordPress data as a PHP array compatible with the config format and as JSON for external tools/backups.

## Media handling

Global media settings store WordPress attachment IDs and fallback URLs for hero, portrait, gallery, logo and sponsor imagery. Organizers have logo IDs, events have action/group/gallery image IDs, and venues have venue/parking image IDs plus optional fallback URLs.

Frontend image resolution order is:

1. WordPress attachment ID
2. stored fallback URL
3. config fallback URL
4. no image

## Pretix and ticket providers

Admins set `ticket_provider = pretix` and a `ticket_shop_url`. The frontend automatically renders:

```html
<pretix-widget event="https://pretix.eu/.../"></pretix-widget>
```

and a direct fallback link. The ticket-provider layer is isolated so additional providers such as Eventbrite, WooCommerce, TicketTailor or external URL-only flows can be added later.

## Multilingual support

Supported query-parameter languages: `?taka_lang=de`, `en`, `nl`, `fr`, `lb`, `fi`, `ja`. If no language is set, the plugin checks `HTTP_ACCEPT_LANGUAGE` and falls back to German.

Translations are static JSON files in `translations/`; there is no live translation API. Visible labels/buttons/status texts use the translation loader. Admin-created event content is rendered as entered, while frontend labels remain translated.

The compact selector remains:

`🌍 🇩🇪 🇫🇷 🇳🇱 🇧🇪▼ 🇱🇺▼ 🇫🇮 🇯🇵`

Belgium dropdown: Nederlands, Français, Deutsch. Luxembourg dropdown: Lëtzebuergesch, Français, Deutsch.

## Changelog

### v1.0.2

- Added WordPress Media Library integration for organizer logos, event photos, venue photos, galleries and global media settings.

### v1.0.1

- Added external import sources for uploaded PHP config files and pasted JSON while preserving dry-run, update modes and duplicate prevention.

### v1.0.0

- Refactored plugin into a WordPress-first event tour management system with admin CMS, import/export, media integration, WordPress data source flow and Pretix provider abstraction.

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
