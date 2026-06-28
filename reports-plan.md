# Reports Plan

## Goal

Document the data already stored on the current branch and identify the reports that can be generated reliably later without first changing the data model.

## Current stored data sources

### 1. Plugin tables

The plugin currently persists four main operational tables:

- `{$wpdb->prefix}sbm_people`
  - Person identity and profile data
  - Key fields: `id`, `wp_user_id`, `display_name`, `email`, `phone`, `qr_token`, `status`, `created_at`, `updated_at`
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Database/Installer.php`

- `{$wpdb->prefix}sbm_locations`
  - Physical location records
  - Key fields: `id`, `name`, `slug`, `timezone`, `capacity`, `calendar_id`, `status`, `is_default`, `created_at`, `updated_at`
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Database/Installer.php`

- `{$wpdb->prefix}sbm_access`
  - Entitlements for visits and memberships
  - Key fields: `id`, `person_id`, `location_id`, `wp_user_id`, `order_id`, `product_id`, `variation_id`, `access_type`, `status`, `total_credits`, `remaining_credits`, `weekly_limit`, `guest_limit`, `starts_at`, `expires_at`, `created_at`, `updated_at`
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Database/Installer.php`

- `{$wpdb->prefix}sbm_visits`
  - Visit and attendance history
  - Key fields: `id`, `person_id`, `location_id`, `access_id`, `booking_id`, `status`, `visit_date`, `scheduled_start`, `scheduled_end`, `checked_in_at`, `checked_out_at`, `guest_count`, `guest_names`, `checkin_method`, `checked_in_by`, `checked_out_by`, `created_at`, `updated_at`
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Database/Installer.php`

### 2. WordPress options

- `sbm_settings`
  - Currently stores `business_name` and `default_timezone`
  - Useful for report headers and fallback timezone handling, not for operational reporting
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Settings/SettingsPage.php`

- `sbm_db_version`
  - Useful for diagnostics only
  - Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Database/Installer.php`

### 3. WooCommerce-linked data when WooCommerce is active

The plugin does not store full order or product snapshots, but it does persist stable references that can support limited commerce reporting:

- Access rows can store `order_id`, `product_id`, `variation_id`
- WooCommerce product and variation configuration is stored in post meta:
  - `_sbm_enabled`
  - `_sbm_access_type`
  - `_sbm_location_id`
  - `_sbm_total_credits`
  - `_sbm_weekly_limit`
  - `_sbm_guest_limit`
  - `_sbm_validity_days`
- Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Commerce/WooCommerce/OrderListener.php`
- Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/WooCommerce/ProductPanel.php`

### 4. Derived live views already supported in code

The current branch already proves these joins and views are valid:

- Access joined to person and location names
- Visits joined to person, location, and access type
- Current visits filtered by `status = checked_in`
- QR lookup by `qr_token`
- Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Access/AccessRepository.php`
- Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/Visits/VisitRepository.php`
- Source: `/home/runner/work/studio-booking-manager/studio-booking-manager/src/People/PersonRepository.php`

## Reports that can be generated reliably now

These are the safest report candidates because the needed fields already exist and the branch already uses them operationally.

### 1. People directory report

- Counts by person status (`active`, `inactive`, archived excluded unless intentionally included)
- New people over time using `created_at`
- People with/without linked `wp_user_id`
- People missing email or phone

### 2. Locations report

- Active vs inactive locations
- Default location identification
- Capacity listing by location
- Location setup completeness (timezone present, calendar ID present/missing)

### 3. Access inventory report

- Access counts by status
- Access counts by type (`single_visit`, `visit_pass`, `membership`)
- Access counts by location
- Access counts by person
- Access created over time

### 4. Active access and expiry report

- Currently active access records
- Access expiring in the next N days using `expires_at`
- Expired access backlog
- Suspended and pending access lists

### 5. Credit and pass utilization report

- Visit passes with remaining credits
- Visit passes with zero remaining credits
- Single-visit access issued vs remaining
- Memberships by active status

This is reliable for inventory-style reporting because `total_credits` and `remaining_credits` are stored directly on access records.

### 6. Visit history report

- Full visit ledger by date range
- Visits by status (`expected`, `checked_in`, `checked_out`, `cancelled`, `no_show`, `archived`)
- Visits by location
- Visits by person
- Visits by access type through the existing access join

### 7. Current occupancy report

- People currently checked in
- Current occupancy by location
- Guest count currently on site

This is already backed by the `current()` visit query used by Operations.

### 8. Attendance trend report

- Daily visit counts using `visit_date`
- Weekly or monthly visit counts by grouping `visit_date`
- Check-in vs check-out totals over time
- Average guests per visit using `guest_count`

### 9. Check-in channel report

- Visits by `checkin_method`
- QR vs reception check-in mix
- Manual override or import usage if those methods are used

### 10. Guest usage report

- Total guests by date range
- Guests by location
- Guests by host person
- Guests by access record

### 11. WooCommerce-created access report

- Access records created from WooCommerce orders
- Access issued by product or variation
- Access issued by WooCommerce customer linkage (`wp_user_id`)
- Order-to-access traceability using stored `order_id`

This is reliable only for access issuance reporting, not for full revenue reporting.

## Reports that are only partially reliable today

These can be attempted, but the current branch does not store enough structured data to treat them as first-class reports yet.

### 1. Revenue or sales reports

Not reliable from plugin data alone.

Why:

- The plugin stores `order_id`, `product_id`, and `variation_id`, but not order totals, taxes, refunds, discounts, or payment outcomes in its own schema
- Commerce reporting would depend on live WooCommerce joins and current WooCommerce data shape

### 2. Booking conversion reports

Not reliable yet.

Why:

- `booking_id` exists on visits, but there is no booking table or booking module on the current branch

### 3. Calendar utilization reports

Not reliable yet.

Why:

- Locations store `calendar_id`, but calendar sync is still a placeholder and no event records are stored

### 4. Capacity utilization history

Only partially reliable.

Why:

- Current occupancy can be reported live
- Historical capacity percentage is weaker because location capacity changes are not versioned over time

### 5. Audit or operational accountability reports

Only partially reliable.

Why:

- Visits store `checked_in_by` and `checked_out_by`
- There is no full audit log for edits, deletions, status changes, or access adjustments

## Recommended first reports to build later

If reports are coded next, the highest-confidence first set is:

1. Current occupancy by location
2. Visit history by date range
3. Access inventory by status and type
4. Access expiry report
5. Attendance trends by day/week/month
6. WooCommerce-created access issuance report

## Data gaps worth solving before deeper reporting

- No dedicated bookings data source behind `booking_id`
- No revenue snapshot fields in plugin tables
- No structured audit/event table
- No historical snapshotting for location capacity changes
- `guest_names` and `metadata` are free text, which limits reliable aggregation
- Reports screen exists in the admin menu, but is still a placeholder on this branch

## Summary

The current branch already has enough stable structured data for reliable people, locations, access, visits, occupancy, guest, check-in method, and basic WooCommerce-issued-access reports. The main gaps are financial reporting, booking funnel reporting, calendar utilization, and deeper auditing.
