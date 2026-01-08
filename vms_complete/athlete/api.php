<?php
// athlete/api.php - Complete with both Athlete and Trainee endpoints

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Accept both 'athlete' and 'athlete/player' roles
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'athlete' && $normalized_role !== 'athlete_player') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied. Athletes only.']);
}

header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)($_SESSION['user']['sports_id'] ?? 0);

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ==========================================
// ATHLETE ENDPOINTS
// ==========================================

if ($action === 'my_teams') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ta.team_id,
        ta.sports_id,
        ta.is_captain,
        t.team_name,
        s.sports_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS coach_name
      FROM tbl_team_athletes ta
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ta.team_id AND st.sports_id = ta.sports_id
      LEFT JOIN tbl_person p ON p.person_id = st.coach_id
      WHERE ta.person_id = :person_id
        AND ta.is_active = 1
      ORDER BY t.team_name, s.sports_name
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'team_players') {
  try {
    $team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        ta.person_id,
        CONCAT(p.f_name, ' ', p.l_name) AS player_name,
        t.team_name,
        ta.is_captain,
        s.sports_name
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      WHERE ta.is_active = 1
    ";

    if ($team_id) {
      $sql .= " AND ta.team_id = :team_id";
    }

    $sql .= " ORDER BY ta.is_captain DESC, p.l_name, p.f_name";

    $stmt = $pdo->prepare($sql);
    
    if ($team_id) {
      $stmt->execute(['team_id' => $team_id]);
    } else {
      $stmt->execute();
    }
    
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'my_matches') {
  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        m.match_id,
        m.sked_date,
        m.sked_time,
        IFNULL(m.match_type, 'Match') as match_type,
        m.sports_id,
        s.sports_name,
        m.team_a_id,
        m.team_b_id,
        IFNULL(ta.team_name, 'TBA') AS team_a_name,
        IFNULL(tb.team_name, 'TBA') AS team_b_name,
        IFNULL(v.venue_name, 'TBA') as venue_name,
        m.winner_id,
        tw.team_name AS winner_name
      FROM tbl_team_athletes athlete
      JOIN tbl_match m ON (m.team_a_id = athlete.team_id OR m.team_b_id = athlete.team_id)
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      WHERE athlete.person_id = :person_id
        AND athlete.is_active = 1
      ORDER BY m.sked_date DESC, m.sked_time DESC
    ");
    $stmt->execute(['person_id' => $person_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($result);
  } catch (PDOException $e) {
    error_log("MY_MATCHES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'training_schedule') {
  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        ts.sked_id,
        ts.team_id,
        ts.sked_date,
        ts.sked_time,
        t.team_name,
        IFNULL(v.venue_name, 'TBA') as venue_name,
        v.venue_building,
        v.venue_room,
        ta_attend.is_present
      FROM tbl_team_athletes athlete
      JOIN tbl_train_sked ts ON ts.team_id = athlete.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_attend ta_attend ON ta_attend.sked_id = ts.sked_id 
        AND ta_attend.person_id = ?
      WHERE athlete.person_id = ?
        AND athlete.is_active = 1
        AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
    ");
    $stmt->execute([$person_id, $person_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($result);
  } catch (PDOException $e) {
    error_log("TRAINING_SCHEDULE ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'rankings') {
  try {
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($team_id <= 0) {
      http_response_code(400);
      out(['ok' => false, 'message' => 'Invalid team ID']);
    }

    // Get the sports_id for this team
    $sport_stmt = $pdo->prepare("
      SELECT DISTINCT sports_id 
      FROM tbl_team_athletes 
      WHERE team_id = :team_id 
      LIMIT 1
    ");
    $sport_stmt->execute(['team_id' => $team_id]);
    $sport = $sport_stmt->fetch();

    if (!$sport) {
      out([]);
      exit;
    }

    $sports_id = $sport['sports_id'];

    // Get standings for this sport
    $stmt = $pdo->prepare("
      SELECT 
        ts.team_id,
        t.team_name,
        s.sports_name,
        ts.no_games_played,
        ts.no_win,
        ts.no_loss,
        ts.no_draw,
        ts.no_gold,
        ts.no_silver,
        ts.no_bronze
      FROM tbl_team_standing ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports s ON s.sports_id = ts.sports_id
      WHERE ts.sports_id = :sports_id
      ORDER BY ts.no_win DESC, ts.no_gold DESC, ts.no_silver DESC, ts.no_bronze DESC
      LIMIT 10
    ");
    $stmt->execute(['sports_id' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINEE ENDPOINTS
// ==========================================

if ($action === 'trainee_stats') {
  try {
    // Find which teams this athlete/trainee belongs to
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
      out([
        'sessions_attended' => 0,
        'attendance_rate' => 0,
        'streak' => 0,
        'total_hours' => 0
      ]);
      exit;
    }
    
    $team_ids_str = implode(',', array_map('intval', $team_ids));
    
    // 1. SCHEDULED sessions this month for athlete's teams
    $sessions_stmt = $pdo->query("
      SELECT COUNT(*) as count 
      FROM tbl_train_sked ts
      WHERE ts.team_id IN ({$team_ids_str})
      AND MONTH(ts.sked_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.sked_date) = YEAR(CURRENT_DATE())
      AND ts.is_active = 1
    ");
    $sessions_this_month = (int)$sessions_stmt->fetch()['count'];
    
    // 2. Attendance rate (ALL TIME)
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
    
    // 3. Current streak
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
    
    // 4. Total training sessions attended
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
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

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
          IFNULL(p1.f_name, ''), ' ', IFNULL(p1.l_name, ''),
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

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
          IFNULL(p1.f_name, ''), ' ', IFNULL(p1.l_name, ''),
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

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
          IFNULL(p1.f_name, ''), ' ', IFNULL(p1.l_name, ''),
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'my_programs') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        activity_id as program_id,
        activity_name as program_name,
        CONCAT(IFNULL(duration, ''), ' / ', IFNULL(repetition, '')) as description,
        4 as duration_weeks,
        'Improve fitness and skills' as goals,
        is_active
      FROM tbl_training_activity
      WHERE sports_id = ?
      AND is_active = 1
      ORDER BY activity_name
    ");
    $stmt->execute([$sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out([]);
  }
}

// Default 404 response
http_response_code(404);
out(['ok' => false, 'message' => 'Unknown action: ' . $action]);