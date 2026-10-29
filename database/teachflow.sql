CREATE DATABASE IF NOT EXISTS teachflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE teachflow;

CREATE TABLE IF NOT EXISTS classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject VARCHAR(120) NOT NULL,
  class_name VARCHAR(100) NOT NULL,
  day ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  room VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  subject VARCHAR(120) NOT NULL,
  unit VARCHAR(100) DEFAULT NULL,
  target_date DATE DEFAULT NULL,
  status ENUM('Planned','In Progress','Completed') NOT NULL DEFAULT 'Planned',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(160) NOT NULL,
  class_name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  attendance_date DATE NOT NULL,
  status ENUM('Present','Absent','Late') NOT NULL DEFAULT 'Present',
  UNIQUE KEY unique_student_date (student_id, attendance_date),
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  subject VARCHAR(120) NOT NULL,
  class_name VARCHAR(100) NOT NULL,
  due_date DATE DEFAULT NULL,
  status ENUM('Pending','In Progress','Completed') NOT NULL DEFAULT 'Pending',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional demo data. Remove these INSERT statements if you prefer a blank database.
INSERT INTO classes (subject,class_name,day,start_time,end_time,room) VALUES
('Web Technology','BTech CSE-A','Monday','09:00:00','10:00:00','C-204'),
('Database Management','BTech CSE-A','Monday','11:00:00','12:00:00','Lab 3'),
('Machine Learning','BTech CSE-B','Wednesday','10:00:00','11:00:00','B-108'),
('Data Structures','BTech CSE-A','Friday','12:00:00','13:00:00','C-102');

INSERT INTO lessons (title,subject,unit,target_date,status,notes) VALUES
('HTML Forms & Validation','Web Technology','Unit 2',DATE_ADD(CURDATE(), INTERVAL 2 DAY),'In Progress','Demonstrate form inputs and client-side validation.'),
('CSS Flexbox and Grid','Web Technology','Unit 3',DATE_ADD(CURDATE(), INTERVAL 4 DAY),'Planned','Responsive layout practice.'),
('SQL Joins','Database Management','Unit 4',DATE_ADD(CURDATE(), INTERVAL 1 DAY),'Completed','Inner and outer joins.');

INSERT INTO students (student_name,class_name) VALUES
('Aarav Mehta','BTech CSE-A'),
('Diya Sharma','BTech CSE-A'),
('Kabir Verma','BTech CSE-A'),
('Ananya Gupta','BTech CSE-A'),
('Rohan Singh','BTech CSE-B');

INSERT INTO attendance (student_id,attendance_date,status) VALUES
(1,CURDATE(),'Present'),(2,CURDATE(),'Present'),(3,CURDATE(),'Absent'),(4,CURDATE(),'Late');

INSERT INTO assignments (title,subject,class_name,due_date,status,notes) VALUES
('HTML Forms Practice','Web Technology','BTech CSE-A',DATE_ADD(CURDATE(), INTERVAL 2 DAY),'Pending','Build a form with required-field validation.'),
('SQL Query Worksheet','Database Management','BTech CSE-A',DATE_ADD(CURDATE(), INTERVAL 3 DAY),'In Progress','Practice SELECT, WHERE and JOIN.'),
('CSS Layout Exercise','Web Technology','BTech CSE-B',DATE_ADD(CURDATE(), INTERVAL 5 DAY),'Pending','Use flexbox and grid to create a responsive page.');
