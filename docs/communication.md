# Communication Center

The Communication Center is the private messaging module for TAKA Platform.
Phase 6 implements targeted email campaigns for events, tours and participant
subsets.

## Scope in Phase 6

- Private admin page under `TAKA Platform -> Communication`.
- Reusable email templates.
- Campaign drafts, scheduled campaign metadata and send-now campaigns.
- Outgoing message history.
- Dynamic recipient resolution from People, Registrations and Ticket Orders.
- Template variables such as `{{FirstName}}`, `{{EventName}}`,
  `{{PaymentStatus}}`, `{{OrderNumber}}`, `{{TicketType}}` and `{{QRCode}}`.

The only active delivery channel in Phase 6 is email. The data model stores a
`channel` field so future modules can add push, SMS, WhatsApp, Signal or
Telegram without changing campaign targeting.

## Data Model

Communication data is stored as private WordPress post types:

- `taka_comm_template`
- `taka_comm_campaign`
- `taka_comm_message`

These post types are intentionally hidden from public UI, public queries and
REST. They are managed only through the TAKA admin module.

## Recipient Targeting

Recipient filters are stored on campaigns. The resolver translates those
filters against existing private People, Registration and Ticketing data.
Future automation should call the same resolver instead of implementing new
recipient logic.

Supported targets include:

- registered, paid, unpaid, checked-in and no-show participants
- operational tags such as VIP, volunteer, instructor, sponsor and press
- event, selected events and tour key
- country, dojo, rank or belt
- ticket type, product and voucher code
- dietary and allergy filters

## Permissions

Administrators receive:

- `view_taka_communication`
- `manage_taka_communication`
- `send_taka_communication`

Future organizer-specific access should restrict campaign visibility by tour
and event ownership before messages are sent.

## Future Phases

Later phases can add automated reminders, richer previews, delivery queues,
unsubscribe handling for newsletter-style messages and non-email channels. The
campaign resolver and template renderer should remain shared infrastructure.
