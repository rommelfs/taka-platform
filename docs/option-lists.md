# Structured Option Lists

TAKA Platform uses structured option lists for enumerated event data. Events store stable option IDs; labels are resolved from the option list for the active language.

## Managed Lists

The core event lists are:

- `ticket_provider`
- `ticket_status`
- `format`
- `audience`
- `level`
- `country`
- `currency`

Country values use ISO-3166 alpha-2 IDs such as `DE`, `LU`, `NL`, `BE`, `FI`, `FR` and `JP`. Currency values use ISO currency IDs such as `EUR`, `JPY` and `USD`.

## Option Structure

Each option stores:

- stable `key`
- source `label`
- `source_language`
- translated labels
- optional `icon`
- optional comma-separated legacy `aliases`
- `sort_order`
- `enabled`

Aliases allow automatic migration from older free-text values or older IDs. For example, `two_day_seminar` maps to `two_day`, and `Alle Stilrichtungen und Level` maps to `all`.

## Admin

Use **TAKA Platform -> Option Lists** to edit, reorder, deactivate, export and import option lists.

The Event editor uses structured controls for ticket provider, ticket status, format, audience, level, country, currency and languages. Organizer and Venue editors use the same structured country selector. Country code and flag are derived from country and are no longer edited directly. Timezone and currency are suggested from country where applicable and can be overridden where the object exposes those fields.

## Translation Workflow

Option-list labels participate in the existing translation package export/import flow as `option_list` objects. Translation imports update labels only. Stable option IDs are not changed by translation import.

## Backwards Compatibility

Legacy free-text values are matched against option keys, labels, translations and aliases. Known values are converted to stable IDs when imported or saved. Unknown values are preserved and reported as warnings on the Option Lists page so administrators can add an option or alias before normalizing them.
