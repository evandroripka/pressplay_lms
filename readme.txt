=== Pressplay LMS ===
Contributors: evandroripka
Tags: lms, courses, woocommerce, elearning, certificates
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 1.1.0
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
* certificate generation based on recorded learner completion
* free lesson samples and course-wide learning materials
* time-weighted Vimeo progress with playback resume
* per-course purchase terms with versioned acceptance records
* manual timed or unlimited access without changing WordPress roles
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

= 1.1.0 =

* Added published lesson samples without login or enrollment, with free-lesson labels.
* Added editable curriculum positions, sequential numbering and registration-order fallback.
* Added general course materials using the same file/link editor as individual lessons.
* Added per-course contract editor, affirmative checkout acceptance and versioned order snapshots.
* Validated contracts for classic checkout, Checkout Blocks and order-payment retries.
* Enabled native trailer fullscreen controls, without an external expansion button, and requested optional Vimeo logo hiding on eligible plans.
* Added manual timed or unlimited enrollments for existing WordPress users, preserving roles and profile information.
* Recorded manual grant history, made notification email opt-in and kept courtesy access separate from paid orders.
* Scoped admin blocking to the selected enrollment and deduplicated dashboard course cards.

= 1.0.3 =

* Recovered Vimeo durations through official oEmbed when the configured API token cannot access an embeddable video.
* Recalculated course duration from published lessons and preserved verified durations during API outages.
* Weighted course progress by watched duration, with decimal percentages and a live progress bar.
* Saved unique played intervals, resumed playback separately and avoided counting seeks or repeated playback twice.
* Added periodic, pause, visibility and page-exit progress persistence with retry handling.

= 1.0.2 =

* Fixed cart validation, guest checkout continuity and cart preservation.
* Hardened certificate CSS and unpublished content access.
* Preserved staff roles and prevented repeated order notifications from renewing access.
* Fulfilled paid orders even when new course sales are paused.
* Fixed course editor tabs and added keyboard navigation and a publication checklist.
* Added manual lesson completion fallback, progress retries and Vimeo resume support.
* Corrected course route context, missing-page status and local icon packaging.
* Added isolated, WordPress integration and browser regression tests.
* Preserved store-wide account settings on activation and retained configuration when uninstall data deletion is disabled.
* Supported pending enrollment creation from Checkout Blocks and recovery of previously unpaid orders.
* Reduced unrelated-page CSS loading and unnecessary lesson taxonomy cache work.
* Improved overlay-header spacing, course pricing and incomplete-course presentation.

= 1.0.1 =

* Improved payment compatibility across the WooCommerce gateway ecosystem.
* Added WordPress.org-ready plugin metadata and WooCommerce compatibility headers.
* Added release and versioning documentation for future maintenance.

= 1.0.0 =

* Initial stable release of Pressplay LMS.
