<?php
declare(strict_types=1);
session_start();

/*
 * TeachFlow — Teacher Planning & Academic Management System
 * Configure database credentials in config.php before running.
 */
require_once __DIR__ . '/config.php';

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url = 'index.php'): never { header("Location: $url"); exit; }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = ['message'=>$message, 'type'=>$type]; }
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }
    return $pdo;
}
function all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
}
function one(string $sql, array $params = []): array|false {
    $stmt = db()->prepare($sql); $stmt->execute($params); return $stmt->fetch();
}
function run(string $sql, array $params = []): void {
    $stmt = db()->prepare($sql); $stmt->execute($params);
}
function post(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }

try {
    $page = (string)($_GET['page'] ?? 'dashboard');
    $allowed = ['dashboard','timetable','lessons','attendance','assignments'];
    if (!in_array($page, $allowed, true)) $page = 'dashboard';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = post('action');
        try {
            if ($action === 'save_class') {
                $id = (int)post('id','0'); $subject = post('subject'); $class_name = post('class_name');
                $day = post('day'); $start = post('start_time'); $end = post('end_time'); $room = post('room');
                if (!$subject || !$class_name || !$day || !$start || !$end) throw new RuntimeException('Please complete all required class fields.');
                if ($start >= $end) throw new RuntimeException('End time must be later than start time.');
                if ($id) run("UPDATE classes SET subject=?,class_name=?,day=?,start_time=?,end_time=?,room=? WHERE id=?",[$subject,$class_name,$day,$start,$end,$room,$id]);
                else run("INSERT INTO classes(subject,class_name,day,start_time,end_time,room) VALUES(?,?,?,?,?,?)",[$subject,$class_name,$day,$start,$end,$room]);
                flash('Class schedule saved.'); redirect('?page=timetable');
            }
            if ($action === 'delete_class') { run("DELETE FROM classes WHERE id=?",[(int)post('id')]); flash('Class removed.'); redirect('?page=timetable'); }

            if ($action === 'save_lesson') {
                $id=(int)post('id','0'); $title=post('title'); $subject=post('subject'); $unit=post('unit'); $target=post('target_date') ?: null; $status=post('status','Planned'); $notes=post('notes');
                if (!$title || !$subject) throw new RuntimeException('Lesson title and subject are required.');
                if (!in_array($status,['Planned','In Progress','Completed'],true)) $status='Planned';
                if ($id) run("UPDATE lessons SET title=?,subject=?,unit=?,target_date=?,status=?,notes=? WHERE id=?",[$title,$subject,$unit,$target,$status,$notes,$id]);
                else run("INSERT INTO lessons(title,subject,unit,target_date,status,notes) VALUES(?,?,?,?,?,?)",[$title,$subject,$unit,$target,$status,$notes]);
                flash('Lesson saved.'); redirect('?page=lessons');
            }
            if ($action === 'delete_lesson') { run("DELETE FROM lessons WHERE id=?",[(int)post('id')]); flash('Lesson deleted.'); redirect('?page=lessons'); }

            if ($action === 'save_student') {
                $name=post('student_name'); $class_name=post('class_name');
                if (!$name || !$class_name) throw new RuntimeException('Student name and class are required.');
                run("INSERT INTO students(student_name,class_name) VALUES(?,?)",[$name,$class_name]);
                flash('Student added to register.'); redirect('?page=attendance');
            }
            if ($action === 'save_attendance') {
                $date=post('attendance_date',date('Y-m-d')); $class_name=post('attendance_class');
                foreach (($_POST['attendance'] ?? []) as $studentId=>$status) {
                    if (in_array($status,['Present','Absent','Late'],true)) {
                        run("INSERT INTO attendance(student_id,attendance_date,status) VALUES(?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)",[(int)$studentId,$date,$status]);
                    }
                }
                flash('Attendance register updated.'); redirect('?page=attendance');
            }

            if ($action === 'save_assignment') {
                $id=(int)post('id','0'); $title=post('title'); $subject=post('subject'); $class_name=post('class_name'); $due=post('due_date') ?: null; $status=post('status','Pending'); $notes=post('notes');
                if (!$title || !$subject || !$class_name) throw new RuntimeException('Title, subject and class are required.');
                if (!in_array($status,['Pending','In Progress','Completed'],true)) $status='Pending';
                if ($id) run("UPDATE assignments SET title=?,subject=?,class_name=?,due_date=?,status=?,notes=? WHERE id=?",[$title,$subject,$class_name,$due,$status,$notes,$id]);
                else run("INSERT INTO assignments(title,subject,class_name,due_date,status,notes) VALUES(?,?,?,?,?,?)",[$title,$subject,$class_name,$due,$status,$notes]);
                flash('Assignment saved.'); redirect('?page=assignments');
            }
            if ($action === 'delete_assignment') { run("DELETE FROM assignments WHERE id=?",[(int)post('id')]); flash('Assignment deleted.'); redirect('?page=assignments'); }
        } catch (Throwable $e) {
            flash($e instanceof PDOException ? 'Database action failed. Check that the database tables have been imported.' : $e->getMessage(), 'error');
            redirect('?page=' . urlencode($page));
        }
    }

    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    $counts = [
        'classes'=>(int)(one("SELECT COUNT(*) c FROM classes")['c'] ?? 0),
        'lessons'=>(int)(one("SELECT COUNT(*) c FROM lessons WHERE status <> 'Completed'")['c'] ?? 0),
        'students'=>(int)(one("SELECT COUNT(*) c FROM students")['c'] ?? 0),
        'assignments'=>(int)(one("SELECT COUNT(*) c FROM assignments WHERE status <> 'Completed'")['c'] ?? 0),
    ];
    $completedLessons = (int)(one("SELECT COUNT(*) c FROM lessons WHERE status='Completed'")['c'] ?? 0);
    $totalLessons = (int)(one("SELECT COUNT(*) c FROM lessons")['c'] ?? 0);
    $progress = $totalLessons ? (int)round($completedLessons/$totalLessons*100) : 0;
    $upcoming = all("SELECT * FROM classes ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time LIMIT 5");
    $dueSoon = all("SELECT * FROM assignments WHERE status <> 'Completed' ORDER BY due_date IS NULL, due_date LIMIT 5");
    $classes = all("SELECT * FROM classes ORDER BY FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
    $lessons = all("SELECT * FROM lessons ORDER BY target_date IS NULL,target_date,id DESC");
    $students = all("SELECT * FROM students ORDER BY class_name,student_name");
    $assignments = all("SELECT * FROM assignments ORDER BY due_date IS NULL,due_date,id DESC");
    $today = date('l');
    $todayClasses = all("SELECT * FROM classes WHERE day=? ORDER BY start_time",[$today]);
    $attendanceDate = (string)($_GET['date'] ?? date('Y-m-d'));
    $attendanceClass = (string)($_GET['class_name'] ?? ($students[0]['class_name'] ?? 'BTech CSE-A'));
    $attendanceStudents = all("SELECT s.*, a.status FROM students s LEFT JOIN attendance a ON a.student_id=s.id AND a.attendance_date=? WHERE s.class_name=? ORDER BY s.student_name",[$attendanceDate,$attendanceClass]);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>TeachFlow setup</title><style>body{font-family:Arial;background:#f7f7f2;padding:40px;color:#24352d}main{max-width:720px;margin:auto;background:white;padding:28px;border-radius:16px}code{background:#eee;padding:3px 6px}</style></head><body><main><h1>TeachFlow setup needed</h1><p>PHP is running, but TeachFlow could not connect to MySQL or the database tables are missing.</p><ol><li>Start Apache and MySQL in XAMPP.</li><li>Open phpMyAdmin and import <code>database/teachflow.sql</code>.</li><li>Check database details in <code>config.php</code>.</li></ol><p><strong>Technical detail:</strong> '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p></main></body></html>';
    exit;
}
function navlink(string $key, string $label, string $icon, string $page): string {
    $active = $key === $page ? ' active' : '';
    return '<a class="nav-link'.$active.'" href="?page='.h($key).'"><span class="nav-icon">'.$icon.'</span><span>'.h($label).'</span></a>';
}
function statusBadge(string $status): string {
    $class = strtolower(str_replace(' ','-',$status));
    return '<span class="status status-'.h($class).'">'.h($status).'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TeachFlow — Teacher Workspace</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="index.php"><span class="brand-mark">tf</span><span><strong>teachflow</strong><small>TEACHER WORKSPACE</small></span></a>
  <div class="nav-label">WORKSPACE</div>
  <?=navlink('dashboard','Overview','⌂',$page)?>
  <?=navlink('timetable','Timetable','▦',$page)?>
  <?=navlink('lessons','Lesson planner','▤',$page)?>
  <?=navlink('attendance','Attendance','✓',$page)?>
  <?=navlink('assignments','Assignments','☷',$page)?>
  <div class="sidebar-bottom"><div class="help-card"><span class="help-icon">✦</span><strong>Small steps, big progress.</strong><p>Keep your teaching week organized and calm.</p></div><div class="profile"><div class="avatar">MS</div><div><strong>Ms. Sharma</strong><small>Faculty workspace</small></div><span class="profile-dots">•••</span></div></div>
</aside>
<main class="main-area">
<header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">☰</button><div class="breadcrumb">Workspace <span>/</span> <?=h(ucwords(str_replace('_',' ',$page)))?></div><div class="top-actions"><span class="today-label"><?=h(date('D, d M Y'))?></span><div class="top-avatar">MS</div></div></header>
<div class="content">
<?php if ($flash): ?><div class="flash <?=h($flash['type'])?>"><?=h($flash['message'])?><button onclick="this.parentElement.remove()" aria-label="Dismiss">×</button></div><?php endif; ?>

<?php if ($page === 'dashboard'): ?>
<section class="welcome-row"><div><p class="eyebrow">YOUR TEACHING SPACE</p><h1>Good <?=((int)date('G')<12?'morning':((int)date('G')<17?'afternoon':'evening'))?>, Ms. Sharma <span>✦</span></h1><p class="subheading">A clear plan makes room for great teaching.</p></div><a class="btn btn-primary" href="?page=lessons#lesson-form">＋ Plan a lesson</a></section>
<div class="stats-grid">
  <article class="stat-card"><div class="stat-top"><span>Classes scheduled</span><span class="stat-icon lavender">▦</span></div><div class="stat-number"><?=$counts['classes']?></div><div class="stat-foot"><span class="dot green-dot"></span> In your weekly timetable</div></article>
  <article class="stat-card"><div class="stat-top"><span>Lessons in progress</span><span class="stat-icon peach">▤</span></div><div class="stat-number"><?=$counts['lessons']?></div><div class="stat-foot">Across all your subjects</div></article>
  <article class="stat-card"><div class="stat-top"><span>Students in register</span><span class="stat-icon mint">♧</span></div><div class="stat-number"><?=$counts['students']?></div><div class="stat-foot">Across your class lists</div></article>
  <article class="stat-card"><div class="stat-top"><span>Open assignments</span><span class="stat-icon butter">☷</span></div><div class="stat-number"><?=$counts['assignments']?></div><div class="stat-foot">Still need your attention</div></article>
</div>
<div class="dashboard-grid">
  <section class="panel schedule-panel"><div class="panel-heading"><div><h2>Today's schedule</h2><p>Your classes for <?=h($today)?></p></div><a class="text-link" href="?page=timetable">Full timetable ↗</a></div>
  <?php if (!$todayClasses): ?><div class="empty-state"><div class="empty-icon">☼</div><strong>A little breathing room</strong><p>No classes are scheduled for today yet.</p><a href="?page=timetable">Add a class</a></div>
  <?php else: foreach($todayClasses as $c): ?><div class="schedule-item"><div class="time-block"><strong><?=h(date('g:i',strtotime($c['start_time'])))?></strong><small><?=h(date('A',strtotime($c['start_time'])))?></small></div><div class="timeline-dot"></div><div class="schedule-details"><strong><?=h($c['subject'])?></strong><p><?=h($c['class_name'])?> · <?=h($c['room'] ?: 'Room not set')?></p></div><span class="schedule-duration"><?=h(date('g:i A',strtotime($c['end_time'])))?></span></div><?php endforeach; endif; ?>
  </section>
  <section class="panel progress-panel"><div class="panel-heading"><div><h2>Syllabus progress</h2><p>Across all planned lessons</p></div><span class="round-icon">↗</span></div><div class="progress-ring" style="--progress:<?=$progress?>"><div><strong><?=$progress?>%</strong><small>completed</small></div></div><div class="progress-legend"><span><i class="legend-dot done"></i>Completed <b><?=$completedLessons?></b></span><span><i class="legend-dot todo"></i>Remaining <b><?=$totalLessons-$completedLessons?></b></span></div><a class="btn btn-outline full-btn" href="?page=lessons">View lesson plan</a></section>
</div>
<section class="panel assignment-panel"><div class="panel-heading"><div><h2>Coming up next</h2><p>Assignments that still need attention</p></div><a class="text-link" href="?page=assignments">All assignments ↗</a></div>
<?php if (!$dueSoon): ?><div class="empty-state compact"><p>You're all caught up. Add an assignment when you're ready.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>ASSIGNMENT</th><th>SUBJECT / CLASS</th><th>DUE DATE</th><th>STATUS</th></tr></thead><tbody><?php foreach($dueSoon as $a): ?><tr><td><strong><?=h($a['title'])?></strong></td><td><?=h($a['subject'])?><small class="cell-sub"><?=h($a['class_name'])?></small></td><td><?=h($a['due_date'] ? date('d M Y',strtotime($a['due_date'])) : 'Not set')?></td><td><?=statusBadge($a['status'])?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<?php elseif ($page === 'timetable'): ?>
<section class="page-heading"><div><p class="eyebrow">PLAN YOUR WEEK</p><h1>Timetable planner</h1><p class="subheading">Give every class a place in your week.</p></div><button class="btn btn-primary" onclick="toggleForm('class-form')">＋ Add class</button></section>
<section class="panel form-panel hidden" id="class-form"><div class="panel-heading"><div><h2>Add a class</h2><p>Required fields are marked in the form.</p></div><button class="icon-button" onclick="toggleForm('class-form')">×</button></div><form method="post" class="form-grid"><input type="hidden" name="action" value="save_class"><label>Subject *<input name="subject" required placeholder="e.g. Web Technology"></label><label>Class / section *<input name="class_name" required placeholder="e.g. BTech CSE-A"></label><label>Day *<select name="day" required><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?><option><?=$d?></option><?php endforeach; ?></select></label><label>Room<input name="room" placeholder="e.g. C-204"></label><label>Start time *<input type="time" name="start_time" required></label><label>End time *<input type="time" name="end_time" required></label><div class="form-actions"><button class="btn btn-primary">Save class</button><button type="button" class="btn btn-quiet" onclick="toggleForm('class-form')">Cancel</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Your weekly classes</h2><p><?=count($classes)?> class(es) scheduled</p></div></div><?php if (!$classes): ?><div class="empty-state"><div class="empty-icon">▦</div><strong>Your week starts here</strong><p>Add a class to start building your timetable.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>DAY</th><th>SUBJECT</th><th>CLASS</th><th>TIME</th><th>ROOM</th><th></th></tr></thead><tbody><?php foreach($classes as $c): ?><tr><td><span class="day-pill"><?=h(substr($c['day'],0,3))?></span></td><td><strong><?=h($c['subject'])?></strong></td><td><?=h($c['class_name'])?></td><td><?=h(date('g:i A',strtotime($c['start_time'])))?> – <?=h(date('g:i A',strtotime($c['end_time'])))?></td><td><?=h($c['room'] ?: '—')?></td><td><form method="post" onsubmit="return confirm('Remove this class?')"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="delete-button" title="Delete class">×</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<?php elseif ($page === 'lessons'): ?>
<section class="page-heading"><div><p class="eyebrow">TEACH WITH A PLAN</p><h1>Lesson planner</h1><p class="subheading">Turn your syllabus into manageable steps.</p></div><button class="btn btn-primary" onclick="toggleForm('lesson-form')">＋ Add lesson</button></section>
<section class="panel form-panel <?=isset($_GET['new'])?'':'hidden'?>" id="lesson-form"><div class="panel-heading"><div><h2>Plan a lesson</h2><p>Record what you want to teach and when.</p></div><button class="icon-button" onclick="toggleForm('lesson-form')">×</button></div><form method="post" class="form-grid"><input type="hidden" name="action" value="save_lesson"><label>Lesson / topic *<input name="title" required placeholder="e.g. DOM manipulation"></label><label>Subject *<input name="subject" required placeholder="e.g. Web Technology"></label><label>Unit / chapter<input name="unit" placeholder="e.g. Unit 3"></label><label>Target date<input type="date" name="target_date"></label><label>Status<select name="status"><option>Planned</option><option>In Progress</option><option>Completed</option></select></label><label class="span-two">Notes<textarea name="notes" rows="2" placeholder="Key concepts, resources or reminders..."></textarea></label><div class="form-actions"><button class="btn btn-primary">Save lesson</button><button type="button" class="btn btn-quiet" onclick="toggleForm('lesson-form')">Cancel</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Lesson roadmap</h2><p><?=count($lessons)?> lesson(s) in your plan</p></div><div class="filter-wrap"><input id="lessonSearch" class="search-input" placeholder="Search lessons..." oninput="filterRows('lessonSearch','lesson-row')"></div></div><?php if (!$lessons): ?><div class="empty-state"><div class="empty-icon">▤</div><strong>Build your first lesson plan</strong><p>Keep topics, dates and progress in one place.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>LESSON</th><th>SUBJECT / UNIT</th><th>TARGET DATE</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach($lessons as $l): ?><tr class="lesson-row"><td><strong><?=h($l['title'])?></strong><?php if($l['notes']): ?><small class="cell-sub"><?=h($l['notes'])?></small><?php endif; ?></td><td><?=h($l['subject'])?><small class="cell-sub"><?=h($l['unit'] ?: 'Unit not set')?></small></td><td><?=h($l['target_date'] ? date('d M Y',strtotime($l['target_date'])) : 'Not set')?></td><td><?=statusBadge($l['status'])?></td><td><form method="post" onsubmit="return confirm('Delete this lesson?')"><input type="hidden" name="action" value="delete_lesson"><input type="hidden" name="id" value="<?=$l['id']?>"><button class="delete-button" title="Delete lesson">×</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<?php elseif ($page === 'attendance'): ?>
<section class="page-heading"><div><p class="eyebrow">KNOW YOUR CLASS</p><h1>Attendance register</h1><p class="subheading">A simple, clear record of who made it to class.</p></div><button class="btn btn-primary" onclick="toggleForm('student-form')">＋ Add student</button></section>
<section class="panel form-panel hidden" id="student-form"><div class="panel-heading"><div><h2>Add a student</h2><p>Add students to a class register.</p></div><button class="icon-button" onclick="toggleForm('student-form')">×</button></div><form method="post" class="form-grid"><input type="hidden" name="action" value="save_student"><label>Student name *<input name="student_name" required placeholder="Full name"></label><label>Class / section *<input name="class_name" required placeholder="e.g. BTech CSE-A"></label><div class="form-actions"><button class="btn btn-primary">Add student</button><button type="button" class="btn btn-quiet" onclick="toggleForm('student-form')">Cancel</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Mark attendance</h2><p>Choose a class and date, then save the register.</p></div></div><form method="get" class="filter-bar"><input type="hidden" name="page" value="attendance"><label>Class / section<input name="class_name" value="<?=h($attendanceClass)?>" placeholder="BTech CSE-A"></label><label>Date<input type="date" name="date" value="<?=h($attendanceDate)?>"></label><button class="btn btn-outline">Load register</button></form>
<?php if (!$attendanceStudents): ?><div class="empty-state"><div class="empty-icon">✓</div><strong>No students in this class yet</strong><p>Add students to create an attendance register.</p><button class="btn btn-outline" onclick="toggleForm('student-form')">Add students</button></div><?php else: ?><form method="post"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="attendance_date" value="<?=h($attendanceDate)?>"><input type="hidden" name="attendance_class" value="<?=h($attendanceClass)?>"><div class="attendance-summary"><span><strong><?=count($attendanceStudents)?></strong> students in register</span><span class="attendance-key"><i class="present-dot"></i> Present <i class="absent-dot"></i> Absent <i class="late-dot"></i> Late</span></div><div class="table-wrap"><table><thead><tr><th>STUDENT</th><th>CLASS</th><th>MARK STATUS</th></tr></thead><tbody><?php foreach($attendanceStudents as $s): ?><tr><td><div class="student-cell"><span class="student-avatar"><?=h(strtoupper(substr($s['student_name'],0,1)))?></span><strong><?=h($s['student_name'])?></strong></div></td><td><?=h($s['class_name'])?></td><td><div class="attendance-options"><?php foreach(['Present','Absent','Late'] as $st): ?><label class="attendance-option <?=strtolower($st)?>"><input type="radio" name="attendance[<?=$s['id']?>]" value="<?=$st?>" <?=($s['status'] ?? 'Present')===$st?'checked':''?>><span><?=$st?></span></label><?php endforeach; ?></div></td></tr><?php endforeach; ?></tbody></table></div><div class="form-actions bottom-actions"><button class="btn btn-primary">Save attendance</button></div></form><?php endif; ?></section>
<section class="panel"><div class="panel-heading"><div><h2>Class register</h2><p>Students currently added to the workspace</p></div></div><?php if (!$students): ?><p class="muted">No students added yet.</p><?php else: ?><div class="student-chip-list"><?php foreach($students as $s): ?><span class="student-chip"><?=h($s['student_name'])?><small><?=h($s['class_name'])?></small></span><?php endforeach; ?></div><?php endif; ?></section>

<?php elseif ($page === 'assignments'): ?>
<section class="page-heading"><div><p class="eyebrow">STAY A STEP AHEAD</p><h1>Assignments & syllabus</h1><p class="subheading">Keep deadlines visible and progress moving.</p></div><button class="btn btn-primary" onclick="toggleForm('assignment-form')">＋ Add assignment</button></section>
<section class="panel form-panel hidden" id="assignment-form"><div class="panel-heading"><div><h2>Create an assignment</h2><p>Track the task, class and deadline.</p></div><button class="icon-button" onclick="toggleForm('assignment-form')">×</button></div><form method="post" class="form-grid"><input type="hidden" name="action" value="save_assignment"><label>Assignment title *<input name="title" required placeholder="e.g. HTML forms practice"></label><label>Subject *<input name="subject" required placeholder="e.g. Web Technology"></label><label>Class / section *<input name="class_name" required placeholder="e.g. BTech CSE-A"></label><label>Due date<input type="date" name="due_date"></label><label>Status<select name="status"><option>Pending</option><option>In Progress</option><option>Completed</option></select></label><label class="span-two">Notes<textarea name="notes" rows="2" placeholder="Instructions or grading reminders..."></textarea></label><div class="form-actions"><button class="btn btn-primary">Save assignment</button><button type="button" class="btn btn-quiet" onclick="toggleForm('assignment-form')">Cancel</button></div></form></section>
<section class="panel"><div class="panel-heading"><div><h2>Assignment tracker</h2><p><?=count($assignments)?> assignment(s) recorded</p></div><div class="filter-wrap"><input id="assignmentSearch" class="search-input" placeholder="Search assignments..." oninput="filterRows('assignmentSearch','assignment-row')"></div></div><?php if (!$assignments): ?><div class="empty-state"><div class="empty-icon">☷</div><strong>No assignments yet</strong><p>Add your first task to keep deadlines on track.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>ASSIGNMENT</th><th>SUBJECT / CLASS</th><th>DUE DATE</th><th>STATUS</th><th></th></tr></thead><tbody><?php foreach($assignments as $a): ?><tr class="assignment-row"><td><strong><?=h($a['title'])?></strong><?php if($a['notes']): ?><small class="cell-sub"><?=h($a['notes'])?></small><?php endif; ?></td><td><?=h($a['subject'])?><small class="cell-sub"><?=h($a['class_name'])?></small></td><td><?=h($a['due_date'] ? date('d M Y',strtotime($a['due_date'])) : 'Not set')?></td><td><?=statusBadge($a['status'])?></td><td><form method="post" onsubmit="return confirm('Delete this assignment?')"><input type="hidden" name="action" value="delete_assignment"><input type="hidden" name="id" value="<?=$a['id']?>"><button class="delete-button" title="Delete assignment">×</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; ?>
<footer class="footer"><span>© <?=date('Y')?> TeachFlow</span><span>Made for more mindful teaching <b>✦</b></span></footer>
</div></main></div>
<script src="assets/app.js"></script>
</body></html>
