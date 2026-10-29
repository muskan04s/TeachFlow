# TeachFlow — Teacher Planning & Academic Management System

TeachFlow is a small full-stack Web Technology project built with HTML, CSS, JavaScript, PHP and MySQL. It helps a teacher organize classes, lessons, attendance and assignments in one workspace.

## Features
- Dashboard with live counts and syllabus completion progress
- Weekly timetable: add and delete classes
- Lesson planner: create, search and delete lesson records; track planned/in-progress/completed status
- Attendance: add students, choose class/date, mark Present/Absent/Late and save the register
- Assignment tracker: create, search and delete assignments; track due dates and status
- Responsive layout, required-field validation, prepared SQL statements and browser-side search

## Requirements
- XAMPP (Apache + MySQL + PHP 8+ recommended)
- A modern browser

## Run locally (Windows)
1. Install/open XAMPP and start **Apache** and **MySQL**.
2. Copy the `TeachFlow_Submission` folder into `C:\xampp\htdocs\` and rename it `TeachFlow` if desired.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import**, select `database/teachflow.sql`, and click **Import/Go**. The script creates the `teachflow` database and demo data.
5. Open `config.php`. The default local settings are host `127.0.0.1`, database `teachflow`, user `root`, and a blank password. Change these if your MySQL setup differs.
6. Visit `http://localhost/TeachFlow/` (use the folder name you copied).
7. Navigate through the sidebar and test each module. Added data is stored in MySQL.

## Database tables
- `classes` — timetable records
- `lessons` — lesson plans and progress
- `students` — class register
- `attendance` — dated attendance records
- `assignments` — assignment deadlines and status

## Notes
- This is a classroom demonstration project, not a production system. It has no login/authentication or role permissions.
- Keep `config.php` credentials private if you deploy the app publicly. Do not publish real student personal data.
- The SQL file contains fictional demo records. You can remove the optional INSERT statements if you want an empty database.
