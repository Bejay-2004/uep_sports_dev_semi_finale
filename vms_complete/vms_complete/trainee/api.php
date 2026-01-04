<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Only trainee role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'trainee') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied']);
}

header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)$_SESSION['user']['sports_id'];

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// TRAINEE STATISTICS
// ==========================================

if ($action === 'trainee_stats') {
  try {
    // Sessions attended this month
    $sessions_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = :pid 
      AND ta.status = 'present'
      AND MONTH(ts.training_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.training_date) = YEAR(CURRENT_DATE())
    ");
    $sessions_stmt->execute(['pid' => $person_id]);
    $sessions_attended = $sessions_stmt->fetch()['count'];
    
    // Attendance rate (last 30 days)
    $total_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = :pid
      AND ts.training_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $total_stmt->execute(['pid' => $person_id]);
    $total = $total_stmt->fetch()['count'];
    
    $present_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = :pid
      AND ta.status = 'present'
      AND ts.training_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $present_stmt->execute(['pid' => $person_id]);
    $present = $present_stmt->fetch()['count'];
    
    $attendance_rate = $total > 0 ? round(($present / $total) * 100) : 0;
    
    // Streak (consecutive days with attendance)
    $streak = 0; // TODO: Calculate streak
    
    // Total hours
    $hours_stmt = $pdo->prepare("
      SELECT SUM(TIMESTAMPDIFF(HOUR, ts.start_time, ts.end_time)) as total
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = :pid
      AND ta.status = 'present'
      AND MONTH(ts.training_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.training_date) = YEAR(CURRENT_DATE())
    ");
    $hours_stmt->execute(['pid' => $person_id]);
    $total_hours = $hours_stmt->fetch()['total'] ?? 0;
    
    out([
      'sessions_attended' => $sessions_attended,
      'attendance_rate' => $attendance_rate,
      'streak' => $streak,
      'total_hours' => $total_hours
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// UPCOMING SESSIONS
// ==========================================

if ($action === 'upcoming_sessions') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_id,
        ts.training_type,
        ts.training_date,
        ts.start_time,
        ts.end_time,
        ts.location,
        ts.description,
        CONCAT(p.f_name, ' ', p.l_name) as trainor_name
      FROM tbl_train_sked ts
      LEFT JOIN tbl_person p ON p.person_id = ts.trainor_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE st.sports_id = :sid
      AND ts.training_date >= CURRENT_DATE()
      AND ts.is_active = 1
      ORDER BY ts.training_date, ts.start_time
      LIMIT 5
    ");
    $stmt->execute(['sid' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ALL SESSIONS
// ==========================================

if ($action === 'all_sessions') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_id,
        ts.training_type,
        ts.training_date,
        ts.start_time,
        ts.end_time,
        ts.location,
        ts.description,
        CONCAT(p.f_name, ' ', p.l_name) as trainor_name
      FROM tbl_train_sked ts
      LEFT JOIN tbl_person p ON p.person_id = ts.trainor_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE st.sports_id = :sid
      AND ts.is_active = 1
      ORDER BY ts.training_date DESC, ts.start_time DESC
      LIMIT 50
    ");
    $stmt->execute(['sid' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MY ATTENDANCE
// ==========================================

if ($action === 'my_attendance') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ta.attend_id,
        ta.status,
        ta.remarks,
        ts.training_type,
        ts.training_date,
        ts.start_time,
        ts.end_time,
        CONCAT(p.f_name, ' ', p.l_name) as trainor_name,
        CONCAT(
          TIMESTAMPDIFF(HOUR, ts.start_time, ts.end_time), 'h'
        ) as duration
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      LEFT JOIN tbl_person p ON p.person_id = ts.trainor_id
      WHERE ta.person_id = :pid
      ORDER BY ts.training_date DESC
      LIMIT 100
    ");
    $stmt->execute(['pid' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MY PROGRAMS
// ==========================================

if ($action === 'my_programs') {
  try {
    // This assumes a table linking trainees to programs
    // For now, return empty array
    $stmt = $pdo->prepare("
      SELECT 
        tp.program_id,
        tp.program_name,
        tp.description,
        tp.duration_weeks,
        tp.goals,
        tp.is_active
      FROM tbl_training_program tp
      WHERE tp.sports_id = :sid
      AND tp.is_active = 1
      ORDER BY tp.created_at DESC
    ");
    $stmt->execute(['sid' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out([]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);