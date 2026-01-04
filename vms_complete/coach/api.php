<?php
// coach/api.php

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// This will check if user is coach and has valid session
require_role('coach');

header("Content-Type: application/json; charset=utf-8");

// Get session data (guard.php already validated these exist)
$coach_person_id = (int)$_SESSION['user']['person_id'];
$sports_id       = (int)$_SESSION['user']['sports_id'];

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

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

    out(['ok'=>true,'message'=>'Training schedule saved']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'get_player') {
  try {
    $person_id = (int)($_GET['person_id'] ?? 0);
    
    error_log("GET_PLAYER: Starting - person_id={$person_id}, coach_id={$coach_person_id}, sports_id={$sports_id}");
    
    if ($person_id <= 0) {
      error_log("GET_PLAYER ERROR: Invalid person_id");
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid person ID']);
    }
    
    // Step 1: Verify person exists
    $check_person = $pdo->prepare("SELECT person_id, role_type FROM tbl_person WHERE person_id = :pid LIMIT 1");
    $check_person->execute(['pid' => $person_id]);
    $person_exists = $check_person->fetch(PDO::FETCH_ASSOC);
    
    if (!$person_exists) {
      error_log("GET_PLAYER ERROR: Person {$person_id} not found in tbl_person");
      http_response_code(404);
      out(['ok'=>false,'message'=>'Person not found']);
    }
    
    error_log("GET_PLAYER: Person exists with role=" . $person_exists['role_type']);
    
    // Step 2: Verify this person is in coach's team
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
      
      // Additional debug: Check what teams this player IS in
      $debug = $pdo->prepare("SELECT team_id, tour_id, sports_id FROM tbl_team_athletes WHERE person_id = :pid");
      $debug->execute(['pid' => $person_id]);
      $player_teams = $debug->fetchAll(PDO::FETCH_ASSOC);
      error_log("GET_PLAYER DEBUG: Player is in teams: " . json_encode($player_teams));
      
      // Check what teams this coach has
      $debug2 = $pdo->prepare("SELECT team_id, tour_id, sports_id FROM tbl_sports_team WHERE coach_id = :cid");
      $debug2->execute(['cid' => $coach_person_id]);
      $coach_teams = $debug2->fetchAll(PDO::FETCH_ASSOC);
      error_log("GET_PLAYER DEBUG: Coach has teams: " . json_encode($coach_teams));
      
      http_response_code(403);
      out(['ok'=>false,'message'=>'Player not in your team']);
    }
    
    error_log("GET_PLAYER: Verification passed - team_ath_id=" . $verification['team_ath_id']);
    
    // Step 3: Get player data
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
      error_log("GET_PLAYER ERROR: Failed to fetch person data for person_id={$person_id}");
      http_response_code(500);
      out(['ok'=>false,'message'=>'Failed to retrieve player data']);
    }
    
    error_log("GET_PLAYER: Person data retrieved");
    
    // Step 4: Get latest vital signs (separate query to avoid issues)
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
    
    // Merge vital signs with player data (use empty strings if no vitals exist)
    if ($vitals) {
      $player['height'] = $vitals['height'];
      $player['weight'] = $vitals['weight'];
      $player['b_pressure'] = $vitals['b_pressure'];
      $player['b_sugar'] = $vitals['b_sugar'];
      $player['b_choles'] = $vitals['b_choles'];
      error_log("GET_PLAYER: Vital signs found");
    } else {
      $player['height'] = '';
      $player['weight'] = '';
      $player['b_pressure'] = '';
      $player['b_sugar'] = '';
      $player['b_choles'] = '';
      error_log("GET_PLAYER: No vital signs found");
    }
    
    error_log("GET_PLAYER SUCCESS: Returning player data");
    out($player);
    
  } catch (PDOException $e) {
    error_log("GET_PLAYER PDO ERROR: " . $e->getMessage());
    error_log("GET_PLAYER SQL STATE: " . $e->errorInfo[0]);
    error_log("GET_PLAYER ERROR CODE: " . $e->errorInfo[1]);
    error_log("GET_PLAYER ERROR MSG: " . $e->errorInfo[2]);
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  } catch (Exception $e) {
    error_log("GET_PLAYER EXCEPTION: " . $e->getMessage());
    error_log("GET_PLAYER TRACE: " . $e->getTraceAsString());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
  }
}

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
    
    // Verify player belongs to coach's team (check if person is in ANY team coached by this coach for this sport)
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
        // Update existing vital signs record
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
        // Insert new vital signs record
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

http_response_code(404);
out(['ok'=>false,'message'=>'Unknown action']);