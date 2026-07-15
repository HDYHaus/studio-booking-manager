=== Studio Booking Manager ===
Contributors: hdyhaus
Tags: bookings, coworking, studio, memberships, operations
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.11.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Open source booking and operations management for studios, coworking spaces, creative hubs, libraries, and shared spaces.

== Description ==

Studio Booking Manager helps shared spaces manage people, access, visits, locations, memberships, and operations from WordPress.

This release cleans up Plugin Check handling for WooCommerce booking capacity queries.

== Changelog ==

= 0.11.2 =

- Prepared booking capacity custom-table queries with identifier placeholders to avoid Plugin Check database warnings.

= 0.11.1 =

- Improved the booking calendar availability admin experience after the WooCommerce booking-date release.

= 0.11.0 =

- Added WooCommerce visit-date selection for booking products, including product/variation controls for default start time, end time, duration, and daily booking capacity.
- Added public-safe schedule context on date-required product pages so customers can see public, private, and unavailable studio activity before choosing a visit date.
- Added shop-loop "Choose date" behavior so date-required products send customers to the product page instead of failing from the shop grid.
- Added simplified checkout support for booking-only carts, including WooCommerce Checkout Block support.
- Day-pass unavailable dates now disable the add-to-basket button and show "Date unavailable" before submit.
- Booking-only checkout labels physical billing details as customer details and hides placeholder address values from customer-facing order output.

= 0.10.1 =

- Improved Plugin Check compatibility for release packaging.
- Removed tracked placeholder files from development-only directories.
- Clarified trusted custom-table SQL handling in access duplicate checks.
- Sanitized QR lookup tokens before resolving them.
- Documented intentional CSV streaming for import/export and report exports.

= 0.10.0 =

- Added booking and reservation management with conflict handling.
- Added Google Calendar booking sync and settings guidance.
- Added notification settings, notification logging, and staff alert support.
- Added member schedule and self-service account shortcodes.
- Added QR settings and QR output tools.
- Added reports dashboard, import/export tools, and pass management polish.
- Added local release quality checks.

= 0.9.1 =

- Updated readme stable tag to match the plugin version.
- Clarified WooCommerce variation nonce handling for Plugin Check.
- Documented the sbm_ public hook prefix for WooCommerce access creation hooks.

= 0.8.5 =

- Added Studio Booking Manager capabilities.
- Added Studio Receptionist and Studio Manager roles.
- Replaced generic administrator permissions with plugin-specific capabilities.
- Added System Health screen.
- Added SECURITY.md.

= 0.8.1 =

- Added QR Check-in screen.
- Added QR identity lookup by permanent person token.
- Added QR check-in links from People records.
- Connected QR lookup to the existing Operations check-in workflow.

= 0.7.1 =

- Fixed guest names sanitization in the Operations check-in workflow.

= 0.7.0 =
* Added the Operations screen.
* Added receptionist-led person search for check-in.
* Added check-in and check-out workflows.
* Added current visits and live occupancy foundation.
* Added sbm_visit_checked_in and sbm_visit_checked_out action hooks.

= 0.6.1 =
* Fixed a fatal error on the Visits admin screen caused by missing notice rendering.
* Documented intentional sbm-prefixed public hooks for Plugin Check compatibility.

= 0.6.0 =
* Added the Visits module.
* Added visit list, add, edit, and archive workflows.
* Added visit fields for person, access, location, scheduled times, guest count, guest names, method, status, and notes.
* Added the sbm_visit_created action hook.

= 0.5.1 =
* Fixed Access save and archive request verification.
* Improved Access repository query handling for Plugin Check compatibility.
* Added guards around plugin constants to avoid duplicate-constant warnings in unusual staging/plugin-loading contexts.

= 0.5.0 =
* Added the Access module.
* Added access list, add, edit, and archive workflows.
* Added Single Visit, Visit Pass, and Membership access types.
* Added access fields for credits, weekly limits, guest limits, start date, expiry, and status.

= 0.4.1 =
* Added shared admin framework foundation.
* Added reusable admin notices, page headers, admin assets, form helpers, and UI components.
* Refactored People and Locations admin screens to use shared page patterns.

= 0.4.0 =
* Added the People module.
* Added create, edit, archive, search, and status handling for people.
* Added permanent QR identity generation for each person.
* Added optional WordPress user linking foundation.

= 0.3.0 =
* Added the Locations module.
* Added create, edit, archive, and default location actions.
* Added location fields for address, timezone, capacity, opening hours, status, and Google Calendar ID placeholder.

= 0.2.2 =
* Removed WooCommerce as a required plugin dependency.
* Added a Settings action link on the Plugins screen.
* Added a real Settings screen with initial settings sections.

= 0.2.1 =
* Fixed release packaging so the plugin slug remains studio-booking-manager.
* Fixed Plugin Check readme headers.
* Improved installer table name handling.

= 0.2.0 =
* Added custom database tables for people, locations, access, and visits.
* Added database installer and version tracking.
* Added default location creation on activation.
* Added product access type foundation.

= 0.1.1 =
* Fixed admin settings visibility.
* Improved Plugin Check compatibility.

= 0.1.0 =
* Initial clean foundation.
