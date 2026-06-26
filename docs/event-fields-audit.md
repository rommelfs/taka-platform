# Event Fields Audit

This audit covers text fields shown in **TAKA Platform -> Events** and traces how they are saved, translated, and rendered in public event/ticket views.

## Summary

The public ticket detail page primarily uses:

- `subtitle` as the event panel subtitle.
- `short_description` as the seminar description (`description` in normalized event data).
- `ticket_tab_label` for the ticket tab button label.
- `format`, `audience`, `level`, `doors_open`, `parking`, `accessibility`, and `notes` in ticket metadata, practical information, or drawers.
- program item text (`title`, `notes`) in the seminar plan.
- event-specific booking information fields in the booking-information panel when overrides are enabled.

The largest potentially confusing fields are `short_description`, `long_description`, and `ticket_card_text`. Today `short_description` is the active seminar description. `long_description` and `ticket_card_text` are saved and translatable but do not appear in the current public ticket/card templates. Legacy `post_content` may still contain old seminar descriptions, but the Event CPT no longer exposes the default WordPress editor and new imports/dashboard saves use `_taka_short_description`.

## Event Admin Text Fields

| Field label in admin UI | Internal field name | Type | Saved? | Used on ticket page? | Used elsewhere? | Translatable? | Recommendation |
|---|---|---:|---:|---:|---:|---:|---|
| WordPress title | `post_title` / `title` | single-line | yes | yes | yes, cards, route/map labels, exports | no object translation yet | keep |
| Legacy WordPress body | `post_content` | rich text | legacy only | fallback only if `short_description` is empty | fallback source for old events | indirectly via `description` fallback | keep as read-only legacy fallback; do not expose the default editor |
| Source language | `_taka_source_language` / `source_language` | single-line select | yes | no direct render | translation package source selection | yes, controls object translations | keep |
| Subtitle | `_taka_subtitle` / `subtitle` | single-line | yes | yes, event panel header | yes, cards and event details drawer | yes | keep |
| Country | `_taka_country` / `country` | single-line | yes | yes, venue/location line | yes, cards, hero flags, route/list labels | no | keep |
| Country code | `_taka_country_code` / `country_code` | single-line | yes | indirectly via flag/location data | yes, hero flag resolution | no | keep |
| Flag | `_taka_flag` / `flag` | single-line | yes | no direct text field, but shown in cards/hero | yes, hero and cards | no | keep |
| Route map label | `_taka_route_map_label` / `route_map_label` | single-line | yes | no | yes, hero route map | no | keep |
| City | `_taka_city` / `city` | single-line | yes | yes, venue/location line | yes, cards, hero, route map fallback | no | keep |
| Doors open | `_taka_doors_open` / `doors_open` | single-line | yes | yes, metadata row when filled | yes, event details drawer | no | keep |
| Timezone | `_taka_timezone` / `timezone` | single-line | yes | no visible render found | no current public render found | no | keep for data completeness; consider hiding if unused by integrations |
| Format | `_taka_format` / `format` | single-line | yes | yes, metadata row | yes, cards/details through normalized type | no package field currently | keep; consider making translatable if editors localize it manually |
| Audience | `_taka_audience` / `audience` | single-line | yes | yes, metadata row | yes, event details drawer | no package field currently | keep; consider making translatable |
| Level | `_taka_level` / `level` | single-line | yes | yes, metadata row | yes, event details drawer | no package field currently | keep; consider making translatable |
| Ticket provider | `_taka_ticket_provider` / `ticket_provider` | single-line | yes | yes, ticket widget behavior and details drawer | yes, provider lookup | no | keep |
| Ticket status | `_taka_ticket_status` / `ticket_status` | single-line | yes | yes, ticket widget behavior/status | yes, details drawer | no | keep |
| Photo credit | `_taka_photo_credit` / `photo_credit` | single-line | yes | no current public render found | exported in event data | no | hide from UI or render near images later |
| Languages, comma-separated | `_taka_languages` / `languages` | single-line | yes | no current ticket detail render found | data fallback/metadata only | no | keep if future language display is planned; otherwise hide later |
| Additional venue IDs, comma-separated | `_taka_venue_ids` / `venues` | single-line | yes | no current ticket detail render found beyond primary venue | used by venue event lookup | no | keep, but consider replacing with relationship UI later |
| Ticket shop URL | `_taka_ticket_shop_url` / `ticket_shop_url` | single-line URL | yes | yes, ticket widget and details drawer | yes, Pretix provider URL | no | keep |
| Fallback action photo URL | `_taka_image_url` / `image_url` | single-line URL | yes | yes, fallback for ticket overview image | yes, details drawer/image exports | no | keep |
| Fallback group photo URL | `_taka_group_image_url` / `group_image_url` | single-line URL | yes | yes, preferred ticket overview image fallback | yes, cards/details image fallback | no | keep |
| Gallery image IDs | `_taka_gallery_image_ids` / `gallery_image_ids` | single-line CSV | yes | no current ticket detail render found | exported as gallery URLs/data | no | hide from Event UI until gallery rendering exists |
| Seminar description | `_taka_short_description` / normalized `description` | multi-line | yes | yes, rendered as “Seminar description” | yes, event details drawer; translation packages as `event:...:description` | yes | keep and treat as canonical seminar description |
| Long description | `_taka_long_description` / `long_description` | multi-line | yes | no current public render found | exported/imported as translatable event text | yes | hide from UI or rename to “Detailed description” only when a frontend placement exists |
| Ticket card text | `_taka_ticket_card_text` / `ticket_card_text` | multi-line | yes | no current ticket/card render found | exported/imported as translatable event text | yes | remove later after migration, or wire into cards if product wants it |
| Accessibility notes | `_taka_accessibility` / `accessibility` | multi-line | yes | yes, venue/practical info drawer when filled | yes, translation packages | yes | keep |
| Notes | `_taka_notes` / `notes` | multi-line | yes | yes, venue/practical info drawer when filled | yes, translation packages | yes | keep |
| Parking notes | `_taka_parking` / `parking` | multi-line | yes | yes, venue/practical info drawer and `parking_display` | yes, translation packages | yes | keep |
| Program item title | `_taka_program_items[][title]` | single-line | yes | yes, seminar plan | yes, cards and event details schedule summary | no package field currently | keep; consider translation support later |
| Program item notes | `_taka_program_items[][notes]` | multi-line | yes | indirectly, event details schedule summary | yes, schedule summary text | no package field currently | keep; consider translation support later |
| Booking information title | `_taka_booking_info_title` / `booking_information.title` | single-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Intro text | `_taka_booking_info_intro` / `booking_information.intro` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Group booking text | `_taka_booking_info_group_booking` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Multi-event discount text | `_taka_booking_info_multi_event_discount` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Booking process text | `_taka_booking_info_booking_process` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Payment methods | `_taka_booking_info_payment_methods` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Cancellation policy text | `_taka_booking_info_cancellation_policy` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |
| Additional notes | `_taka_booking_info_additional_notes` | multi-line | yes | yes, booking info panel if override applies | yes, config import/export | no package field for per-event overrides currently | keep |

## Confusing Or Redundant Fields

- `short_description` versus `post_content`: `short_description` is labeled “Seminar description” and is the canonical field for the ticket page. `post_content` remains only as a legacy fallback for older events that have not yet saved `_taka_short_description`.
- `long_description`: saved and translatable, but not rendered in the current ticket page or cards. It is a likely candidate to hide until a clear frontend placement exists.
- `ticket_card_text`: saved and translatable, but not used by `templates/partials/seminar-card.php`. Either wire it into card summaries later or remove after migration.
- `gallery_image_ids`: saved/exported, but no current public gallery rendering was found in the event ticket/detail templates.
- per-event booking override fields are functional but not included in the current Translation Package export scope. Global booking information is included.

## Current Event Detail Source Of Truth

The public event/ticket detail data flows through `TAKA_Platform_Data::events_for_language()`.

- `description` is resolved from `_taka_short_description`, falling back to legacy `post_content`.
- object text translations are resolved before rendering.
- `templates/tickets.php` renders `description` under “Seminar description” only when non-empty.
- practical event text (`parking`, `accessibility`, `notes`) is shown through venue/practical info drawers.

## Recommended Next Cleanup

1. Keep `short_description` as the canonical seminar description and consider renaming the internal label/documentation consistently.
2. Hide `long_description` and `ticket_card_text` from the Event UI unless a frontend placement is added.
3. Decide whether `format`, `audience`, `level`, and program item text should join the translation package workflow.
4. Keep the Event, Organizer and Venue CPTs on structured fields only; the default WordPress editor should remain disabled unless a field becomes the canonical content source.
