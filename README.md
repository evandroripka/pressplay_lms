<p align="center"><img src="assets/pressplay_lms_logo.png" alt="Pressplay LMS" width="220"></p>

<p align="center"><strong>Pressplay LMS</strong></p>
<p align="center">A commerce-ready WordPress LMS built to sell, deliver, protect, and operate online courses inside a custom student experience.</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-Plugin-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="WordPress Plugin">
  <img src="https://img.shields.io/badge/WooCommerce-Integrated-96588A?style=for-the-badge&logo=woocommerce&logoColor=white" alt="WooCommerce Integrated">
  <img src="https://img.shields.io/badge/Frontend-Custom%20Student%20Area-111827?style=for-the-badge" alt="Custom Student Area">
  <img src="https://img.shields.io/badge/Certificates-Automated-0F766E?style=for-the-badge" alt="Automated Certificates">
  <img src="https://img.shields.io/badge/Theme-Compatible-Header%20%26%20Footer-1D4ED8?style=for-the-badge" alt="Theme Compatible">
</p>

---

## Overview

Pressplay LMS is a custom WordPress plugin designed to turn WordPress + WooCommerce into a complete course business workflow.

It was built to solve a practical product problem:

- sell courses through WooCommerce
- control student access beyond checkout
- provide a branded student area instead of relying on wp-admin
- track lesson progress and unlock certificates
- give administrators operational control over enrollments, expirations, and student lifecycle

This is not just a course post type plugin. It is a full product flow that connects content, commerce, access control, student account management, and course completion.

---

## Why This Plugin Exists

Most LMS setups inside WordPress feel fragmented:

- checkout lives in one place
- lessons live in another
- student profile is generic
- certificates feel bolted on
- operational control is weak once the payment is completed

Pressplay LMS was built to create a more intentional experience:

- a public catalog to discover courses
- a conversion-focused course page
- a protected lesson experience
- a dedicated student library
- a clean account area for profile and password management
- certificate delivery tied to real progress
- enrollment lifecycle rules that reflect real commerce events

---

## What Was Built

| Area | What it delivers | Why it matters |
| --- | --- | --- |
| Course Commerce | WooCommerce product sync, checkout integration, purchase validation, duplicate-purchase protection | Keeps WordPress in charge of content while WooCommerce remains the commerce engine |
| Student Experience | Custom routes for catalog, course, lesson, library, certificates, profile, and password | Creates a product-like experience instead of sending students into wp-admin |
| Access Control | Pending, active, blocked, expired, refunded, cancelled, and failed enrollment states | Makes course access behave like a real subscription/access product |
| Learning Flow | Lesson progression tracking, resume behavior, completion logic, and certificate unlock | Connects content consumption with measurable outcomes |
| Admin Operations | Student listing, enrollment actions, extensions, reactivation, and access management | Makes the plugin operationally usable, not just visually complete |
| Account Management | Student profile editing, avatar upload, email/phone updates, password change | Reduces friction and keeps students self-sufficient |
| Theme Compatibility | LMS frontend routes rendered with the active WordPress theme header and footer | Makes the LMS feel native to the site and compatible with theme builders |

---

## Frontend Journey

These routes were designed intentionally. Each one exists for a specific stage of the student or buyer journey.

| Route | Role in the product | Why this path exists |
| --- | --- | --- |
| `/cursos/` | Public catalog | Discovery layer for browsing all published courses |
| `/curso/{course-slug}/` | Course landing page | Sales and decision page with overview, trailer, metadata, and CTA |
| `/curso/{course-slug}/aula/{lesson-slug}/` | Protected lesson view | Focused learning environment for enrolled students |
| `/meus-cursos/` | Student library | Central hub for active enrollments, progress, and resume actions |
| `/meus-cursos/certificados/` | Certificate center | Dedicated place for proof of completion and re-issuance |
| `/perfil/` | Student account page | Clean account editing flow outside the course library |
| `/perfil/trocar-senha/` | Security screen | Dedicated password update route for clarity and UX |
| `/cadastro/` | Student onboarding | Purpose-built registration experience for new students |

---

## Product Flow

```text
Catalog -> Course Page -> WooCommerce Checkout -> Enrollment Activation
-> Student Library -> Lesson Progress -> Course Completion -> Certificate
```

The plugin connects these steps with real state transitions instead of treating them as isolated pages.

---

## How It Works

### 1. Course creation

Admins create:

- courses
- lessons
- teachers
- certificate configuration
- course features and metadata

Each course can also define its access window:

- lifetime
- days
- months
- years

### 2. Commerce integration

Each course is linked to a WooCommerce product.

When a student starts checkout:

- a pending enrollment can be created before payment is complete
- the order is attached to the enrollment
- access is activated after valid WooCommerce payment states
- access is revoked when the order becomes cancelled, failed, or refunded

### 3. Learning access

Once access is active, the student can:

- open the course page
- continue from the next lesson
- track lesson progress
- see access validity
- return to their library at any time

### 4. Completion and certificate

When the student reaches full completion:

- the course is marked as completed
- the certificate becomes available
- the student can reissue it later from the dashboard

### 5. Operations and lifecycle

From the admin side, the team can:

- block access
- reactivate enrollments
- extend access windows
- filter students by status
- inspect progress and course relationship

---

## Student Experience Highlights

- Custom student dashboard with a real library feel
- Separate profile and password routes for cleaner UX
- Avatar upload and editable profile data
- Course progress summary and resume actions
- Access validity labels such as lifetime or expiration date
- Certificate availability independent from access expiration
- WooCommerce account entry point connected to the LMS profile area

---

## Admin and Business Highlights

- Custom student role
- WooCommerce-backed enrollment activation
- Order-aware access revocation
- Manual enrollment operations for real support workflows
- Certificate generation with configurable placeholders
- Video and duration support through Vimeo and external embeds
- Theme-compatible frontend routes that inherit the active site header/footer

---

## Recruiter Notes

This plugin is a good representation of practical WordPress product engineering because it combines:

- custom post types and custom database tables
- WooCommerce order lifecycle integration
- access control logic and expiration handling
- theme-compatible frontend routing
- custom student UX instead of default wp-admin flows
- business-oriented operational tooling for real support and enrollment management

In short, this project shows not only WordPress coding, but product thinking:

- why each screen exists
- how money flow affects access
- how completion affects certification
- how admin tools support real-world operations
- how UX and backend logic stay aligned

---

## Architecture at a Glance

```text
pressplay_lms/
├── assets/        # Styles, scripts, logo, and material icons
├── includes/
│   ├── Core/      # Bootstrap, activation, assets, rewrites, templates
│   ├── Support/   # Shared helpers
│   └── *.php      # Domain modules: enrollments, frontend, Woo, mailer, progress
├── templates/
│   ├── certificado/
│   ├── frontend/
│   └── panel/
└── README.md
```

Main modules:

- `Frontend.php`: custom routes, student dashboard, course and lesson rendering
- `Woo.php`: WooCommerce bridge for products, cart, checkout, and account integration
- `Enrollments.php`: access rules, statuses, expirations, reactivation, and extensions
- `Progress.php`: lesson completion and course progress
- `Certificate.php`: validation and certificate output
- `Actions.php`: profile, password, enrollment, and AJAX actions
- `Settings.php`: operational admin screens

---

## Dependencies

Required:

- WordPress
- WooCommerce

Optional:

- Vimeo API token for richer video metadata and duration sync

---

## Installation and Usage

### Install

1. Copy the plugin into the WordPress plugins directory.
2. Activate the plugin in WordPress.
3. Make sure WooCommerce is active.
4. Save permalinks once after activation if custom routes need refreshing.

### Basic usage

1. Create a course.
2. Add lessons and a teacher.
3. Configure product and access rules.
4. Publish the course.
5. Let students register, purchase, access lessons, and complete the course.

---

## Current Positioning

Pressplay LMS is positioned as a custom learning commerce plugin for creators, schools, studios, and teams that need:

- direct course sales
- protected educational content
- a custom student area
- operational control over access and enrollments
- a branded LMS experience inside WordPress

---

## Final Summary

Pressplay LMS was built as a real product system, not only as a content plugin.

It combines:

- WordPress content modeling
- WooCommerce commerce flow
- student access lifecycle
- progress tracking
- certificate delivery
- profile/account management
- theme-compatible frontend UX

The result is a more cohesive learning product inside WordPress, with both product experience and operational depth.
