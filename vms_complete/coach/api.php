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
             st.coach_id, st.asst_coach_id,
             CONCAT(cp.f_name,' ',cp.l_name) AS coach_name,
             CONCAT(ap.f_name,' ',ap.l_name) AS asst_coach_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      LEFT JOIN tbl_person cp ON cp.person_id = st.coach_id
      LEFT JOIN tbl_person ap ON ap.person_id = st.asst_coach_id
      WHERE st.sports_id = :sports_id AND st.coach_id = :coach_id
    ");
    $stmt->execute(['sports_id'=>$sports_id, 'coach_id'=>$coach_person_id]);
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

if ($action === 'matches') {
  try {
    $stmt = $pdo->prepare("
      SELECT m.sked_date, m.sked_time, m.game_no, m.match_type,
             v.venue_name,
             ta.team_name AS team_a,
             tb.team_name AS team_b
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
// TRAINING LIST
// ==========================================

if ($action === 'training_list') {
  try {
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
    $stmt->execute(['coach_id'=>$coach_person_id, 'sports_id'=>$sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
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
      SELECT st.team_id, t.team_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      GROUP BY st.team_id, t.team_name
      ORDER BY t.team_name ASC
    ");
    $stmt->execute(['coach_id'=>$coach_person_id, 'sports_id'=>$sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING CREATE
// ==========================================

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

    out(['ok'=>true,'message'=>'Training schedule saved']);
  } catch (PDOException $e) {
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