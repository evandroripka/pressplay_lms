# Quality and Release Readiness

Validation date: **2026-09-24**. Candidate: **1.1.0**.

The automated checks below pass. This is not a claim of universal compatibility,
a completed security assessment, or a fully accepted payment integration.

## Observed environment

| Component | Installed version / mode |
| --- | --- |
| WordPress | 7.1.2 |
| WooCommerce | 11.1.2, HPOS enabled, classic checkout |
| Elementor | 4.3.1 |
| PHP | 8.3.6 |
| Theme | Style Craft 1.2.2 |
| Browser | Chromium, 1440px and 390px viewports |

The published `Tested up to` and `WC tested up to` baselines are intentionally
unchanged. Smoke tests on a newer installation are not full release acceptance.

## Automated coverage

| Suite | Passing checks | What it covers |
| --- | --- | --- |
| `tests/regression.php` | 35 | Cart hook signatures, CSS escaping, draft/password protection, staff roles, paid-order replay, unpaid-order recovery, manual blocks, cart preservation, progress storage failures and completion rounding |
| `tests/lifecycle.php` | 14 | Activation order, unchanged store settings, legacy option restoration, retain-data uninstall and opted-in cleanup using doubles |
| `tests/video-progress.php` | 24 | API/oEmbed fallback, metadata preservation, duration totals, unique watched ranges, seek/replay protection, weighted progress, resume and storage failures |
| `tests/learning-commerce.php` | 41 | Curriculum ordering, public samples, draft/password protections, contract validation and snapshots, classic/Blocks/order-pay consent and unauthorized progress rejection |
| `tests/manual-enrollments.php` | 30 | Permissions, nonces, valid accounts/courses, expiry, unlimited access, role preservation, duplicate prevention, advisory locks, audit records, opt-in email and scoped blocking |
| `tests/runtime.php --fixtures` | 22 | Real WordPress hooks, metadata escaping, editor tabs, color categories, query context, materials permissions, native trailer settings and public sample rendering; includes 20 ordinary runtime checks plus two fixture-only checks |
| `tests/browser.cjs` | 22 | Public routes, responsive layout, overlay-header clearance, 404s, editor controls, certificate preview, contract reading, materials/manual access forms, samples, native trailer permissions, simulated Vimeo resume and guest checkout arrival |

Total: **188 assertions**, counting runtime fixtures once. All 45 PHP files
passed syntax checks. All six plugin JavaScript files and the browser runner
passed Node syntax checks. `git diff --check` passed.

### Running the suites

```sh
php tests/regression.php
php tests/lifecycle.php
php tests/video-progress.php
php tests/learning-commerce.php
php tests/manual-enrollments.php
php tests/runtime.php
node tests/browser.cjs
```

The browser suite requires `playwright-core`, its Chromium binary and operating
system browser dependencies. Install those in a development/QA environment, not
automatically on a production server. `PLAYWRIGHT_MODULE` may point at an existing
installation. `PRESS_LMS_QA_OUTPUT` changes the output directory; the default is
`/tmp/presslms-qa/results`. Results and screenshots stay outside this repository.

`PRESS_LMS_WP_ROOT` overrides the WordPress directory used by runtime fixtures.
`PRESS_LMS_QA_HTTPS=0` allows an HTTP-only local environment.

Opt into a guest cart smoke test with `PRESS_LMS_QA_CART=1`. It adds a course to an
anonymous cart and reaches checkout; it never submits the checkout form or
creates an order. Ordinary HTTP requests can still create session/cache records.

### Safety and scope

- Isolated suites do not load WordPress, access the network or change a database.
- Runtime checks block SQL writes, mail and HTTP calls **after WordPress bootstrap**.
  Normal bootstrap behavior is not intercepted; use staging for strict isolation.
- Admin and lesson browser screens use HTML rendered from actual plugin templates
  in memory. They are not full authenticated `wp-admin` save/reload tests.
- Browser progress requests use fictional responses; Vimeo uses an SDK double.
- CLI fixtures contain temporary nonces. Do not publish fixture output or serve
  the test directory as a public testing endpoint. PHP tests reject non-CLI use.
- No payment credentials, real order records, course content or user passwords
  were changed during this verification.
- A separate, authorized metadata repair refreshed the 15 published videos of
  course 43832: 25,254 seconds in total (7h 00min 54s). It did not modify titles,
  prices, lesson order, contracts or existing student progress. The pre-existing
  course contract was preserved rather than replaced with the supplied draft.
- No real manual grants or contract acceptances were fabricated for the tests.

## Important behavior changes

- Course carts require an account through WooCommerce filters, without forcing
  global registration/default-role settings when the plugin is activated.
- Legacy activation backups are restored on deactivation only when the current
  value still matches the old forced value. Existing installations are not
  silently reconfigured on update.
- Paid-order fulfillment uses WooCommerce order CRUD metadata. Repeated sequential
  events cannot renew expired access or override manual blocks. A never-fulfilled
  failed/cancelled order may activate access once WooCommerce actually marks it paid.
- Checkout Blocks now create pending enrollments through their Store API hook.
  A full block-checkout transaction still needs acceptance testing.
- Manual lesson completion is available for text/non-Vimeo lessons and player
  failures. Completion is learner progress, not proof of attendance or identity.
- Known-duration video progress is weighted by unique watched seconds, not by
  the furthest seek position or by equal lesson weights. Unknown-duration lessons
  use an explicit lesson fallback. Historical data cannot reconstruct intervals
  that were never recorded; browser-submitted progress is not attendance proof.
- Samples are opt-in and require a published, unprotected course and lesson.
  Anonymous or non-enrolled sample viewers cannot persist student progress or
  receive the enrolled-materials list through the sample page.
- Manual grants use administrator permissions, a per-user/course advisory lock,
  an audit trail and optional notification. They preserve WordPress roles and
  remain separate from paid orders. Administrative preview permissions remain.
- Purchase contracts require explicit acceptance before payment. Orders keep
  append-only versioned snapshots through WooCommerce CRUD; later edits do not
  overwrite accepted text. Blocks use session-bound, expiring consent. This is
  electronic acceptance evidence, not a certified digital signature.
- Trailer fullscreen uses the native player, not an external expansion button.
  Vimeo branding removal depends on the owner's plan and player settings.
- Uninstall preserves settings as well as records unless deletion is explicitly
  enabled. Opted-in cleanup covers current/legacy LMS post types and owned tables;
  it does not delete WordPress users, shared media, WooCommerce orders or products.
- Shared frontend CSS is limited to LMS routes and registration shortcode pages.
  Lesson-list queries skip unnecessary taxonomy cache priming.

## Before accepting sales

- **Configure a sandbox gateway.** At verification, all five Mercado Pago methods
  and all three offline methods were disabled. The checkout correctly reported
  that no payment method was available. No gateway was enabled by this work.
- Complete a sandbox purchase as a new customer and an existing customer. Confirm
  account creation, emails, order ownership, access and resuming an unpaid order.
- Verify asynchronous payment confirmation, repeated webhook delivery, failure,
  cancellation, full refund, expiration and a later legitimate repurchase.
- Test authenticated profile/password/avatar updates, actual Vimeo playback and
  database persistence across devices, certificates and printing, and saving/
  reopening admin editors. Exercise real manual grants only in staging.
- Have the purchase contract and retention/privacy policy reviewed. Test changed
  contracts, mixed carts, payment retries and Checkout Blocks end to end in sandbox.
- Test fresh install, update, deactivate/reactivate and opted-in uninstall against
  a disposable database backup. Lifecycle doubles do not execute `dbDelta`.
- Review the publication checklist, including product availability, materials,
  teacher, duration, sample settings and the final version of the contract.
- Resolve or isolate third-party diagnostics in staging. Six browser errors came
  from CMSMasters Elementor Addon's rejected-AJAX handling during this run. The
  runner deliberately blocks unrelated POST requests, so those errors are not
  evidence by themselves of an LMS failure in normal browsing.

## Scale and remaining boundaries

Concurrent paid-order activation and simultaneous video writes have not been
load-tested. Manual grants are serialized separately, but that does not establish
payment-webhook or cross-device concurrency safety. The enrollment table permits
multiple rows per user/course. Before scaling, test a transactional or locking
design and versioned schema migrations that preserve renewal history.

Large catalogs/admin lists still need pagination and query/load profiling. No
capacity, latency or simultaneous-student guarantee is claimed. Material URLs
still point to WordPress uploads or external files; hiding their lists does not
make them signed protected download URLs.
Multisite network lifecycle and other themes/browsers need their own acceptance.

The pre-existing `includes/Database.php` contact-table change was preserved.
It is not a completed contact REST API, and this release does not claim that
earlier requested endpoint has been delivered.

## References

- [WooCommerce HPOS integration and order CRUD](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/)
- [WordPress metadata escaping](https://developer.wordpress.org/reference/functions/update_post_meta/)
- [WordPress nonce scope](https://developer.wordpress.org/apis/security/nonces/)
- [Font Awesome Free license source](https://github.com/FortAwesome/Font-Awesome/blob/5.15.3/LICENSE.txt)
