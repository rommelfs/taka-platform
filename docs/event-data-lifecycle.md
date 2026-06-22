# Event Data Lifecycle

TAKA Platform uses WordPress Event CPT posts as the event source of truth after migration. The bundled `config/tour-events.php` file is seed, demo and empty-database fallback data only.

## Source Precedence

For events, precedence is intentionally whole-object, not field-by-field:

1. Database: if at least one `taka_event` post exists in any non-trash status, `TAKA_Platform_Data::get_events()` reads Event CPT records through `load_events_from_wp()`.
2. Config fallback: if zero `taka_event` posts exist, `get_events()` reads bundled config through `normalize_config_events()`.

Once a database event exists, config values are not merged into frontend events. This prevents stale config ticket fields from replacing admin-edited event meta.

Imported database events keep the config/event ID as their public `id`; the WordPress post ID is exposed separately as `wp_post_id`. This keeps lookups such as `netherlands-2026`, post-ID diagnostics and integrations from crossing wires.

Config import is seed-oriented:

- `missing` creates missing posts only.
- `update` fills empty meta on existing posts but does not replace editor-maintained values.
- `overwrite` is the explicit destructive repair mode and may replace existing post fields/meta from the import source.

Related objects have their own fallbacks:

- Organizers: WordPress organizers first, then config organizers if no organizer posts exist.
- Venues: WordPress venues first, then config venues if no venue posts exist.
- Content Sections: option-backed editable sections merged with bundled defaults.
- Content Blocks: WordPress `taka_content_block` posts.
- Translations: object-level translation meta/options first, then static JSON label translations and fallback text.
- Ticket section, hero and booking settings: WordPress options with bundled/static fallback defaults.

## Event Build Steps

1. `events_for_language()` calls `get_public_events()`.
2. `get_public_events()` calls `get_events()` and removes events whose normalized `status` is `draft`.
3. `get_events()` chooses exactly one event source:
   - database via `load_events_from_wp()` when any `taka_event` post exists
   - bundled config via `normalize_config_events()` when no `taka_event` posts exist
4. `events_for_language()` resolves event text translations.
5. Organizer and venue relationships are enriched from their repositories.
6. Event description content blocks can override the event description body.
7. Display labels such as country, format, audience, dates and program groups are resolved.
8. Ticket state is derived from the final event array:
   - `pretix_event_url($event)` delegates to the ticket provider registry.
   - The Pretix provider returns `ticket_shop_url` only when `ticket_provider` is `pretix`.
   - `events_for_language()` stores that value as final `pretix_event_url`.
   - `ticket_status_label()` shows the Pretix-open label when `pretix_event_url()` is non-empty; otherwise it shows the coming-soon label.

## Netherlands Trace

Current bundled config fallback row:

| Step | ticket_provider | ticket_status | ticket_shop_url | pretix_event_url() |
| --- | --- | --- | --- | --- |
| `config/tour-events.php` | empty | `coming_soon` | empty | empty |
| `normalize_config_events()` | empty | `coming_soon` | empty | empty |
| `get_events()` with zero DB events | empty | `coming_soon` | empty | empty |
| `events_for_language()` final fallback event | empty | `coming_soon` | empty | empty |

Result: the frontend renders the coming-soon ticket status because the final event has no Pretix provider and no ticket URL.

After config import and admin update, the database row must be:

| Step | ticket_provider | ticket_status | ticket_shop_url | pretix_event_url() |
| --- | --- | --- | --- | --- |
| `taka_event` post meta | `pretix` | `available` | Pretix event URL | Pretix event URL |
| `load_events_from_wp()` | `pretix` | `available` | Pretix event URL | Pretix event URL |
| `get_events()` with DB events present | `pretix` | `available` | Pretix event URL | Pretix event URL |
| `events_for_language()` final database event | `pretix` | `available` | Pretix event URL | Pretix event URL |

Result: the frontend renders the Pretix widget/button.

## Diagnostics

Use **TAKA Platform -> Diagnostics** to inspect every event and confirm:

- active event data source
- config ID
- WordPress post ID and status
- `ticket_provider`
- `ticket_status`
- `ticket_shop_url`
- `pretix_event_url()`
- final `ticket_status_label`
- raw database ticket fields
- raw config fallback ticket fields

If Netherlands still shows `config_fallback`, no Event CPT posts exist. Import the config seed first. If Netherlands shows `database` but `pretix_event_url()` is empty, the Event CPT meta values `_taka_ticket_provider` and `_taka_ticket_shop_url` are incomplete.
