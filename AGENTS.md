# AGENTS.md

# TAKA Platform — AI Engineering Guidelines

**Version:** 1.0  
**Status:** Active  
**Applies to:** All AI coding assistants (OpenAI Codex, ChatGPT, Claude Code, Gemini Code Assist, Cursor AI, etc.)

---

## Purpose

This repository is developed together with AI coding assistants.

This document defines the mandatory engineering principles every AI agent must follow when contributing to the project.

These rules have higher priority than individual feature requests unless the project maintainer explicitly states otherwise.

The objective is not merely to generate working code, but to build a long-lived, maintainable, extensible and professional software platform.

---

## What is TAKA Platform?

Originally inspired by Takafumi Nakayama Sensei.

Today the acronym stands for:

> **TAKA – Ticketing, Attendance, Knowledge & Administration**

TAKA Platform is intended to become a generic Open Source Event Management Platform.

The current Karate Tour website is only the first deployment.

Future deployments may include:

- Conferences
- Seminars
- Workshops
- Festivals
- Sport events
- Educational events
- Associations
- Clubs
- Commercial events

Never design features only for the current website.

Always think **platform-first**.

---

## Core Engineering Principle

Whenever implementing a new feature, ask yourself:

> Would this still be the correct implementation if 500 independent organizations were using this platform?

If the answer is **No**, redesign the solution.

---

## Long-Term Vision

The project should become:

- Modular
- Configurable
- Multilingual
- White-label capable
- API-first
- Maintainable
- Pleasant to extend
- Pleasant to use

The project must never become a collection of special cases.

---

## Golden Rules

### 1. Configuration beats hardcoding

Never hardcode information that could reasonably become configuration.

GOOD examples:

- Headings
- Descriptions
- Colors
- Contact information
- Booking information
- Cancellation rules
- Hero content
- Gallery
- Sections
- Sponsors

BAD:

- Hardcoded strings inside templates

### 2. Relationships beat fixed fields

Never assume exactly one:

- Organizer
- Venue
- Speaker
- Sponsor

Prefer relationship objects.

### 3. Composition beats duplication

Before writing code:

1. Search for existing functionality.
2. Prefer helpers, services, repositories and reusable renderers.
3. Avoid copy & paste.

### 4. Generic beats specific

Prefer generic names:

- Event
- Organizer
- Venue
- Program
- Ticket Provider

Internal architecture should remain generic.

---

## Architecture

Prefer modular components:

- Admin
- API
- Core
- Data
- Frontend
- ImportExport
- I18n
- Models
- Permissions
- Rendering
- Services
- Support
- Tickets

Avoid monolithic classes.

---

## Data Model

Preferred domain objects:

- Event
- Organizer
- Venue
- Program Item
- Ticket Provider
- User
- Content Section
- Sponsor
- Partner
- Speaker
- Volunteer

Do not build around "Seminar Days".

---

## Content Management

Everything visible on the frontend should eventually become editable.

Avoid hardcoded frontend content.

Sections should support:

- Enable/disable
- Ordering
- Multilingual content
- Media selection
- Reusable rendering

---

## Media

Prefer:

1. WordPress attachment ID
2. Configured URL
3. Placeholder

---

## Translation

Every visible frontend string must be translatable.

Dynamic content must support translations.

Editors must be able to override translations.

Fallback chain:

Current language → Configured fallback → English → Source language

Supported languages:

- German
- English
- French
- Dutch
- Luxembourgish
- Finnish
- Japanese

---

## CSS

Component-scoped only.

Do not leak styles into third-party widgets such as Pretix.

Avoid layout shifts.

---

## JavaScript

Prefer modular JavaScript.

Support:

- Keyboard navigation
- Accessibility
- Progressive enhancement

---

## Responsive Design

Everything must work on:

- Desktop
- Tablet
- Mobile

---

## Accessibility

Support:

- Keyboard navigation
- Visible focus states
- Semantic HTML
- Screen readers where practical

---

## Ticket Providers

Current:

- Pretix

Future:

- Eventbrite
- WooCommerce
- Manual registration
- Custom providers

Use abstractions.

---

## Permissions

Design for multiple roles.

Do not assume administrators perform all editing.

---

## White Label

Branding must become configurable.

Avoid embedding organization-specific data into application logic.

---

## Repository Philosophy

Before implementing new code:

1. Understand existing architecture.
2. Search for reusable components.
3. Refactor if duplication appears.
4. Keep APIs stable.
5. Preserve backwards compatibility.

---

## Backwards Compatibility

Do not unnecessarily break:

- Configuration
- Events
- Organizers
- Venues
- Translations
- Imports
- Templates

---

## Performance

Prefer:

- Lazy loading
- Caching where appropriate
- Minimal database queries
- Reusable repositories

---

## Code Quality

Aim for:

- Readable
- Modular
- Documented
- Testable
- Reusable

Avoid:

- Duplicated logic
- Magic strings
- Magic numbers
- Giant switch statements
- Deep nesting

---

## Documentation

Keep documentation in sync with architecture.

---

## Commits

Preferred:

`v1.4.7: add organizer relationships`

Avoid vague commit messages.

---

## Pull Requests

Document:

- Purpose
- User-visible changes
- Architectural changes
- Migration requirements
- Backwards compatibility

---

## Testing Checklist

- PHP syntax passes
- JavaScript loads correctly
- Frontend renders correctly
- Mobile verified
- Desktop verified
- Translation support verified
- Pretix integration verified
- Accessibility not degraded
- No merge conflict markers
- No debug code left behind

---

## AI Behaviour

Always:

- Think before coding
- Prefer reusable solutions
- Preserve consistency
- Avoid technical debt
- Improve the codebase where reasonable

Optimize for the long-term health of the platform.

---

## Final Principle

Every implementation should leave the project in a better state than before.

The goal is to build a professional Open Source Event Management Platform that remains understandable, maintainable and enjoyable to develop for many years.
