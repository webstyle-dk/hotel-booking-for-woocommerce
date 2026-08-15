=== WSH Hotel Booking Management ===
Contributors: cnwebstyle
Tags: hotel booking, room booking, accommodation, reservations, woocommerce
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.1.53
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple room booking for WordPress and WooCommerce. Create room types, show availability and accept direct bookings.

== Description ==

WSH Hotel Booking Management is a free hotel booking plugin for WordPress websites that use WooCommerce for checkout.

The free version is focused on a simple booking flow:

* create room types
* show room pages and room overviews
* search by check-in, check-out and guests
* display available rooms
* send bookings to WooCommerce cart and checkout
* manage reservations from WordPress admin
* use a booking calendar and a guided setup wizard

The plugin is suitable for hotels, guesthouses, apartments and similar accommodation websites that need a lightweight direct-booking setup.

The free plugin does not require any paid activation step.

= Core features =

* Room Types as native WordPress content
* Search form with check-in, check-out and guests
* Room grid overview shortcode
* Single room pages
* Availability-aware booking flow
* Booking calendar in admin
* Reservation overview in admin
* Guided setup wizard for first-time setup
* WooCommerce cart and checkout integration
* Temporary cart hold support
* Basic room gallery support

= Optional Pro add-on =

WSH Hotel Free works as a complete free booking engine without a license key or paid activation step.

A separate Pro add-on can be used when a site needs advanced hotel tools such as rate plans, seasonal price rules, add-ons and expanded guest management. Pro functionality is not locked inside the Free plugin.

= WooCommerce integration =

WooCommerce is required for cart, checkout and payment handling.

The plugin stores booking data in its own WordPress database tables and links completed bookings to WooCommerce orders. WooCommerce handles checkout, payment and order storage.

= Data and uninstall behavior =

When the plugin is uninstalled, plugin-owned database tables, settings and scheduled cleanup events are removed.

Room Type posts are user-created WordPress content and are preserved. WooCommerce orders remain under WooCommerce's control.

= Bundled assets =

All bundled images and visual assets are original WebStyle assets and are distributed with the plugin under the plugin license.

= Shortcodes =

Use these shortcodes on your pages:

* `[cnwshotel_search]` - room search form
* `[cnwshotel_room_results]` - filtered room results after search
* `[cnwshotel_rooms]` - all-room overview grid

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wsh-hotel/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure WooCommerce is installed and activated.
4. Open Hotel Booking in WordPress admin.
5. Run the setup wizard or create your first room type manually.
6. Add the shortcodes to your booking page.
7. Test the booking flow before accepting live bookings.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. WooCommerce is required for cart, checkout and payment handling.

= Is the free plugin usable without a paid activation step? =

Yes. The free plugin works without a paid activation step.

= Can I translate the plugin? =

Yes. The plugin uses the `wsh-hotel` text domain and includes translation files in the `languages` folder.

= Can I accept payment in WooCommerce, with this plugin? =

Yes. You can use whatever you want in your WooCommerce setup to receive payment for the booking.

== Screenshots ==

1. Booking calendar in admin
2. Setup wizard
3. Room management in WordPress admin
4. Room search and room grid on the frontend
5. WooCommerce cart and checkout booking flow

== Changelog ==

= 1.1.53 =
* Synced internal WooCommerce order item booking status meta with booking status changes.
* Made the no-availability room result state visible on the frontend.
* Documented bundled asset licensing for WordPress.org review.
* Cleaned release formatting and readability without changing booking behavior.

= 1.1.52 =
* Preserved user-created Room Type posts during uninstall while keeping plugin table, option and scheduled event cleanup.
* Declared WooCommerce HPOS and Cart/Checkout Blocks compatibility from the main plugin file.
* Limited room-grid custom table reads to the requested room post IDs.
* Aligned the PHP compatibility test target with the plugin's declared PHP 8.0 minimum.

= 1.1.51 =
* Hardened admin-post setup and booking actions with explicit capability checks and WordPress nonce verification before submitted fields are read.
* Split request verification from input sanitization for setup, room save and booking admin flows.
* Replaced unsafe raw request filters with explicit sanitizing filters and cleaned source text encoding.

= 1.1.46 =
* Final package audit release after the WordPress.org compliance refactor.
* Kept the stable booking, cart and WooCommerce checkout block flow from 1.1.44.
* Verified the free package remains free of license checks, external updater code and bundled Pro functionality.

= 1.1.44 =
* Prevented checkout/store-api processing from requiring the original room booking nonce.
* Kept booking nonce validation scoped to the actual single-room booking form.
* Replaced the transaction SQL wrapper with fixed internal transaction statements to avoid variable SQL while keeping booking allocation transactions intact.

= 1.1.38 =
* Fixed frontend booking nonce verification by preserving uppercase server keys such as REQUEST_METHOD in the typed request helper.

= 1.1.32 =
* Centralized prepared custom-table reads behind a controlled DB helper.
* Reduced business-code SQL suppressions and added cache keys for stable bulk lookups.
* Tightened request helper sanitization so dynamic fields are unslashed and sanitized at read time.

= 1.1.29 =
* Internal SQL helper cleanup for sanitized IN() queries in availability and room result lookups.
* Request helper now sanitizes scalar and array values at the source before validation.
* Kept Pro awareness visuals while keeping Free free of Pro functionality, license checks and updater code.

= 1.1.25 =
* Moved cart-hold cleanup scheduling to activation/cron instead of request-time cleanup checks.
* Reduced booking availability query load with per-request unit capacity caching and bulk minimum-stay lookup.
* Added composite database indexes for date/status availability lookups on bookings, room units, pricing rules and cart holds.
* Optimized WooCommerce order status sync to update matching bookings in one operation and clear affected availability caches.
* Added frontend room-grid query flags to avoid unnecessary result counts and taxonomy cache work.

= 1.1.24 =
* Restored a contextual Pro information screen with visual feature cards and Free vs Pro comparison, without adding Pro feature code, license handling or updater logic to the Free plugin.

= 1.1.23 =

* Added contextual Free/Pro information for administrators without including locked Pro functionality in the Free plugin.

= 1.1.22 =
* Removed unused upgrade/promotional admin page code and related image assets from the free package.
* Moved frontend booking summary and gallery media labels into localized script data.
* Removed hardcoded user-facing strings from the bundled JavaScript fallbacks.
* Replaced room editor promotional tab with free-core room settings only.
* Aligned the free request layer with field-specific GET/POST readers and removed GET/POST merge usage from booking flow.
* Added explicit POST nonce verification helpers for data-changing handlers.
* Improved frontend search availability by passing bulk availability counts into room cards.
* Tightened WooCommerce booking payload handling to read only validated POST fields after nonce verification.
* Added Woo room-product gating and per-request caches to reduce custom table lookups in cart and checkout hooks.

= 1.1.19 =
* Refactored request input handling to use field-specific reads instead of broad raw GET/POST array reads.
* Added frontend search nonce support for search forms and room links.
* Hardened admin POST handlers to read sanitized typed values after nonce/capability checks.
* Improved room result performance by resolving room rows and date availability in grouped queries.
* Reduced repeated WooCommerce room-product lookups with product meta markers and per-request cache.

= 1.1.18 =
* Fixes booking admin unit-availability SQL prepare argument order so dates cannot be treated as table identifiers when editing booking status or unit assignment.
* Keeps cancelled bookings visible in booking history while preventing admin database output/warnings.

= 1.1.16 =
* Changed main admin menu target so Hotel Booking opens the Dashboard first.
* Polished admin shell alignment so the top navigation reaches the left edge of the plugin content area.
* Rechecked PHP templates for inline CSS and JavaScript.

= 1.1.15 =
* Finalized uninstall cleanup for the current cnwshotel database structure only.
* Removed old development/test table cleanup from the WordPress.org-ready free package.
* Reconfirmed that installer and uninstall use the same current table prefix.

= 1.1.13 =
* Switched the Free settings form to the WordPress Settings API.
* Replaced broad raw request reads with field-specific request helpers, nonce/capability guarded admin handlers, and stricter validation.
* Rechecked nonce and capability protection for admin-post actions, room saves and settings.
* Reconfirmed cnwshotel prefix usage for classes, options, hooks, handles, custom tables, CSS classes and shortcodes.

= 1.1.12 =
* Removed the remaining internal public booking-page legacy slug handling.
* Kept the setup wizard public booking page on `/room/` with `[cnwshotel_search]` and `[cnwshotel_room_results]`.
* Removed unused legacy frontend template files that are not loaded by the current Free runtime.
* Limited frontend CSS/JS loading to booking pages, room pages, shortcode pages and WooCommerce cart/checkout where relevant.

= 1.1.11 =
* Set the public booking/search page slug to `/room/` when created by the setup wizard.
* Updated search and room-page fallbacks to use `/room/` instead of internal plugin naming.

= 1.1.10 =
* Aligned booking result pages to use `[cnwshotel_room_results]`.
* Kept `[cnwshotel_rooms]` as the all-room overview shortcode.

= 1.1.9 =
* Migrated the free plugin foundation to the cnwshotel prefix standard for classes, constants, options, tables, hooks, assets and shortcodes.
* Added a central frontend request parser and removed direct request parsing from room templates.
* Fixed setup wizard room intro handling so the same short description is not displayed twice on single room pages.
* Renamed frontend and admin assets to the cnwshotel handle/file structure.

= 1.1.6 =
* Renamed the plugin package folder to `wsh-hotel` for a clean WordPress.org package structure.
* Strengthened uninstall cleanup for plugin database tables.
* Removed PHP closing tags from templates to reduce activation-output risk.
* Tightened database identifier handling for WordPress 6.2+ SQL preparation.

= 1.1.5 =

* Cleaned the free plugin package for WordPress.org.

* Simplified the free booking flow to check-in, check-out and guests.

* Added guided setup wizard and improved free admin UI.

* Cleaned activation and database upgrade handling.

* Improved admin menu, calendar landing page and release readiness.

= 1.0.0 =

* Initial release.
