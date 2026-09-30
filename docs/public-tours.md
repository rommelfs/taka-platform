# Public tours and archives

Public tours group existing Events without copying venues, organizers, ticket shops or
private logistics. There is no limit of one tour per year. The private Tour Agenda
remains a separate feature and is never exposed by public tour pages.

## Prepare 2027

1. Deploy the updated plugin and open **TAKA Platform → Tour setup**.
2. Run **Set up 2026 archive and 2027 tours** once. If the site still uses bundled
   fallback events, setup first imports those Events, Organizers and Venues using
   the existing missing-only importer, so adding the first 2027 Event cannot make
   the 2026 archive disappear. Repeating the action does not
   duplicate tours or overwrite their edited settings.
3. The action creates a published **Tour 2026** with lifecycle **Archive**, plus
   draft tours **Tour Juni 2027** and **Tour September 2027**.
4. Existing unassigned events whose normalized start date is in 2026 are assigned
   to the archive, including unpublished WordPress events. Other years and existing
   assignments are preserved. Undated events need manual assignment.
5. Under **Tours**, edit each 2027 tour's title, theme, description, translations,
   hero image, accent color and shared sections. Add reusable Content Block IDs in
   display order for additional shared or tour-specific content.
6. Create/edit Events and select the appropriate tour in the **Tour** sidebar.
   Configure dates and ticket providers on each Event as before. No 2027 dates,
   prices or ticket URLs are invented or copied from 2026.
7. Publish each tour when ready. On a page containing `[taka_homepage]`, current
   tours and the archive are now reachable through the tour navigation.

Until setup is run (or a tour is created manually), existing public shortcodes keep
rendering the original single-tour site. No database migration runs simply by
updating the plugin. A backup and staging check are appropriate before deploying
any database migration.

The setup page is `/wp-admin/admin.php?page=taka-tour-setup` and requires a
site administrator (`manage_options`). Version 2.4.1 fixes an admin-menu
registration-order bug that could deny access even to administrators. Its menu
entry is registered by the central admin menu after the TAKA parent menu exists.

## Online seminars and equal card sizes (2.5.0)

The overview lists both tours and online seminar collections. Existing records keep
category **Tour**. In **TAKA Platform → Tours & seminars**, the category field can
be changed to **Online seminars** for any collection. Publication, translations,
shared Content Blocks, image selection, event assignment and ticket providers use
the same existing workflow. An archived online collection also disables booking.

For the initial online category, open **Tour setup → Create / edit Online seminars**.
This creates a published **Online-Seminare** card with editable translated titles;
it does not invent dates, prices, meeting links or events. Repeating the action
reuses an existing online collection and preserves its content, archive state and
publication status, including an intentionally trashed collection. Restore such a
collection from Trash if it should be visible again. The action requires the same
administrator permission and a separate nonce as the initial tour setup.

Assign each online Event to that collection using **Tour / seminar series** in the
Event editor. The online collection has no geographic hero route; its schedule
uses online seminar headings. Meeting access or streaming itself is not provided
by this collection feature. Do not put private attendee joining credentials into
public descriptions.

All directory cards reserve the same square image area, including image-free
cards. Images use `object-fit: contain`: portrait and landscape images stay fully
visible, with unused space where their aspect ratios differ. Grid rows have equal
height, and the existing responsive grid stacks cards on narrow screens.

Storage additions: `_taka_tour_settings.category` (`tour` or `online`) and the
`taka_platform_online_seminars` setup option holding the default collection ID.
Existing post types, assignment metadata, query parameters and shortcodes stay
compatible. The global hero settings also expose a **Hidden** location display
mode; online collections select that mode automatically.

## Public pages and editing

`[taka_homepage]`, `[taka_tour_schedule]`, `[taka_tickets]` and their platform/event
aliases show a tour directory when tours exist. Select a tour with
`?taka_tour_id=123`, or set its default with `[taka_homepage tour="123"]`.
Explicit URL navigation takes precedence over that default.
`?taka_tours=archive` shows the archive directory; `?taka_tours=current` shows
published current/upcoming tours. A period is an editorial label such as `2027-06`,
not an inferred event date or an automatic archive deadline.

Draft tours remain invisible publicly. Authorized tour editors can preview a draft
on a shortcode page with `?taka_tour_id=123`; preview responses send no-cache headers.
Tour ownership and assigned-user/organizer access use the existing TAKA permission
model. Assignment changes require permission to edit the Event and the old/new Tour.

Title, theme and description support every registered site language. Blank theme
text falls back to the public title. Shared sections and Content Blocks keep their
existing translation behavior. Images prefer WordPress attachment IDs over URLs.
Tour styling is scoped outside third-party ticket widgets.

Events in archived tours remain readable but have no active ticket links or
widgets. Native ticket detection and new order creation (including standalone
products related to the event) reject archive purchases even outside tour rendering.
Existing orders and payment records remain intact. External shops themselves must
still be closed at their provider; this plugin does not administer Pretix sales.

Generated Event share links include the tour ID. Old `#tickets/event` links are
resolved by the directory's small client-side map; with JavaScript disabled the
ordinary tour navigation remains available.

## Storage and compatibility

- Public Tour CPT: `taka_public_tour` (managed admin object, no public WP single route).
- Tour settings: `_taka_tour_settings` post meta.
- Event assignment: `_taka_public_tour` post meta (one collection per Event).
- Config-only event assignments: `taka_platform_config_event_tours` option.
- Initial setup IDs: `taka_platform_initial_tours` option.
- Preserved 2026 option settings: `_taka_tour_snapshot` post meta.

The setup snapshots the old hero, sections, media, booking information and ticket
section settings. Tour rendering applies these options only within its own render
scope and restores the previous scope even after an exception. Events, referenced
Content Blocks, organizers, venues and media remain live records; this is an online
archive, not an immutable historical copy. For a frozen offline copy use the
[static archive exporter](static-archives.md).

Public repository queries exclude Events assigned to unpublished tours. Unassigned
Events remain available to existing APIs/integrations but do not appear inside a
selected tour. Draft Event visibility remains governed by the existing Event model.

The legacy PHP/JSON config export is not a full tour backup: tour CPT metadata,
assignment metadata and setup options must be included in a WordPress database
backup. The static exporter remains a separate site-wide export; selecting a tour
on the public site does not change its export scope.

## Verification

Run `php scripts/test_public_tours.php` for same-year isolation, draft visibility,
archive policy, directory separation, repeatable setup and cleanup after rendering exceptions.
Run `php scripts/test_tour_ticket_policy.php` for actual provider and native-order
archive gates, and
run the other `scripts/test_*.php` regression checks. Deployment QA should also
exercise the setup action twice, assigned editor permissions, tour publication,
real Event ticket widgets and native checkout in a WordPress staging instance.

## Overview design (v2.6.0)

Open **TAKA Platform → Overview design** (`/wp-admin/admin.php?page=taka-platform-overview`).
The directory now has independent settings for:

- Multilingual headings and introductions, separately for current collections and the archive; empty headings retain the standard translated labels.
- A header image selected from the Media Library, with an optional fallback URL. Images remain fully visible.
- Background, card, text and accent colors; two or three desktop columns, automatically one on mobile.
- Published Content Blocks below the cards. Enter an order number to include a block; leave the field empty to hide it. Edit block content and translations in Content Blocks.

Save with the form button. These settings apply to the directory rendered by `[taka_homepage]`, not individual collection pages. Card content remains editable in **Tours & seminars**.

Individual collections now default to **Hero layout → Full photo beside text (no cropping)**.
On mobile, the photo sits above the text. **Full-width background (cropped)** remains selectable in each collection's content/design box. Collections using **Use preserved legacy hero and sections**, including the initial 2026 archive, retain their original presentation.

The independent `taka_platform_overview` option is managed by `TAKA_Platform_Overview`, with capability and nonce protection. Existing Content Block rendering and dynamic translation resolution are reused. Collection metadata adds the optional `hero_layout` field; missing values use `split` for nonlegacy collections. No data migration or changes to ticket-provider integration are required.
