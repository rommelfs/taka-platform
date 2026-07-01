# Native TAKA Ticketing

Native TAKA Ticketing is the platform-owned ticketing architecture for seminars, workshops and event tours.

It is intentionally smaller than a full ticketing suite. The goal is a focused flow for event editors and participants, while existing external ticket shop modes continue to work.

## Roadmap

Phase 1: ticketing architecture and event configuration.

Phase 2: frontend order flow with bank transfer.

Phase 3: admin order management.

Phase 4: participant list, CSV export and basic check-in.

Phase 5: QR code check-in.

Phase 6: PayPal provider.

Phase 7: invoices, discounts, refunds and advanced features.

Only Phase 1 is implemented now.

## Phase 1 Scope

Phase 1 adds:

- Dedicated `includes/Ticketing/` module files for native ticketing.
- `native_taka_ticketing` as an event ticket mode.
- Backward-compatible support for existing external ticket shop, no-shop, pay-at-door, free-entry and coming-soon modes.
- Event-level native ticket type configuration.
- Payment provider interface scaffold.
- Bank transfer provider scaffold and settings shape.
- Order, participant, payment and repository placeholders for later phases.
- Reserved ticketing capabilities.
- Backup/export/import support for event ticket type configuration.

Phase 1 does not add:

- Public checkout.
- Order creation.
- Participant registration.
- Payment collection.
- Admin order management.
- PayPal, Stripe, Mollie, invoices, refunds or discounts.

## Ticket Modes

Events can use these ticket modes:

- `online_shop`: existing online ticket shop mode, currently used for Pretix widget rendering.
- `external`: external booking URL.
- `none`: no ticket shop.
- `coming_soon`: tickets are not available yet.
- `sold_out`: sold out or waiting list.
- `pay_at_door`: admission/payment on site.
- `free`: free entry.
- `native_taka_ticketing`: native TAKA ticketing configuration.

Legacy stored values remain supported:

- `external_url` maps to `external`.
- `free_entry` maps to `free`.
- `no_ticket_shop` maps to `none`.

## Ticket Type Data Model

Phase 1 stores native ticket types as structured event meta under `_taka_native_ticket_types`.

Each ticket type contains:

- `id`
- `name`
- `description`
- `price`
- `currency`
- `capacity`
- `sale_start_date`
- `sale_start_time`
- `sale_end_date`
- `sale_end_time`
- `status`
- `sort_order`

Valid status values:

- `active`
- `hidden`
- `sold_out`
- `disabled`

This event-meta storage is deliberately simple for Phase 1. The shape is close to the future table-backed model so ticket type configuration can later migrate without changing admin callers.

## Future Tables

Later phases are expected to use dedicated storage for operational data:

- Orders
- Participants
- Payments
- Check-in records

The placeholder classes `TAKA_Ticketing_Order`, `TAKA_Ticketing_Participant` and `TAKA_Ticketing_Payment` document the intended fields without creating public workflows yet.

## Payment Providers

Payment providers implement `TAKA_Ticketing_Payment_Provider_Interface`.

The interface prepares for:

- `get_id()`
- `get_label()`
- `is_enabled()`
- `get_public_instructions( $order )`
- `create_payment( $order )`
- `handle_return( $request )`
- `handle_webhook( $request )`
- `mark_paid( $order, $transaction_id )`
- `refund( $order )`
- `get_admin_fields()`

The Phase 1 bank transfer provider registers the configuration shape for account holder, IBAN, BIC, bank name, payment reference template and instructions text. It does not create frontend orders yet.

## Capabilities

The module reserves these capabilities:

- `manage_taka_ticketing`
- `view_taka_orders`
- `edit_taka_orders`
- `checkin_taka_participants`

Administrators receive these capabilities. Later phases can assign subsets to ticketing managers, check-in staff or organizer-specific roles.

## Backup And Export

WordPress export data includes native ticket type configuration as `native_ticket_types` on each event. Import restores the same data into `_taka_native_ticket_types`.

No private order or participant data exists in Phase 1.
