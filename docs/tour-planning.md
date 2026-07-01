# Private Tour Planning

TAKA Platform includes a private Tour Planning module for internal logistics around tours, seminars and workshops.

This module is not part of the public event website. Planning items must never be rendered on public pages or exposed through public REST endpoints.

## Phase 1 Scope

Phase 1 adds the private planning foundation:

- Private `taka_tour_plan` post type.
- TAKA Platform -> Tour Planning agenda view.
- Chronological agenda, day, event/station, type and cost views.
- Filters for tour key, date range, related event, item type, responsible person and status.
- Event editor section for private planning items linked to an event.
- Optional Event Assistant private logistics section with links to the agenda.
- Backup/export support under the explicit `private_tour_planning` key.
- Server-side access checks with dedicated planning capabilities.

## Data Model

Planning items are stored as private WordPress posts in phase 1. Their fields are stored as `_taka_planning_*` post meta.

Common fields:

- Tour / agenda key
- Type
- Start and end date/time
- Location
- Notes
- Responsible person
- Financially responsible person
- Estimated and actual cost
- Currency
- Access group
- Assigned users
- Assigned organizer members
- Related event
- Status

Type-specific fields are available for accommodation, transfers and meals. Other item types reuse the common fields.

This storage is intentionally simple for phase 1. Later phases can migrate high-volume records, reminders or operational workflows to custom tables without changing the public event model.

## Privacy And Access

The planning post type is registered with:

- `public => false`
- `publicly_queryable => false`
- `show_in_rest => false`
- `exclude_from_search => true`

Access is enforced server-side. Admins can manage all planning items. Users with the `taka_tour_planner` role or equivalent capabilities can only access items allowed by owner, assigned user, assigned organizer, related event access or all-planners access rules.

## Future Extensions

The current model is prepared for future private workflows:

- Upcoming transfer reminders
- Missing hotel confirmation alerts
- Unpaid booking warnings
- Missing responsible person reports
- Private CSV/PDF planning exports
- Dedicated planning tables if needed

These are intentionally not implemented in phase 1.
