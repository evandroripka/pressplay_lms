=== Pressplay LMS ===
Contributors: evandroripka
Tags: lms, courses, woocommerce, elearning, certificates
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WordPress + WooCommerce into a commerce-ready LMS with protected lessons, student dashboards, progress tracking, certificates, and enrollment operations.

== Description ==

Pressplay LMS is a custom WordPress plugin built to connect course content, checkout, access control, student operations, progress tracking, and certificate delivery in one product flow.

Main capabilities:

* WooCommerce-backed course sales
* protected lesson access
* custom student dashboard and account routes
* lesson progress tracking
* certificate generation based on real completion
* enrollment lifecycle management for support and operations
* theme-compatible frontend rendering

Payment compatibility:

* follows the WooCommerce payment lifecycle
* reacts to `woocommerce_payment_complete`
* respects WooCommerce paid statuses
* stays compatible with well-behaved gateways such as PayPal, Mercado Pago, PagBank/PagSeguro, Stripe, and similar extensions

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Make sure WooCommerce is active.
4. Save permalinks once after activation if the LMS routes need refreshing.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. WooCommerce is required for products, cart, checkout, payment state, and order lifecycle.

= Which payment gateways are supported? =

The plugin is designed to be gateway-agnostic and relies on the standard WooCommerce payment lifecycle. It should work with gateways that correctly update WooCommerce orders after payment confirmation, cancellation, failure, and refund events.

= Does it create a custom student area? =

Yes. The plugin includes custom routes for catalog, course pages, lessons, student dashboard, profile, password management, and certificates.

== Changelog ==

= 1.0.1 =

* Improved payment compatibility across the WooCommerce gateway ecosystem.
* Added WordPress.org-ready plugin metadata and WooCommerce compatibility headers.
* Added release and versioning documentation for future maintenance.

= 1.0.0 =

* Initial stable release of Pressplay LMS.
