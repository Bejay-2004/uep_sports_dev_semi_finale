<?php
// athlete/api.php - Updated to handle coach as trainor

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
// ATHLETE ENDPOINTS - FILTERED BY SPORTS_ID
// ==========================================

if ($action === 'my_teams') {
  try {
    error_log("MY_TEAMS: person_id={$person_id}, sports_id={$sports_id}");
    
    $stmt = $pdo->prepare("
      SELECT 
        ta.team_id,
        ta.sports_id,
        ta.is_captain,
        t.team_name,
        s.sports_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS coach_name,
        CONCAT(
          COALESCE(CONCAT(p1.f_name, ' ', p1.l_name), ''),
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
        ) AS trainor_names
      FROM tbl_team_athletes ta
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ta.team_id 
        AND st.sports_id = ta.sports_id
      LEFT JOIN tbl_person p ON p.person_id = st.coach_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE ta.person_id = :person_id
        AND ta.sports_id = :sports_id
        AND ta.is_active = 1
      ORDER BY t.team_name
    ");
    $stmt->execute(['person_id' => $person_id, 'sports_id' => $sports_id]);
    $results = $stmt->fetchAll();
    
    error_log("MY_TEAMS: Found " . count($results) . " teams");
    out($results);
  } catch (PDOException $e) {
    error_log("MY_TEAMS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'team_players') {
  try {
    $team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
    
    error_log("TEAM_PLAYERS: person_id={$person_id}, sports_id={$sports_id}, team_id={$team_id}");

    // First, get all teams this athlete belongs to
    $athlete_teams_stmt = $pdo->prepare("
      SELECT DISTINCT team_id 
      FROM tbl_team_athletes 
      WHERE person_id = :person_id 
        AND sports_id = :sports_id 
        AND is_active = 1
    ");
    $athlete_teams_stmt->execute([
      'person_id' => $person_id,
      'sports_id' => $sports_id
    ]);
    $athlete_team_ids = $athlete_teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If athlete has no teams, return empty
    if (empty($athlete_team_ids)) {
      error_log("TEAM_PLAYERS: Athlete not in any teams");
      out([]);
      exit;
    }

    // Build the WHERE clause
    if ($team_id) {
      // Verify the athlete is actually in this team
      if (!in_array($team_id, $athlete_team_ids)) {
        error_log("TEAM_PLAYERS: Athlete not authorized to view team_id={$team_id}");
        out([]);
        exit;
      }
      
      // Show only the selected team
      $where_clause = "AND ta.team_id = :team_id";
      $params = [
        'sports_id' => $sports_id,
        'team_id' => $team_id
      ];
    } else {
      // Show all players from athlete's teams
      $placeholders = implode(',', array_fill(0, count($athlete_team_ids), '?'));
      $where_clause = "AND ta.team_id IN ($placeholders)";
      $params = array_merge([$sports_id], $athlete_team_ids);
    }

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
        AND ta.sports_id = ?
        {$where_clause}
      ORDER BY ta.is_captain DESC, p.l_name, p.f_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    error_log("TEAM_PLAYERS: Found " . count($results) . " players");
    out($results);
  } catch (PDOException $e) {
    error_log("TEAM_PLAYERS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'my_matches') {
  try {
    error_log("MY_MATCHES: person_id={$person_id}, sports_id={$sports_id}");
    
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
        m.winner_team_id as winner_id,
        tw.team_name AS winner_name
      FROM tbl_team_athletes athlete
      JOIN tbl_match m ON (m.team_a_id = athlete.team_id OR m.team_b_id = athlete.team_id)
        AND m.sports_id = athlete.sports_id
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      WHERE athlete.person_id = :person_id
        AND athlete.sports_id = :sports_id
        AND athlete.is_active = 1
      ORDER BY m.sked_date DESC, m.sked_time DESC
    ");
    $stmt->execute(['person_id' => $person_id, 'sports_id' => $sports_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("MY_MATCHES: Found " . count($result) . " matches");
    out($result);
  } catch (PDOException $e) {
    error_log("MY_MATCHES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'training_schedule') {
  try {
    error_log("TRAINING_SCHEDULE: person_id={$person_id}, sports_id={$sports_id}");
    
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
        ta_attend.is_present,
        CONCAT(
          IFNULL(CONCAT(coach.f_name, ' ', coach.l_name), ''),
          CASE 
            WHEN p1.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p1.person_id)
            THEN CONCAT(CASE WHEN coach.person_id IS NOT NULL THEN ', ' ELSE '' END, p1.f_name, ' ', p1.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p2.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p2.person_id) AND (p1.person_id IS NULL OR p1.person_id != p2.person_id)
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p3.person_id) AND (p1.person_id IS NULL OR p1.person_id != p3.person_id) AND (p2.person_id IS NULL OR p2.person_id != p3.person_id)
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_names
      FROM tbl_team_athletes athlete
      JOIN tbl_train_sked ts ON ts.team_id = athlete.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ts.team_id AND st.sports_id = athlete.sports_id
      LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_attend ta_attend ON ta_attend.sked_id = ts.sked_id 
        AND ta_attend.person_id = ?
      WHERE athlete.person_id = ?
        AND athlete.sports_id = ?
        AND athlete.is_active = 1
        AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
    ");
    $stmt->execute([$person_id, $person_id, $sports_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("TRAINING_SCHEDULE: Found " . count($result) . " sessions");
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

    error_log("RANKINGS: team_id={$team_id}, sports_id={$sports_id}");

    // First verify the athlete is in this team
    $verify_stmt = $pdo->prepare("
      SELECT 1 
      FROM tbl_team_athletes 
      WHERE person_id = :person_id 
        AND team_id = :team_id 
        AND sports_id = :sports_id 
        AND is_active = 1
    ");
    $verify_stmt->execute([
      'person_id' => $person_id,
      'team_id' => $team_id,
      'sports_id' => $sports_id
    ]);
    
    if (!$verify_stmt->fetch()) {
      error_log("RANKINGS: Athlete not authorized to view team_id={$team_id}");
      http_response_code(403);
      out(['ok' => false, 'message' => 'You are not authorized to view rankings for this team']);
    }

    // Get ONLY this team's ranking/standing
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
      WHERE ts.team_id = :team_id
        AND ts.sports_id = :sports_id
      LIMIT 1
    ");
    $stmt->execute([
      'team_id' => $team_id,
      'sports_id' => $sports_id
    ]);
    $result = $stmt->fetch();
    
    if ($result) {
      error_log("RANKINGS: Found ranking for team_id={$team_id}");
      out([$result]); // Return as array with single item
    } else {
      error_log("RANKINGS: No ranking found for team_id={$team_id}");
      out([]); // Return empty array
    }
  } catch (PDOException $e) {
    error_log("RANKINGS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINEE ENDPOINTS
// ==========================================

if ($action === 'trainee_stats') {
  try {
    $trainee_teams_stmt = $pdo->prepare("
      SELECT DISTINCT team_id FROM tbl_team_trainees 
      WHERE trainee_id = ? AND is_active = 1
      UNION
      SELECT DISTINCT team_id FROM tbl_team_athletes 
      WHERE person_id = ? AND sports_id = ? AND is_active = 1
    ");
    $trainee_teams_stmt->execute([$person_id, $person_id, $sports_id]);
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
    
    $sessions_stmt = $pdo->query("
      SELECT COUNT(*) as count 
      FROM tbl_train_sked ts
      WHERE ts.team_id IN ({$team_ids_str})
      AND MONTH(ts.sked_date) = MONTH(CURRENT_DATE())
      AND YEAR(ts.sked_date) = YEAR(CURRENT_DATE())
      AND ts.is_active = 1
    ");
    $sessions_this_month = (int)$sessions_stmt->fetch()['count'];
    
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
    // Get only the teams where this athlete is a member (filtered by sports_id)
    $teams_stmt = $pdo->prepare("
      SELECT DISTINCT team_id 
      FROM tbl_team_athletes 
      WHERE person_id = :person_id 
        AND sports_id = :sports_id 
        AND is_active = 1
    ");
    $teams_stmt->execute([
      'person_id' => $person_id,
      'sports_id' => $sports_id
    ]);
    $team_ids = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($team_ids)) {
      out([]);
      exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
    
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
          IFNULL(CONCAT(coach.f_name, ' ', coach.l_name), ''),
          CASE 
            WHEN p1.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p1.person_id)
            THEN CONCAT(CASE WHEN coach.person_id IS NOT NULL THEN ', ' ELSE '' END, p1.f_name, ' ', p1.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p2.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p2.person_id) AND (p1.person_id IS NULL OR p1.person_id != p2.person_id)
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p3.person_id) AND (p1.person_id IS NULL OR p1.person_id != p3.person_id) AND (p2.person_id IS NULL OR p2.person_id != p3.person_id)
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE ts.team_id IN ($placeholders)
      AND ts.sked_date >= CURRENT_DATE()
      AND ts.is_active = 1
      ORDER BY ts.sked_date, ts.sked_time
      LIMIT 5
    ");
    $stmt->execute($team_ids);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'all_sessions') {
  try {
    $teams_stmt = $pdo->prepare("
      SELECT DISTINCT team_id FROM tbl_team_athletes 
      WHERE person_id = ? AND sports_id = ? AND is_active = 1
      UNION
      SELECT DISTINCT team_id FROM tbl_team_trainees 
      WHERE trainee_id = ? AND is_active = 1
    ");
    $teams_stmt->execute([$person_id, $sports_id, $person_id]);
    $team_ids = $teams_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($team_ids)) {
      out([]);
      exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
    
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
          IFNULL(CONCAT(coach.f_name, ' ', coach.l_name), ''),
          CASE 
            WHEN p1.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p1.person_id)
            THEN CONCAT(CASE WHEN coach.person_id IS NOT NULL THEN ', ' ELSE '' END, p1.f_name, ' ', p1.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p2.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p2.person_id) AND (p1.person_id IS NULL OR p1.person_id != p2.person_id)
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p3.person_id) AND (p1.person_id IS NULL OR p1.person_id != p3.person_id) AND (p2.person_id IS NULL OR p2.person_id != p3.person_id)
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
      LEFT JOIN tbl_game_venue gv ON gv.venue_id = ts.venue_id
      LEFT JOIN tbl_person p1 ON p1.person_id = st.trainor1_id
      LEFT JOIN tbl_person p2 ON p2.person_id = st.trainor2_id
      LEFT JOIN tbl_person p3 ON p3.person_id = st.trainor3_id
      WHERE ts.team_id IN ($placeholders)
      AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
      LIMIT 50
    ");
    $stmt->execute($team_ids);
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
          IFNULL(CONCAT(coach.f_name, ' ', coach.l_name), ''),
          CASE 
            WHEN p1.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p1.person_id)
            THEN CONCAT(CASE WHEN coach.person_id IS NOT NULL THEN ', ' ELSE '' END, p1.f_name, ' ', p1.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p2.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p2.person_id) AND (p1.person_id IS NULL OR p1.person_id != p2.person_id)
            THEN CONCAT(', ', p2.f_name, ' ', p2.l_name)
            ELSE ''
          END,
          CASE 
            WHEN p3.person_id IS NOT NULL AND (coach.person_id IS NULL OR coach.person_id != p3.person_id) AND (p1.person_id IS NULL OR p1.person_id != p3.person_id) AND (p2.person_id IS NULL OR p2.person_id != p3.person_id)
            THEN CONCAT(', ', p3.f_name, ' ', p3.l_name)
            ELSE ''
          END
        ) as trainor_name,
        'N/A' as duration
      FROM tbl_train_attend ta
      JOIN tbl_train_sked ts ON ts.sked_id = ta.sked_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
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

http_response_code(404);
out(['ok' => false, 'message' => 'Unknown action: ' . $action]);