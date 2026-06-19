# TAKA Translation Packages

TAKA Translation Packages (TTP) are provider-independent JSON files for translating dynamic platform content without coupling TAKA Platform to one translation API.

Editors export a package, send it to ChatGPT, Claude, Gemini, DeepL or a human translator, then import the completed JSON back into WordPress. No external API key is required.

## Export Workflow

Go to **TAKA Platform -> Translations** and use **Export Translation Package**.

The export includes dynamic content such as Content Sections, Booking Information, Ticket Section copy and Hero text. Each item has a stable ID, context, source language, source text, SHA-256 source hash and empty target-language translation slots.

Each translatable object can define its own `source_language`. This allows a Finnish event, Japanese guest text or English international block to be translated from its real original language instead of assuming German.

## ChatGPT Workflow

Copy or upload the exported JSON and use the included `translator_prompt`.

The translator should:

- preserve the JSON structure
- preserve item IDs
- fill only the `translations` object
- leave `source_text` unchanged
- preserve HTML tags and placeholders
- return valid JSON only

## Import Workflow

Go to **TAKA Platform -> Translations** and use **Import Translation Package**.

You can upload a `.json` file or paste JSON into the textarea. By default, imports do not overwrite non-empty translations. Editors may explicitly allow overwrites or allow importing when the source text changed since export.

The import summary reports imported translations, skipped existing translations, skipped changed source texts, warnings and errors.

## Glossary

The glossary stores terms that should be preserved or translated consistently. Default entries include TAKA, Sensei, Dojo, Karate-Do, Kobujutsu, Soft Blocking, Shorin-Ryu, Okinawa, Kata and Kumite.

Glossary entries are included in exported packages when enabled.

## Source Hash Protection

Every package item includes a SHA-256 hash of the source-language text. During import, TAKA compares the package hash with the current source-language value.

If the source text changed after export, the import skips that item by default and reports a warning. Editors can allow changed-source imports when they intentionally want to apply translations anyway.

## Fallback Behavior

Frontend dynamic values resolve in this order:

1. current selected language
2. object source language
3. platform fallback language
4. English
5. first non-empty value

This applies to Content Sections, Booking Information, Ticket Section headings and Hero headings/buttons.

## Provider Independence

TTP does not call OpenAI, DeepL or any other provider. Future provider integrations can be added behind the package workflow without changing the stored content architecture.
