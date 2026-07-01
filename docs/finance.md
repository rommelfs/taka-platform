# Finance & Tour Budget

The Finance module gives organizers a private budget view for tours and events.
It combines ticketing revenue with tour-planning expenses without moving either
domain away from its source of truth.

## Phase 7 Scope

- Private admin page under `TAKA Platform -> Finance`.
- Dashboard cards for revenue, expenses, profit, outstanding payments and cash
  flow.
- Manual expense entries for costs that do not belong to a planning item.
- Read-only import of expense values from Tour Planning items.
- Revenue reports by tour, event, organizer, ticket type, product and country.
- Expense reports by category, responsible person and financial owner.
- Payment overview by payment method and payment status.
- CSV export designed for spreadsheets.

## Data Sources

Revenue is read from native TAKA Ticketing orders. Paid orders contribute to
revenue. Pending orders contribute to outstanding payments. Product and add-on
line items are included in product reports.

Expenses come from two sources:

- private Finance expense entries, stored as `taka_fin_expense`
- private Tour Planning items with estimated or actual costs

Tour Planning remains the owner of hotel, flight, restaurant and logistics
planning data. Finance only normalizes those costs for reporting.

## Expense Model

Manual expenses store:

- title
- category
- date
- amount and currency
- responsible person
- financial owner
- tour key
- optional event
- optional planning item ID
- status
- invoice availability
- notes

## Permissions

Administrators receive:

- `view_taka_finance`
- `manage_taka_finance`

Future organizer access should restrict reports to tours and events that the
current user may administer.

## Future Phases

Finance is intentionally not an accounting package. Later modules can add
invoices, VAT, tax reports, refund reporting and provider-specific payment
exports without changing the revenue/expense aggregation boundary.
