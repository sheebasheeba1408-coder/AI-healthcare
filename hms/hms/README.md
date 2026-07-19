# 🏥 MedCare HMS — Hospital Management System

A fully deployable Hospital Management System built with **PHP 8.2**, **MySQL 8.0**, and **Docker**.

---

## 🚀 Quick Start (Docker)

### Prerequisites
- Docker Desktop installed and running
- Git (optional)

### 1. Deploy in 3 commands

```bash
# Clone or extract the project
cd hms

# Start all services
docker-compose up -d --build

# Wait ~30 seconds for MySQL to initialize, then open:
# http://localhost:8080
```

### 2. Login Credentials

| Role         | Username       | Password   |
|--------------|---------------|------------|
| Admin        | admin          | password   |
| Doctor       | dr.smith       | password   |
| Doctor       | dr.priya       | password   |
| Nurse        | nurse.anita    | password   |
| Receptionist | receptionist1  | password   |

> **phpMyAdmin**: http://localhost:8081

---

## 📦 Project Structure

```
hms/
├── docker-compose.yml      # Docker orchestration
├── Dockerfile              # PHP 8.2 + Apache
├── apache.conf             # Apache virtual host config
├── config/
│   └── database.php        # DB connection & helpers
├── database/
│   └── init.sql            # Full schema + seed data
├── includes/
│   ├── auth.php            # Auth, session, logging
│   ├── header.php          # Sidebar + topbar layout
│   └── footer.php          # Closing tags
└── public/                 # Web root
    ├── index.php           # Redirect to login
    ├── login.php           # Login page
    ├── logout.php          # Session destroy
    ├── dashboard.php       # Main dashboard
    ├── patients.php        # Patient CRUD
    ├── appointments.php    # Appointment booking
    ├── doctors.php         # Doctor management
    ├── admissions.php      # Ward admissions
    ├── medical_records.php # Medical records + prescriptions
    ├── pharmacy.php        # Medicine inventory
    ├── lab.php             # Lab orders & results
    ├── billing.php         # Billing & invoicing
    ├── wards.php           # Wards & bed management
    ├── departments.php     # Departments
    ├── users.php           # User management (admin)
    ├── reports.php         # Analytics & reports (admin)
    ├── css/style.css       # Full custom UI
    └── js/app.js           # Frontend interactions
```

---

## 🌟 Features

### Patient Management
- Register patients with full demographics
- Blood group, allergies, insurance info
- Patient ID auto-generation (PAT-001...)
- Search, view, edit, delete

### Appointments
- Book by doctor, date, time slot
- Status workflow: Scheduled → Confirmed → Completed
- Filter by date, doctor, status

### Admissions & Beds
- Ward overview with occupancy %
- Real-time bed status grid (Available/Occupied/Maintenance)
- One-click discharge

### Medical Records
- Symptoms, diagnosis, treatment notes
- Integrated prescription writing
- Follow-up date tracking

### Pharmacy
- Medicine inventory with stock levels
- Low-stock alerts + restock modal
- Expiry date tracking

### Laboratory
- Lab test catalog management
- Order creation with multi-test selection
- Result entry by technician

### Billing & Invoicing
- Line-item billing (consultation, medicine, lab, procedure)
- Auto tax (5%) calculation
- Print-ready invoice
- Payment tracking (Paid/Partial/Pending)

### Reports (Admin)
- Monthly revenue summary
- Appointment status breakdown
- Top doctors ranking
- Department analytics
- Bed occupancy heat map
- Daily revenue table

### User Management (Admin)
- 6 roles: Admin, Doctor, Nurse, Receptionist, Pharmacist, Lab Tech
- Activate/deactivate accounts
- Password reset

---

## 🐳 Docker Services

| Service     | Port  | Description          |
|-------------|-------|----------------------|
| PHP/Apache  | 8080  | Main application     |
| MySQL       | 3307  | Database             |
| phpMyAdmin  | 8081  | DB management UI     |

---

## ⚙️ Manual Setup (without Docker)

```bash
# 1. Import the SQL file into MySQL
mysql -u root -p < database/init.sql

# 2. Update config/database.php with your credentials
# 3. Point Apache/Nginx DocumentRoot to /public
# 4. Enable mod_rewrite
```

---

## 🔒 Security Notes
- Passwords hashed with bcrypt
- All user inputs sanitized via `sanitize()`
- PDO prepared statements throughout
- Role-based access control (RBAC)
- Session-based authentication

---

## 📞 Support
Built by MedCare HMS v1.0
