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
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ==========================================
// TRAINEE STATISTICS (SECURE - Only THIS trainee's data)
// ==========================================

// Replace the trainee_stats action in trainee/api.php with this:

// Replace the trainee_stats action in trainee/api.php with this:

if ($action === 'trainee_stats') {
  try {
    // First, let's find which teams this trainee belongs to
    // Check if trainee is in tbl_team_trainees (for trainees) or tbl_team_athletes (if also an athlete)
    $trainee_teams_stmt = $pdo->prepare("
      SELECT DISTINCT team_id FROM tbl_team_trainees 
      WHERE trainee_id = ? AND is_active = 1
      UNION
      SELECT DISTINCT team_id FROM tbl_team_athletes 
      WHERE person_id = ? AND is_active = 1
    ");
    $trainee_teams_stmt->execute([$person_id, $person_id]);
    $team_ids = $trainee_teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($team_ids)) {
      // No teams found, return zeros
      out([
        'sessions_attended' => 0,
        'attendance_rate' => 0,
        'streak' => 0,
        'total_hours' => 0
      ]);
      return;
    }
    
    $team_ids_str = implode(',', array_map('intval', $team_ids));
    
    // 1. SCHEDULED sessions this month for trainee's teams
    $sessions_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_sked ts
      WHERE ts.team_id IN ({$team_ids_str})
      AND MONTH(ts.sked_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.sked_date) = YEAR(CURRENT_DATE())
      AND ts.is_active = 1
    ");
    $sessions_stmt->execute();
    $sessions_this_month = (int)$sessions_stmt->fetch()['count'];
    
    // 2. Attendance rate (ALL TIME - only for sessions where attendance was recorded)
    $total_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      WHERE ta.person_id = ?
    ");
    $total_stmt->execute([$person_id]);
    $total = (int)$total_stmt->fetch()['count'];
    
    $present_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      WHERE ta.person_id = ?
      AND ta.is_present = 1
    ");
    $present_stmt->execute([$person_id]);
    $present = (int)$present_stmt->fetch()['count'];
    
    $attendance_rate = $total > 0 ? round(($present / $total) * 100) : 0;
    
    // 3. Current streak (consecutive training sessions attended)
    $streak_stmt = $pdo->prepare("
      SELECT ta.is_present, ts.sked_date, ts.sked_time
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = ?
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
      LIMIT 50
    ");
    $streak_stmt->execute([$person_id]);
    $attendance_records = $streak_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $streak = 0;
    foreach ($attendance_records as $record) {
      if ($record['is_present'] == 1) {
        $streak++;
      } else {
        break;
      }
    }
    
    // 4. Total training sessions attended (all time)
    $total_attended_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_train_attend ta
      WHERE ta.person_id = ?
      AND ta.is_present = 1
    ");
    $total_attended_stmt->execute([$person_id]);
    $total_sessions = (int)$total_attended_stmt->fetch()['count'];
    
    out([
      'sessions_attended' => $sessions_this_month,
      'attendance_rate' => $attendance_rate,
      'streak' => $streak,
      'total_hours' => $total_sessions
    ]);
  } catch (PDOException $e) {
    error_log("TRAINEE_STATS ERROR: " . $e->getMessage());
    error_log("TRAINEE_STATS TRACE: " . $e->getTraceAsString());
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// UPCOMING SESSIONS (Shows all sessions for trainee's sport - not filtered by trainee)
// ==========================================

if ($action === 'upcoming_sessions') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_id,
        ts.sked_date as training_date,
        ts.sked_time as start_time,
        '' as end_time,
        CONCAT(
          IFNULL(gv.venue_name, 'TBA'),
          CASE 
            WHEN gv.venue_building IS NOT NULL AND gv.venue_building != '' 
            THEN CONCAT(' - ', gv.venue_building)
            ELSE ''
          END
        ) as location,
        '' as description,
        t.team_name,
        'Training Session' as training_type,
        CONCAT(
          p1.f_name, ' ', p1.l_name,
          CASE 
            WHEN p2.person_id IS NOT NULL 
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL 
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE st.sports_id = ?
      AND ts.sked_date >= CURRENT_DATE()
      AND ts.is_active = 1
      ORDER BY ts.sked_date, ts.sked_time
      LIMIT 5
    ");
    $stmt->execute([$sports_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($sessions);
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
        ts.sked_date as training_date,
        ts.sked_time as start_time,
        '' as end_time,
        CONCAT(
          IFNULL(gv.venue_name, 'TBA'),
          CASE 
            WHEN gv.venue_building IS NOT NULL AND gv.venue_building != '' 
            THEN CONCAT(' - ', gv.venue_building)
            ELSE ''
          END
        ) as location,
        '' as description,
        t.team_name,
        'Training Session' as training_type,
        CONCAT(
          p1.f_name, ' ', p1.l_name,
          CASE 
            WHEN p2.person_id IS NOT NULL 
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL 
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE st.sports_id = ?
      AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
      LIMIT 50
    ");
    $stmt->execute([$sports_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($sessions);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MY ATTENDANCE (SECURE - Only THIS trainee's attendance records)
// ==========================================

if ($action === 'my_attendance') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_date as training_date,
        ts.sked_time as start_time,
        ta.is_present,
        CASE 
          WHEN ta.is_present = 1 THEN 'present'
          ELSE 'absent'
        END as status,
        'Training Session' as training_type,
        CONCAT(
          p1.f_name, ' ', p1.l_name,
          CASE 
            WHEN p2.person_id IS NOT NULL 
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL 
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name,
        'N/A' as duration
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE ta.person_id = ?
      ORDER BY ts.sked_date DESC
      LIMIT 100
    ");
    $stmt->execute([$person_id]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($attendance);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MY PROGRAMS
// ==========================================

if ($action === 'my_programs') {
  try {
    // Check if tbl_training_activity exists and get activities for the sport
    $stmt = $pdo->prepare("
      SELECT 
        activity_id as program_id,
        activity_name as program_name,
        CONCAT(duration, ' / ', repetition) as description,
        4 as duration_weeks,
        'Improve fitness and skills' as goals,
        is_active
      FROM tbl_training_activity
      WHERE sports_id = ?
      AND is_active = 1
      ORDER BY activity_name
    ");
    $stmt->execute([$sports_id]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($programs);
  } catch (PDOException $e) {
    out([]);
  }
}

// Add this temporarily to trainee/api.php to debug the issue

if ($action === 'debug_stats') {
  try {
    // Check teams
    $teams_query = $pdo->prepare("
      SELECT DISTINCT team_id, 'team_trainees' as source FROM tbl_team_trainees 
      WHERE trainee_id = ? AND is_active = 1
      UNION
      SELECT DISTINCT team_id, 'team_athletes' as source FROM tbl_team_athletes 
      WHERE person_id = ? AND is_active = 1
    ");
    $teams_query->execute([$person_id, $person_id]);
    $teams = $teams_query->fetchAll(PDO::FETCH_ASSOC);
    
    // Get team IDs
    $team_ids = array_column($teams, 'team_id');
    
    // Count scheduled sessions this month
    $scheduled = [];
    if (!empty($team_ids)) {
      $team_ids_str = implode(',', array_map('intval', $team_ids));
      $sched_query = $pdo->query("
        SELECT * FROM tbl_train_sked 
        WHERE team_id IN ({$team_ids_str})
        AND MONTH(sked_date) = MONTH(CURRENT_DATE())
        AND YEAR(sked_date) = YEAR(CURRENT_DATE())
        AND is_active = 1
      ");
      $scheduled = $sched_query->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get attendance records
    $attend_query = $pdo->prepare("
      SELECT ta.*, ts.sked_date, ts.sked_time 
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ta.person_id = ?
      ORDER BY ts.sked_date DESC
    ");
    $attend_query->execute([$person_id]);
    $attendance = $attend_query->fetchAll(PDO::FETCH_ASSOC);
    
    out([
      'person_id' => $person_id,
      'current_month' => date('Y-m'),
      'teams' => $teams,
      'scheduled_this_month' => $scheduled,
      'scheduled_count' => count($scheduled),
      'attendance_records' => $attendance,
      'attendance_count' => count($attendance)
    ]);
  } catch (Exception $e) {
    out(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
  }
}

// To use this, open your browser console and run:
// fetch('api.php?action=debug_stats').then(r => r.json()).then(console.log)

// Default response for unknown actions
out(['ok' => false, 'message' => 'Unknown action: ' . $action]);