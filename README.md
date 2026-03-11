# Pressplay LMS

Pressplay LMS is a WordPress plugin for selling and delivering online courses with a custom student experience inside WordPress.

## Current scope

The plugin currently covers these areas:

- course, lesson, and teacher management
- WooCommerce product synchronization for courses
- enrollment creation and activation
- protected course and lesson access
- lesson progress tracking
- certificate generation after completion
- student dashboard and profile actions
- custom frontend pages for course, lesson, student dashboard, registration, and course catalog

## Main features

### Course and lesson model

- Custom post type `press_course` for courses
- Custom post type `press_lesson` for lessons
- Custom post type `press_teacher` for teachers
- Course-to-lesson relationship stored through `post_parent` and `_press_lesson_course_id`
- Lesson creation and editing routed from the course editor
- Course editor organized in tabs for settings, certificate, features, and lessons

### Frontend routes

The plugin uses custom frontend routes instead of relying only on native single templates:

- `/curso/{course-slug}`
- `/curso/{course-slug}/aula/{lesson-slug}`
- `/meus-cursos`
- `/cadastro`
- `/cursos`

The student certificate flow is available from the student dashboard and resolves through the frontend instead of the WordPress admin.

### Student dashboard

The student area currently includes:

- active course library
- progress summary
- certificate list
- certificate reissue links
- account summary
- password change form

Login redirects for students and enrolled users point to `/meus-cursos` by default when no explicit redirect target is present.

### Access and enrollment flow

- Student role `press_student`
- Admin area blocked for student accounts
- Enrollment statuses managed in a custom table
- Pending enrollment created before checkout
- Enrollment activated after WooCommerce order processing/completion
- Course pause mode blocks new enrollments while preserving access for enrolled students

### WooCommerce integration

- A WooCommerce product is created or updated from the course editor
- Course products are sold individually
- Duplicate quantities are prevented in the cart
- Product visibility is adjusted when a course is paused
- WooCommerce loop and single product buttons can link back to the course page

### Video, duration, and materials

- Vimeo and YouTube URLs supported at lesson level
- Vimeo API integration for validation, duration, and thumbnail data
- Automatic course duration recalculation from published lessons
- Lesson materials support attachments and external links
- Material type detection with SVG icon mapping

### Certificates

- Certificate layout is configurable per course
- Certificate placeholders include student name, course name, duration, completion date, description, logo, and signature
- Certificate access is restricted to completed courses, except for administrators

## Database tables

The plugin manages these custom tables:

- `wp_press_students`
- `wp_press_enrollments`
- `wp_press_progress`

They are used for:

- extended student profile data
- enrollment records and expiration windows
- lesson progress and completion timestamps

## Activation behavior

On activation, the plugin:

- runs database migrations
- registers the student role
- registers custom post types
- registers frontend rewrite rules
- enables public registration in WordPress
- sets `press_student` as the default role when available
- aligns core WooCommerce account settings with the LMS enrollment flow
- flushes rewrite rules

## Directory structure

```text
pressplay-lms/
├── assets/
│   ├── css/
│   ├── js/
│   └── svg/
├── includes/
│   ├── Core/
│   │   ├── Activator.php
│   │   ├── Assets.php
│   │   ├── Deactivator.php
│   │   ├── Dependencies.php
│   │   ├── Plugin.php
│   │   ├── Rewrite.php
│   │   └── Templates.php
│   ├── Support/
│   │   └── Helpers.php
│   ├── Actions.php
│   ├── CPT.php
│   ├── CPT_Teacher.php
│   ├── Certificate.php
│   ├── Course_Lifecycle.php
│   ├── Database.php
│   ├── Duration.php
│   ├── Enrollments.php
│   ├── Frontend.php
│   ├── Mailer.php
│   ├── Materials.php
│   ├── Metabox_Course.php
│   ├── Metabox_Lesson.php
│   ├── Metabox_Teacher.php
│   ├── Progress.php
│   ├── Roles.php
│   ├── Settings.php
│   ├── Vimeo.php
│   └── Woo.php
├── templates/
│   ├── certificado/
│   ├── frontend/
│   └── panel/
├── pressplay-lms.php
└── README.md
```

## Architectural notes

The plugin is currently organized by responsibility instead of a strict MVC structure.

- `includes/Core`: bootstrap, activation, rewrite, assets, templates, and dependency checks
- `includes/Support`: shared helpers
- `includes/*.php`: domain and workflow modules
- `templates/`: frontend and certificate rendering
- `assets/`: CSS, JavaScript, and SVG resources

This structure is intentionally incremental and keeps compatibility with WordPress plugin conventions while the codebase is being reorganized.

## Dependencies

Required:

- WooCommerce

Optional:

- Vimeo access token for API validation, duration sync, and thumbnails
- Mercado Pago for WooCommerce when that payment gateway is needed

## Notes for development

- Custom routes depend on rewrite rules being registered correctly
- Some student-facing flows intentionally bypass theme templates and render through the plugin
- WooCommerce remains the source of truth for checkout and order status changes
