<?php
// coach/api.php - Enhanced with training functionality

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('coach');

header("Content-Type: application/json; charset=utf-8");

$coach_person_id = (int)$_SESSION['user']['person_id'];
$sports_id       = (int)$_SESSION['user']['sports_id'];

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// VENUES
// ==========================================

if ($action === 'venues') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        venue_id,
        venue_name,
        venue_building,
        venue_room
      FROM tbl_game_venue
      WHERE is_active = 1
      ORDER BY venue_name
    ");
    $stmt->execute();
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS
// ==========================================

if ($action === 'teams') {
  try {
    $stmt = $pdo->prepare("
      SELECT st.tour_id, st.team_id, t.team_name,
             st.coach_id, st.asst_coach_id, st.sports_id,
             s.sports_name,
             CONCAT(cp.f_name,' ',cp.l_name) AS coach_name,
             CONCAT(ap.f_name,' ',ap.l_name) AS asst_coach_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      LEFT JOIN tbl_person cp ON cp.person_id = st.coach_id
      LEFT JOIN tbl_person ap ON ap.person_id = st.asst_coach_id
      WHERE st.coach_id = :coach_id
      ORDER BY s.sports_name, t.team_name
    ");
    $stmt->execute(['coach_id'=>$coach_person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// PLAYERS
// ==========================================

if ($action === 'players') {
  try {
    $stmt = $pdo->prepare("
      SELECT p.person_id, CONCAT(p.f_name,' ',p.l_name) AS player_name,
             t.team_name, ta.is_captain
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports_team st ON st.team_id = ta.team_id AND st.tour_id = ta.tour_id
      WHERE ta.sports_id = :sports_id
        AND st.coach_id = :coach_id
        AND ta.is_active = 1
    ");
    $stmt->execute(['sports_id'=>$sports_id, 'coach_id'=>$coach_person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STANDINGS
// ==========================================

if ($action === 'standings') {
  try {
    $stmt = $pdo->prepare("
      SELECT ts.tour_id, tr.tour_name, ts.team_id, t.team_name,
             ts.no_games_played, ts.no_win, ts.no_loss, ts.no_draw,
             ts.no_gold, ts.no_silver, ts.no_bronze
      FROM tbl_team_standing ts
      JOIN tbl_tournament tr ON tr.tour_id = ts.tour_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.sports_id = :sports_id
      ORDER BY tr.tour_date DESC, ts.no_win DESC
    ");
    $stmt->execute(['sports_id'=>$sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MATCHES
// ==========================================

// ==========================================
// MATCHES
// ==========================================

if ($action === 'matches') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        m.sked_date, 
        m.sked_time, 
        m.game_no, 
        m.match_type,
        v.venue_name,
        ta.team_name AS team_a,
        tb.team_name AS team_b,
        m.team_a_id,
        m.team_b_id
      FROM tbl_match m
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      WHERE m.sports_id = :sports_id
      ORDER BY m.sked_date ASC, m.sked_time ASC
    ");
    $stmt->execute(['sports_id'=>$sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// COACH MATCH HISTORY
// ==========================================

// ==========================================
// COACH MATCH HISTORY
// ==========================================

if ($action === 'coach_match_history') {
  try {
    // First, get coach's team IDs to simplify the main query
    $team_ids_stmt = $pdo->prepare("
      SELECT team_id 
      FROM tbl_sports_team 
      WHERE coach_id = :coach_id AND sports_id = :sports_id
    ");
    $team_ids_stmt->execute([
      'coach_id' => $coach_person_id,
      'sports_id' => $sports_id
    ]);
    $coach_teams = $team_ids_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($coach_teams)) {
      out([]);
      return;
    }
    
    // Create placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($coach_teams), '?'));
    
    // Get all matches where coach's teams participated
    $sql = "
      SELECT 
        m.match_id,
        m.game_no,
        m.sked_date,
        m.sked_time,
        m.match_type,
        v.venue_name,
        v.venue_building,
        s.sports_name,
        
        -- Team A info
        ta.team_name AS team_a_name,
        m.team_a_id,
        
        -- Team B info
        tb.team_name AS team_b_name,
        m.team_b_id,
        
        -- Winner info
        m.winner_team_id,
        m.winner_athlete_id,
        tw.team_name AS winner_team_name,
        CONCAT(pw.f_name, ' ', IFNULL(pw.l_name, '')) AS winner_athlete_name,
        
        -- Check if coach's team is involved
        CASE 
          WHEN m.team_a_id IN ($placeholders) THEN m.team_a_id
          WHEN m.team_b_id IN ($placeholders) THEN m.team_b_id
          ELSE NULL
        END AS coach_team_id,
        
        CASE 
          WHEN m.team_a_id IN ($placeholders) THEN ta.team_name
          WHEN m.team_b_id IN ($placeholders) THEN tb.team_name
          ELSE NULL
        END AS coach_team_name,
        
        CASE 
          WHEN m.team_a_id IN ($placeholders) THEN 'A'
          WHEN m.team_b_id IN ($placeholders) THEN 'B'
          ELSE NULL
        END AS coach_team_position,
        
        -- Opponent info
        CASE 
          WHEN m.team_a_id IN ($placeholders) THEN tb.team_name
          WHEN m.team_b_id IN ($placeholders) THEN ta.team_name
          ELSE NULL
        END AS opponent_name
        
      FROM tbl_match m
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
      WHERE m.sports_id = ?
        AND (
          m.team_a_id IN ($placeholders)
          OR
          m.team_b_id IN ($placeholders)
        )
      ORDER BY m.sked_date DESC, m.sked_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters - need to repeat coach_teams array for each IN clause
    $params = array_merge(
      $coach_teams,  // First IN clause (coach_team_id)
      $coach_teams,  // Second IN clause (coach_team_id check)
      $coach_teams,  // Third IN clause (coach_team_name)
      $coach_teams,  // Fourth IN clause (coach_team_name check)
      $coach_teams,  // Fifth IN clause (coach_team_position)
      $coach_teams,  // Sixth IN clause (coach_team_position check)
      $coach_teams,  // Seventh IN clause (opponent_name)
      $coach_teams,  // Eighth IN clause (opponent_name check)
      [$sports_id],  // WHERE sports_id
      $coach_teams,  // WHERE team_a_id IN
      $coach_teams   // WHERE team_b_id IN
    );
    
    $stmt->execute($params);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Now get scores and medals for each match
foreach ($matches as &$match) {
  // Get scores for coach's team only
  $scores_stmt = $pdo->prepare("
    SELECT 
      cs.score,
      cs.medal_type,
      CASE 
        WHEN cs.athlete_id IS NOT NULL THEN CONCAT(p.f_name, ' ', IFNULL(p.l_name, ''))
        ELSE t.team_name
      END AS scorer_name
    FROM tbl_comp_score cs
    LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
    LEFT JOIN tbl_team t ON t.team_id = cs.team_id
    WHERE cs.match_id = ? AND cs.team_id = ?
  ");
  $scores_stmt->execute([$match['match_id'], $match['coach_team_id']]);
  $scores = $scores_stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Format scores for coach's team
  $score_strings = [];
  $medal_won = null;
  
  foreach ($scores as $score) {
    if ($score['score']) {
      $score_strings[] = $score['score'] . ' (' . $score['scorer_name'] . ')';
    }
    if ($score['medal_type']) {
      $medal_won = $score['medal_type'];
    }
  }
  
  $match['coach_team_scores'] = !empty($score_strings) ? implode(', ', $score_strings) : null;
  $match['medal_won'] = $medal_won;
  
  // ADD THIS: Get opponent scores too
  $opponent_team_id = ($match['coach_team_position'] === 'A') ? $match['team_b_id'] : $match['team_a_id'];
  
  $opponent_scores_stmt = $pdo->prepare("
    SELECT 
      cs.score,
      CASE 
        WHEN cs.athlete_id IS NOT NULL THEN CONCAT(p.f_name, ' ', IFNULL(p.l_name, ''))
        ELSE t.team_name
      END AS scorer_name
    FROM tbl_comp_score cs
    LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
    LEFT JOIN tbl_team t ON t.team_id = cs.team_id
    WHERE cs.match_id = ? AND cs.team_id = ?
  ");
  $opponent_scores_stmt->execute([$match['match_id'], $opponent_team_id]);
  $opponent_scores = $opponent_scores_stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $opponent_score_strings = [];
  foreach ($opponent_scores as $score) {
    if ($score['score']) {
      $opponent_score_strings[] = $score['score'] . ' (' . $score['scorer_name'] . ')';
    }
  }
  
  $match['opponent_scores'] = !empty($opponent_score_strings) ? implode(', ', $opponent_score_strings) : null;
  
  // Determine match result
  if ($match['winner_team_id']) {
    $match['match_result'] = ($match['winner_team_id'] == $match['coach_team_id']) ? 'WON' : 'LOST';
  } else {
    $match['match_result'] = 'PENDING';
  }
}
    
    error_log("COACH_MATCH_HISTORY: Found " . count($matches) . " matches");
    
    out($matches);
  } catch (PDOException $e) {
    error_log("COACH_MATCH_HISTORY ERROR: " . $e->getMessage());
    error_log("COACH_MATCH_HISTORY TRACE: " . $e->getTraceAsString());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING LIST
// ==========================================

if ($action === 'training_list') {
  try {
    // First check if trainor_id column exists
    $cols = $pdo->query("SHOW COLUMNS FROM tbl_train_sked LIKE 'trainor_id'")->fetch();
    
    if ($cols) {
      // New schema with trainor_id
      $stmt = $pdo->prepare("
        SELECT 
          sk.sked_id,
          t.team_name,
          sk.sked_date,
          sk.sked_time,
          v.venue_name,
          sk.is_active
        FROM tbl_train_sked sk
        JOIN tbl_team t ON t.team_id = sk.team_id
        JOIN tbl_sports_team st ON st.team_id = sk.team_id
        LEFT JOIN tbl_game_venue v ON v.venue_id = sk.venue_id
        WHERE sk.trainor_id = :coach_id AND st.sports_id = :sports_id
        ORDER BY sk.sked_date DESC, sk.sked_time DESC
      ");
    } else {
      // Old schema - use sports_team join
      $stmt = $pdo->prepare("
        SELECT 
          sk.sked_id,
          t.team_name,
          sk.sked_date,
          sk.sked_time,
          v.venue_name,
          sk.is_active
        FROM tbl_train_sked sk
        JOIN tbl_team t ON t.team_id = sk.team_id
        JOIN tbl_sports_team st ON st.team_id = sk.team_id
        LEFT JOIN tbl_game_venue v ON v.venue_id = sk.venue_id
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
        ORDER BY sk.sked_date DESC, sk.sked_time DESC
      ");
    }
    
    $stmt->execute(['coach_id'=>$coach_person_id, 'sports_id'=>$sports_id]);
    $results = $stmt->fetchAll();
    
    error_log("TRAINING_LIST: Found " . count($results) . " sessions for coach {$coach_person_id}");
    
    out($results);
  } catch (PDOException $e) {
    error_log("TRAINING_LIST ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING TEAMS
// ==========================================

if ($action === 'training_teams') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        st.team_id, 
        st.tour_id,
        t.team_name,
        s.sports_name,
        st.sports_id,
        CONCAT(t.team_name, ' (', s.sports_name, ')') as display_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      WHERE st.coach_id = :coach_id
      ORDER BY s.sports_name, t.team_name ASC
    ");
    $stmt->execute(['coach_id'=>$coach_person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING CREATE
// ==========================================

if ($action === 'migrate_session_participants') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.team_id 
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    $session = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    $team_id = $session['team_id'];
    
    // Check if participants already exist
    $check = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_train_sked_participants WHERE sked_id = ?");
    $check->execute([$sked_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing['cnt'] > 0) {
      out(['ok' => true, 'message' => 'Participants already assigned', 'count' => $existing['cnt']]);
    }
    
    // Get all athletes for this team
    $athletes = $pdo->prepare("
      SELECT person_id 
      FROM tbl_team_athletes 
      WHERE team_id = ? AND sports_id = ? AND is_active = 1
    ");
    $athletes->execute([$team_id, $sports_id]);
    
    // Get all trainees for this team - UPDATED to use person_id
    $trainees = $pdo->prepare("
      SELECT person_id 
      FROM tbl_team_trainees 
      WHERE team_id = ? AND is_active = 1
    ");
    $trainees->execute([$team_id]);
    
    $stmt = $pdo->prepare("
      INSERT INTO tbl_train_sked_participants (sked_id, person_id, participant_type)
      VALUES (?, ?, ?)
    ");
    
    $count = 0;
    
    // Add athletes
    while ($athlete = $athletes->fetch(PDO::FETCH_ASSOC)) {
      $stmt->execute([$sked_id, $athlete['person_id'], 'athlete']);
      $count++;
    }
    
    // Add trainees
    while ($trainee = $trainees->fetch(PDO::FETCH_ASSOC)) {
      $stmt->execute([$sked_id, $trainee['person_id'], 'trainee']);
      $count++;
    }
    
    out(['ok' => true, 'message' => "Successfully assigned {$count} participants", 'count' => $count]);
    
  } catch (PDOException $e) {
    error_log("MIGRATE_PARTICIPANTS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}



// Add this endpoint to auto-migrate all sessions
if ($action === 'migrate_all_sessions') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    // Get all sessions for this coach that don't have participants
    $sessions = $pdo->prepare("
      SELECT ts.sked_id, ts.team_id
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE st.coach_id = ? AND st.sports_id = ?
      AND NOT EXISTS (
        SELECT 1 FROM tbl_train_sked_participants 
        WHERE sked_id = ts.sked_id
      )
    ");
    $sessions->execute([$coach_person_id, $sports_id]);
    
    $insert_stmt = $pdo->prepare("
      INSERT INTO tbl_train_sked_participants (sked_id, person_id, participant_type)
      VALUES (?, ?, ?)
    ");
    
    $total_sessions = 0;
    $total_participants = 0;
    
    while ($session = $sessions->fetch(PDO::FETCH_ASSOC)) {
      $sked_id = $session['sked_id'];
      $team_id = $session['team_id'];
      
      // Get athletes
      $athletes = $pdo->prepare("
        SELECT person_id FROM tbl_team_athletes 
        WHERE team_id = ? AND sports_id = ? AND is_active = 1
      ");
      $athletes->execute([$team_id, $sports_id]);
      
      while ($athlete = $athletes->fetch(PDO::FETCH_ASSOC)) {
        $insert_stmt->execute([$sked_id, $athlete['person_id'], 'athlete']);
        $total_participants++;
      }
      
      // Get trainees
      $trainees = $pdo->prepare("
        SELECT trainee_id as person_id FROM tbl_team_trainees 
        WHERE team_id = ? AND is_active = 1
      ");
      $trainees->execute([$team_id]);
      
      while ($trainee = $trainees->fetch(PDO::FETCH_ASSOC)) {
        $insert_stmt->execute([$sked_id, $trainee['person_id'], 'trainee']);
        $total_participants++;
      }
      
      $total_sessions++;
    }
    
    out([
      'ok' => true, 
      'message' => "Migrated {$total_sessions} sessions with {$total_participants} total participants",
      'sessions' => $total_sessions,
      'participants' => $total_participants
    ]);
    
  } catch (PDOException $e) {
    error_log("MIGRATE_ALL ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}



// Replace the training_create section in api.php with this improved version

if ($action === 'training_create') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_id  = (int)($_POST['team_id'] ?? 0);
    $date     = $_POST['sked_date'] ?? '';
    $time     = $_POST['sked_time'] ?? '';
    $venue_id = (int)($_POST['venue_id'] ?? 0);

    if ($team_id <= 0 || !$date || !$time || $venue_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing fields']);
    }

    // Validate team belongs to coach + sport
    $chk = $pdo->prepare("
      SELECT 1 FROM tbl_sports_team
      WHERE team_id = :team_id AND coach_id = :coach_id AND sports_id = :sports_id
      LIMIT 1
    ");
    $chk->execute([
      'team_id'=>$team_id,
      'coach_id'=>$coach_person_id,
      'sports_id'=>$sports_id
    ]);
    if (!$chk->fetch()) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'You are not assigned to this team/sport']);
    }

    // Validate venue exists and active
    $vchk = $pdo->prepare("
      SELECT 1 FROM tbl_game_venue
      WHERE venue_id = :venue_id AND is_active = 1
      LIMIT 1
    ");
    $vchk->execute(['venue_id'=>$venue_id]);
    if (!$vchk->fetch()) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid venue']);
    }

    // Check if tbl_train_sked has trainor_id column
    $cols = $pdo->query("SHOW COLUMNS FROM tbl_train_sked LIKE 'trainor_id'")->fetch();
    
    if ($cols) {
      // New schema with trainor_id
      $ins = $pdo->prepare("
        INSERT INTO tbl_train_sked (team_id, trainor_id, sked_date, sked_time, venue_id, is_active)
        VALUES (:team_id, :trainor_id, :sked_date, :sked_time, :venue_id, 1)
      ");
      $ins->execute([
        'team_id'=>$team_id,
        'trainor_id'=>$coach_person_id,
        'sked_date'=>$date,
        'sked_time'=>$time,
        'venue_id'=>$venue_id
      ]);
    } else {
      // Old schema without trainor_id
      $ins = $pdo->prepare("
        INSERT INTO tbl_train_sked (team_id, sked_date, sked_time, venue_id, is_active)
        VALUES (:team_id, :sked_date, :sked_time, :venue_id, 1)
      ");
      $ins->execute([
        'team_id'=>$team_id,
        'sked_date'=>$date,
        'sked_time'=>$time,
        'venue_id'=>$venue_id
      ]);
    }
    
    $sked_id = $pdo->lastInsertId();
    error_log("TRAINING_CREATE: Created session sked_id={$sked_id}");

    out(['ok'=>true,'message'=>'Training schedule saved', 'sked_id' => $sked_id]);
  } catch (PDOException $e) {
    error_log("TRAINING_CREATE ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}



// ==========================================
// TRAINEES
// ==========================================

// ==========================================
// TRAINEES (Now Athletes/Team Members)
// ==========================================

if ($action === 'trainees') {
  try {
    error_log("TRAINEES: coach_id={$coach_person_id}");
    
    // Get athletes from BOTH tbl_team_athletes AND tbl_team_trainees
    // tbl_team_trainees.person_id now references athletes, not trainees
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        p.person_id,
        CONCAT(IFNULL(p.f_name, ''), ' ', IFNULL(p.l_name, '')) as trainee_name,
        t.team_name,
        COALESCE(ats.semester, tt.semester, 'N/A') as semester,
        COALESCE(ats.school_year, tt.school_year, 'N/A') as school_year,
        CASE 
          WHEN ta.team_ath_id IS NOT NULL THEN 'Current Semester'
          ELSE COALESCE(DATE_FORMAT(tt.date_applied, '%Y-%m-%d'), 'N/A')
        END as date_applied,
        COALESCE(ta.is_active, tt.is_active, 1) as is_active,
        p.role_type,
        CASE 
          WHEN ta.team_ath_id IS NOT NULL THEN 'Athlete (Official)'
          WHEN tt.team_id IS NOT NULL THEN 'Athlete (Trainee)'
          ELSE 'Other'
        END as member_type
      FROM tbl_person p
      LEFT JOIN tbl_team_athletes ta ON ta.person_id = p.person_id AND ta.is_active = 1
      LEFT JOIN tbl_team_trainees tt ON tt.person_id = p.person_id AND tt.is_active = 1
      LEFT JOIN tbl_ath_status ats ON ats.person_id = p.person_id
      JOIN tbl_team t ON (t.team_id = ta.team_id OR t.team_id = tt.team_id)
      JOIN tbl_sports_team st ON st.team_id = t.team_id
      WHERE st.coach_id = :coach_id
        AND st.sports_id = :sports_id
        AND (ta.team_ath_id IS NOT NULL OR tt.team_id IS NOT NULL)
        AND p.is_active = 1
      ORDER BY 
        CASE 
          WHEN ta.team_ath_id IS NOT NULL THEN 1
          ELSE 2
        END,
        t.team_name, 
        p.l_name, 
        p.f_name
    ");
    
    $stmt->execute(['coach_id' => $coach_person_id, 'sports_id' => $sports_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("TRAINEES: Found " . count($results) . " members");
    
    out($results);
  } catch (PDOException $e) {
    error_log("TRAINEES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ACTIVITIES (GET ALL)
// ==========================================

if ($action === 'activities') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        activity_id,
        activity_name,
        duration,
        repetition,
        is_active
      FROM tbl_training_activity
      WHERE sports_id = :sports_id
      ORDER BY activity_name
    ");
    $stmt->execute(['sports_id' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SAVE ACTIVITY
// ==========================================

if ($action === 'save_activity') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $activity_name = trim($_POST['activity_name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $repetition = trim($_POST['repetition'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($activity_name)) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Activity name is required']);
    }

    if ($activity_id > 0) {
      // Update existing
      $stmt = $pdo->prepare("
        UPDATE tbl_training_activity
        SET activity_name = :activity_name,
            duration = :duration,
            repetition = :repetition,
            is_active = :is_active
        WHERE activity_id = :activity_id AND sports_id = :sports_id
      ");
      $stmt->execute([
        'activity_id' => $activity_id,
        'activity_name' => $activity_name,
        'duration' => $duration ?: null,
        'repetition' => $repetition ?: null,
        'is_active' => $is_active,
        'sports_id' => $sports_id
      ]);
      out(['ok'=>true,'message'=>'Activity updated successfully']);
    } else {
      // Insert new
      $stmt = $pdo->prepare("
        INSERT INTO tbl_training_activity 
        (activity_name, sports_id, duration, repetition, is_active)
        VALUES (:activity_name, :sports_id, :duration, :repetition, :is_active)
      ");
      $stmt->execute([
        'activity_name' => $activity_name,
        'sports_id' => $sports_id,
        'duration' => $duration ?: null,
        'repetition' => $repetition ?: null,
        'is_active' => $is_active
      ]);
      out(['ok'=>true,'message'=>'Activity created successfully']);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET ACTIVITY
// ==========================================

if ($action === 'get_activity') {
  try {
    $activity_id = (int)($_GET['activity_id'] ?? 0);
    
    if ($activity_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid activity ID']);
    }
    
    $stmt = $pdo->prepare("
      SELECT 
        activity_id,
        activity_name,
        duration,
        repetition,
        is_active
      FROM tbl_training_activity
      WHERE activity_id = :activity_id AND sports_id = :sports_id
      LIMIT 1
    ");
    $stmt->execute(['activity_id' => $activity_id, 'sports_id' => $sports_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activity) {
      out($activity);
    } else {
      out(['ok' => false, 'message' => 'Activity not found']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// DELETE ACTIVITY (DEACTIVATE)
// ==========================================

if ($action === 'delete_activity') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $activity_id = (int)($_POST['activity_id'] ?? 0);

    if ($activity_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid activity ID']);
    }

    // Deactivate instead of delete
    $stmt = $pdo->prepare("
      UPDATE tbl_training_activity
      SET is_active = 0
      WHERE activity_id = :activity_id AND sports_id = :sports_id
    ");
    $stmt->execute(['activity_id' => $activity_id, 'sports_id' => $sports_id]);

    if ($stmt->rowCount() > 0) {
      out(['ok' => true, 'message' => 'Activity deactivated successfully']);
    } else {
      out(['ok' => false, 'message' => 'Activity not found']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
  }
}

// ==========================================
// REPORT: ATTENDANCE
// ==========================================


// ==========================================
// REPORT: SESSION DETAILS
// ==========================================

if ($action === 'report_session_details') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.sked_id, ts.team_id, ts.sked_date, ts.sked_time,
             t.team_name, v.venue_name, v.venue_building, v.venue_room,
             CONCAT(c.f_name, ' ', c.l_name) as coach_name
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_person c ON c.person_id = st.coach_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    $session = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    // Get activities
    $activities_stmt = $pdo->prepare("
      SELECT sa.sequence_order, ta.activity_name, ta.duration, ta.repetition
      FROM tbl_train_sked_activities sa
      JOIN tbl_training_activity ta ON ta.activity_id = sa.activity_id
      WHERE sa.sked_id = ?
      ORDER BY sa.sequence_order
    ");
    $activities_stmt->execute([$sked_id]);
    $session['activities'] = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get participants with attendance
    $participants_stmt = $pdo->prepare("
      SELECT 
        CONCAT(p.f_name, ' ', IFNULL(p.m_name, ''), ' ', p.l_name) as full_name,
        sp.participant_type,
        IFNULL(ta.is_present, 0) as is_present,
        p.date_birth,
        p.blood_type,
        p.course
      FROM tbl_train_sked_participants sp
      JOIN tbl_person p ON p.person_id = sp.person_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = sp.sked_id AND ta.person_id = sp.person_id
      WHERE sp.sked_id = ?
      ORDER BY sp.participant_type, p.l_name, p.f_name
    ");
    $participants_stmt->execute([$sked_id]);
    $session['participants'] = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance stats
    $total = count($session['participants']);
    $present = 0;
    $athletes_present = 0;
    $trainees_present = 0;
    $athletes_total = 0;
    $trainees_total = 0;
    
    foreach ($session['participants'] as $p) {
      if ($p['is_present'] == 1) {
        $present++;
        if ($p['participant_type'] === 'athlete') {
          $athletes_present++;
        } else {
          $trainees_present++;
        }
      }
      if ($p['participant_type'] === 'athlete') {
        $athletes_total++;
      } else {
        $trainees_total++;
      }
    }
    
    $session['stats'] = [
      'total_participants' => $total,
      'total_present' => $present,
      'total_absent' => $total - $present,
      'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
      'athletes_total' => $athletes_total,
      'athletes_present' => $athletes_present,
      'trainees_total' => $trainees_total,
      'trainees_present' => $trainees_present
    ];
    
    out($session);
  } catch (PDOException $e) {
    error_log("REPORT_SESSION_DETAILS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORT: ALL SESSIONS LIST
// ==========================================

if ($action === 'report_sessions_list') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ts.sked_id,
        ts.sked_date,
        ts.sked_time,
        t.team_name,
        v.venue_name,
        COUNT(DISTINCT sp.person_id) as total_participants,
        COUNT(DISTINCT CASE WHEN ta.is_present = 1 THEN ta.person_id END) as present_count
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_sked_participants sp ON sp.sked_id = ts.sked_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id AND ta.person_id = sp.person_id
      WHERE st.coach_id = ? AND st.sports_id = ?
      GROUP BY ts.sked_id, ts.sked_date, ts.sked_time, t.team_name, v.venue_name
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
    ");
    $stmt->execute([$coach_person_id, $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}



if ($action === 'report_attendance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    // Build WHERE conditions
    $conditions = [];
    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];
    
    if ($date_from) {
      $conditions[] = "ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }
    if ($date_to) {
      $conditions[] = "ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }
    if ($team_id > 0) {
      $conditions[] = "ts.team_id = :team_id";
      $params['team_id'] = $team_id;
    }
    
    $where_clause = !empty($conditions) ? 'AND ' . implode(' AND ', $conditions) : '';
    
    $sql = "
      SELECT 
        ts.sked_id,
        ts.sked_date,
        ts.sked_time,
        t.team_name,
        v.venue_name,
        COUNT(DISTINCT sp.person_id) as total_members,
        COUNT(DISTINCT CASE WHEN ta.is_present = 1 THEN ta.person_id END) as present_count,
        ROUND(
          (COUNT(DISTINCT CASE WHEN ta.is_present = 1 THEN ta.person_id END) / 
           NULLIF(COUNT(DISTINCT sp.person_id), 0)) * 100, 
          1
        ) as attendance_rate
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_sked_participants sp ON sp.sked_id = ts.sked_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id AND ta.person_id = sp.person_id
      WHERE st.coach_id = :coach_id 
        AND st.sports_id = :sports_id
        $where_clause
      GROUP BY ts.sked_id, ts.sked_date, ts.sked_time, t.team_name, v.venue_name
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("REPORT_ATTENDANCE: Found " . count($results) . " sessions");
    
    out($results);
  } catch (PDOException $e) {
    error_log("REPORT_ATTENDANCE ERROR: " . $e->getMessage());
    error_log("REPORT_ATTENDANCE SQL: " . ($sql ?? 'N/A'));
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORT: PERFORMANCE
// ==========================================

if ($action === 'report_performance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    $sql = "
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as player_name,
        t.team_name,
        COUNT(tp.perf_id) as total_evaluations,
        ROUND(AVG(tp.rating), 2) as avg_rating,
        MAX(tp.rating) as max_rating,
        MIN(tp.rating) as min_rating,
        MAX(tp.date_eval) as last_evaluated
      FROM tbl_train_perf tp
      JOIN tbl_person p ON p.person_id = tp.person_id
      JOIN tbl_team t ON t.team_id = tp.team_id
      WHERE tp.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
    ";
    
    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];
    
    if ($date_from) {
      $sql .= " AND tp.date_eval >= :date_from";
      $params['date_from'] = $date_from;
    }
    if ($date_to) {
      $sql .= " AND tp.date_eval <= :date_to";
      $params['date_to'] = $date_to;
    }
    if ($team_id > 0) {
      $sql .= " AND tp.team_id = :team_id";
      $params['team_id'] = $team_id;
    }
    
    $sql .= " GROUP BY p.person_id, player_name, t.team_name";
    $sql .= " ORDER BY avg_rating DESC, player_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET SESSION DETAILS (with activities and participants)
// ==========================================

if ($action === 'session_details') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.*, t.team_name, v.venue_name, v.venue_building, v.venue_room
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
      LIMIT 1
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    $session = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    // Get planned activities for this session
    $activities_stmt = $pdo->prepare("
      SELECT sa.sked_activity_id, sa.activity_id, sa.sequence_order, sa.notes,
             ta.activity_name, ta.duration, ta.repetition
      FROM tbl_train_sked_activities sa
      JOIN tbl_training_activity ta ON ta.activity_id = sa.activity_id
      WHERE sa.sked_id = ?
      ORDER BY sa.sequence_order
    ");
    $activities_stmt->execute([$sked_id]);
    $session['activities'] = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned participants for this session
    $participants_stmt = $pdo->prepare("
      SELECT sp.participant_id, sp.person_id, sp.participant_type, sp.notes,
             CONCAT(p.f_name, ' ', p.l_name) as full_name
      FROM tbl_train_sked_participants sp
      JOIN tbl_person p ON p.person_id = sp.person_id
      WHERE sp.sked_id = ?
      ORDER BY p.l_name, p.f_name
    ");
    $participants_stmt->execute([$sked_id]);
    $session['participants'] = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    out($session);
  } catch (PDOException $e) {
    error_log("SESSION_DETAILS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ADD ACTIVITIES TO SESSION
// ==========================================

if ($action === 'session_add_activities') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    $activity_ids = json_decode($_POST['activity_ids'] ?? '[]', true);
    
    if ($sked_id <= 0 || empty($activity_ids) || !is_array($activity_ids)) {
      out(['ok' => false, 'message' => 'Invalid parameters']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT 1 FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    // Clear existing activities
    $pdo->prepare("DELETE FROM tbl_train_sked_activities WHERE sked_id = ?")->execute([$sked_id]);
    
    // Add new activities
    $stmt = $pdo->prepare("
      INSERT INTO tbl_train_sked_activities (sked_id, activity_id, sequence_order)
      VALUES (?, ?, ?)
    ");
    
    $sequence = 1;
    foreach ($activity_ids as $activity_id) {
      $stmt->execute([$sked_id, (int)$activity_id, $sequence]);
      $sequence++;
    }
    
    out(['ok' => true, 'message' => 'Activities added successfully']);
  } catch (PDOException $e) {
    error_log("ADD_ACTIVITIES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SESSION EQUIPMENT - GET (Add this to api.php)
// ==========================================

if ($action === 'session_equipment') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    // Verify coach owns this session (optional security check)
    $verify = $pdo->prepare("
      SELECT 1 FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'Access denied']);
    }
    
    // Get equipment for this session
    $stmt = $pdo->prepare("
      SELECT 
        se.sked_equip_id,
        se.equip_id, 
        se.quantity_used, 
        se.notes,
        e.equip_name, 
        e.description,
        e.quantity as total_available
      FROM tbl_train_sked_equipment se
      JOIN tbl_team_equipment e ON e.equip_id = se.equip_id
      WHERE se.sked_id = ?
      ORDER BY e.equip_name
    ");
    $stmt->execute([$sked_id]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    out($equipment);
  } catch (PDOException $e) {
    error_log("SESSION_EQUIPMENT ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// EQUIPMENT FOR SESSIONS
// ==========================================

if ($action === 'equipment_list') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        e.equip_id,
        e.equip_name,
        e.description,
        e.quantity,
        e.is_functional,
        COALESCE(SUM(tse.quantity_used), 0) as total_borrowed
      FROM tbl_team_equipment e
      LEFT JOIN tbl_train_sked_equipment tse ON tse.equip_id = e.equip_id
      LEFT JOIN tbl_train_sked ts ON ts.sked_id = tse.sked_id
      WHERE e.is_functional = 1
      GROUP BY e.equip_id, e.equip_name, e.description, e.quantity, e.is_functional
      ORDER BY e.equip_name
    ");
    $stmt->execute();
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate available quantity for each equipment
    foreach ($equipment as &$equip) {
      $equip['total_borrowed'] = (int)$equip['total_borrowed'];
      $equip['available'] = max(0, (int)$equip['quantity'] - (int)$equip['total_borrowed']);
      $equip['in_stock'] = $equip['available'] > 0;
    }
    
    out($equipment);
  } catch (PDOException $e) {
    error_log("EQUIPMENT_LIST ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SESSION EQUIPMENT (Add/View)
// ==========================================

// ==========================================
// SESSION ADD EQUIPMENT (With Stock Validation)
// ==========================================

if ($action === 'session_add_equipment') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    $equipment = json_decode($_POST['equipment'] ?? '[]', true);
    
    error_log("SESSION_ADD_EQUIPMENT: sked_id={$sked_id}, equipment=" . json_encode($equipment));
    
    if ($sked_id <= 0 || !is_array($equipment)) {
      out(['ok' => false, 'message' => 'Invalid parameters']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.sked_date, ts.team_id
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    $session = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    $session_date = $session['sked_date'];
    error_log("SESSION_ADD_EQUIPMENT: session_date={$session_date}");
    
    // Validate stock availability for each equipment
    $stock_errors = [];
    
    foreach ($equipment as $equip) {
      $equip_id = (int)($equip['equip_id'] ?? 0);
      $requested_qty = (int)($equip['quantity'] ?? 1);
      
      if ($equip_id <= 0 || $requested_qty <= 0) {
        continue;
      }
      
      // Get equipment details and calculate available quantity
      $check_stock = $pdo->prepare("
        SELECT 
          e.equip_name,
          e.quantity as total_quantity,
          COALESCE(SUM(CASE 
            WHEN ts.sked_date = ? AND ts.sked_id != ?
            THEN tse.quantity_used 
            ELSE 0 
          END), 0) as borrowed_on_date
        FROM tbl_team_equipment e
        LEFT JOIN tbl_train_sked_equipment tse ON tse.equip_id = e.equip_id
        LEFT JOIN tbl_train_sked ts ON ts.sked_id = tse.sked_id
        WHERE e.equip_id = ? AND e.is_functional = 1
        GROUP BY e.equip_id, e.equip_name, e.quantity
      ");
      $check_stock->execute([$session_date, $sked_id, $equip_id]);
      $stock_info = $check_stock->fetch(PDO::FETCH_ASSOC);
      
      if (!$stock_info) {
        $stock_errors[] = "Equipment ID {$equip_id} not found or not functional";
        continue;
      }
      
      $available = (int)$stock_info['total_quantity'] - (int)$stock_info['borrowed_on_date'];
      
      error_log("STOCK CHECK: {$stock_info['equip_name']} - total={$stock_info['total_quantity']}, borrowed={$stock_info['borrowed_on_date']}, available={$available}, requested={$requested_qty}");
      
      if ($requested_qty > $available) {
        $stock_errors[] = "{$stock_info['equip_name']}: Only {$available} available (you requested {$requested_qty})";
      }
    }
    
    // If there are stock errors, return them
    if (!empty($stock_errors)) {
      error_log("STOCK ERRORS: " . json_encode($stock_errors));
      out([
        'ok' => false, 
        'message' => 'Equipment not available in requested quantities',
        'errors' => $stock_errors
      ]);
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
      // Get existing equipment for this session to handle updates
      $existing_stmt = $pdo->prepare("
        SELECT equip_id, quantity_used 
        FROM tbl_train_sked_equipment 
        WHERE sked_id = ?
      ");
      $existing_stmt->execute([$sked_id]);
      $existing_equipment = $existing_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
      
      // Return previously borrowed equipment to inventory (mark as "in")
      foreach ($existing_equipment as $old_equip_id => $old_quantity) {
        // Record return transaction in inventory
        $return_stmt = $pdo->prepare("
          INSERT INTO tbl_equip_inventory 
          (equip_id, trans_type, transdate, trans_by, rec_rel_by, equip_cond)
          VALUES (?, 'in', NOW(), ?, ?, 'Good')
        ");
        $return_stmt->execute([
          $old_equip_id,
          $coach_person_id,
          $coach_person_id
        ]);
        
        error_log("RETURNED EQUIPMENT: equip_id={$old_equip_id}, qty={$old_quantity}");
      }
      
      // Clear existing equipment assignments
      $pdo->prepare("DELETE FROM tbl_train_sked_equipment WHERE sked_id = ?")->execute([$sked_id]);
      
      // Add new equipment assignments and record inventory transactions
      if (!empty($equipment)) {
        $stmt = $pdo->prepare("
          INSERT INTO tbl_train_sked_equipment (sked_id, equip_id, quantity_used)
          VALUES (?, ?, ?)
        ");
        
        $inventory_stmt = $pdo->prepare("
          INSERT INTO tbl_equip_inventory 
          (equip_id, trans_type, transdate, trans_by, rec_rel_by, equip_cond)
          VALUES (?, 'out', NOW(), ?, ?, 'Good')
        ");
        
        foreach ($equipment as $equip) {
          $equip_id = (int)($equip['equip_id'] ?? 0);
          $quantity = (int)($equip['quantity'] ?? 1);
          
          if ($equip_id > 0 && $quantity > 0) {
            // Record equipment assignment to session
            $stmt->execute([$sked_id, $equip_id, $quantity]);
            
            // Record inventory transaction (borrowing - "out")
            // Note: We insert one record per unit borrowed for proper tracking
            for ($i = 0; $i < $quantity; $i++) {
              $inventory_stmt->execute([
                $equip_id,
                $coach_person_id,
                $coach_person_id
              ]);
            }
            
            error_log("BORROWED EQUIPMENT: sked_id={$sked_id}, equip_id={$equip_id}, qty={$quantity}");
          }
        }
      }
      
      $pdo->commit();
      out(['ok' => true, 'message' => 'Equipment assigned successfully']);
      
    } catch (Exception $e) {
      $pdo->rollBack();
      throw $e;
    }
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log("ADD_EQUIPMENT ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// CHECK EQUIPMENT AVAILABILITY FOR SPECIFIC DATE
// ==========================================

if ($action === 'check_equipment_availability') {
  try {
    $date = $_GET['date'] ?? '';
    $sked_id = (int)($_GET['sked_id'] ?? 0);
    
    if (!$date) {
      out(['ok' => false, 'message' => 'Date is required']);
    }
    
    error_log("CHECK_EQUIPMENT_AVAILABILITY: date={$date}, sked_id={$sked_id}");
    
    $stmt = $pdo->prepare("
      SELECT 
        e.equip_id,
        e.equip_name,
        e.description,
        e.quantity as total_quantity,
        -- Calculate total borrowed on specific date (excluding current session if editing)
        COALESCE(SUM(CASE 
          WHEN ts.sked_date = :check_date AND ts.sked_id != :sked_id
          THEN tse.quantity_used 
          ELSE 0 
        END), 0) as borrowed_on_date,
        -- Calculate total currently out from inventory (overall stock level)
        (
          SELECT COALESCE(
            (SELECT COUNT(*) FROM tbl_equip_inventory WHERE equip_id = e.equip_id AND trans_type = 'out') -
            (SELECT COUNT(*) FROM tbl_equip_inventory WHERE equip_id = e.equip_id AND trans_type = 'in'),
            0
          )
        ) as currently_out
      FROM tbl_team_equipment e
      LEFT JOIN tbl_train_sked_equipment tse ON tse.equip_id = e.equip_id
      LEFT JOIN tbl_train_sked ts ON ts.sked_id = tse.sked_id
      WHERE e.is_functional = 1
      GROUP BY e.equip_id, e.equip_name, e.description, e.quantity
      ORDER BY e.equip_name
    ");
    $stmt->execute([
      'check_date' => $date,
      'sked_id' => $sked_id
    ]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($equipment as &$equip) {
      $equip['total_quantity'] = (int)$equip['total_quantity'];
      $equip['borrowed_on_date'] = (int)$equip['borrowed_on_date'];
      $equip['currently_out'] = (int)$equip['currently_out'];
      
      // Available = Total - Currently Out in Inventory - Borrowed on this specific date
      $equip['available'] = max(0, $equip['total_quantity'] - $equip['currently_out'] - $equip['borrowed_on_date']);
      $equip['in_stock'] = $equip['available'] > 0;
      
      error_log("EQUIPMENT {$equip['equip_name']}: total={$equip['total_quantity']}, currently_out={$equip['currently_out']}, borrowed_on_date={$equip['borrowed_on_date']}, available={$equip['available']}");
    }
    
    error_log("CHECK_EQUIPMENT_AVAILABILITY: Found " . count($equipment) . " equipment items");
    
    out(['ok' => true, 'equipment' => $equipment]);
  } catch (PDOException $e) {
    error_log("CHECK_EQUIPMENT_AVAILABILITY ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Add this new action to api.php
if ($action === 'return_session_equipment') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    
    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT 1 FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    $pdo->beginTransaction();
    
    try {
      // Get all equipment for this session
      $equipment_stmt = $pdo->prepare("
        SELECT equip_id, quantity_used 
        FROM tbl_train_sked_equipment 
        WHERE sked_id = ?
      ");
      $equipment_stmt->execute([$sked_id]);
      $equipment_list = $equipment_stmt->fetchAll(PDO::FETCH_ASSOC);
      
      if (empty($equipment_list)) {
        $pdo->rollBack();
        out(['ok' => false, 'message' => 'No equipment found for this session']);
      }
      
      // Record return transaction for each equipment
      $return_stmt = $pdo->prepare("
        INSERT INTO tbl_equip_inventory 
        (equip_id, trans_type, transdate, trans_by, rec_rel_by, equip_cond)
        VALUES (?, 'in', NOW(), ?, ?, ?)
      ");
      
      foreach ($equipment_list as $equip) {
        // Insert one "in" transaction per unit borrowed
        for ($i = 0; $i < $equip['quantity_used']; $i++) {
          $return_stmt->execute([
            $equip['equip_id'],
            $coach_person_id,
            $coach_person_id,
            $_POST['equip_cond'] ?? 'Good' // Can pass condition: Good, Damaged, etc.
          ]);
        }
      }
      
      $pdo->commit();
      out(['ok' => true, 'message' => 'Equipment returned successfully']);
      
    } catch (Exception $e) {
      $pdo->rollBack();
      throw $e;
    }
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log("RETURN_EQUIPMENT ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ADD PARTICIPANTS TO SESSION
// ==========================================

if ($action === 'session_add_participants') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    $participants = json_decode($_POST['participants'] ?? '[]', true);
    
    if ($sked_id <= 0 || empty($participants) || !is_array($participants)) {
      out(['ok' => false, 'message' => 'Invalid parameters']);
    }
    
    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT 1 FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }
    
    // Clear existing participants
    $pdo->prepare("DELETE FROM tbl_train_sked_participants WHERE sked_id = ?")->execute([$sked_id]);
    
    // Add new participants
    $stmt = $pdo->prepare("
      INSERT INTO tbl_train_sked_participants (sked_id, person_id, participant_type)
      VALUES (?, ?, ?)
    ");
    
    foreach ($participants as $participant) {
      $person_id = (int)($participant['person_id'] ?? 0);
      $type = $participant['type'] ?? 'athlete';
      
      if ($person_id > 0 && in_array($type, ['athlete', 'trainee'])) {
        $stmt->execute([$sked_id, $person_id, $type]);
      }
    }
    
    out(['ok' => true, 'message' => 'Participants added successfully']);
  } catch (PDOException $e) {
    error_log("ADD_PARTICIPANTS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET AVAILABLE TEAM MEMBERS (for a specific session)
// ==========================================

// ==========================================
// GET AVAILABLE TEAM MEMBERS (for a specific session)
// ==========================================

// ==========================================
// GET AVAILABLE TEAM MEMBERS (for a specific session)
// ==========================================

if ($action === 'session_available_members') {
  try {
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($team_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid team ID']);
    }
    
    // Get the sports_id for this team
    $team_query = $pdo->prepare("
      SELECT sports_id 
      FROM tbl_sports_team 
      WHERE team_id = ? AND coach_id = ?
      LIMIT 1
    ");
    $team_query->execute([$team_id, $coach_person_id]);
    $team_data = $team_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$team_data) {
      out(['ok' => false, 'message' => 'Team not found or access denied']);
    }
    
    $team_sports_id = $team_data['sports_id'];
    
    error_log("AVAILABLE_MEMBERS: team_id={$team_id}, sports_id={$team_sports_id}");
    
    // Get athletes for this team
    $athletes_stmt = $pdo->prepare("
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.l_name, '')) as full_name,
        'athlete' as member_type,
        IFNULL(ta.is_captain, 0) as is_captain
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      WHERE ta.team_id = ? 
        AND ta.sports_id = ? 
        AND ta.is_active = 1
      ORDER BY p.l_name, p.f_name
    ");
    $athletes_stmt->execute([$team_id, $team_sports_id]);
    $athletes = $athletes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("AVAILABLE_MEMBERS: Found " . count($athletes) . " athletes");
    
    // Get trainees for this team - NOW USING person_id instead of trainee_id
    $trainees_stmt = $pdo->prepare("
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.l_name, '')) as full_name,
        'trainee' as member_type,
        0 as is_captain
      FROM tbl_team_trainees tt
      JOIN tbl_person p ON p.person_id = tt.person_id
      WHERE tt.team_id = ? 
        AND tt.is_active = 1
      ORDER BY p.l_name, p.f_name
    ");
    $trainees_stmt->execute([$team_id]);
    $trainees = $trainees_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("AVAILABLE_MEMBERS: Found " . count($trainees) . " trainees");
    
    out([
      'ok' => true,
      'athletes' => $athletes,
      'trainees' => $trainees
    ]);
  } catch (PDOException $e) {
    error_log("AVAILABLE_MEMBERS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Update the training_create endpoint to return sked_id
if ($action === 'training_create') {
  // ... existing validation code ...
  
  // After the INSERT, add this before out():
  $sked_id = $pdo->lastInsertId();
  out(['ok'=>true,'message'=>'Training schedule saved', 'sked_id' => $sked_id]);
}

// Update session_attendance to use participants table
if ($action === 'session_attendance_v2') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);

    if ($sked_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }

    error_log("SESSION_ATTENDANCE_V2: sked_id={$sked_id}, coach_id={$coach_person_id}, sports_id={$sports_id}");

    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.sked_id, ts.team_id, t.team_name
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    $session = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      error_log("SESSION_ATTENDANCE_V2 ERROR: Session not found or access denied");
      out(['ok' => false, 'message' => 'Session not found or access denied']);
    }

    error_log("SESSION_ATTENDANCE_V2: Found session for team_id={$session['team_id']}");

    // Get assigned participants for this session
    $participants_stmt = $pdo->prepare("
      SELECT 
        sp.person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.m_name, ''), ' ', p.l_name) as full_name,
        sp.participant_type as member_type,
        IFNULL(ta.is_present, 0) as is_present
      FROM tbl_train_sked_participants sp
      JOIN tbl_person p ON p.person_id = sp.person_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = sp.sked_id AND ta.person_id = sp.person_id
      WHERE sp.sked_id = ?
      ORDER BY p.l_name, p.f_name
    ");
    $participants_stmt->execute([$sked_id]);
    $participants = $participants_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("SESSION_ATTENDANCE_V2: Found " . count($participants) . " participants");
    
    if (count($participants) === 0) {
      // Check if there are team members who could be assigned
      $team_members_check = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM (
          SELECT person_id FROM tbl_team_athletes 
          WHERE team_id = ? AND sports_id = ? AND is_active = 1
          UNION
          SELECT person_id FROM tbl_team_trainees 
          WHERE team_id = ? AND is_active = 1
        ) as members
      ");
      $team_members_check->execute([$session['team_id'], $sports_id, $session['team_id']]);
      $members_count = $team_members_check->fetch(PDO::FETCH_ASSOC);
      
      error_log("SESSION_ATTENDANCE_V2: Team has {$members_count['cnt']} total members");
      
      if ($members_count['cnt'] > 0) {
        out([
          'ok' => false, 
          'message' => "No participants assigned to this session yet. This session has no participants assigned, but the team \"{$session['team_name']}\" has {$members_count['cnt']} members. Click the 'Migrate All Sessions' button above to auto-assign team members to this session.",
          'can_migrate' => true,
          'sked_id' => $sked_id
        ]);
      } else {
        out([
          'ok' => false, 
          'message' => "The team \"{$session['team_name']}\" has no active members (athletes or trainees). Please add team members before tracking attendance.",
          'can_migrate' => false
        ]);
      }
    }
    
    out($participants);
  } catch (PDOException $e) {
    error_log("SESSION_ATTENDANCE_V2 ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}
// ==========================================
// REPORT: SESSIONS
// ==========================================

if ($action === 'report_sessions') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    $sql = "
      SELECT 
        t.team_name,
        COUNT(ts.sked_id) as total_sessions,
        COUNT(DISTINCT DATE_FORMAT(ts.sked_date, '%Y-%m')) as months_active,
        MIN(ts.sked_date) as first_session,
        MAX(ts.sked_date) as last_session
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
    ";
    
    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];
    
    if ($date_from) {
      $sql .= " AND ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }
    if ($date_to) {
      $sql .= " AND ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }
    if ($team_id > 0) {
      $sql .= " AND ts.team_id = :team_id";
      $params['team_id'] = $team_id;
    }
    
    $sql .= " GROUP BY t.team_name";
    $sql .= " ORDER BY total_sessions DESC, t.team_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORT: PLAYER ATTENDANCE DETAIL
// ==========================================

if ($action === 'report_player_attendance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    error_log("REPORT_PLAYER_ATTENDANCE: date_from={$date_from}, date_to={$date_to}, team_id={$team_id}");
    
    // Build WHERE conditions
    $conditions = [];
    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];
    
    if ($date_from) {
      $conditions[] = "ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }
    if ($date_to) {
      $conditions[] = "ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }
    if ($team_id > 0) {
      $conditions[] = "sp.team_id = :team_id";
      $params['team_id'] = $team_id;
    }
    
    $where_clause = !empty($conditions) ? 'AND ' . implode(' AND ', $conditions) : '';
    
    $sql = "
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', IFNULL(p.l_name, '')) as player_name,
        t.team_name,
        sp.participant_type as member_type,
        COUNT(DISTINCT ts.sked_id) as total_sessions,
        COUNT(DISTINCT CASE WHEN ta.is_present = 1 THEN ts.sked_id END) as sessions_attended,
        COUNT(DISTINCT CASE WHEN ta.is_present = 0 OR ta.is_present IS NULL THEN ts.sked_id END) as sessions_missed,
        ROUND(
          (COUNT(DISTINCT CASE WHEN ta.is_present = 1 THEN ts.sked_id END) / 
           NULLIF(COUNT(DISTINCT ts.sked_id), 0)) * 100, 
          1
        ) as attendance_rate
      FROM tbl_train_sked_participants sp
      JOIN tbl_person p ON p.person_id = sp.person_id
      JOIN tbl_train_sked ts ON ts.sked_id = sp.sked_id
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id AND ta.person_id = sp.person_id
      WHERE st.coach_id = :coach_id 
        AND st.sports_id = :sports_id
        $where_clause
      GROUP BY p.person_id, player_name, t.team_name, sp.participant_type
      ORDER BY attendance_rate DESC, player_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("REPORT_PLAYER_ATTENDANCE: Found " . count($results) . " players");
    
    out($results);
  } catch (PDOException $e) {
    error_log("REPORT_PLAYER_ATTENDANCE ERROR: " . $e->getMessage());
    error_log("REPORT_PLAYER_ATTENDANCE SQL: " . ($sql ?? 'N/A'));
    error_log("REPORT_PLAYER_ATTENDANCE PARAMS: " . json_encode($params ?? []));
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET PLAYER
// ==========================================

if ($action === 'get_player') {
  try {
    $person_id = (int)($_GET['person_id'] ?? 0);
    
    error_log("GET_PLAYER: Starting - person_id={$person_id}, coach_id={$coach_person_id}, sports_id={$sports_id}");
    
    if ($person_id <= 0) {
      error_log("GET_PLAYER ERROR: Invalid person_id");
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid person ID']);
    }
    
    // Verify person exists
    $check_person = $pdo->prepare("SELECT person_id, role_type FROM tbl_person WHERE person_id = :pid LIMIT 1");
    $check_person->execute(['pid' => $person_id]);
    $person_exists = $check_person->fetch(PDO::FETCH_ASSOC);
    
    if (!$person_exists) {
      error_log("GET_PLAYER ERROR: Person {$person_id} not found in tbl_person");
      http_response_code(404);
      out(['ok'=>false,'message'=>'Person not found']);
    }
    
    error_log("GET_PLAYER: Person exists with role=" . $person_exists['role_type']);
    
    // Verify player belongs to coach's team
    $verify = $pdo->prepare("
      SELECT 
        ta.team_ath_id, 
        ta.team_id, 
        ta.tour_id, 
        ta.person_id,
        ta.sports_id
      FROM tbl_team_athletes ta
      INNER JOIN tbl_sports_team st 
        ON st.team_id = ta.team_id 
        AND st.tour_id = ta.tour_id 
        AND st.sports_id = ta.sports_id
      WHERE ta.person_id = :person_id 
        AND ta.sports_id = :sports_id
        AND ta.is_active = 1
        AND st.coach_id = :coach_id
      LIMIT 1
    ");
    
    $verify->execute([
      'person_id' => $person_id,
      'coach_id' => $coach_person_id,
      'sports_id' => $sports_id
    ]);
    
    $verification = $verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
      error_log("GET_PLAYER ERROR: Player {$person_id} not in coach {$coach_person_id}'s team for sport {$sports_id}");
      http_response_code(403);
      out(['ok'=>false,'message'=>'Player not in your team']);
    }
    
    error_log("GET_PLAYER: Verification passed");
    
    // Get player data
    $stmt = $pdo->prepare("
      SELECT 
        p.person_id,
        p.f_name,
        p.l_name,
        IFNULL(p.m_name, '') as m_name,
        p.date_birth,
        IFNULL(p.blood_type, '') as blood_type,
        IFNULL(p.course, '') as course
      FROM tbl_person p
      WHERE p.person_id = :person_id
      LIMIT 1
    ");
    
    $stmt->execute(['person_id' => $person_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
      error_log("GET_PLAYER ERROR: Failed to fetch person data");
      http_response_code(500);
      out(['ok'=>false,'message'=>'Failed to retrieve player data']);
    }
    
    // Get latest vital signs
    $vital_stmt = $pdo->prepare("
      SELECT 
        IFNULL(height, '') as height,
        IFNULL(weight, '') as weight,
        IFNULL(b_pressure, '') as b_pressure,
        IFNULL(b_sugar, '') as b_sugar,
        IFNULL(b_choles, '') as b_choles
      FROM tbl_vital_signs
      WHERE person_id = :person_id
      ORDER BY date_taken DESC
      LIMIT 1
    ");
    
    $vital_stmt->execute(['person_id' => $person_id]);
    $vitals = $vital_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vitals) {
      $player['height'] = $vitals['height'];
      $player['weight'] = $vitals['weight'];
      $player['b_pressure'] = $vitals['b_pressure'];
      $player['b_sugar'] = $vitals['b_sugar'];
      $player['b_choles'] = $vitals['b_choles'];
    } else {
      $player['height'] = '';
      $player['weight'] = '';
      $player['b_pressure'] = '';
      $player['b_sugar'] = '';
      $player['b_choles'] = '';
    }
    
    error_log("GET_PLAYER SUCCESS: Returning player data");
    out($player);
    
  } catch (PDOException $e) {
    error_log("GET_PLAYER PDO ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  } catch (Exception $e) {
    error_log("GET_PLAYER EXCEPTION: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
  }
}

// ==========================================
// UPDATE PLAYER
// ==========================================

if ($action === 'update_player') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $person_id = (int)($_POST['person_id'] ?? 0);
    
    if ($person_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid person ID']);
    }
    
    // Verify player belongs to coach's team
    $verify = $pdo->prepare("
      SELECT 1 FROM tbl_team_athletes ta
      WHERE ta.person_id = :person_id 
      AND ta.sports_id = :sports_id
      AND ta.is_active = 1
      AND EXISTS (
        SELECT 1 FROM tbl_sports_team st 
        WHERE st.team_id = ta.team_id 
        AND st.tour_id = ta.tour_id 
        AND st.sports_id = ta.sports_id
        AND st.coach_id = :coach_id
      )
      LIMIT 1
    ");
    $verify->execute([
      'person_id'=>$person_id,
      'coach_id'=>$coach_person_id,
      'sports_id'=>$sports_id
    ]);
    
    if (!$verify->fetch()) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Player not in your team']);
    }
    
    $pdo->beginTransaction();
    
    // Update tbl_person
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET f_name = :f_name,
          l_name = :l_name,
          m_name = :m_name,
          date_birth = :date_birth,
          blood_type = :blood_type,
          course = :course
      WHERE person_id = :person_id
    ");
    
    $stmt->execute([
      'person_id' => $person_id,
      'f_name' => trim($_POST['f_name']),
      'l_name' => trim($_POST['l_name']),
      'm_name' => trim($_POST['m_name'] ?? ''),
      'date_birth' => $_POST['date_birth'],
      'blood_type' => !empty($_POST['blood_type']) ? $_POST['blood_type'] : null,
      'course' => trim($_POST['course'] ?? '')
    ]);
    
    // Check if vital signs record exists
    $check_vital = $pdo->prepare("SELECT vital_id FROM tbl_vital_signs WHERE person_id = :person_id ORDER BY date_taken DESC LIMIT 1");
    $check_vital->execute(['person_id'=>$person_id]);
    $vital_exists = $check_vital->fetch();
    
    // Only update/insert vital signs if at least one field has a value
    $has_vital_data = !empty($_POST['height']) || !empty($_POST['weight']) || 
                      !empty($_POST['b_pressure']) || !empty($_POST['b_sugar']) || !empty($_POST['b_choles']);
    
    if ($has_vital_data) {
      if ($vital_exists) {
        $stmt = $pdo->prepare("
          UPDATE tbl_vital_signs 
          SET height = :height,
              weight = :weight,
              b_pressure = :b_pressure,
              b_sugar = :b_sugar,
              b_choles = :b_choles,
              date_taken = CURDATE()
          WHERE vital_id = :vital_id
        ");
        $stmt->execute([
          'vital_id' => $vital_exists['vital_id'],
          'height' => !empty($_POST['height']) ? $_POST['height'] : null,
          'weight' => !empty($_POST['weight']) ? $_POST['weight'] : null,
          'b_pressure' => !empty($_POST['b_pressure']) ? trim($_POST['b_pressure']) : null,
          'b_sugar' => !empty($_POST['b_sugar']) ? trim($_POST['b_sugar']) : null,
          'b_choles' => !empty($_POST['b_choles']) ? trim($_POST['b_choles']) : null
        ]);
      } else {
        $stmt = $pdo->prepare("
          INSERT INTO tbl_vital_signs 
          (person_id, height, weight, b_pressure, b_sugar, b_choles, date_taken)
          VALUES 
          (:person_id, :height, :weight, :b_pressure, :b_sugar, :b_choles, CURDATE())
        ");
        $stmt->execute([
          'person_id' => $person_id,
          'height' => !empty($_POST['height']) ? $_POST['height'] : null,
          'weight' => !empty($_POST['weight']) ? $_POST['weight'] : null,
          'b_pressure' => !empty($_POST['b_pressure']) ? trim($_POST['b_pressure']) : null,
          'b_sugar' => !empty($_POST['b_sugar']) ? trim($_POST['b_sugar']) : null,
          'b_choles' => !empty($_POST['b_choles']) ? trim($_POST['b_choles']) : null
        ]);
      }
    }
    
    $pdo->commit();
    
    out(['ok'=>true,'message'=>'Player information updated successfully']);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SESSION ATTENDANCE
// ==========================================

if ($action === 'session_attendance') {
  try {
    $sked_id = (int)($_GET['sked_id'] ?? 0);

    if ($sked_id <= 0) {
      error_log("SESSION_ATTENDANCE ERROR: Invalid sked_id={$sked_id}");
      out(['ok' => false, 'message' => 'Invalid session ID']);
    }

    error_log("SESSION_ATTENDANCE: sked_id={$sked_id}, coach_id={$coach_person_id}, sports_id={$sports_id}");

    // First, get the session details
    $session_query = $pdo->prepare("
      SELECT ts.sked_id, ts.team_id, ts.sked_date, ts.sked_time
      FROM tbl_train_sked ts
      WHERE ts.sked_id = ?
    ");
    $session_query->execute([$sked_id]);
    $session = $session_query->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
      error_log("SESSION_ATTENDANCE ERROR: Session {$sked_id} not found in tbl_train_sked");
      out(['ok' => false, 'message' => 'Session not found']);
    }
    
    $team_id = $session['team_id'];
    error_log("SESSION_ATTENDANCE: Found session - team_id={$team_id}");

    // Verify this coach owns this team
    $coach_check = $pdo->prepare("
      SELECT st.team_id, st.coach_id, st.sports_id
      FROM tbl_sports_team st
      WHERE st.team_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $coach_check->execute([$team_id, $coach_person_id, $sports_id]);
    $coach_team = $coach_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$coach_team) {
      error_log("SESSION_ATTENDANCE ERROR: Coach {$coach_person_id} does not own team {$team_id} for sport {$sports_id}");
      out(['ok' => false, 'message' => 'You can only view attendance for your own team sessions']);
    }

    error_log("SESSION_ATTENDANCE: Coach verified for team {$team_id}");

    // Get ALL people associated with this team - BOTH athletes AND trainees
    // UNION to combine tbl_team_athletes and tbl_team_trainees
    $players_query = $pdo->prepare("
      SELECT DISTINCT
        p.person_id,
        p.f_name,
        p.m_name,
        p.l_name,
        'athlete' as member_type
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      WHERE ta.team_id = ?
      AND ta.sports_id = ?
      AND ta.is_active = 1
      
      UNION
      
      SELECT DISTINCT
        p.person_id,
        p.f_name,
        p.m_name,
        p.l_name,
        'trainee' as member_type
      FROM tbl_team_trainees tt
      JOIN tbl_person p ON p.person_id = tt.trainee_id
      WHERE tt.team_id = ?
      AND tt.is_active = 1
      
      ORDER BY l_name, f_name
    ");
    $players_query->execute([$team_id, $sports_id, $team_id]);
    $players = $players_query->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("SESSION_ATTENDANCE: Found " . count($players) . " members (athletes + trainees) in team {$team_id}");

    if (count($players) === 0) {
      // Debug: Check both tables
      $debug_athletes = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_team_athletes WHERE team_id = ?");
      $debug_athletes->execute([$team_id]);
      $athletes_count = $debug_athletes->fetch(PDO::FETCH_ASSOC);
      
      $debug_trainees = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_team_trainees WHERE team_id = ?");
      $debug_trainees->execute([$team_id]);
      $trainees_count = $debug_trainees->fetch(PDO::FETCH_ASSOC);
      
      error_log("SESSION_ATTENDANCE DEBUG: Athletes in team: " . $athletes_count['cnt'] . ", Trainees: " . $trainees_count['cnt']);
      
      out(['ok' => false, 'message' => 'No active members found in this team. Please add athletes or trainees to this team first.']);
    }

    // Now get attendance records for this session
    $attendance_query = $pdo->prepare("
      SELECT person_id, is_present
      FROM tbl_train_attend
      WHERE sked_id = ?
    ");
    $attendance_query->execute([$sked_id]);
    $attendance_records = $attendance_query->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of attendance
    $attendance_map = [];
    foreach ($attendance_records as $record) {
      $attendance_map[$record['person_id']] = (int)$record['is_present'];
    }
    
    error_log("SESSION_ATTENDANCE: Found " . count($attendance_records) . " attendance records");

    // Combine players with attendance status
    $result = [];
    foreach ($players as $player) {
      $person_id = $player['person_id'];
      $middle = $player['m_name'] ? ' ' . $player['m_name'] . ' ' : ' ';
      
      $result[] = [
        'person_id' => $person_id,
        'player_name' => $player['f_name'] . $middle . $player['l_name'],
        'member_type' => $player['member_type'],
        'is_present' => isset($attendance_map[$person_id]) ? $attendance_map[$person_id] : 0
      ];
    }
    
    error_log("SESSION_ATTENDANCE SUCCESS: Returning " . count($result) . " records");
    out($result);
    
  } catch (PDOException $e) {
    error_log("SESSION_ATTENDANCE PDO ERROR: " . $e->getMessage());
    error_log("SESSION_ATTENDANCE TRACE: " . $e->getTraceAsString());
    out(['ok' => false, 'error' => $e->getMessage(), 'message' => 'Database error: ' . $e->getMessage()]);
  } catch (Exception $e) {
    error_log("SESSION_ATTENDANCE EXCEPTION: " . $e->getMessage());
    out(['ok' => false, 'error' => $e->getMessage(), 'message' => 'Error: ' . $e->getMessage()]);
  }
}

// ==========================================
// MARK ATTENDANCE
// ==========================================

if ($action === 'mark_attendance') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $sked_id = (int)($_POST['sked_id'] ?? 0);
    $person_id_player = (int)($_POST['person_id'] ?? 0);
    $is_present = (int)($_POST['is_present'] ?? 0);

    if ($sked_id <= 0 || $person_id_player <= 0) {
      out(['ok' => false, 'message' => 'Invalid parameters']);
    }

    // Verify coach owns this session
    $verify = $pdo->prepare("
      SELECT ts.sked_id 
      FROM tbl_train_sked ts
      JOIN tbl_sports_team st ON st.team_id = ts.team_id
      WHERE ts.sked_id = ? AND st.coach_id = ? AND st.sports_id = ?
    ");
    $verify->execute([$sked_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      out(['ok' => false, 'message' => 'You can only mark attendance for your own sessions']);
    }

    // Check if attendance record exists
    $check = $pdo->prepare("
      SELECT * FROM tbl_train_attend 
      WHERE sked_id = ? AND person_id = ?
    ");
    $check->execute([$sked_id, $person_id_player]);
    
    if ($check->fetch()) {
      // Update existing
      $stmt = $pdo->prepare("
        UPDATE tbl_train_attend
        SET is_present = ?
        WHERE sked_id = ? AND person_id = ?
      ");
      $stmt->execute([$is_present, $sked_id, $person_id_player]);
    } else {
      // Insert new
      $stmt = $pdo->prepare("
        INSERT INTO tbl_train_attend (sked_id, person_id, is_present)
        VALUES (?, ?, ?)
      ");
      $stmt->execute([$sked_id, $person_id_player, $is_present]);
    }

    out(['ok' => true, 'message' => 'Attendance saved successfully']);
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
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

// ==========================================
// PERFORMANCE LIST
// ==========================================

if ($action === 'performance_list') {
  try {
    error_log("PERFORMANCE_LIST: coach_id={$coach_person_id}, sports_id={$sports_id}");
    
    $stmt = $pdo->prepare("
      SELECT 
        tp.perf_id,
        tp.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as player_name,
        ta.activity_name,
        t.team_name,
        tp.rating,
        tp.date_eval,
        tp.activitity_id as activity_id,
        tp.team_id
      FROM tbl_train_perf tp
      JOIN tbl_person p ON p.person_id = tp.person_id
      JOIN tbl_training_activity ta ON ta.activity_id = tp.activitity_id
      JOIN tbl_team t ON t.team_id = tp.team_id
      WHERE tp.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = ? AND st.sports_id = ?
      )
      ORDER BY tp.date_eval DESC, p.l_name, p.f_name
    ");
    $stmt->execute([$coach_person_id, $sports_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("PERFORMANCE_LIST SUCCESS: Found " . count($results) . " records");
    out($results);
  } catch (PDOException $e) {
    error_log("PERFORMANCE_LIST ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage(), 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SAVE PERFORMANCE
// ==========================================

if ($action === 'save_performance') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $perf_id = (int)($_POST['perf_id'] ?? 0);
    $person_id_param = (int)($_POST['person_id'] ?? 0);
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);
    $rating = floatval($_POST['rating'] ?? 0);
    $date_eval = $_POST['date_eval'] ?? '';

    error_log("SAVE_PERFORMANCE: perf_id={$perf_id}, person_id={$person_id_param}, activity_id={$activity_id}, team_id={$team_id}, rating={$rating}, date={$date_eval}");

    // Validation
    if (!$person_id_param || !$activity_id || !$team_id || !$rating || !$date_eval) {
      error_log("SAVE_PERFORMANCE ERROR: Missing required fields");
      out(['ok' => false, 'message' => 'All fields are required']);
    }

    if ($rating < 1 || $rating > 10) {
      error_log("SAVE_PERFORMANCE ERROR: Invalid rating={$rating}");
      out(['ok' => false, 'message' => 'Rating must be between 1 and 10']);
    }

    // Verify team belongs to coach
    $verify = $pdo->prepare("
      SELECT team_id 
      FROM tbl_sports_team 
      WHERE team_id = ? AND coach_id = ? AND sports_id = ?
    ");
    $verify->execute([$team_id, $coach_person_id, $sports_id]);
    
    if (!$verify->fetch()) {
      error_log("SAVE_PERFORMANCE ERROR: Coach {$coach_person_id} not authorized for team {$team_id}");
      out(['ok' => false, 'message' => 'You are not authorized for this team']);
    }

    if ($perf_id > 0) {
      // Update existing - using activitity_id (typo in DB)
      error_log("SAVE_PERFORMANCE: Updating existing perf_id={$perf_id}");
      $stmt = $pdo->prepare("
        UPDATE tbl_train_perf
        SET person_id = ?,
            activitity_id = ?,
            rating = ?,
            date_eval = ?,
            team_id = ?
        WHERE perf_id = ?
      ");
      $stmt->execute([
        $person_id_param,
        $activity_id,
        $rating,
        $date_eval,
        $team_id,
        $perf_id
      ]);
      error_log("SAVE_PERFORMANCE SUCCESS: Updated perf_id={$perf_id}");
      out(['ok' => true, 'message' => 'Performance rating updated successfully']);
    } else {
      // Insert new - using activitity_id (typo in DB)
      error_log("SAVE_PERFORMANCE: Inserting new record");
      $stmt = $pdo->prepare("
        INSERT INTO tbl_train_perf 
        (person_id, activitity_id, rating, date_eval, team_id)
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->execute([
        $person_id_param,
        $activity_id,
        $rating,
        $date_eval,
        $team_id
      ]);
      $new_id = $pdo->lastInsertId();
      error_log("SAVE_PERFORMANCE SUCCESS: Inserted new perf_id={$new_id}");
      out(['ok' => true, 'message' => 'Performance rating saved successfully']);
    }
  } catch (PDOException $e) {
    error_log("SAVE_PERFORMANCE PDO ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage(), 'error' => $e->getMessage()]);
  }
}

// ==========================================
// GET PERFORMANCE
// ==========================================

if ($action === 'get_performance') {
  try {
    $perf_id = (int)($_GET['perf_id'] ?? 0);
    
    if ($perf_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid performance ID']);
    }
    
    $stmt = $pdo->prepare("
      SELECT 
        tp.perf_id,
        tp.person_id,
        tp.activitity_id as activity_id,
        tp.team_id,
        tp.rating,
        tp.date_eval
      FROM tbl_train_perf tp
      WHERE tp.perf_id = ?
      AND tp.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = ? AND st.sports_id = ?
      )
      LIMIT 1
    ");
    $stmt->execute([$perf_id, $coach_person_id, $sports_id]);
    $perf = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($perf) {
      out($perf);
    } else {
      out(['ok' => false, 'message' => 'Performance record not found']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// DELETE PERFORMANCE
// ==========================================

if ($action === 'delete_performance') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $perf_id = (int)($_POST['perf_id'] ?? 0);

    if ($perf_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid performance ID']);
    }

    // Delete only if coach owns the team
    $stmt = $pdo->prepare("
      DELETE tp FROM tbl_train_perf tp
      WHERE tp.perf_id = ?
      AND tp.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = ? AND st.sports_id = ?
      )
    ");
    $stmt->execute([$perf_id, $coach_person_id, $sports_id]);

    if ($stmt->rowCount() > 0) {
      out(['ok' => true, 'message' => 'Performance rating deleted successfully']);
    } else {
      out(['ok' => false, 'message' => 'Performance record not found or you do not have permission']);
    }
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok'=>false,'message'=>'Unknown action']);