# Changelog

## Unreleased

## 0.13.7 - 2026-07-19

### Fixed
- Fixed database upgrade detection for existing booking tables missing newer booking columns.
- Improved Gravity Forms duration parsing for labels such as "2 Hours".

## 0.13.6 - 2026-07-19

### Fixed
- Fixed the Studio Booking product panel for variable WooCommerce products.

## 0.13.5 - 2026-07-17

### Improved
- Linked member calendar event titles to booking edit pages for users with booking-management access.
- Kept staff calendar links scoped to Studio Booking Manager roles and capabilities.

## 0.13.4 - 2026-07-17

### Improved
- Improved member calendar labels to show public event, private booking, studio unavailable, and pending hold context.
- Displayed public booking titles on the member calendar when available.

## 0.13.3 - 2026-07-16

### Fixed
- Fixed Gravity Forms booking creation when no explicit end time is mapped by using the duration fallback instead of midnight.

## 0.13.2 - 2026-07-16

### Added
- Added Gravity Forms duration-hours mapping for pending booking end times.
- Added two custom Gravity Forms note mappings with editable labels.

## 0.13.1 - 2026-07-16

### Fixed
- Allowed Gravity Forms date-only booking requests to create pending bookings by using a configurable default start time.

## 0.13.0 - 2026-07-16

### Added
- Added Gravity Forms form selection, field mapping, and submission handling for people and pending bookings.
- Added default Gravity Forms booking location, duration, and visibility settings.

## 0.12.0 - 2026-07-16

### Added
- Added an Integrations admin screen with provider detection and saved settings for form plugins.
- Added integration provider foundations for Gravity Forms, Contact Form 7, WPForms, Fluent Forms, and Ninja Forms.

## 0.11.3 - 2026-07-16

### Added
- Documented the member schedule shortcode and attributes on the Studio Booking dashboard.

## 0.11.2 - 2026-07-15

### Fixed
- Prepared booking capacity custom-table queries with identifier placeholders to avoid Plugin Check database warnings.

## 0.11.1 - 2026-07-15

### Fixed
- Improved the booking calendar availability admin experience after the WooCommerce booking-date release.

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
