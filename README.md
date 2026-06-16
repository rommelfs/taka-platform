# TAKA Tour Website Builder

WordPress plugin for the TAKA European Tour 2026 website. It provides modular templates, central seminar data and GeneratePress-friendly shortcodes without Elementor, Divi or premium-plugin dependencies.

## Shortcodes

- `[taka_homepage]` renders the complete landing page.
- `[taka_tour_schedule]` renders the seminar overview and equal seminar cards.
- `[taka_tickets]` renders the standalone Konz Pretix ticket block.
- `[taka_sponsor]` renders the kanso sponsor section.

## Multilingual support

Supported query-parameter languages: `?taka_lang=de`, `en`, `nl`, `fr`, `lb`, `fi`. If no language is set, the plugin checks `HTTP_ACCEPT_LANGUAGE` and falls back to German.

Use `[taka_language_switcher]` to render language links. `[taka_homepage]` also includes the switcher in the hero.

German is the master language. Translations are static JSON files in `translations/`; there is no live translation API or runtime external-translation dependency. Missing keys fall back to `translations/de.json` and then to the template fallback.

## Changelog

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
