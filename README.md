# 🎓 Pressplay LMS

A lightweight WordPress LMS plugin focused on clean architecture, WooCommerce integration and controlled course access.

Built for performance, extensibility and real-world Brazilian payment flows.

---

## 🚀 Current Version (v0.1.x)

Pressplay LMS currently provides:

### 📚 Course System
- Custom Post Type: Courses (press_course)
- Custom Post Type: Lessons (press_lesson)
- Course → Lesson relationship via post meta
- Custom frontend rendering (no theme dependency)
- Clean URL structure:
  - /curso/{course-slug}
  - /curso/{course-slug}/aula/{lesson-slug}

---

### 👨‍🎓 Student System
- Custom role: `press_student`
- Custom registration form via shortcode `[press_register]`
- Extra student fields:
  - Full name
  - Phone
- Automatic email with "set password" link
- Student profile stored in custom table

---

### 💳 WooCommerce Integration
- Course automatically linked to WooCommerce product
- Enrollment button shown when user is not enrolled
- Redirect to WooCommerce checkout
- Automatic enrollment on order completion
- Enrollment expiration supported
- Access control based on:
  - Logged-in status
  - Enrollment active status
  - Expiration date
  - Administrator bypass

---

### 🔐 Access Control
- Lessons visible only to:
  - Enrolled students (active enrollment)
  - Administrators
- Expired enrollments block lesson access
- Course page displays "Matricular" button when access is restricted

---

### 🧱 Database Layer
Custom tables:

- `wp_press_students`
- `wp_press_enrollments`
- `wp_press_progress`

Enrollment table supports:
- Status
- Provider reference
- Expiration date
- Order reference

---

### ⚙ Automatic WordPress Configuration

On activation the plugin:

- Creates custom roles
- Registers CPTs
- Registers custom rewrite rules
- Flushes rewrite rules
- Enables public user registration
- Sets default role to `press_student`

This ensures zero manual configuration required.

---

## 🛠 Architecture

- Object-oriented structure
- Hook-based integration
- WooCommerce order hooks
- Custom rewrite system
- Custom frontend rendering (template_include)
- Clean separation of:
  - CPT
  - Roles
  - Enrollment logic
  - Woo integration
  - Frontend rendering
  - Database layer

---

## 📂 Current Plugin Structure

pressplay-lms/
│
├── assets/
│   ├── css/
│   └── js/
│
├── includes/
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── CPT.php
│   ├── Database.php
│   ├── Dependencies.php
│   ├── Enrollments.php
│   ├── Frontend.php
│   ├── Helpers.php
│   ├── Mailer.php
│   ├── Metabox_Course.php
│   ├── Metabox_Lesson.php
│   ├── Rewrite.php
│   ├── Roles.php
│   ├── Settings.php
│   ├── Templates.php
│   └── Woo.php
│
├── pressplay-lms.php
├── uninstall.php
└── README.md

---

## 📌 Roadmap

Planned next improvements:

- Improved progress tracking
- Certificate generation
- Real admin dashboard for enrollments
- Better settings UI
- Anti-spam protection on registration
- REST API endpoints
- Improved sanitization & validation

---

## 🌎 Vision

Pressplay LMS aims to be:

- Developer-first
- Lightweight
- WooCommerce-native
- Cleanly extensible
- Open-source friendly

---

## 🤝 Contributing

Pull requests are welcome.  
Let's build a modern and clean LMS for the WordPress ecosystem.

---

## 📄 License

GPL v2 or later
