<p align="center">
  <img src="assets/pressplay_lms_logo.svg" alt="Pressplay LMS" width="180">
</p>

<h1 align="center">Pressplay LMS</h1>

<p align="center">
  <strong>A branded learning-commerce platform built as a WordPress plugin.</strong>
  <br>
  Pressplay LMS transforms WordPress + WooCommerce into a complete online-course operation with custom routes, protected lessons, student dashboards, progress tracking, certificates, payment-aware enrollments, and operational admin tooling.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress 6.0+">
  <img src="https://img.shields.io/badge/WooCommerce-10.x-96588A?style=for-the-badge&logo=woocommerce&logoColor=white" alt="WooCommerce 10.x">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.0+">
  <img src="https://img.shields.io/badge/Vimeo-Integrated-1AB7EA?style=for-the-badge&logo=vimeo&logoColor=white" alt="Vimeo Integrated">
  <img src="https://img.shields.io/badge/HPOS-Compatible-111827?style=for-the-badge" alt="HPOS Compatible">
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Branded-Student%20Experience-0F766E?style=flat-square" alt="Branded Student Experience">
  <img src="https://img.shields.io/badge/Gateway-Agnostic%20Payments-1D4ED8?style=flat-square" alt="Gateway Agnostic Payments">
  <img src="https://img.shields.io/badge/Custom-CSS%20System-B45309?style=flat-square" alt="Custom CSS System">
  <img src="https://img.shields.io/badge/Certificates-Ready-7C3AED?style=flat-square" alt="Certificates Ready">
  <img src="https://img.shields.io/badge/Operations-Ready-111827?style=flat-square" alt="Operations Ready">
</p>

---

## Recruiter Snapshot

| What this project is | What it proves |
| --- | --- |
| A full LMS product plugin, not just a theme customization | Product thinking, plugin architecture, and real-world business flow design |
| Deep WooCommerce integration | Checkout, cart, payment lifecycle, enrollment activation, and access revocation |
| A custom branded student experience | Frontend routing, theme compatibility, UX ownership, and WordPress rendering control |
| An operational platform, not only course pages | Support-minded engineering with enrollment management, extensions, reactivation, and certificates |

> Pressplay LMS was built to make WordPress feel like a real education product, not a stack of disconnected plugins.

---

## Why This Project Stands Out

- It connects catalog, purchase, access, learning progress, and certificates in one coherent product flow.
- It keeps students out of `wp-admin` and delivers a branded frontend experience with custom LMS routes.
- It uses WooCommerce as the payment and order engine without locking the LMS to one single gateway.
- It includes operational tooling for support teams, not only content delivery for students.
- It adds a real customization layer with theme-aware and Elementor-aware CSS overrides for the LMS UI.

---

## Feature Surface

| Product pillar | Implemented capabilities |
| --- | --- |
| **Commerce & enrollment** | Course-to-product sync, LMS-controlled purchase flow, checkout bootstrap, payment-aware activation, revocation on failed/cancelled/refunded orders, pending and active enrollments, payment method tracking |
| **Learning experience** | Public catalog, branded course pages, protected lesson routes, lesson progression, resume behavior, progress summaries, certificate unlock on real completion |
| **Student area** | Custom dashboard, student library, certificate center, profile editing, avatar upload, password management, login-aware redirects |
| **Content operations** | Course, lesson, and teacher models, lesson materials, Vimeo metadata and embed support, certificate templates with dynamic placeholders, access windows by days, months, years, or lifetime |
| **Admin tooling** | Enrollment filtering, blocking, reactivation, access extension, password assistance flows, notice handling, student support operations |
| **Branding & customization** | Theme-compatible frontend rendering, brand-aware public titles, custom CSS editor, Elementor/WordPress/theme variable suggestions, dark editor experience, variable copy shortcuts |
| **Platform quality** | Custom tables for enrollments and progress, secure action handlers, nonce and capability validation, WooCommerce HPOS compatibility, product instance caching compatibility, release documentation |

---

## Experience Flow

```mermaid
flowchart LR
  A[Public Catalog] --> B[Course Page]
  B --> C[LMS Enrollment CTA]
  C --> D[WooCommerce Checkout]
  D --> E[Pending Enrollment]
  E --> F[Payment Confirmed]
  F --> G[Active Access]
  G --> H[Lesson Progress]
  H --> I[Course Completion]
  I --> J[Certificate Center]
```

---

## Key Differentiators

### 1. Brand-first LMS experience

This plugin does not expose the internal product identity to the final customer. The public experience reflects the brand that installs it, including branded titles and a custom student area that feels native to the site.

### 2. Universal WooCommerce payment strategy

Instead of coupling the LMS to one gateway, Pressplay LMS follows the WooCommerce payment lifecycle. That makes it naturally compatible with well-behaved gateways such as PayPal, Mercado Pago, PagBank/PagSeguro, Stripe, and similar WooCommerce extensions.

### 3. Real operational maturity

The plugin handles more than content access. It includes enrollment status management, access extension, reactivation, blocking, expiration logic, and certificate release rules, which is the kind of detail real businesses need after the sale.

### 4. Advanced theming control

The LMS can keep its default design or accept only the CSS overrides the brand wants to change. The custom CSS editor suggests variables from Elementor, WordPress, and the active theme, which makes visual adaptation much faster and more professional.

---

## What This Project Demonstrates Technically

- Strong WordPress plugin architecture with custom post types, rewrites, admin screens, templates, and lifecycle hooks
- Deep WooCommerce knowledge across cart, checkout, paid statuses, refunds, and feature compatibility
- Productized access control using custom tables instead of fragile `postmeta`-only state
- Secure handling of admin-post, AJAX, permissions, nonces, redirects, and ownership validation
- Frontend implementation that balances custom product UX with theme compatibility
- Developer maturity through release notes, internal docs, payment compatibility notes, and collaboration guides

---

## Public Frontend Footprint

| Route | Purpose |
| --- | --- |
| `/cursos/` | Public course catalog |
| `/curso/{course-slug}/` | Course landing page and purchase entry point |
| `/curso/{course-slug}/aula/{lesson-slug}/` | Protected lesson experience |
| `/meus-cursos/` | Student dashboard and course library |
| `/meus-cursos/certificados/` | Certificate center |
| `/meus-cursos/certificado/{course-slug}/` | Individual certificate output |
| `/perfil/` | Student profile page |
| `/perfil/trocar-senha/` | Password management |
| `/cadastro/` | Student registration |

---

## Architecture Snapshot

```text
pressplay_lms/
├── assets/        # Frontend and admin CSS, JS, icons, logos
├── docs/          # Internal guides for collaboration, payments, releases
├── includes/
│   ├── Core/      # Bootstrap, activation, dependencies, rewrites, templates, assets
│   ├── Support/   # Shared helpers
│   └── *.php      # Main LMS modules such as Frontend, Woo, Enrollments, Progress
├── templates/
│   ├── certificado/
│   ├── frontend/
│   └── panel/
├── pressplay-lms.php
└── uninstall.php
```

### Main modules

| Module | Responsibility |
| --- | --- |
| `Frontend.php` | Catalog, course pages, lesson pages, student dashboard, registration, branded page titles |
| `Woo.php` | Product sync, payment-aware enrollment lifecycle, WooCommerce compatibility |
| `Enrollments.php` | Access windows, status rules, activation, reactivation, extension, pause handling |
| `Progress.php` | Lesson tracking, course summaries, completion state |
| `Certificate.php` | Completion validation, certificate output, admin preview |
| `Actions.php` | Enrollment CTA flow, profile updates, password changes, AJAX actions |
| `Settings.php` | Brand settings, admin operations, CSS customization tooling |

---

## Stack

| Technology | Role in the project |
| --- | --- |
| WordPress | Plugin runtime, hooks, admin UI, custom post types, media handling |
| WooCommerce | Products, cart, checkout, order lifecycle, payment state |
| PHP 8+ | Business rules, rendering, validation, integrations |
| Vimeo API | Video metadata, embed support, duration and preview workflows |
| Custom tables | Scalable storage for students, enrollments, and progress |

---

## Why It Feels Production-Ready

- Supports WooCommerce feature compatibility declarations for modern stores
- Uses route-driven frontend rendering with active-theme integration
- Keeps payment support universal through WooCommerce instead of hardcoded provider logic
- Includes operational documentation for future contributors
- Has release and versioning guidance for long-term maintenance

---

## Developer Docs

- [Developer Guide](docs/DEVELOPER_GUIDE.md)
- [Release Guide](docs/RELEASING.md)
- [Payment Compatibility Notes](docs/PAYMENT_COMPATIBILITY.md)

---

## Installation

1. Copy the plugin into the WordPress plugins directory.
2. Activate the plugin.
3. Make sure WooCommerce is active.
4. Save permalinks once if the LMS routes need refreshing.

---

## Short Portfolio Description

Pressplay LMS is a custom WordPress LMS plugin built to turn WordPress + WooCommerce into a complete online-course business. It delivers a branded student experience, protected lessons, progress tracking, certificates, payment-aware enrollments, and operational admin tooling in one cohesive product flow.
