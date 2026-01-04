<?php
// tournament_manager/api.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

require_role('Tournament manager');


header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// MODULE 1: TOURNAMENT CREATION
// ==========================================

if ($action === 'tournaments') {
  try {
    $stmt = $pdo->query("
      SELECT tour_id, tour_name, school_year, tour_date, is_active
      FROM tbl_tournament
      ORDER BY tour_date DESC, tour_id DESC
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("tournaments error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_tournament') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_name = trim($_POST['tour_name'] ?? '');
    $school_year = trim($_POST['school_year'] ?? '');
    $tour_date = $_POST['tour_date'] ?? '';

    if (!$tour_name || !$school_year || !$tour_date) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_tournament (tour_name, school_year, tour_date, is_active)
      VALUES (:tour_name, :school_year, :tour_date, 1)
    ");
    $stmt->execute([
      'tour_name' => $tour_name,
      'school_year' => $school_year,
      'tour_date' => $tour_date
    ]);

    out(['ok'=>true,'message'=>'Tournament created successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_tournament') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);

    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_tournament
      SET is_active = :is_active
      WHERE tour_id = :tour_id
    ");
    $stmt->execute(['is_active' => $is_active, 'tour_id' => $tour_id]);

    out(['ok'=>true,'message'=>'Tournament updated']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 1: SPORTS SELECTION
// ==========================================

if ($action === 'all_sports') {
  try {
    $stmt = $pdo->query("
      SELECT sports_id, sports_name, team_individual, weight_class, 
             men_women, num_req_players, num_res_players, is_active
      FROM tbl_sports
      WHERE is_active = 1
      ORDER BY sports_name
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out($result);
  } catch (PDOException $e) {
    error_log("all_sports error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'tournament_sports') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    // Get sports selected for this tournament from the selection table
    $stmt = $pdo->prepare("
      SELECT DISTINCT s.sports_id, s.sports_name, s.team_individual, 
             s.weight_class, s.men_women, s.num_req_players, s.num_res_players, s.is_active
      FROM tbl_tournament_sports_selection tss
      JOIN tbl_sports s ON s.sports_id = tss.sports_id
      WHERE tss.tour_id = :tour_id AND s.is_active = 1
      ORDER BY s.sports_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return the selected sports (empty array if none selected yet)
    out($result);
  } catch (PDOException $e) {
    error_log("tournament_sports error: " . $e->getMessage());
    // If table doesn't exist yet, return empty array
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
      out([]);
    } else {
      http_response_code(500);
      out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
    }
  }
}

if ($action === 'add_tournament_sports') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $pdo->beginTransaction();
    
    // First, ensure we have a tracking table for tournament sports
    // This table will hold sport selections before teams are assigned
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS tbl_tournament_sports_selection (
        tour_id INT NOT NULL,
        sports_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (tour_id, sports_id),
        FOREIGN KEY (tour_id) REFERENCES tbl_tournament(tour_id) ON DELETE CASCADE,
        FOREIGN KEY (sports_id) REFERENCES tbl_sports(sports_id) ON DELETE CASCADE
      ) ENGINE=InnoDB
    ");
    
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sport_ids = $_POST['sport_ids'] ?? '';

    if ($tour_id <= 0 || !$sport_ids) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    // Verify tournament exists
    $chkTour = $pdo->prepare("SELECT 1 FROM tbl_tournament WHERE tour_id = :tour_id");
    $chkTour->execute(['tour_id' => $tour_id]);
    if (!$chkTour->fetch()) {
      $pdo->rollBack();
      http_response_code(404);
      out(['ok'=>false,'message'=>'Tournament not found']);
    }

    $sport_ids_array = array_map('intval', explode(',', $sport_ids));
    $sport_ids_array = array_filter($sport_ids_array, function($id) { return $id > 0; });
    
    // Get currently selected sports for this tournament
    $currentStmt = $pdo->prepare("
      SELECT DISTINCT sports_id 
      FROM tbl_tournament_sports_selection 
      WHERE tour_id = :tour_id
    ");
    $currentStmt->execute(['tour_id' => $tour_id]);
    $currentSports = array_column($currentStmt->fetchAll(PDO::FETCH_ASSOC), 'sports_id');
    
    // Determine which sports to add and which to remove
    $sportsToAdd = array_diff($sport_ids_array, $currentSports);
    $sportsToRemove = array_diff($currentSports, $sport_ids_array);
    
    $added = 0;
    $removed = 0;

    // Remove deselected sports from selection table
    if (!empty($sportsToRemove)) {
      foreach ($sportsToRemove as $sport_id) {
        // Delete from selection table
        $deleteStmt = $pdo->prepare("
          DELETE FROM tbl_tournament_sports_selection 
          WHERE tour_id = :tour_id AND sports_id = :sports_id
        ");
        $deleteStmt->execute([
          'tour_id' => $tour_id,
          'sports_id' => $sport_id
        ]);
        
        // Also delete any teams assigned to this sport in this tournament
        $deleteTeamsStmt = $pdo->prepare("
          DELETE FROM tbl_sports_team 
          WHERE tour_id = :tour_id AND sports_id = :sports_id
        ");
        $deleteTeamsStmt->execute([
          'tour_id' => $tour_id,
          'sports_id' => $sport_id
        ]);
        
        // Delete associated players
        $deletePlayersStmt = $pdo->prepare("
          DELETE FROM tbl_team_athletes 
          WHERE tour_id = :tour_id AND sports_id = :sports_id
        ");
        $deletePlayersStmt->execute([
          'tour_id' => $tour_id,
          'sports_id' => $sport_id
        ]);
        
        $removed++;
      }
    }

    // Add newly selected sports to selection table
    foreach ($sportsToAdd as $sport_id) {
      // Verify sport exists and is active
      $chkSport = $pdo->prepare("
        SELECT 1 FROM tbl_sports 
        WHERE sports_id = :sports_id AND is_active = 1
      ");
      $chkSport->execute(['sports_id' => $sport_id]);
      
      if (!$chkSport->fetch()) continue;

      // Insert into selection table
      $stmt = $pdo->prepare("
        INSERT INTO tbl_tournament_sports_selection (tour_id, sports_id)
        VALUES (:tour_id, :sports_id)
        ON DUPLICATE KEY UPDATE tour_id = tour_id
      ");
      $stmt->execute([
        'tour_id' => $tour_id,
        'sports_id' => $sport_id
      ]);
      $added++;
    }

    $pdo->commit();

    $message = [];
    if ($added > 0) $message[] = "$added sport(s) added";
    if ($removed > 0) $message[] = "$removed sport(s) removed";
    if (empty($message)) $message[] = "No changes made";

    out([
      'ok' => true, 
      'message' => implode(', ', $message),
      'sports_count' => count($sport_ids_array)
    ]);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log("add_tournament_sports error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 1: TEAM SELECTION FOR SPORTS
// ==========================================

if ($action === 'available_teams_for_sport') {
  try {
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    
    if ($sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid sport ID']);
    }

    // Get all teams that can participate in this sport
    // Teams are linked through tbl_sports_team but we want ALL available teams
    $stmt = $pdo->prepare("
      SELECT DISTINCT t.team_id, t.team_name, t.is_active
      FROM tbl_team t
      WHERE t.is_active = 1
      ORDER BY t.team_name
    ");
    $stmt->execute();
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("available_teams_for_sport error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'tournament_sport_teams') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    
    if ($tour_id <= 0 || $sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid parameters']);
    }

    // Get teams already registered for this sport in this tournament
    $stmt = $pdo->prepare("
      SELECT DISTINCT 
        st.team_id,
        t.team_name,
        CONCAT(COALESCE(c.f_name, ''), ' ', COALESCE(c.l_name, '')) AS coach_name,
        CONCAT(COALESCE(ac.f_name, ''), ' ', COALESCE(ac.l_name, '')) AS asst_coach_name,
        (SELECT COUNT(*) FROM tbl_team_athletes ta 
         WHERE ta.team_id = st.team_id 
         AND ta.tour_id = st.tour_id 
         AND ta.sports_id = st.sports_id 
         AND ta.is_active = 1) AS num_players
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      LEFT JOIN tbl_person c ON c.person_id = st.coach_id
      LEFT JOIN tbl_person ac ON ac.person_id = st.asst_coach_id
      WHERE st.tour_id = :tour_id 
      AND st.sports_id = :sports_id
      AND st.team_id IS NOT NULL
      ORDER BY t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("tournament_sport_teams error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'add_teams_to_tournament_sport') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $pdo->beginTransaction();
    
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sports_id = (int)($_POST['sports_id'] ?? 0);
    $team_ids = $_POST['team_ids'] ?? '';

    if ($tour_id <= 0 || $sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $team_ids_array = [];
    if ($team_ids) {
      $team_ids_array = array_map('intval', explode(',', $team_ids));
      $team_ids_array = array_filter($team_ids_array, function($id) { return $id > 0; });
    }

    // Get currently registered teams
    $currentStmt = $pdo->prepare("
      SELECT DISTINCT team_id 
      FROM tbl_sports_team 
      WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id IS NOT NULL
    ");
    $currentStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    $currentTeams = array_column($currentStmt->fetchAll(), 'team_id');
    
    // Determine which teams to add and which to remove
    $teamsToAdd = array_diff($team_ids_array, $currentTeams);
    $teamsToRemove = array_diff($currentTeams, $team_ids_array);
    
    $added = 0;
    $removed = 0;

    // Remove deselected teams
    if (!empty($teamsToRemove)) {
      foreach ($teamsToRemove as $team_id) {
        // Delete from tbl_sports_team
        $deleteStmt = $pdo->prepare("
          DELETE FROM tbl_sports_team 
          WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
        ");
        $deleteStmt->execute([
          'tour_id' => $tour_id,
          'sports_id' => $sports_id,
          'team_id' => $team_id
        ]);
        
        // Delete associated players
        $deletePlayersStmt = $pdo->prepare("
          DELETE FROM tbl_team_athletes 
          WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
        ");
        $deletePlayersStmt->execute([
          'tour_id' => $tour_id,
          'sports_id' => $sports_id,
          'team_id' => $team_id
        ]);
        
        $removed++;
      }
    }

    // Add newly selected teams
    foreach ($teamsToAdd as $team_id) {
      // Verify team exists
      $chkTeam = $pdo->prepare("SELECT 1 FROM tbl_team WHERE team_id = :team_id");
      $chkTeam->execute(['team_id' => $team_id]);
      if (!$chkTeam->fetch()) continue;

      // Check if entry already exists (shouldn't happen, but safeguard)
      $checkExist = $pdo->prepare("
        SELECT 1 FROM tbl_sports_team 
        WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
      ");
      $checkExist->execute([
        'tour_id' => $tour_id,
        'sports_id' => $sports_id,
        'team_id' => $team_id
      ]);
      
      if ($checkExist->fetch()) {
        // Already exists, skip
        continue;
      }

      // Insert team registration
      $stmt = $pdo->prepare("
        INSERT INTO tbl_sports_team 
        (tour_id, team_id, sports_id, coach_id, asst_coach_id, trainor1_id, trainor2_id, trainor3_id)
        VALUES (:tour_id, :team_id, :sports_id, NULL, NULL, NULL, NULL, NULL)
      ");
      $stmt->execute([
        'tour_id' => $tour_id,
        'team_id' => $team_id,
        'sports_id' => $sports_id
      ]);
      $added++;
    }

    $pdo->commit();

    $message = [];
    if ($added > 0) $message[] = "$added team(s) added";
    if ($removed > 0) $message[] = "$removed team(s) removed";
    if (empty($message)) $message[] = "No changes made";

    out(['ok'=>true, 'message'=>implode(', ', $message), 'added' => $added, 'removed' => $removed]);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'team_players') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($team_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid team ID']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        ta.team_ath_id,
        ta.person_id,
        CONCAT(p.f_name, ' ', p.l_name) AS player_name,
        p.college_code,
        p.course,
        ta.is_captain,
        ta.is_active
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      WHERE ta.team_id = :team_id
      " . ($tour_id > 0 ? "AND ta.tour_id = :tour_id" : "") . "
      " . ($sports_id > 0 ? "AND ta.sports_id = :sports_id" : "") . "
      AND ta.is_active = 1
      ORDER BY ta.is_captain DESC, p.l_name, p.f_name
    ");
    
    $params = ['team_id' => $team_id];
    if ($tour_id > 0) $params['tour_id'] = $tour_id;
    if ($sports_id > 0) $params['sports_id'] = $sports_id;
    
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("team_players error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'team_coaches') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($tour_id <= 0 || $sports_id <= 0 || $team_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid parameters']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        st.coach_id,
        st.asst_coach_id,
        st.trainor1_id,
        st.trainor2_id,
        st.trainor3_id,
        CONCAT(COALESCE(c.f_name, ''), ' ', COALESCE(c.l_name, '')) AS coach_name,
        CONCAT(COALESCE(ac.f_name, ''), ' ', COALESCE(ac.l_name, '')) AS asst_coach_name,
        CONCAT(COALESCE(t1.f_name, ''), ' ', COALESCE(t1.l_name, '')) AS trainor1_name,
        CONCAT(COALESCE(t2.f_name, ''), ' ', COALESCE(t2.l_name, '')) AS trainor2_name,
        CONCAT(COALESCE(t3.f_name, ''), ' ', COALESCE(t3.l_name, '')) AS trainor3_name
      FROM tbl_sports_team st
      LEFT JOIN tbl_person c ON c.person_id = st.coach_id
      LEFT JOIN tbl_person ac ON ac.person_id = st.asst_coach_id
      LEFT JOIN tbl_person t1 ON t1.person_id = st.trainor1_id
      LEFT JOIN tbl_person t2 ON t2.person_id = st.trainor2_id
      LEFT JOIN tbl_person t3 ON t3.person_id = st.trainor3_id
      WHERE st.tour_id = :tour_id 
      AND st.sports_id = :sports_id
      AND st.team_id = :team_id
      LIMIT 1
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'sports_id' => $sports_id,
      'team_id' => $team_id
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    out($result ?: ['ok' => false, 'message' => 'No coaching staff found']);
  } catch (PDOException $e) {
    error_log("team_coaches error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 2: MATCHES & SCHEDULE
// ==========================================

if ($action === 'sport_teams') {
  try {
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    
    if ($sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid sport ID']);
    }

    $stmt = $pdo->prepare("
      SELECT DISTINCT t.team_id, t.team_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      WHERE st.sports_id = :sports_id AND st.team_id IS NOT NULL
      ORDER BY t.team_name
    ");
    $stmt->execute(['sports_id' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_match') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sports_id = (int)($_POST['sports_id'] ?? 0);
    $sports_type = trim($_POST['sports_type'] ?? '');
    $match_type = trim($_POST['match_type'] ?? '');
    $team_a_id = (int)($_POST['team_a_id'] ?? 0);
    $team_b_id = (int)($_POST['team_b_id'] ?? 0);
    $sked_date = $_POST['sked_date'] ?? '';
    $sked_time = $_POST['sked_time'] ?? '';
    $venue_id = (int)($_POST['venue_id'] ?? 0);

    if ($tour_id <= 0 || $sports_id <= 0 || !$sports_type || !$match_type || 
        $team_a_id <= 0 || $team_b_id <= 0 || !$sked_date || !$sked_time) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    if ($team_a_id == $team_b_id) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Teams must be different']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_match (tour_id, sports_id, sports_type, match_type, team_a_id, team_b_id, sked_date, sked_time, venue_id)
      VALUES (:tour_id, :sports_id, :sports_type, :match_type, :team_a_id, :team_b_id, :sked_date, :sked_time, :venue_id)
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'sports_id' => $sports_id,
      'sports_type' => $sports_type,
      'match_type' => $match_type,
      'team_a_id' => $team_a_id,
      'team_b_id' => $team_b_id,
      'sked_date' => $sked_date,
      'sked_time' => $sked_time,
      'venue_id' => $venue_id
    ]);

    out(['ok'=>true,'message'=>'Match created successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'matches') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;

    $sql = "
      SELECT 
        m.match_id,
        m.tour_id,
        m.sports_id,
        s.sports_name,
        m.match_type,
        m.sked_date,
        m.sked_time,
        m.team_a_id,
        m.team_b_id,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name,
        v.venue_name,
        m.winner_id,
        tw.team_name AS winner_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      WHERE m.match_type != 'Placeholder'
    ";

    if ($tour_id) {
      $sql .= " AND m.tour_id = :tour_id";
    }

    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC";

    $stmt = $pdo->prepare($sql);
    if ($tour_id) {
      $stmt->execute(['tour_id' => $tour_id]);
    } else {
      $stmt->execute();
    }
    
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 3: SCORING
// ==========================================

if ($action === 'save_score') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $match_id = (int)($_POST['match_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);
    $athlete_id = (int)($_POST['athlete_id'] ?? 0);
    $score_value = trim($_POST['score_value'] ?? '');
    $rank_no = (int)($_POST['rank_no'] ?? 0);
    $medal_type = trim($_POST['medal_type'] ?? '');

    // athlete_id is NOT NULL in schema, so it's required
    if ($match_id <= 0 || $athlete_id <= 0 || !$score_value || $rank_no <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields (athlete_id is required)']);
    }

    // Get tour_id from match if not provided
    if ($tour_id <= 0) {
      $tourStmt = $pdo->prepare("SELECT tour_id FROM tbl_match WHERE match_id = :match_id");
      $tourStmt->execute(['match_id' => $match_id]);
      $tourData = $tourStmt->fetch();
      if ($tourData) {
        $tour_id = $tourData['tour_id'];
      }
    }

    // team_id can be NULL for individual sports
    $team_id = $team_id > 0 ? $team_id : NULL;
    $medal_type = $medal_type ?: 'None';

    $stmt = $pdo->prepare("
      INSERT INTO tbl_comp_score (tour_id, match_id, team_id, athlete_id, score, rank_no, medal_type)
      VALUES (:tour_id, :match_id, :team_id, :athlete_id, :score, :rank_no, :medal_type)
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'match_id' => $match_id,
      'team_id' => $team_id,
      'athlete_id' => $athlete_id,
      'score' => $score_value,
      'rank_no' => $rank_no,
      'medal_type' => $medal_type
    ]);

    out(['ok'=>true,'message'=>'Score saved successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'scores') {
  try {
    $stmt = $pdo->query("
      SELECT 
        cs.competetors_score_id,
        cs.match_id,
        cs.team_id,
        cs.athlete_id,
        cs.score,
        cs.rank_no,
        cs.medal_type,
        t.team_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS athlete_name,
        CONCAT(s.sports_name, ' - ', m.match_type, ' (', m.sked_date, ')') AS match_info
      FROM tbl_comp_score cs
      JOIN tbl_match m ON m.match_id = cs.match_id
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      JOIN tbl_person p ON p.person_id = cs.athlete_id
      LEFT JOIN tbl_team t ON t.team_id = cs.team_id
      ORDER BY cs.competetors_score_id DESC
      LIMIT 50
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_score') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $score_id = (int)($_POST['score_id'] ?? 0);

    if ($score_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid score ID']);
    }

    $stmt = $pdo->prepare("DELETE FROM tbl_comp_score WHERE competetors_score_id = :score_id");
    $stmt->execute(['score_id' => $score_id]);

    out(['ok'=>true,'message'=>'Score deleted']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'declare_winner') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $match_id = (int)($_POST['match_id'] ?? 0);
    $winner_id = (int)($_POST['winner_id'] ?? 0);

    if ($match_id <= 0 || $winner_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_match
      SET winner_id = :winner_id
      WHERE match_id = :match_id
    ");
    $stmt->execute(['winner_id' => $winner_id, 'match_id' => $match_id]);

    out(['ok'=>true,'message'=>'Winner declared successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 3: STANDINGS & MEDALS
// ==========================================

if ($action === 'standings') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        ts.team_id,
        t.team_name,
        ts.sports_id,
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
      WHERE ts.tour_id = :tour_id
      ORDER BY ts.no_win DESC, ts.no_gold DESC, t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'medal_tally') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        t.team_id,
        t.team_name,
        SUM(ts.no_gold) AS gold,
        SUM(ts.no_silver) AS silver,
        SUM(ts.no_bronze) AS bronze
      FROM tbl_team_standing ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.tour_id = :tour_id
      GROUP BY t.team_id, t.team_name
      ORDER BY gold DESC, silver DESC, bronze DESC, t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MODULE 2: VENUES
// ==========================================

if ($action === 'venues') {
  try {
    $stmt = $pdo->query("
      SELECT venue_id, venue_name, venue_building, venue_room, is_active
      FROM tbl_game_venue
      ORDER BY venue_name
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_venue') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $venue_name = trim($_POST['venue_name'] ?? '');
    $venue_building = trim($_POST['venue_building'] ?? '');
    $venue_room = trim($_POST['venue_room'] ?? '');

    if (!$venue_name) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Venue name is required']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_game_venue (venue_name, venue_building, venue_room, is_active)
      VALUES (:venue_name, :venue_building, :venue_room, 1)
    ");
    $stmt->execute([
      'venue_name' => $venue_name,
      'venue_building' => $venue_building ?: NULL,
      'venue_room' => $venue_room ?: NULL
    ]);

    out(['ok'=>true,'message'=>'Venue created successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_venue') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $venue_id = (int)($_POST['venue_id'] ?? 0);
    $venue_name = trim($_POST['venue_name'] ?? '');
    $venue_building = trim($_POST['venue_building'] ?? '');
    $venue_room = trim($_POST['venue_room'] ?? '');

    if ($venue_id <= 0 || !$venue_name) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid data']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_game_venue
      SET venue_name = :venue_name, venue_building = :venue_building, venue_room = :venue_room
      WHERE venue_id = :venue_id
    ");
    $stmt->execute([
      'venue_id' => $venue_id,
      'venue_name' => $venue_name,
      'venue_building' => $venue_building ?: NULL,
      'venue_room' => $venue_room ?: NULL
    ]);

    out(['ok'=>true,'message'=>'Venue updated successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_venue') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $venue_id = (int)($_POST['venue_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);

    if ($venue_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid venue ID']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_game_venue
      SET is_active = :is_active
      WHERE venue_id = :venue_id
    ");
    $stmt->execute(['is_active' => $is_active, 'venue_id' => $venue_id]);

    out(['ok'=>true,'message'=>'Venue updated']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok'=>false,'message'=>'Unknown action']);