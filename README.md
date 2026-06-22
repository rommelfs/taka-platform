# TAKA Platform

**TAKA** means **Ticketing, Attendance, Knowledge & Administration**. The plugin is a reusable WordPress platform for international event and seminar tours: seminars, conferences, martial-arts events, workshops, festivals, concerts, community events and multi-location tours.

The TAKA European Tour 2026 remains the first reference implementation and bundled demo/fallback dataset, but it is no longer the product boundary.

## White-label direction

Core architecture uses the `TAKA_Platform_*` namespace, the `taka-platform` text domain and generic platform constants. Existing TAKA Tour class names, constants and shortcodes remain available through compatibility shims so existing sites keep working.

See [docs/branding-migration.md](docs/branding-migration.md) for the compatibility rules that guide the migration from the original TAKA Tour plugin identity to the generic TAKA Platform.

## Public shortcodes

Backward-compatible shortcodes:

- `[taka_homepage]`
- `[taka_tour_schedule]`
- `[taka_tickets]`
- `[taka_sponsor]`
- `[taka_language_switcher]`

Generic platform aliases:

- `[taka_platform_homepage]`
- `[taka_platform_schedule]`
- `[taka_platform_tickets]`
- `[taka_platform_sponsor]`
- `[taka_platform_language_switcher]`

Additional event-tour aliases:

- `[event_tour_homepage]`
- `[event_tour_schedule]`
- `[event_tour_tickets]`
- `[event_tour_sponsor]`
- `[event_tour_language_switcher]`
- `[taka_organizer_dashboard]`
- `[taka_platform_organizer_dashboard]`
- `[event_tour_organizer_dashboard]`

All aliases call the same rendering logic.

## WordPress-first data flow

WordPress is the primary live data source. If at least one `taka_event` post exists, the frontend renders published WordPress events. If no WordPress events exist, the plugin falls back to `config/tour-events.php` so a fresh install still renders the reference TAKA European Tour.

`config/tour-events.php` remains seed, demo, fallback, import/export and backup data.

Use **TAKA Platform -> Import / Export** to seed database posts from the bundled config. Use **TAKA Platform -> Status** to verify required CPT registration and whether the frontend is using the database or config fallback.

## Admin CMS

The WordPress admin menu is branded **TAKA Platform** and includes:

- Dashboard
- Events (`taka_event`)
- Organizers (`taka_organizer`)
- Venues (`taka_venue`)
- Content Blocks (`taka_content_block`)
- Media
- Content Sections
- Status
- Import / Export
- Settings
- Translations

The dashboard explains the long form: **TAKA – Ticketing, Attendance, Knowledge & Administration**. Settings expose editable hero copy, hero image/layout controls, overlay strength, readable text-box options, ticket section headings and configurable booking information. Content Sections expose fully configurable homepage editorial blocks with add/delete controls, visibility, sort order, multilingual kicker/title/subtitle/body/button fields, layout/background styles, main/secondary images, galleries, image fit and image focus controls.

## Content Blocks

Reusable Content Blocks provide a single source of truth for editorial content that can appear in multiple places. Blocks have a source language, translated kicker/title/subtitle/body/button fields, optional media and an enabled flag. Homepage Content Sections and Event seminar descriptions can reference an existing block while preserving their local content as a fallback. TAKA Translation Packages export referenced blocks once with usage contexts, so shared copy is translated once instead of repeatedly. See [docs/content-blocks.md](docs/content-blocks.md).

## Organizer access control

Version 1.2.0 adds the `taka_organizer` role. Administrators can assign users to one or more organizer CPTs from the WordPress user profile. Organizer users can access the TAKA Platform dashboard, upload media and create/edit only events assigned to their organizer(s); administrators continue to manage everything. Version 1.3.0 adds a frontend organizer dashboard shortcode for focused self-service event listing, creation, editing and duplication.

## Data model

Events support tour/event concepts such as title, subtitle, description, organizer, venues, dates, times, doors-open, timezone, format, audience, level, ticket provider, ticket URL, action/group/gallery media, languages, notes, parking and sort order.

Organizers support legal names, websites, logos, contact data, social links and repeatable co-organizers with their own logo, website, email, description and active/sort controls. Events can also use global or event-specific booking information for groups, multi-event discounts, payment and cancellation notes. Homepage content sections can be exported as portable config data and used as fallback/demo editorial content. Venues support addresses, websites, parking/accessibility notes, geo data and venue/parking images.

## Option lists

Recurring event fields such as audience and format use configurable option lists from **TAKA Platform -> Settings**. Events store stable option keys where possible, while unknown legacy text values remain valid and continue to render as fallback text. Option labels have a source language, translations, sort order and enabled state, and are included in TAKA Translation Packages.

## Hero route map

The Hero location overview supports configurable display modes: list, flags, route map and route map with list. The route map is a lightweight SVG/HTML tour infographic with clickable event stops, optional `route_map_x` / `route_map_y` event or venue coordinates, automatic route layout fallback, flags and an accessible fallback list for keyboard, screen-reader and mobile users. Legacy `map` and `map_with_list` display modes are normalized to the route map modes for backward compatibility.

## Import / Export

The Import / Export screen supports:

- bundled PHP config
- uploaded JSON config
- pasted JSON with `organizers`, `venues` and `events`
- dry-run previews
- import missing only
- update existing
- overwrite existing
- optional deletion of existing plugin data before import

Imports are idempotent through stable `_taka_config_id` identifiers. Export provides a PHP config representation and JSON.

## Integrations

The Events Manager integration exports normalized TAKA event data without making Events Manager the source of truth. Admins can download ICS, CSV, JSON and Events Manager compatible CSV files, or consume the stable REST endpoint at `/wp-json/taka-platform/v1/events`. See [docs/integrations-events-manager.md](docs/integrations-events-manager.md).

## Translation Packages

TAKA Translation Packages (TTP) provide a provider-independent JSON workflow for dynamic multilingual content. Editors can export untranslated or changed content, translate it with ChatGPT, Claude, Gemini, DeepL or human translators, then import the completed JSON without an API requirement. See [docs/translations.md](docs/translations.md).

## Media handling

Media access is centralized in the data layer. Frontend image resolution priority is:

1. WordPress attachment ID
2. stored fallback URL
3. bundled config fallback
4. render nothing

The admin uses WordPress Media Library selection for global images, organizer logos, event images/galleries and venue images.

## Ticket providers

Pretix remains supported. Admins set:

- `ticket_provider = pretix`
- `ticket_shop_url = https://pretix.eu/.../`

The frontend automatically renders the correct Pretix widget plus a direct fallback link. The ticket layer now uses provider classes and a registry so Eventbrite, WooCommerce, TicketTailor or external URL providers can be added later.

## Multilingual frontend

Supported query-parameter languages: `?taka_lang=de`, `en`, `nl`, `fr`, `lb`, `fi`, `ja`. Static JSON translations in `translations/` are used for frontend labels. Missing keys fall back to German and then to the supplied template fallback.

The compact selector remains:

`🌍 🇩🇪 🇫🇷 🇳🇱 🇧🇪▼ 🇱🇺▼ 🇫🇮 🇯🇵`

## File structure

New platform modules live in smaller responsibility-based folders:

- `includes/Core/` plugin wiring and shortcode registration
- `includes/Admin/` native WordPress admin CMS
- `includes/Data/` repository/data-source logic
- `includes/Frontend/` shortcode renderer
- `includes/I18n/` static translation loader
- `includes/Tickets/` ticket-provider interface, Pretix provider and registry
- `includes/Support/` shared helper functions

Legacy `includes/class-taka-tour-*.php` files remain as thin compatibility shims.

Assets are split into platform files such as `assets/css/frontend.css`, `assets/css/admin.css`, `assets/css/language-switcher.css`, `assets/css/tickets.css`, `assets/js/frontend.js`, `assets/js/admin.js`, `assets/js/media-fields.js` and `assets/js/language-switcher.js` while legacy assets remain in place for compatibility.

## Development workflow

Run the repository lint script before submitting changes:

```bash
./scripts/lint.sh
```

The script checks PHP syntax for all `*.php` files with `php -l` and scans the repository for merge conflict markers. No Composer, npm or PHPUnit test configuration is currently included, so broader functional testing is manual in a WordPress install.

## Migration notes

Existing pages using `[taka_homepage]` and existing CPT data (`taka_event`, `taka_organizer`, `taka_venue`) continue to work. Existing constants such as `TAKA_TOUR_VERSION` map to the new platform constants. Existing class names such as `Taka_Tour_Data` are aliased to the new `TAKA_Platform_*` classes.

## Changelog

### v2.2.1

- Audited and hardened import/export handling, replacing uploaded PHP config execution with JSON-only uploads and documenting remaining security review findings.

### v2.2.0

- Added an Events Manager export integration with ICS, CSV, JSON, Events Manager compatible CSV and a normalized REST event feed.

### v2.1.0

- Added reusable Content Blocks and Content References to reduce duplicated content and translations across events, sections and platform objects.

### v2.0.1

- Fixed TAKA Translation Package exports so target languages are computed per item source language, allowing German translations for English-source content.

### v2.0.0

- Added provider-independent TAKA Translation Packages for exporting, translating and importing dynamic multilingual content.

### v1.5.5

- Added structured multilingual Content Section fields with admin language tabs, safe fallback rendering and a helper to copy default-language content into missing translations.

### v1.5.4

- Restored the Hero route map as an emotional pin-and-label overview without dates or stop lists, with automatic label collision avoidance.

### v1.5.3

- Fixed Hero route map label collisions by separating route pins from evenly spaced clickable labels inside the map panel.

### v1.5.2

- Improved mobile Hero route map sizing so pins and labels fit inside the viewport without horizontal clipping.

### v1.5.1

- Polished Hero route map labels with contained layout, numeric route dates and screen-reader-only fallback navigation.

### v1.5.0

- Replaced abstract Hero map with a dynamic tour route map using event data, route pins, flags and ticket-tab navigation.

### v1.4.10

- Fixed Hero Europe map rendering by defaulting the location display mode to map with list and validating stored display modes.

### v1.4.9

- Added a configurable stylized Europe map for Hero event location navigation with accessible fallback list.

### v1.4.8

- Added country flags to Hero location links and preserved ticket tab navigation.

### v1.4.7

- Added event-level organizer relationships with multiple organizers, roles, logos and frontend display.

### v1.4.6

- Fixed Pretix widget CSS isolation and removed unstable button borders from clickable organizer and venue info triggers.

### v1.4.5

- Polished ticket panel image placeholders, metadata grid sizing, clickable info styling and Pretix widget readability.

### v1.4.4

- Polished ticket panel layout, fixed grouped program timetable display, removed redundant venue details and restored translated booking information rendering.

### v1.4.3

- Added flexible multi-day event program items with translated grouped schedule display and refined clickable organizer/venue info cards.

### v1.4.2

- Fixed multilingual rendering and language picker behavior, restored translation completeness workflow, cleaned ticket info actions and connected hero navigation to ticket tabs.

### v1.4.1

- Polished tabbed ticket layout, replaced redundant seminar overview by the ticket selector and improved event summary panel spacing.

### v1.4.0

- Refactored ticket section into a tabbed booking layout with editable heading, event summary panel, ticket widget and visible Before You Book information.

### v1.3.9

- Added translation audit and dynamic translation groundwork, completed static translation keys and fixed rendering of configurable Before You Book information.

### v1.3.8

- Fixed dynamic content section image source priority and improved image fitting to avoid cropping portraits and people photos.

### v1.3.7

- Added dynamic homepage content sections with configurable layouts, images, ordering and visibility; removed redundant static practical information section.

### v1.3.6

- Added configurable “Before you book” information section and improved ticket overview layout.

### v1.3.5

- Added repeatable co-organizers with logos, links and frontend organizer modal display.

### v1.3.4

- Added optional past event photos to ticket overview cards using event and global media settings.

### v1.3.3

- Improved ticket detail modal layout, centered organizer and venue information, combined practical venue info and cleaned up ticket card summaries.

### v1.3.2

- Rendered ticket info drawer in frontend with event, organizer, venue and practical information.

### v1.3.1

- Fixed backend access for TAKA Organizer users with refreshed role capabilities, event CPT capability mapping and limited wp-admin menus.

### v1.3.0

- Added frontend organizer dashboard with scoped event listing, creation, editing and duplication for organizer self-service.

### v1.2.0

- Added organizer user role, user-to-organizer assignments and scoped event editing for organizer self-service.

### v1.1.1

- Added editable hero, content sections and dynamic venue practical information while removing hardcoded frontend texts.

### v1.1.0

- Refactored plugin into the TAKA Platform – Ticketing, Attendance, Knowledge & Administration – with a white-label architecture, smaller components, generic platform shortcodes and preserved TAKA Tour compatibility.

### v1.0.2

- Added WordPress Media Library integration for organizer logos, event photos, venue photos, galleries and global media settings.

### v1.0.1

- Added external import sources for uploaded PHP config files and pasted JSON while preserving dry-run, update modes and duplicate prevention.

### v1.0.0

- Refactored plugin into a WordPress-first event tour management system with admin CMS, import/export, media integration, WordPress data source flow and Pretix provider abstraction.
