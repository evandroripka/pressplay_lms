# Pressplay LMS Developer Guide

This guide is the collaboration baseline for developers working on Pressplay LMS.

It explains how the plugin is structured, what happens during activation, where the business rules live, and which areas require extra care when changing code.

## 1. Plugin Purpose

Pressplay LMS turns WordPress + WooCommerce into a learning commerce stack with:

- custom course and lesson content models
- WooCommerce-backed enrollments
- protected lesson access
- a custom student area
- progress tracking
- certificates
- operational admin tooling

The plugin is intentionally split between:

- content modeling
- commerce and access lifecycle
- custom frontend routes
- operational admin workflows

## 2. Directory Structure

```text
pressplay_lms/
├── assets/
│   ├── css/
│   ├── js/
│   └── svg/
├── docs/
├── includes/
│   ├── Core/
│   ├── Support/
│   └── *.php
├── templates/
│   ├── certificado/
│   ├── frontend/
│   └── panel/
├── pressplay-lms.php
└── uninstall.php
```

### Structure assessment

The current layout is already professional for a medium-sized WordPress plugin:

- `includes/Core` isolates bootstrap and infrastructure concerns
- `includes/Support` holds reusable helpers without mixing them into domain files
- the flat modules in `includes/` keep the main LMS domains easy to find
- `templates/frontend`, `templates/panel`, and `templates/certificado` make rendering intent explicit
- `assets/` is organized by file type, which keeps frontend and admin work easy to scan

This is a sensible WordPress-oriented structure. It favors clarity over excessive nesting, which is usually the right trade-off for a plugin of this size.

### Suggested evolution if the plugin grows

If the number of modules or contributors grows significantly, the next structural step would be moving the flat files inside `includes/` into clearer ownership folders such as:

- `Admin/`
- `Domain/`
- `Frontend/`
- `Integrations/`

That refactor should happen only when it makes ownership, navigation, or testing meaningfully better. Right now, forcing that split early would add ceremony more than clarity.

## 3. Bootstrap Order

The entrypoint is `pressplay-lms.php`.

Key responsibilities:

- define plugin constants
- load dependencies
- register activation and deactivation hooks
- boot domain modules through `PRESS_LMS_Plugin`

Core bootstrap happens in:

- `includes/Core/Plugin.php`
- `includes/Core/Activator.php`
- `includes/Core/Deactivator.php`
- `includes/Core/Dependencies.php`

## 4. Activation and Deactivation

Activation currently does more than database setup. It also aligns WordPress and WooCommerce settings with the LMS flow.

### Activation responsibilities

- create or update custom tables
- register LMS roles
- register course and lesson post types
- register rewrite rules and WooCommerce account endpoint
- backup registration and checkout-related options once
- enable account creation required by the LMS purchase flow
- flush rewrite rules

### Important side effects

Activation changes:

- `users_can_register`
- `default_role`
- WooCommerce guest checkout and registration options

Because of that, activation logic must stay conservative and reversible.

### Deactivation responsibilities

- restore backed up options
- clear the LMS daily lifecycle cron hook
- flush rewrite rules

## 5. Data Model

### WordPress post types

- `press_course`
- `press_lesson`
- `press_teacher`

### Custom tables

- `{$wpdb->prefix}press_students`
- `{$wpdb->prefix}press_enrollments`
- `{$wpdb->prefix}press_progress`

### Why custom tables are used

- enrollment state needs to be queryable and operational
- progress updates happen frequently
- access state should not depend on fragmented `postmeta`

## 6. Main Domain Modules

### `Frontend.php`

Handles:

- custom LMS routes
- course rendering
- lesson rendering
- catalog rendering
- student dashboard rendering
- public registration form

### `Woo.php`

Handles:

- course/product sync
- add-to-cart validation
- checkout enrollment bootstrap
- order-based activation and revocation
- WooCommerce account integration

Payment compatibility strategy:

- prefer WooCommerce lifecycle hooks over gateway-specific hooks
- treat `woocommerce_payment_complete` as the primary payment-confirmation event
- respect `wc_get_is_paid_statuses()` so custom paid statuses remain compatible
- store the real WooCommerce gateway ID on the enrollment when it is available

### `Enrollments.php`

Handles:

- access windows
- enrollment creation and updates
- access checks
- status labeling
- reactivation and extension flows

### `Progress.php`

Handles:

- lesson progress persistence
- course progress summaries
- resume logic

### `Certificate.php`

Handles:

- completion validation
- certificate placeholder rendering
- admin certificate preview links
- student certificate output

### `Settings.php`

Handles:

- LMS settings page
- admin enrollment panel
- custom CSS tooling for LMS pages

## 7. Frontend Routes

Primary routes:

- `/cursos/`
- `/curso/{course-slug}/`
- `/curso/{course-slug}/aula/{lesson-slug}/`
- `/meus-cursos/`
- `/meus-cursos/certificados/`
- `/perfil/`
- `/perfil/trocar-senha/`
- `/meus-cursos/certificado/{course-slug}/`
- `/cadastro/`

Rewrite logic lives in `includes/Core/Rewrite.php`.

Template compatibility lives in `includes/Core/Templates.php`.

Frontend route rendering lives in `includes/Frontend.php`.

## 8. Payment Compatibility

The LMS should remain gateway-agnostic.

That means payment access rules should be driven by WooCommerce order events, not by a single payment plugin.

Baseline rules:

- pending enrollments can be created before payment is confirmed
- access should be activated when WooCommerce confirms payment or moves the order into a paid status
- access should be revoked only for clearly invalid states such as cancelled, failed, or refunded
- custom gateways should work as long as they correctly update WooCommerce order state or trigger the payment-complete flow

When evaluating a new gateway, verify:

- the order receives a real WooCommerce payment method ID
- the plugin updates order statuses through standard WooCommerce flows
- delayed-confirmation gateways still move the order into a paid status after webhook confirmation
- refunds and payment failures propagate back to WooCommerce order status changes

Reference notes live in `docs/PAYMENT_COMPATIBILITY.md`.

## 9. Security Checklist

When changing the plugin, always verify:

- every state-changing admin action has capability checks
- every state-changing admin action has nonce validation
- AJAX endpoints verify auth, nonce, and object ownership
- admin-post actions do not trust raw `$_GET` or `$_POST`
- uploads are restricted to safe media flows
- redirects go through `wp_safe_redirect()` or `wp_validate_redirect()`
- SQL writes and reads use `$wpdb->prepare()` or structured APIs

### Areas that deserve extra attention

- enrollment management actions
- certificate preview/download actions
- student profile update and avatar upload
- WooCommerce order lifecycle hooks
- virtual route rendering

## 10. Frontend Asset Rules

Frontend asset loading is centralized in `includes/Core/Assets.php`.

Guidelines:

- route checks should use the LMS route context, not ad-hoc `REQUEST_URI` parsing
- custom CSS from settings must be appended after base LMS styles
- external CDN assets should be introduced carefully and documented

## 11. Code Style Expectations

When contributing:

- prefer small domain-focused methods
- sanitize input as close to the boundary as possible
- escape output at render time
- avoid duplicated business rules across modules
- keep comments short and explain intent, not obvious syntax
- preserve the plugin naming conventions already in use

## 12. Recommended Workflow For New Changes

1. Identify which domain owns the behavior.
2. Check whether a helper or existing module already centralizes the rule.
3. Update templates only after the domain logic is correct.
4. Run PHP lint across the plugin.
5. Manually test the affected route or admin flow.
6. Update this guide or `README.md` when architecture changes.

## 13. Release Checklist

- activation still runs without fatal errors
- rewrite rules and virtual routes still resolve correctly
- course purchase still creates pending enrollments
- paid orders still activate access
- invalid orders still revoke access
- common WooCommerce gateways still activate access through standard payment hooks
- lesson progress still persists
- student dashboard still renders every section
- certificate generation still works for admin preview and student access
- custom CSS editor still appends overrides without breaking default styles

## 14. Known Architectural Constraints

- the plugin intentionally changes some global registration settings on activation
- WooCommerce remains the source of truth for order state, while LMS tables remain the source of truth for access state
- frontend routes are virtual and must keep template compatibility stable with builders/themes
- some business behaviors depend on the active theme header/footer because the LMS renders inside theme compatibility mode

## 14. Good First Places To Start Reading

If you are onboarding into the plugin, read in this order:

1. `pressplay-lms.php`
2. `includes/Core/Plugin.php`
3. `includes/Core/Rewrite.php`
4. `includes/Frontend.php`
5. `includes/Woo.php`
6. `includes/Enrollments.php`
7. `includes/Settings.php`
