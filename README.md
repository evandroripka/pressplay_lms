# 🎓 Pressplay LMS

A lightweight and powerful WordPress LMS plugin designed to simplify course sales and student management — built with performance, flexibility and Brazilian payment gateways in mind.

> Our mission is to provide a free, modern and developer-friendly LMS solution for the WordPress community.

---

## 🚀 Why Pressplay LMS?

Most LMS plugins are either:
- Overcomplicated
- Expensive
- Bloated with features most creators don’t need

Pressplay LMS focuses on:

✔ Simple course structure  
✔ Clean architecture  
✔ Full control over UI  
✔ Brazilian checkout transparency support  
✔ Developer-first approach  

---

## ✨ Core Features (v1 Roadmap)

### 📚 Course Management
- Create unlimited courses
- Create lessons inside each course
- Attach materials per lesson or per course:
  - PDFs
  - External links
  - Downloadable files
  - Custom notes

### 👨‍🎓 Student Management
- Custom student role automatically created
- Custom student registration fields:
  - Full name
  - Phone number (with DDD)
  - Valid email
- Course access controlled via enrollment
- Automatic expiration (1-year access)

### 💳 Payments
- Transparent checkout integration
- Designed to work with:
  - Mercado Pago
  - PagSeguro
- Payment plugin independent (LMS handles logic, gateway handles transaction)

### 📈 Learning Progress
- Track lesson completion per student
- Course progress percentage
- Automatic course completion detection

### 🏆 Certificates
- Auto-generate certificate when:
  - All lessons marked as completed
- Certificate sent via email
- Customizable email template with logo support

### 🔐 Video Protection
- Vimeo embed only
- No direct download access
- Frontend protection layer

---

## 🎨 UI Philosophy

Pressplay LMS does not rely on WordPress default UI.

- Custom dashboard area
- Custom admin screens
- SVG icon support
- Dedicated CSS namespace
- Modern component-based styling

---

## 🛠 Technical Stack

- PHP 8+
- WordPress Hooks API
- Custom Post Types
- Custom Roles
- Custom Capabilities
- REST-ready architecture (future)
- Object-oriented plugin structure

---

## 📂 Plugin Structure

pressplay-lms/
│
├── assets/
│ ├── css/
│ ├── js/
│ └── svg/
│
├── includes/
│ ├── class-cpt.php
│ ├── class-roles.php
│ ├── class-enrollment.php
│ ├── class-progress.php
│ ├── class-certificate.php
│ └── class-payment-handler.php
│
├── malibu-lms.php
├── uninstall.php
└── README.md


---

## 🌎 Vision

Pressplay LMS aims to:

- Empower independent course creators
- Provide a free alternative for emerging markets
- Deliver clean code and extensibility
- Strengthen the open-source WordPress ecosystem

---

## 🤝 Contributing

Pull requests are welcome.  
Let’s build something meaningful for the WordPress community.

---

## 📄 License

GPL v2 or later
