CREATE DATABASE IF NOT EXISTS hospital_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospital_db;

-- Users / Staff
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin','doctor','nurse','receptionist','pharmacist','lab_tech') NOT NULL,
    phone VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    head_doctor_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department_id INT,
    specialization VARCHAR(100),
    qualification VARCHAR(200),
    experience_years INT DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    available_days VARCHAR(100) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Patients
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    dob DATE,
    gender ENUM('Male','Female','Other') NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    allergies TEXT,
    medical_history TEXT,
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(100),
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Wards / Rooms
CREATE TABLE wards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_name VARCHAR(100) NOT NULL,
    ward_type ENUM('General','ICU','Private','Semi-Private','Emergency') NOT NULL,
    total_beds INT NOT NULL,
    available_beds INT NOT NULL,
    price_per_day DECIMAL(10,2) DEFAULT 0.00,
    floor_number INT DEFAULT 1
);

-- Beds
CREATE TABLE beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    status ENUM('Available','Occupied','Maintenance') DEFAULT 'Available',
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE
);

-- Admissions
CREATE TABLE admissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    bed_id INT,
    doctor_id INT,
    admission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    discharge_date DATETIME,
    diagnosis TEXT,
    status ENUM('Admitted','Discharged','Transferred') DEFAULT 'Admitted',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (bed_id) REFERENCES beds(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Appointments
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    type ENUM('Consultation','Follow-up','Emergency','Routine Check') DEFAULT 'Consultation',
    status ENUM('Scheduled','Confirmed','Completed','Cancelled','No-Show') DEFAULT 'Scheduled',
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Medical Records / Prescriptions
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    visit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    symptoms TEXT,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    follow_up_date DATE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Medicines
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    generic_name VARCHAR(100),
    category VARCHAR(100),
    manufacturer VARCHAR(100),
    unit VARCHAR(50),
    unit_price DECIMAL(10,2) NOT NULL,
    stock_quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    expiry_date DATE,
    description TEXT
);

-- Prescriptions
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    FOREIGN KEY (record_id) REFERENCES medical_records(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- Lab Tests
CREATE TABLE lab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    normal_range VARCHAR(200),
    category VARCHAR(100)
);

-- Lab Orders
CREATE TABLE lab_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Processing','Completed','Cancelled') DEFAULT 'Pending',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Lab Order Items
CREATE TABLE lab_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    test_id INT NOT NULL,
    result TEXT,
    result_date DATETIME,
    technician_id INT,
    FOREIGN KEY (order_id) REFERENCES lab_orders(id),
    FOREIGN KEY (test_id) REFERENCES lab_tests(id)
);

-- Billing
CREATE TABLE bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_number VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    admission_id INT,
    bill_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    discount DECIMAL(12,2) DEFAULT 0.00,
    tax DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('Pending','Partial','Paid','Cancelled') DEFAULT 'Pending',
    payment_method ENUM('Cash','Card','Insurance','Online','Cheque') DEFAULT 'Cash',
    notes TEXT,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Bill Items
CREATE TABLE bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    item_type ENUM('Consultation','Admission','Medicine','Lab Test','Procedure','Other') NOT NULL,
    description VARCHAR(200) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- Activity Log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============ SEED DATA ============

-- Default Admin
INSERT INTO users (username, password, full_name, email, role, phone) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@hospital.com', 'admin', '9999999999'),
('dr.smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. John Smith', 'john.smith@hospital.com', 'doctor', '9876543210'),
('dr.priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Priya Sharma', 'priya.sharma@hospital.com', 'doctor', '9876543211'),
('nurse.anita', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nurse Anita Roy', 'anita.roy@hospital.com', 'nurse', '9876543212'),
('receptionist1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ravi Kumar', 'ravi.kumar@hospital.com', 'receptionist', '9876543213');

-- Departments
INSERT INTO departments (name, description) VALUES
('Cardiology', 'Heart and cardiovascular diseases'),
('Neurology', 'Brain and nervous system disorders'),
('Orthopedics', 'Bone and joint disorders'),
('Pediatrics', 'Medical care for children'),
('General Medicine', 'General health and wellness'),
('Emergency', 'Emergency and critical care'),
('Radiology', 'Imaging and diagnostics'),
('Pharmacy', 'Medicine dispensary');

-- Doctors
INSERT INTO doctors (user_id, department_id, specialization, qualification, experience_years, consultation_fee) VALUES
(2, 1, 'Cardiologist', 'MBBS, MD Cardiology', 15, 800.00),
(3, 5, 'General Physician', 'MBBS, MD General Medicine', 10, 500.00);

-- Wards
INSERT INTO wards (ward_name, ward_type, total_beds, available_beds, price_per_day, floor_number) VALUES
('Ward A', 'General', 20, 18, 1000.00, 1),
('Ward B', 'Semi-Private', 10, 8, 2500.00, 2),
('ICU', 'ICU', 5, 3, 8000.00, 3),
('Private Suite', 'Private', 8, 6, 5000.00, 2),
('Emergency Ward', 'Emergency', 10, 7, 3000.00, 1);

-- Beds
INSERT INTO beds (ward_id, bed_number, status) VALUES
(1,'A-001','Available'),(1,'A-002','Occupied'),(1,'A-003','Available'),(1,'A-004','Available'),(1,'A-005','Available'),
(2,'B-001','Available'),(2,'B-002','Occupied'),(2,'B-003','Available'),
(3,'ICU-001','Available'),(3,'ICU-002','Occupied'),(3,'ICU-003','Available'),
(4,'PS-001','Available'),(4,'PS-002','Available'),(4,'PS-003','Occupied'),
(5,'ER-001','Available'),(5,'ER-002','Available'),(5,'ER-003','Occupied');

-- Medicines
INSERT INTO medicines (name, generic_name, category, unit, unit_price, stock_quantity, reorder_level, expiry_date) VALUES
('Paracetamol 500mg', 'Acetaminophen', 'Analgesic', 'Strip', 25.00, 500, 50, '2026-12-31'),
('Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'Strip', 85.00, 300, 30, '2026-06-30'),
('Metformin 500mg', 'Metformin HCl', 'Antidiabetic', 'Strip', 45.00, 400, 40, '2026-09-30'),
('Atorvastatin 10mg', 'Atorvastatin', 'Statin', 'Strip', 120.00, 200, 25, '2026-08-31'),
('Omeprazole 20mg', 'Omeprazole', 'Antacid', 'Strip', 65.00, 350, 35, '2026-11-30'),
('Aspirin 75mg', 'Acetylsalicylic acid', 'Antiplatelet', 'Strip', 30.00, 450, 50, '2026-10-31'),
('Ciprofloxacin 500mg', 'Ciprofloxacin', 'Antibiotic', 'Strip', 95.00, 250, 30, '2026-07-31'),
('Insulin Glargine', 'Insulin', 'Antidiabetic', 'Vial', 450.00, 100, 15, '2026-05-31'),
('Normal Saline 500ml', 'Sodium Chloride', 'IV Fluid', 'Bottle', 55.00, 200, 30, '2026-12-31'),
('Azithromycin 500mg', 'Azithromycin', 'Antibiotic', 'Strip', 110.00, 180, 25, '2026-09-30');

-- Lab Tests
INSERT INTO lab_tests (name, description, price, normal_range, category) VALUES
('Complete Blood Count (CBC)', 'Full blood panel analysis', 350.00, 'Various', 'Hematology'),
('Blood Glucose Fasting', 'Fasting blood sugar test', 150.00, '70-99 mg/dL', 'Biochemistry'),
('Lipid Profile', 'Cholesterol and lipids panel', 450.00, 'Various', 'Biochemistry'),
('Liver Function Test (LFT)', 'Liver enzyme panel', 500.00, 'Various', 'Biochemistry'),
('Kidney Function Test (KFT)', 'Creatinine and urea', 400.00, 'Various', 'Biochemistry'),
('Thyroid Function Test (TSH)', 'Thyroid stimulating hormone', 350.00, '0.4-4.0 mIU/L', 'Endocrinology'),
('Urine Routine', 'Urine analysis', 120.00, 'Normal', 'Urology'),
('ECG', 'Electrocardiogram', 200.00, 'Normal sinus rhythm', 'Cardiology'),
('Chest X-Ray', 'Thoracic radiograph', 400.00, 'Normal', 'Radiology'),
('HbA1c', 'Glycated haemoglobin', 450.00, 'Below 5.7%', 'Biochemistry');

-- Sample Patients
INSERT INTO patients (patient_id, full_name, dob, gender, blood_group, phone, email, address, emergency_contact, emergency_phone) VALUES
('PAT-001', 'Rajesh Kumar', '1985-03-15', 'Male', 'O+', '9812345678', 'rajesh@email.com', '123 MG Road, Chennai', 'Sunita Kumar', '9812345679'),
('PAT-002', 'Priya Venkatesh', '1990-07-22', 'Female', 'B+', '9823456789', 'priya@email.com', '456 Anna Nagar, Chennai', 'Ram Venkatesh', '9823456780'),
('PAT-003', 'Arjun Singh', '1978-11-08', 'Male', 'A+', '9834567890', 'arjun@email.com', '789 T Nagar, Chennai', 'Meena Singh', '9834567891'),
('PAT-004', 'Lakshmi Devi', '1965-05-30', 'Female', 'AB+', '9845678901', 'lakshmi@email.com', '101 Adyar, Chennai', 'Ramu Devi', '9845678902'),
('PAT-005', 'Mohammed Ali', '1995-09-12', 'Male', 'B-', '9856789012', 'ali@email.com', '202 Velachery, Chennai', 'Fatima Ali', '9856789013');
