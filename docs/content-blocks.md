# Content Blocks and References

Content Blocks are reusable editorial content objects for the TAKA Platform. They reduce duplicated text and duplicated translations across homepage sections, events and future platform objects.

## Content Blocks

Content Blocks live in the WordPress admin as `taka_content_block` posts under **TAKA Platform -> Content Blocks**.

Each block has:

- stable slug
- internal title
- type and category
- source language
- translated fields for kicker, title, subtitle, body, button label and button URL
- optional image and gallery media
- enabled flag
- admin notes

The block body uses the WordPress editor content. The other source fields are stored as `_taka_*` post meta, and translations are stored in `_taka_text_translations` using the existing object translation model.

## Content References

A Content Reference is a lightweight pointer from another object to a Content Block. It stores:

- block ID or slug
- context
- enabled flag
- sort order
- display style
- optional custom title
- optional local override translations

In v2.1.0, references are supported for homepage Content Sections and the Event seminar description.

Referenced content is not copied. Updating a Content Block updates every enabled reference automatically. Existing local section and event text remains saved and is used again if the reference is disabled.

## Rendering

Frontend rendering resolves references through `TAKA_Platform_Data::resolve_content_reference()`. `TAKA_Platform_Data::render_content_reference()` is available for places that need to render a reference as a standalone content section.

For homepage sections, a referenced block can replace the visible section text and media. The reference display style can optionally override the parent section layout. For events, the referenced block body can replace the seminar description; layout-oriented display styles are stored but not used by the plain description renderer.

Reference-level custom titles and local text overrides are multilingual. Empty override fields fall back to the reusable Content Block values.

Homepage rendering is descriptor-driven through `TAKA_Platform_Data::get_homepage_sections()` and `TAKA_Platform_Data::render_homepage_section()`. Core sections such as hero, tickets and image grid keep their existing templates, while administrator-created Content Sections render through the generic `partials/content-section.php` partial. New Content Section keys do not require PHP template files. A Content Block is not rendered by itself; it appears on the homepage when a Content Section references it.

Content References with a block ID are treated as enabled unless they explicitly store `enabled=0`. This keeps imported or older section data with a selected block from resolving to an empty section.

Fallback behavior follows the existing dynamic-content chain:

1. current selected language
2. object source language
3. platform fallback language
4. English
5. first non-empty value

## Translation Packages

TAKA Translation Packages export Content Blocks once as `content_block` items. If one block is referenced by multiple sections or events, the package item includes usage contexts rather than duplicating the same source text.

Imports update the Content Block translation data. All references benefit from the imported translations automatically.

## Backward Compatibility

No destructive migration is performed. Existing homepage sections and event descriptions keep rendering from their current local content until an editor explicitly attaches a reusable block.
