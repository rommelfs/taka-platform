# Native TAKA Ticketing

Native TAKA Ticketing is the platform-owned ticketing architecture for seminars, workshops and event tours.

It is intentionally smaller than a full ticketing suite. The goal is a focused flow for event editors and participants, while existing external ticket shop modes continue to work.

## Roadmap

Phase 1: ticketing architecture and event configuration.

Phase 2: frontend order flow with bank transfer and pay at the door.

Phase 3: expanded admin order management.

Phase 4: participant list, CSV export and basic check-in.

Phase 5: QR code check-in.

Phase 6: PayPal provider.

Phase 7: invoices, discounts, refunds and advanced features.

Phases 1 and 2 are implemented now.

## Implemented Scope

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

The value objects `TAKA_Ticketing_Order`, `TAKA_Ticketing_Participant` and `TAKA_Ticketing_Payment` document the intended fields and keep checkout, admin and future table-backed repositories on the same data shape.

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

Bank transfer and pay-at-the-door both use this interface. Future API providers such as PayPal, Stripe and Mollie should implement the same contract.

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

## Phase 2 Scope

Phase 2 adds:

- Public native checkout rendering for Events using `native_taka_ticketing`.
- Ticket type selection with capacity display.
- Buyer information capture.
- Participant information capture with buyer-is-participant default.
- Payment method selection.
- Pending order creation.
- Participant reservation and capacity checks.
- Confirmation screen with payment instructions.
- Confirmation email to the buyer.
- New order email notification to administrators.
- Private admin order list and detail view under TAKA Platform -> Ticketing.
- Mark paid, cancel and delete actions for privileged users.

The checkout currently supports one participant per order. The order shape keeps participant data separate so later phases can add multiple participants without changing the provider contract.

## Payment Providers

Phase 2 includes two first-class providers:

- `bank_transfer`
- `pay_at_door`

Both implement `TAKA_Ticketing_Payment_Provider_Interface`.

Bank transfer displays account holder, IBAN, BIC, bank name, payment reference and instructions after order submission.

Pay at the Door reserves capacity immediately, keeps payment status pending, and tells the visitor that payment is collected at the venue before participation.

Events choose enabled providers in the Event editor's Native TAKA Ticketing section.

## Orders

Native ticketing orders are stored in the private `taka_ticket_order` post type. This is a Phase 2 repository implementation detail, not a public content type.

Order data includes:

- Order number
- Public confirmation token
- Event ID and event title
- Ticket type ID and name
- Buyer data
- Participant data
- Amount and currency
- Payment method
- Payment status
- Order status
- Check-in status placeholder
- Timeline entries

The post type is private, hidden from public queries and not exposed through REST.

## Capacity

Capacity is reserved when an order is created. Available capacity is calculated from active non-cancelled orders for the same event and ticket type.

Cancelling an order releases the reserved capacity because cancelled orders are ignored by the capacity calculation.
