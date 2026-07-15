# Changelog

## Unreleased

## 0.11.0 - 2026-07-15

### Added
- Added WooCommerce visit-date selection for booking products, including product/variation controls for default start time, end time, duration, and daily booking capacity.
- Added public-safe schedule context on date-required product pages so customers can see public, private, and unavailable studio activity before choosing a visit date.
- Added shop-loop "Choose date" behavior so date-required products send customers to the product page instead of failing from the shop grid.
- Added simplified checkout support for booking-only carts, including WooCommerce Checkout Block support.
- Added automated coverage for WooCommerce booking date validation, shop-loop choose-date links, and simplified checkout behavior.

### Changed
- Day-pass unavailable dates now disable the add-to-basket button and show "Date unavailable" before submit.
- Booking-only checkout labels physical billing details as customer details and hides placeholder address values from customer-facing order output.

## 0.10.1 - 2026-07-04

### Fixed
- Improved Plugin Check compatibility for release packaging.
- Removed tracked placeholder files from development-only directories.
- Clarified trusted custom-table SQL handling in access duplicate checks.
- Sanitized QR lookup tokens before resolving them.
- Documented intentional CSV streaming for import/export and report exports.

## 0.10.0 - 2026-07-04

### Added
- Added booking and reservation management with conflict handling.
- Added Google Calendar booking sync and settings guidance.
- Added notification settings, notification logging, and staff alert support.
- Added member schedule and self-service account shortcodes.
- Added QR settings and QR output tools.
- Added reports dashboard and import/export tools.
- Added local release quality checks.

### Changed
- Polished pass management screens and admin workflows.

## 0.9.1 - 2026-06-27

### Fixed
- Updated readme stable tag to match the plugin version.
- Clarified WooCommerce variation nonce handling for Plugin Check.
- Documented the `sbm_` public hook prefix for WooCommerce access creation hooks.

## 0.9.0
- Added optional WooCommerce commerce adapter.
- Added product and variation access mapping fields.
- Added automatic person and access creation after paid WooCommerce orders.
- Added Commerce status screen.

## 0.8.5
- Added Studio Booking Manager capabilities.
- Added Studio Receptionist and Studio Manager roles.
- Replaced generic administrator permissions with plugin-specific capabilities.
- Added System Health screen.
- Added SECURITY.md.

## 0.8.1
- Fixed missing QR check-in URL helper in the People module.

## 0.8.0
- Added QR Check-in screen.
- Added QR identity token lookup.
- Added QR Check-in links from People records.
- Connected QR lookup to the existing check-in workflow.
