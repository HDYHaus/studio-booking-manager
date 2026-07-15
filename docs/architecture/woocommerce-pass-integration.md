# WooCommerce Pass Integration

Status: Draft

## Purpose

WooCommerce products and variations can issue Studio Booking Manager Access after a successful purchase.

Products now support two configuration modes:

- Pass mode: issue Access from a selected Pass template.
- Legacy mode: issue Access from the original product metadata fields.

## Product Configuration

Studio Booking Manager keeps the existing **Enable Studio Booking** option.

When enabled, the product or variation can select a Pass. If a Pass is selected, WooCommerce orders issue Access from that Pass. If no Pass is selected, the order listener uses the legacy Access Type, Location, Credits, Weekly Limit, Guest Limit, and Validity Days metadata.

Variable products use the variation configuration when the variation has Studio Booking enabled. Otherwise, they fall back to the parent product configuration.

Date-required products can also define booking behavior:

- **Require booking date** asks the customer to choose a visit date before adding the item to the cart.
- **Booking start time** and **Booking end time** define the booking window created from a customer-selected date.
- **Booking duration minutes** overrides the selected Pass default duration when no valid end time is configured.
- **Daily booking capacity** limits how many quantities can be booked for the same visit date.

Pass Types can define a **Default booking duration** in minutes. This supports 30-minute, 60-minute, or other hourly/minute booking products while keeping day, flex, and resident passes on the same pass template model.

## Customer Date Selection

Products that require a booking date render a visit-date field on the single product page. The date field also shows public-safe schedule context for the selected date:

- Public bookings show their public title.
- Private bookings show as private booking activity.
- Unavailable bookings show as studio unavailable and block day-pass purchase for that date.

When a selected date is unavailable, the add-to-basket button is disabled and relabeled **Date unavailable**. Server-side validation still blocks unavailable dates and over-capacity requests.

Shop and product collection buttons for date-required products are relabeled **Choose date** and link to the single product page. They do not perform direct add-to-cart actions from the shop grid because the customer must choose a visit date first.

## Checkout

The **Simplify checkout for booking-only carts** setting lives under **Studio Booking → Settings → Commerce**.

When enabled and every cart item is a Studio Booking product, checkout keeps the customer contact fields and hides physical address fields. This applies to both classic checkout fields and the WooCommerce Checkout Block. The Checkout Block still receives internal fallback address values so payment validation can complete, but customer-facing order output hides those placeholder address values.

Mixed carts, such as a Studio Booking product plus a shippable merchandise product, keep WooCommerce's normal billing and shipping address behavior.

## Order Flow

```text
WooCommerce paid order
  ↓
Find or create Person from billing email
  ↓
For each eligible order item
  ↓
Selected active Pass?
  ↓ yes
Copy Pass rules into Access snapshot
  ↓ no
Use legacy product Access metadata
  ↓
Store Access ID on the WooCommerce order item
```

The order listener runs for `processing` and `completed` order statuses. Order items with an existing `_sbm_access_id` are skipped so repeated status transitions do not create duplicate Access records.

## Offline Payment Status

Cash on delivery and direct bank transfer orders are set to `pending` when placed.

These offline payment methods do not issue Access immediately. Access is created later if the order moves to `processing` or `completed`.

## Snapshot Rule

Passes are templates. Access records are issued snapshots.

When a WooCommerce purchase issues Access from a Pass, the current Pass behaviour, visits, weekly limit, guest allowance, and validity period are copied into the Access record. Future edits to the Pass do not mutate already issued Access.

## Backward Compatibility

Existing products that only use legacy Studio Booking metadata continue to issue Access without selecting a Pass.

The legacy metadata keys remain supported:

- `_sbm_enabled`
- `_sbm_access_type`
- `_sbm_location_id`
- `_sbm_total_credits`
- `_sbm_weekly_limit`
- `_sbm_guest_limit`
- `_sbm_validity_days`

The new Pass metadata key is:

- `_sbm_pass_type_id`
