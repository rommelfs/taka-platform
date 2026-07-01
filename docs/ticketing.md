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
- `manage_taka_promotions`
- `manage_taka_products`
- `manage_taka_people`
- `view_taka_people`
- `edit_taka_people`
- `view_taka_registrations`
- `edit_taka_registrations`

Administrators receive these capabilities. Later phases can assign subsets to ticketing managers, check-in staff or organizer-specific roles.

## Backup And Export

WordPress export data includes native ticket type configuration as `native_ticket_types` on each event. Import restores the same data into `_taka_native_ticket_types`.

The export also includes the global `ticketing` settings block for native checkout consent labels, booking terms URL and privacy notice URL. The `ticketing` export block contains `settings`, `products` and `promotions`; older exports where `ticketing` is only the settings object remain importable. Product and promotion definitions are included so purchasable product and voucher/benefit configuration can be backed up and restored. Private order and participant records are intentionally not included in the public config export.

## Phase 2 Scope

Phase 2 adds:

- Public native checkout rendering for Events using `native_taka_ticketing`.
- Ticket type selection with capacity display.
- Optional checkout add-on products such as meals, parties, merch or donations.
- Promotion/voucher code application through the shared pricing service.
- Buyer information capture with country select options.
- Participant information capture with buyer-is-participant default, optional dojo/rank details and dietary preference select options.
- Payment method selection.
- Required booking terms and privacy notice checkboxes with configurable links.
- Pending order creation.
- Participant reservation and capacity checks.
- Confirmation screen with payment instructions.
- Localized confirmation email to the buyer.
- New order email notification to administrators.
- Private admin order list and detail view under TAKA Platform -> Ticketing.
- Mark paid, cancel and delete actions for privileged users.

The checkout currently supports one participant per order. The order shape keeps participant data separate so later phases can add multiple participants without changing the provider contract.

## Phase 4 Scope

Phase 4 introduces Orders & People.

The ticketing data model now separates durable community data from financial orders:

- `Person`
- `Registration`
- `Order`
- `Payment`

People are private records managed under TAKA Platform -> People. A person can attend multiple events, place multiple orders, use vouchers, purchase products and later become an organizer, volunteer, speaker or certificate holder.

Registrations connect a person to an event and order. Orders remain financial objects that reference buyer and participant people instead of treating participant data as anonymous order-only data.

When a checkout order is submitted, TAKA searches existing People by email first and then by name plus country. If a match exists, that person is reused and enriched with newer non-empty seminar data. Otherwise, a private person record is created. Existing native ticketing orders are migrated gradually in the admin by creating missing people and registrations when a privileged user visits the backend.

People and registrations are private post types, hidden from public queries and REST. Public event pages never render People data.

## Promotions And Benefits

Native ticketing includes a reusable Promotion & Benefits engine. Promotions are managed under TAKA Platform -> Ticketing -> Promotions / Vouchers.

A promotion has a voucher code, title, description, category, validity dates, use limits, scope, status and one or more benefits. Scopes can target all events, a selected tour, a selected event or a ticket type ID.

Supported benefits are free ticket, percentage discount, fixed amount discount, included meal, included merch, special access, manual note and manual approval required.

Checkout does not calculate discounts directly. It calls `TAKA_Ticketing_Pricing_Service`, which starts with the base ticket price, validates the promotion through `TAKA_Ticketing_Promotion_Repository`, applies benefits, returns the final amount and decides whether a payment provider is required.

If the final amount is zero because of a promotion, no payment provider is selected. The order stores `payment_method = promotion`, `payment_status = paid`, the original amount, discount amount, final amount, voucher code, promotion ID and a snapshot of the applied benefits. Non-monetary benefits are shown in confirmation screens and emails and remain available even if the promotion changes later.

## Products And Add-ons

Native ticketing products are generic purchasable items managed under TAKA Platform -> Ticketing -> Products. They are intentionally separate from seminar ticket types.

Products can be add-ons shown during event checkout or standalone products rendered with `[taka_ticketing_product id="product-id"]`.

Product fields include product ID, title, description, type, price, currency, capacity/stock, sale window, related event, related tour ID, whether an event ticket is required, whether standalone purchase is allowed, checkout visibility, max quantity per order, sort order and status.

Event checkout shows visible active products attached to that event when they require an event ticket. Orders store the seminar ticket and selected products as line items. Product capacity is reserved from active non-cancelled orders and is independent from seminar ticket capacity.

Standalone products can be purchased without selecting a seminar ticket. They use the same private order storage, buyer data, payment providers and confirmation email flow as event checkout.

Promotions are line-item aware. Voucher scope can target the whole order, the ticket only, add-ons only or one specific product. This prepares future benefit logic for product-specific vouchers without hardcoding product rules into checkout.

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
- Buyer person ID
- Participant person ID
- Registration IDs
- Buyer data
- Participant data
- Line items for tickets, products and promotion discounts
- Original amount, discount amount, final amount and currency
- Payment method
- Payment status
- Order status
- Applied voucher code, promotion ID and applied benefits snapshot
- Check-in status placeholder
- Timeline entries

The post type is private, hidden from public queries and not exposed through REST.

## People Profiles

The People admin profile stores:

- Basic fields: first name, last name, email, phone and country
- Seminar fields: dojo, association, style and rank
- Preferences: dietary preference and allergies
- Administration: notes, tags, GDPR consent and newsletter consent
- System timestamps

The profile view summarizes upcoming registrations, events attended, previous tours, products purchased, vouchers used and placeholders for future organizer and volunteer activity modules.

## Capacity

Capacity is reserved when an order is created. Available capacity is calculated from active non-cancelled orders for the same event and ticket type.

Cancelling an order releases the reserved capacity because cancelled orders are ignored by the capacity calculation.

## Phase 5 Event Operations

Event Operations is the private event-day control center under TAKA Platform -> Event Operations. It reuses the People, Registration, Order, Product and Payment services instead of storing attendance data inside orders.

The attendance service records:

- Registration attendance state
- Check-in status
- Walk-in flag
- Per-registration validation token for QR payloads
- Internal operation notes
- Operation timeline entries
- A placeholder array for future session attendance

Orders remain financial objects. When operations receive a payment, the existing order payment service marks the order paid and the registration sync keeps payment status current. When operations check someone in, the registration is updated first and the linked order receives a matching timeline entry for backward compatibility.

QR payloads contain only the registration ID and validation token:

`TAKA-REG:{registration_id}:{validation_token}`

The default admin UI accepts scanned or pasted QR payloads. Visual QR rendering is exposed through the `taka_event_operations_qr_markup` filter so a later offline-capable QR renderer can be added without changing attendance logic.

Walk-in registration creates or reuses a Person, creates a private ticket order, reserves capacity immediately, syncs a Registration, and marks it as a walk-in. This keeps walk-ins compatible with existing People profiles, order timelines, products, payments and future check-in features.

The Phase 5 UI includes dashboard metrics, quick actions, search by QR/name/email/order/dojo/country, participant cards, payment receive actions, check-in/undo/no-show actions, product visibility and operational warning badges.
