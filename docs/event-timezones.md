# Event timezones

Event wall times remain in the existing `_taka_timezone`, `_taka_date_*`,
`_taka_time_*` and program-item fields. No automatic data migration or clock-time
conversion runs. Editors choose an IANA timezone in the Event Assistant, classic
Event editor, or organizer dashboard. The selector includes `Asia/Tokyo` for
Fukuoka; existing timezone aliases and numeric WordPress offsets remain visible.

All program items and doors-open times use the event timezone. Events without an
explicit valid timezone retain the existing country suggestion, falling back to
the WordPress website timezone (UTC when WordPress is unavailable). Use an explicit
zone for online events and for countries spanning several timezones.

`TAKA_Platform_Event_Time` centralizes timezone resolution, strict date/time
parsing, original-time labels and overnight ranges. Invalid calendar dates and
nonexistent times during a daylight-saving gap are not converted. Avoid scheduling
in the repeated hour when clocks go back: the current wall-time model cannot
record which occurrence was intended. A session end earlier than its start is
interpreted as the following local calendar day. Explicit export end dates remain
authoritative.

The shared `event-schedule.php` partial serves Event cards and ticket detail
panels. Server-rendered original times include the timezone and date-specific UTC
offset. A separate `event-timezones.js` enhancement adds a native timezone selector
and a second date/time line, defaulting to the browser timezone. Original times
remain visible, including with JavaScript disabled. Browser Intl supplies localized
conversion and timezone options; older browsers without `supportedValuesOf` offer
the browser zone, event zone and UTC. No browser choice changes stored event data.
New native ticket details also carry the event timezone into generated ticket documents.
All labels are present in the eight static translation catalogues.

Normalized JSON/REST events and generic CSV exports include `timezone`. ICS uses
that zone to convert local date/time values to UTC rather than treating local wall
times as UTC. Events Manager CSV retains its existing column contract; importers
must configure their target calendar's timezone accordingly.

## Existing online seminar

1. Open the event’s **Dates & schedule** section.
2. Select **Asia/Tokyo** and enter **21:00** as the program start.
3. Adjust the end time and doors-open time to Japan time too, then save.
4. Verify the original Japan time and the additional Europe/Berlin time.

For example, 12 November 2026 at 21:00 in Japan is 13:00 in Berlin; 12 July 2026
at 21:00 in Japan is 14:00 in Berlin. The date of the event controls the offset.

## Verification

Run `php scripts/test_event_timezones.php`, the existing `scripts/test_*.php`
regressions, `node scripts/test_event_timezones.js`, `./scripts/lint.sh`, and `node --check assets/js/event-timezones.js`.
The timezone regression covers seasonal offsets, date rollover, legacy fallbacks,
invalid input, overnight sessions and UTC calendar output independent of the PHP
server timezone.
