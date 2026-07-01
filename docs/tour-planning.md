# Private Tour Agenda

TAKA Platform includes a private Tour Agenda module for internal logistics around tours, seminars and workshops.

This module is not part of the public event website. Agenda logistics, hotel data, transfers, costs, notes and responsibilities must never be rendered on public pages or exposed through public REST endpoints.

## Concept

A Tour is a chronological list of Agenda Items.

Existing Events are projected into the agenda as read-only `seminar` items. Event data is not duplicated. If an Event title, date, time or venue changes, the Seminar agenda item changes with it.

Manual private Agenda Items are stored in the private `taka_tour_plan` post type. They fill the logistical gaps around public seminars: hotels, flights, trains, transfers, meals, meetings, press appointments, video shoots, excursions, shopping, free time, logistics and other internal tasks.

## Current Scope

The current implementation provides:

- Private `taka_tour_plan` post type for manual Agenda Items.
- Automatic read-only Seminar Agenda Items from existing Events.
- TAKA Platform -> Tour Planning / Tour Agenda view.
- Tour dashboard with seminar days, agenda counts, hotel/flight/meal counts, cost summary and open-task indicators.
- Timeline, calendar, kanban, station and cost overview views.
- Filters for tour key, date range, related event, item type, responsible person and status.
- Event editor section for private Agenda Items linked to an Event.
- Optional Event Assistant private logistics section with links to the agenda.
- Backup/export support under the explicit `private_tour_planning` key.
- Server-side access checks with dedicated planning capabilities.
- Extensible Agenda Item type registry via `TAKA_Platform_Tour_Planning::registerAgendaItemType()`.

## Data Model

Manual Agenda Items are stored as private WordPress posts. Their fields are stored as `_taka_planning_*` post meta.

Common fields:

- Tour key
- Type
- Start and end date/time
- All-day flag
- Location
- Description
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

Type-specific fields are available for hotel/accommodation, transfers/travel and meals. Other item types reuse the common fields.

Seminar Agenda Items are generated from `taka_event` posts at render time. They are read-only in the agenda; editing opens the linked Event editor.

This storage is intentionally simple. Later phases can add a real Tour CPT or migrate high-volume records, reminders or operational workflows to custom tables without changing the public Event model.

## Privacy And Access

The manual agenda post type is registered with:

- `public => false`
- `publicly_queryable => false`
- `show_in_rest => false`
- `exclude_from_search => true`

Access is enforced server-side. Admins can manage all manual Agenda Items. Users with the `taka_tour_planner` role or equivalent capabilities can only access manual items allowed by owner, assigned user, assigned organizer, related event access or all-planners access rules.

Public Seminars remain public event content. Private agenda details remain private.

## Future Extensions

The current model is prepared for future private workflows:

- Upcoming transfer reminders
- Missing hotel confirmation alerts
- Unpaid booking warnings
- Missing responsible person reports
- Private CSV/PDF agenda exports
- Dedicated Tour object / CPT
- Dedicated planning tables if needed

These are intentionally not implemented in phase 1.
