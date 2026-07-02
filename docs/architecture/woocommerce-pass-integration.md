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
