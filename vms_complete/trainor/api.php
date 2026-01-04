<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Only trainor role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'trainor') {
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
// TRAINOR STATISTICS (SECURE - Only THIS trainor's data)
// ==========================================

if ($action === 'trainor_stats') {
  try {
    // Active trainees - only in teams where THIS trainor is assigned
    $trainees_stmt = $pdo->prepare("
      SELECT COUNT(DISTINCT tt.trainee_id) as count
      FROM tbl_team_trainees tt
      JOIN tbl_sports_team st ON st.team_id = tt.team_id
      WHERE st.sports_id = ?
      AND (st.trainor1_id = ? OR st.trainor2_id = ? OR st.trainor3_id = ?)
      AND tt.is_active = 1
    ");
    $trainees_stmt->execute([$sports_id, $person_id, $person_id, $person_id]);
    $active_trainees = (int)$trainees_stmt->fetch()['count'];
    
    // Sessions this month - only THIS trainor's sessions
    $sessions_stmt = $pdo->prepare("
      SELECT COUNT(*) as count
      FROM tbl_train_sked ts
      WHERE ts.trainor_id = ?
      AND MONTH(ts.sked_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.sked_date) = YEAR(CURRENT_DATE())
      AND ts.is_active = 1
    ");
    $sessions_stmt->execute([$person_id]);
    $sessions_this_month = (int)$sessions_stmt->fetch()['count'];
    
    // Average attendance rate - only THIS trainor's sessions
    $avg_stmt = $pdo->prepare("
      SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) as present
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE ts.trainor_id = ?
      AND ts.sked_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
    ");
    $avg_stmt->execute([$person_id]);
    $avg_data = $avg_stmt->fetch();
    $avg_attendance = $avg_data['total'] > 0 ? round(($avg_data['present'] / $avg_data['total']) * 100) : 0;
    
    // Total sessions (can't calculate hours without duration)
    $total_hours = $sessions_this_month;
    
    out([
      'active_trainees' => $active_trainees,
      'sessions_this_month' => $sessions_this_month,
      'avg_attendance' => $avg_attendance,
      'total_hours' => $total_hours
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// UPCOMING SESSIONS (SECURE - Only THIS trainor's sessions)
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
        'Training Session' as training_type
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      WHERE ts.trainor_id = ?
      AND ts.sked_date >= CURRENT_DATE()
      AND ts.is_active = 1
      ORDER BY ts.sked_date, ts.sked_time
      LIMIT 5
    ");
    $stmt->execute([$person_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($sessions);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ALL SESSIONS (SECURE - Only THIS trainor's sessions)
// ==========================================

if ($action === 'all_sessions' || $action === 'my_sessions') {
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
        'Training Session' as training_type
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      WHERE ts.trainor_id = ?
      AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
      LIMIT 100
    ");
    $stmt->execute([$person_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($sessions);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// RECENT ACTIVITY (SECURE - Only THIS trainor's activities)
// ==========================================

if ($action === 'recent_activity') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        CONCAT('Training Session for ', t.team_name) as activity,
        CONCAT(
          DATE_FORMAT(ts.sked_date, '%b %d, %Y'),
          ' at ',
          TIME_FORMAT(ts.sked_time, '%h:%i %p')
        ) as timestamp
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.trainor_id = ?
      AND ts.is_active = 1
      ORDER BY ts.sked_date DESC
      LIMIT 10
    ");
    $stmt->execute([$person_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($activities);
  } catch (PDOException $e) {
    out([]);
  }
}

// ==========================================
// MY TEAMS (SECURE - Only teams where THIS trainor is assigned)
// ==========================================

if ($action === 'my_teams') {
  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT t.team_id, t.team_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      WHERE st.sports_id = ?
      AND (st.trainor1_id = ? OR st.trainor2_id = ? OR st.trainor3_id = ?)
      AND t.is_active = 1
      ORDER BY t.team_name
    ");
    $stmt->execute([$sports_id, $person_id, $person_id, $person_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($teams);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET VENUES
// ==========================================

if ($action === 'get_venues') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        venue_id, 
        venue_name, 
        venue_building,
        CONCAT(
          venue_name,
          CASE 
            WHEN venue_building IS NOT NULL AND venue_building != '' 
            THEN CONCAT(' - ', venue_building)
            ELSE ''
          END
        ) as full_name
      FROM tbl_game_venue
      WHERE is_active = 1
      ORDER BY venue_name
    ");
    $stmt->execute();
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($venues);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SAVE SESSION (SECURE - Adds trainor_id)
// ==========================================

if ($action === 'save_session') {
  try {
    $sked_id = $input['sked_id'] ?? null;
    $team_id = (int)($input['team_id'] ?? 0);
    $training_date = $input['training_date'] ?? '';
    $start_time = $input['start_time'] ?? '';
    $venue_id = (int)($input['venue_id'] ?? 0);

    // Validation
    if (!$team_id || !$training_date || !$start_time || !$venue_id) {
      out(['ok' => false, 'message' => 'All fields are required (team, date, time, and venue)']);
    }

    // Verify team belongs to trainor
    $verify = $pdo->prepare("
      SELECT st.team_id 
      FROM tbl_sports_team st
      WHERE st.team_id = ?
      AND st.sports_id = ?
      AND (st.trainor1_id = ? OR st.trainor2_id = ? OR st.trainor3_id = ?)
    ");
    $verify->execute([$team_id, $sports_id, $person_id, $person_id, $person_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'You are not authorized for this team']);
    }

    if ($sked_id) {
      // Update existing session - verify ownership first
      $owner_check = $pdo->prepare("SELECT sked_id FROM tbl_train_sked WHERE sked_id = ? AND trainor_id = ?");
      $owner_check->execute([$sked_id, $person_id]);
      
      if (!$owner_check->fetch()) {
        out(['ok' => false, 'message' => 'You can only edit your own sessions']);
      }
      
      $stmt = $pdo->prepare("
        UPDATE tbl_train_sked
        SET team_id = ?,
            sked_date = ?,
            sked_time = ?,
            venue_id = ?
        WHERE sked_id = ?
        AND trainor_id = ?
      ");
      $stmt->execute([
        $team_id,
        $training_date,
        $start_time,
        $venue_id,
        $sked_id,
        $person_id
      ]);
      out(['ok' => true, 'message' => 'Session updated successfully']);
    } else {
      // Create new session with trainor_id
      $stmt = $pdo->prepare("
        INSERT INTO tbl_train_sked 
        (team_id, trainor_id, sked_date, sked_time, venue_id, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
      ");
      $stmt->execute([
        $team_id,
        $person_id,
        $training_date,
        $start_time,
        $venue_id
      ]);
      out(['ok' => true, 'message' => 'Training session created successfully']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
  }
}

// ==========================================
// DELETE SESSION (SECURE - Only own sessions)
// ==========================================

if ($action === 'delete_session') {
  try {
    $sked_id = (int)($input['sked_id'] ?? 0);

    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }

    // Soft delete - only if trainor owns the session
    $stmt = $pdo->prepare("
      UPDATE tbl_train_sked
      SET is_active = 0
      WHERE sked_id = ?
      AND trainor_id = ?
    ");
    $stmt->execute([$sked_id, $person_id]);

    if ($stmt->rowCount() > 0) {
      out(['ok' => true, 'message' => 'Session deleted successfully']);
    } else {
      out(['ok' => false, 'message' => 'Session not found or you do not have permission']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
  }
}

// ==========================================
// GET SESSION (SECURE - Only own sessions)
// ==========================================

if ($action === 'get_session') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_id,
        ts.team_id,
        ts.sked_date as training_date,
        ts.sked_time as start_time,
        ts.venue_id
      FROM tbl_train_sked ts
      WHERE ts.sked_id = ?
      AND ts.trainor_id = ?
      AND ts.is_active = 1
    ");
    $stmt->execute([$sked_id, $person_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session) {
      out($session);
    } else {
      out(['ok' => false, 'message' => 'Session not found or you do not have permission']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SESSION ATTENDANCE (SECURE - Only for trainor's own sessions)
// ==========================================

if ($action === 'session_attendance') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);

    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }

    // Verify session belongs to this trainor
    $verify = $pdo->prepare("SELECT sked_id FROM tbl_train_sked WHERE sked_id = ? AND trainor_id = ?");
    $verify->execute([$sked_id, $person_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'You can only view attendance for your own sessions']);
    }

    // Get all trainees from the team for this session
    $stmt = $pdo->prepare("
      SELECT 
        tt.trainee_id as person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.m_name, ''), ' ', p.l_name) as trainee_name,
        IFNULL(ta.is_present, 0) as is_present,
        ta.sked_id
      FROM tbl_train_sked ts
      JOIN tbl_team_trainees tt ON tt.team_id = ts.team_id AND tt.is_active = 1
      JOIN tbl_person p ON p.person_id = tt.trainee_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id AND ta.person_id = tt.trainee_id
      WHERE ts.sked_id = ?
      AND ts.trainor_id = ?
      ORDER BY p.l_name, p.f_name
    ");
    $stmt->execute([$sked_id, $person_id]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($attendance);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MARK ATTENDANCE (SECURE - Only for trainor's own sessions)
// ==========================================

if ($action === 'mark_attendance') {
  try {
    $sked_id = (int)($input['sked_id'] ?? 0);
    $person_id_trainee = (int)($input['person_id'] ?? 0);
    $is_present = (int)($input['is_present'] ?? 0);

    if ($sked_id <= 0 || $person_id_trainee <= 0) {
      out(['ok' => false, 'message' => 'Invalid parameters']);
    }

    // Verify trainor owns this session
    $verify = $pdo->prepare("SELECT sked_id FROM tbl_train_sked WHERE sked_id = ? AND trainor_id = ?");
    $verify->execute([$sked_id, $person_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'You can only mark attendance for your own sessions']);
    }

    // Check if attendance record exists
    $check = $pdo->prepare("
      SELECT * FROM tbl_train_attend 
      WHERE sked_id = ? AND person_id = ?
    ");
    $check->execute([$sked_id, $person_id_trainee]);
    
    if ($check->fetch()) {
      // Update existing
      $stmt = $pdo->prepare("
        UPDATE tbl_train_attend
        SET is_present = ?
        WHERE sked_id = ? AND person_id = ?
      ");
      $stmt->execute([$is_present, $sked_id, $person_id_trainee]);
    } else {
      // Insert new
      $stmt = $pdo->prepare("
        INSERT INTO tbl_train_attend (sked_id, person_id, is_present)
        VALUES (?, ?, ?)
      ");
      $stmt->execute([$sked_id, $person_id_trainee, $is_present]);
    }

    out(['ok' => true, 'message' => 'Attendance saved successfully']);
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
  }
}

// ==========================================
// MY TRAINEES (SECURE - Only trainees in trainor's teams)
// ==========================================

if ($action === 'my_trainees') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        tt.trainee_id as person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.m_name, ''), ' ', p.l_name) as trainee_name,
        t.team_name,
        tt.is_active,
        COUNT(DISTINCT CASE WHEN ts.trainor_id = ? THEN ta.sked_id END) as sessions_attended,
        ROUND(
          (SUM(CASE WHEN ta.is_present = 1 AND ts.trainor_id = ? THEN 1 ELSE 0 END) / 
           NULLIF(COUNT(DISTINCT CASE WHEN ts.trainor_id = ? THEN ta.sked_id END), 0)) * 100
        ) as attendance_rate
      FROM tbl_team_trainees tt
      JOIN tbl_person p ON p.person_id = tt.trainee_id
      JOIN tbl_team t ON t.team_id = tt.team_id
      JOIN tbl_sports_team st ON st.team_id = tt.team_id
      LEFT JOIN tbl_train_attend ta ON ta.person_id = tt.trainee_id
      LEFT JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      WHERE st.sports_id = ?
      AND (st.trainor1_id = ? OR st.trainor2_id = ? OR st.trainor3_id = ?)
      GROUP BY tt.trainee_id, p.f_name, p.m_name, p.l_name, t.team_name, tt.is_active
      ORDER BY p.l_name, p.f_name
    ");
    $stmt->execute([$person_id, $person_id, $person_id, $sports_id, $person_id, $person_id, $person_id]);
    $trainees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($trainees);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING ACTIVITIES
// ==========================================

if ($action === 'training_activities') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        activity_id,
        activity_name,
        duration,
        repetition,
        is_active
      FROM tbl_training_activity
      WHERE sports_id = ?
      AND is_active = 1
      ORDER BY activity_name
    ");
    $stmt->execute([$sports_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($activities);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Default response for unknown actions
out(['ok' => false, 'message' => 'Unknown action: ' . $action]);