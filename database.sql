-- ============================================================
--  Panacea Hospital – MySQL Database Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS panacea_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE panacea_hospital;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description TEXT,
  icon VARCHAR(60),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO departments (name, description, icon) VALUES
  ('Internal Medicine','Diagnosis and management of adult diseases.','bi-heart-pulse-fill'),
  ('Pediatrics','Specialized care for infants, children, and adolescents.','bi-emoji-smile-fill'),
  ('Maternal & Child Health','Antenatal, delivery, and postnatal services.','bi-gender-female'),
  ('Surgery','General, laparoscopic, and specialized procedures.','bi-scissors'),
  ('Laboratory Services','Full diagnostic lab with accurate results.','bi-flask-fill'),
  ('Pharmacy','Fully stocked pharmacy with licensed pharmacists.','bi-capsule-pill'),
  ('Emergency Department','24/7 emergency care with rapid response teams.','bi-activity'),
  ('Radiology & Diagnostics','X-ray, ultrasound, and imaging services.','bi-radioactive');

CREATE TABLE doctors (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  specialization VARCHAR(120) NOT NULL,
  years_exp TINYINT UNSIGNED DEFAULT 0,
  bio TEXT,
  phone VARCHAR(30),
  email VARCHAR(120),
  photo_url VARCHAR(255),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

INSERT INTO doctors (department_id, full_name, specialization, years_exp, bio, phone, email) VALUES
  (1,'Dr. Tadesse Bekele','Internal Medicine Specialist',14,'Specialist in chronic disease management, hypertension, and diabetes.','+251917000101','tadesse@panaceahospital.et'),
  (2,'Dr. Hiwot Alemu','Pediatrician',10,'Expert in childhood development, immunization, and pediatric illnesses.','+251917000102','hiwot@panaceahospital.et'),
  (3,'Dr. Mekdes Haile','Obstetrician & Gynecologist',11,'Dedicated specialist in maternal health and womens healthcare.','+251917000103','mekdes@panaceahospital.et'),
  (4,'Dr. Solomon Girma','General Surgeon',12,'Experienced in abdominal, laparoscopic, and emergency surgical procedures.','+251917000104','solomon@panaceahospital.et');

CREATE TABLE patients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_no VARCHAR(20) UNIQUE NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  date_of_birth DATE,
  gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male',
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(120),
  address TEXT,
  blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
  emergency_name VARCHAR(150),
  emergency_phone VARCHAR(30),
  allergies TEXT,
  notes TEXT,
  status ENUM('Active','Inactive','Deceased') DEFAULT 'Active',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO patients (patient_no,full_name,date_of_birth,gender,phone,email,address,blood_type,emergency_name,emergency_phone) VALUES
  ('PAN-2024-00001','Abrham Tesfaye','1985-04-12','Male','+251917100001','abrham@email.com','Hawassa, Sidama','O+','Tigist Tesfaye','+251917100002'),
  ('PAN-2024-00002','Selam Bekele','1990-08-23','Female','+251917100003','selam@email.com','Hawassa, Sidama','A+','Daniel Bekele','+251917100004'),
  ('PAN-2024-00003','Tigist Getahun','1995-01-07','Female','+251917100005','tigist@email.com','Hawassa, Sidama','B+','Getahun Lemma','+251917100006'),
  ('PAN-2024-00004','Kebede Mulugeta','1978-11-30','Male','+251917100007',NULL,'Dilla, SNNPR','AB-','Almaz Kebede','+251917100008'),
  ('PAN-2024-00005','Meron Alemu','2005-06-15','Female','+251917100009','meron@email.com','Hawassa, Sidama','O-','Alemu Girma','+251917100010');

CREATE TABLE appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED,
  doctor_id INT UNSIGNED,
  department_id INT UNSIGNED NOT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME NOT NULL DEFAULT '08:00:00',
  reason TEXT,
  status ENUM('Pending','Confirmed','Completed','Cancelled','No-Show') DEFAULT 'Pending',
  notes TEXT,
  guest_name VARCHAR(150),
  guest_phone VARCHAR(30),
  guest_email VARCHAR(120),
  source ENUM('Website','Walk-In','Phone','Referral') DEFAULT 'Website',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

INSERT INTO appointments (patient_id,doctor_id,department_id,appt_date,appt_time,reason,status,source) VALUES
  (1,1,1,CURDATE(),'09:00:00','Routine check-up for hypertension','Confirmed','Website'),
  (2,2,2,CURDATE(),'10:30:00','Child vaccination schedule','Pending','Phone'),
  (3,3,3,DATE_ADD(CURDATE(),INTERVAL 1 DAY),'08:00:00','Antenatal visit 28 weeks','Confirmed','Walk-In'),
  (4,4,4,DATE_ADD(CURDATE(),INTERVAL 2 DAY),'11:00:00','Post-surgical follow-up','Pending','Website'),
  (5,2,2,DATE_ADD(CURDATE(),INTERVAL 3 DAY),'14:00:00','Fever and throat pain','Pending','Website');

CREATE TABLE medical_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  doctor_id INT UNSIGNED,
  appointment_id INT UNSIGNED,
  visit_date DATE NOT NULL,
  chief_complaint TEXT,
  diagnosis TEXT,
  treatment TEXT,
  prescription TEXT,
  bp_systolic SMALLINT UNSIGNED,
  bp_diastolic SMALLINT UNSIGNED,
  heart_rate SMALLINT UNSIGNED,
  temperature DECIMAL(4,1),
  weight_kg DECIMAL(5,1),
  height_cm DECIMAL(5,1),
  follow_up_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

INSERT INTO medical_records (patient_id,doctor_id,appointment_id,visit_date,chief_complaint,diagnosis,treatment,prescription,bp_systolic,bp_diastolic,heart_rate,temperature,weight_kg,height_cm,follow_up_date) VALUES
  (1,1,1,CURDATE(),'Headache, dizziness','Essential Hypertension Stage 1','Lifestyle modification, medication review','Amlodipine 5mg OD, Enalapril 10mg OD',148,92,78,36.8,78.5,174.0,DATE_ADD(CURDATE(),INTERVAL 30 DAY)),
  (3,3,3,CURDATE(),'Antenatal visit','Pregnancy 28 weeks normal','Iron supplementation, prenatal counseling','Ferrous Sulfate 200mg BD, Folic Acid 5mg OD',110,70,82,36.5,68.0,162.0,DATE_ADD(CURDATE(),INTERVAL 14 DAY));

CREATE TABLE contact_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(120),
  subject VARCHAR(200),
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  replied_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  username VARCHAR(60) UNIQUE NOT NULL,
  email VARCHAR(120) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','receptionist','doctor') DEFAULT 'receptionist',
  last_login TIMESTAMP NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- password = Admin@1234 (bcrypt)
INSERT INTO admin_users (full_name,username,email,password,role) VALUES
  ('System Administrator','admin','admin@panaceahospital.et','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','super_admin');

CREATE OR REPLACE VIEW v_appointments_full AS
SELECT a.id,a.appt_date,a.appt_time,a.status,a.source,a.reason,
  COALESCE(p.full_name,a.guest_name) AS patient_name,
  COALESCE(p.phone,a.guest_phone) AS patient_phone,
  COALESCE(p.patient_no,'Walk-In') AS patient_no,
  d.full_name AS doctor_name,dep.name AS department_name,a.created_at
FROM appointments a
LEFT JOIN patients p ON a.patient_id=p.id
LEFT JOIN doctors d ON a.doctor_id=d.id
LEFT JOIN departments dep ON a.department_id=dep.id;
