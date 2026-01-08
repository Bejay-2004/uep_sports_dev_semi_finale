<?php
// tournament_manager/api.php - FIXED VERSION

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

// Helper function to verify tournament manager has access to sport/tournament
function verifyTournamentManagerAccess($pdo, $person_id, $tour_id, $sports_id = null, $team_id = null) {
  try {
    $sql = "SELECT COUNT(*) as has_access
            FROM tbl_sports_team
            WHERE tour_id = ? AND tournament_manager_id = ?";
    
    $params = [$tour_id, $person_id];
    
    if ($sports_id !== null) {
      $sql .= " AND sports_id = ?";
      $params[] = $sports_id;
    }
    
    if ($team_id !== null) {
      $sql .= " AND team_id = ?";
      $params[] = $team_id;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['has_access'] > 0;
  } catch (PDOException $e) {
    return false;
  }
}

// Helper function to deny access
function denyAccess($message = 'Access denied. You are not assigned to manage this.') {
  http_response_code(403);
  out(['ok' => false, 'error' => $message]);
}

// ==========================================
// TOURNAMENTS
// ==========================================

if ($action === 'tournaments') {
  try {
    // Only show tournaments where this person is assigned as tournament manager
    $stmt = $pdo->prepare("
      SELECT DISTINCT t.tour_id, t.tour_name, t.school_year, t.tour_date, t.is_active
      FROM tbl_tournament t
      INNER JOIN tbl_sports_team st ON st.tour_id = t.tour_id
      WHERE st.tournament_manager_id = ?
      ORDER BY t.tour_date DESC, t.tour_id DESC
    ");
    $stmt->execute([$person_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
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
// SPORTS
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
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

    // Get sports selected for this tournament
    $stmt = $pdo->prepare("
      SELECT DISTINCT s.sports_id, s.sports_name, s.team_individual, 
             s.weight_class, s.men_women, s.num_req_players, s.num_res_players
      FROM tbl_tournament_sports_selection tss
      JOIN tbl_sports s ON s.sports_id = tss.sports_id
      WHERE tss.tour_id = :tour_id AND s.is_active = 1
      ORDER BY s.sports_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    // If table doesn't exist, return empty array
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
    
    // Create table if doesn't exist
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
      $pdo->rollBack();
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $sport_ids_array = array_map('intval', explode(',', $sport_ids));
    $sport_ids_array = array_filter($sport_ids_array, function($id) { return $id > 0; });
    
    // Get current sports
    $currentStmt = $pdo->prepare("
      SELECT DISTINCT sports_id 
      FROM tbl_tournament_sports_selection 
      WHERE tour_id = :tour_id
    ");
    $currentStmt->execute(['tour_id' => $tour_id]);
    $currentSports = array_column($currentStmt->fetchAll(PDO::FETCH_ASSOC), 'sports_id');
    
    // Add new sports
    $sportsToAdd = array_diff($sport_ids_array, $currentSports);
    $sportsToRemove = array_diff($currentSports, $sport_ids_array);
    
    // Remove deselected sports
    foreach ($sportsToRemove as $sport_id) {
      $deleteStmt = $pdo->prepare("
        DELETE FROM tbl_tournament_sports_selection 
        WHERE tour_id = :tour_id AND sports_id = :sports_id
      ");
      $deleteStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sport_id]);
      
      // Also remove teams for this sport
      $deleteTeamsStmt = $pdo->prepare("
        DELETE FROM tbl_sports_team 
        WHERE tour_id = :tour_id AND sports_id = :sports_id
      ");
      $deleteTeamsStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sport_id]);
    }
    
    // Add new sports
    foreach ($sportsToAdd as $sport_id) {
      $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO tbl_tournament_sports_selection (tour_id, sports_id)
        VALUES (:tour_id, :sports_id)
      ");
      $insertStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sport_id]);
    }
    
    $pdo->commit();
    out(['ok'=>true,'message'=>'Sports selection updated successfully']);
  } catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETE APPROVAL & DISQUALIFICATION
// ==========================================

// Get pending athletes for approval (is_active = 0)
if ($action === 'pending_athletes') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    
    $sql = "SELECT 
              ta.team_ath_id,
              ta.tour_id,
              ta.team_id,
              ta.sports_id,
              ta.person_id,
              ta.is_captain,
              ta.is_active,
              p.f_name,
              p.l_name,
              p.m_name,
              p.college_code,
              p.course,
              p.date_birth,
              t.team_name,
              s.sports_name,
              tour.tour_name,
              CONCAT(coach.f_name, ' ', coach.l_name) as coach_name
            FROM tbl_team_athletes ta
            INNER JOIN tbl_person p ON p.person_id = ta.person_id
            INNER JOIN tbl_team t ON t.team_id = ta.team_id
            INNER JOIN tbl_sports s ON s.sports_id = ta.sports_id
            INNER JOIN tbl_tournament tour ON tour.tour_id = ta.tour_id
            INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                           AND st.team_id = ta.team_id 
                                           AND st.sports_id = ta.sports_id
                                           AND st.tournament_manager_id = :person_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            WHERE ta.is_active = 0";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id > 0) {
      $sql .= " AND ta.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    $sql .= " ORDER BY ta.tour_id DESC, s.sports_name, t.team_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Get approved athletes
if ($action === 'approved_athletes') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    
    $sql = "SELECT 
              ta.team_ath_id,
              ta.tour_id,
              ta.team_id,
              ta.sports_id,
              ta.person_id,
              ta.is_captain,
              ta.is_active,
              p.f_name,
              p.l_name,
              p.m_name,
              p.college_code,
              p.course,
              p.date_birth,
              t.team_name,
              s.sports_name,
              tour.tour_name,
              CONCAT(coach.f_name, ' ', coach.l_name) as coach_name
            FROM tbl_team_athletes ta
            INNER JOIN tbl_person p ON p.person_id = ta.person_id
            INNER JOIN tbl_team t ON t.team_id = ta.team_id
            INNER JOIN tbl_sports s ON s.sports_id = ta.sports_id
            INNER JOIN tbl_tournament tour ON tour.tour_id = ta.tour_id
            INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                           AND st.team_id = ta.team_id 
                                           AND st.sports_id = ta.sports_id
                                           AND st.tournament_manager_id = :person_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            WHERE ta.is_active = 1";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id > 0) {
      $sql .= " AND ta.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    $sql .= " ORDER BY ta.tour_id DESC, s.sports_name, t.team_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Approve athlete
if ($action === 'approve_athlete') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_id = (int)($_POST['team_ath_id'] ?? 0);
    
    if ($team_ath_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete ID']);
    }
    
    // Verify tournament manager has access
    $checkStmt = $pdo->prepare("
      SELECT ta.tour_id, ta.team_id, ta.sports_id
      FROM tbl_team_athletes ta
      INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                     AND st.team_id = ta.team_id 
                                     AND st.sports_id = ta.sports_id
                                     AND st.tournament_manager_id = :person_id
      WHERE ta.team_ath_id = :team_ath_id
    ");
    $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
    $athlete = $checkStmt->fetch();
    
    if (!$athlete) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied or athlete not found']);
    }
    
    // Approve the athlete
    $stmt = $pdo->prepare("
      UPDATE tbl_team_athletes
      SET is_active = 1
      WHERE team_ath_id = :team_ath_id
    ");
    $stmt->execute(['team_ath_id' => $team_ath_id]);
    
    out(['ok'=>true,'message'=>'Athlete approved successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Bulk approve athletes
if ($action === 'bulk_approve_athletes') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_ids = $_POST['team_ath_ids'] ?? '';
    
    if (!$team_ath_ids) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'No athletes selected']);
    }
    
    $ids = array_map('intval', explode(',', $team_ath_ids));
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if (count($ids) === 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete IDs']);
    }
    
    $pdo->beginTransaction();
    
    $approved = 0;
    foreach ($ids as $team_ath_id) {
      // Verify access
      $checkStmt = $pdo->prepare("
        SELECT 1
        FROM tbl_team_athletes ta
        INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                       AND st.team_id = ta.team_id 
                                       AND st.sports_id = ta.sports_id
                                       AND st.tournament_manager_id = :person_id
        WHERE ta.team_ath_id = :team_ath_id
      ");
      $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
      
      if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("
          UPDATE tbl_team_athletes
          SET is_active = 1
          WHERE team_ath_id = :team_ath_id
        ");
        $stmt->execute(['team_ath_id' => $team_ath_id]);
        $approved++;
      }
    }
    
    $pdo->commit();
    
    out(['ok'=>true,'message'=>"$approved athlete(s) approved successfully"]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Disqualify athlete
if ($action === 'disqualify_athlete') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_id = (int)($_POST['team_ath_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($team_ath_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete ID']);
    }
    
    // Verify tournament manager has access
    $checkStmt = $pdo->prepare("
      SELECT ta.tour_id, ta.team_id, ta.sports_id
      FROM tbl_team_athletes ta
      INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                     AND st.team_id = ta.team_id 
                                     AND st.sports_id = ta.sports_id
                                     AND st.tournament_manager_id = :person_id
      WHERE ta.team_ath_id = :team_ath_id
    ");
    $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
    $athlete = $checkStmt->fetch();
    
    if (!$athlete) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied or athlete not found']);
    }
    
    // Disqualify the athlete (set is_active = 0)
    $stmt = $pdo->prepare("
      UPDATE tbl_team_athletes
      SET is_active = 0
      WHERE team_ath_id = :team_ath_id
    ");
    $stmt->execute(['team_ath_id' => $team_ath_id]);
    
    // Log the disqualification
    if ($reason) {
      $logStmt = $pdo->prepare("
        INSERT INTO tbl_logs (user_id, log_event, log_date, module_name)
        VALUES (:user_id, :log_event, NOW(), 'Tournament Management')
      ");
      $logStmt->execute([
        'user_id' => $user_id,
        'log_event' => "Disqualified athlete (team_ath_id: $team_ath_id). Reason: $reason"
      ]);
    }
    
    out(['ok'=>true,'message'=>'Athlete disqualified successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETE APPROVAL & DISQUALIFICATION
// ==========================================

// Get pending athletes for approval (is_active = 0)
if ($action === 'pending_athletes') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    
    $sql = "SELECT 
              ta.team_ath_id,
              ta.tour_id,
              ta.team_id,
              ta.sports_id,
              ta.person_id,
              ta.is_captain,
              ta.is_active,
              p.f_name,
              p.l_name,
              p.m_name,
              p.college_code,
              p.course,
              p.date_birth,
              t.team_name,
              s.sports_name,
              tour.tour_name,
              CONCAT(coach.f_name, ' ', coach.l_name) as coach_name
            FROM tbl_team_athletes ta
            INNER JOIN tbl_person p ON p.person_id = ta.person_id
            INNER JOIN tbl_team t ON t.team_id = ta.team_id
            INNER JOIN tbl_sports s ON s.sports_id = ta.sports_id
            INNER JOIN tbl_tournament tour ON tour.tour_id = ta.tour_id
            INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                           AND st.team_id = ta.team_id 
                                           AND st.sports_id = ta.sports_id
                                           AND st.tournament_manager_id = :person_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            WHERE ta.is_active = 0";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id > 0) {
      $sql .= " AND ta.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    $sql .= " ORDER BY ta.tour_id DESC, s.sports_name, t.team_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Get approved athletes
if ($action === 'approved_athletes') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    
    $sql = "SELECT 
              ta.team_ath_id,
              ta.tour_id,
              ta.team_id,
              ta.sports_id,
              ta.person_id,
              ta.is_captain,
              ta.is_active,
              p.f_name,
              p.l_name,
              p.m_name,
              p.college_code,
              p.course,
              p.date_birth,
              t.team_name,
              s.sports_name,
              tour.tour_name,
              CONCAT(coach.f_name, ' ', coach.l_name) as coach_name
            FROM tbl_team_athletes ta
            INNER JOIN tbl_person p ON p.person_id = ta.person_id
            INNER JOIN tbl_team t ON t.team_id = ta.team_id
            INNER JOIN tbl_sports s ON s.sports_id = ta.sports_id
            INNER JOIN tbl_tournament tour ON tour.tour_id = ta.tour_id
            INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                           AND st.team_id = ta.team_id 
                                           AND st.sports_id = ta.sports_id
                                           AND st.tournament_manager_id = :person_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            WHERE ta.is_active = 1";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id > 0) {
      $sql .= " AND ta.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    $sql .= " ORDER BY ta.tour_id DESC, s.sports_name, t.team_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Approve athlete
if ($action === 'approve_athlete') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_id = (int)($_POST['team_ath_id'] ?? 0);
    
    if ($team_ath_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete ID']);
    }
    
    // Verify tournament manager has access
    $checkStmt = $pdo->prepare("
      SELECT ta.tour_id, ta.team_id, ta.sports_id
      FROM tbl_team_athletes ta
      INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                     AND st.team_id = ta.team_id 
                                     AND st.sports_id = ta.sports_id
                                     AND st.tournament_manager_id = :person_id
      WHERE ta.team_ath_id = :team_ath_id
    ");
    $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
    $athlete = $checkStmt->fetch();
    
    if (!$athlete) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied or athlete not found']);
    }
    
    // Approve the athlete
    $stmt = $pdo->prepare("
      UPDATE tbl_team_athletes
      SET is_active = 1
      WHERE team_ath_id = :team_ath_id
    ");
    $stmt->execute(['team_ath_id' => $team_ath_id]);
    
    out(['ok'=>true,'message'=>'Athlete approved successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Bulk approve athletes
if ($action === 'bulk_approve_athletes') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_ids = $_POST['team_ath_ids'] ?? '';
    
    if (!$team_ath_ids) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'No athletes selected']);
    }
    
    $ids = array_map('intval', explode(',', $team_ath_ids));
    $ids = array_filter($ids, function($id) { return $id > 0; });
    
    if (count($ids) === 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete IDs']);
    }
    
    $pdo->beginTransaction();
    
    $approved = 0;
    foreach ($ids as $team_ath_id) {
      // Verify access
      $checkStmt = $pdo->prepare("
        SELECT 1
        FROM tbl_team_athletes ta
        INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                       AND st.team_id = ta.team_id 
                                       AND st.sports_id = ta.sports_id
                                       AND st.tournament_manager_id = :person_id
        WHERE ta.team_ath_id = :team_ath_id
      ");
      $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
      
      if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("
          UPDATE tbl_team_athletes
          SET is_active = 1
          WHERE team_ath_id = :team_ath_id
        ");
        $stmt->execute(['team_ath_id' => $team_ath_id]);
        $approved++;
      }
    }
    
    $pdo->commit();
    
    out(['ok'=>true,'message'=>"$approved athlete(s) approved successfully"]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// Disqualify athlete
if ($action === 'disqualify_athlete') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $team_ath_id = (int)($_POST['team_ath_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($team_ath_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid athlete ID']);
    }
    
    // Verify tournament manager has access
    $checkStmt = $pdo->prepare("
      SELECT ta.tour_id, ta.team_id, ta.sports_id
      FROM tbl_team_athletes ta
      INNER JOIN tbl_sports_team st ON st.tour_id = ta.tour_id 
                                     AND st.team_id = ta.team_id 
                                     AND st.sports_id = ta.sports_id
                                     AND st.tournament_manager_id = :person_id
      WHERE ta.team_ath_id = :team_ath_id
    ");
    $checkStmt->execute(['team_ath_id' => $team_ath_id, 'person_id' => $person_id]);
    $athlete = $checkStmt->fetch();
    
    if (!$athlete) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied or athlete not found']);
    }
    
    // Disqualify the athlete (set is_active = 0)
    $stmt = $pdo->prepare("
      UPDATE tbl_team_athletes
      SET is_active = 0
      WHERE team_ath_id = :team_ath_id
    ");
    $stmt->execute(['team_ath_id' => $team_ath_id]);
    
    // Log the disqualification
    if ($reason) {
      $logStmt = $pdo->prepare("
        INSERT INTO tbl_logs (user_id, log_event, log_date, module_name)
        VALUES (:user_id, :log_event, NOW(), 'Tournament Management')
      ");
      $logStmt->execute([
        'user_id' => $user_id,
        'log_event' => "Disqualified athlete (team_ath_id: $team_ath_id). Reason: $reason"
      ]);
    }
    
    out(['ok'=>true,'message'=>'Athlete disqualified successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS
// ==========================================

if ($action === 'available_teams_for_sport') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    $sports_id = isset($_GET['sports_id']) ? (int)$_GET['sports_id'] : 0;
    
    // Get all active teams
    $stmt = $pdo->query("
      SELECT team_id, team_name, school_id, is_active
      FROM tbl_team
      WHERE is_active = 1
      ORDER BY team_name
    ");
    $allTeams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If tournament and sport specified, mark registered teams
    if ($tour_id > 0 && $sports_id > 0) {
      $registeredStmt = $pdo->prepare("
        SELECT team_id 
        FROM tbl_sports_team 
        WHERE tour_id = :tour_id AND sports_id = :sports_id
      ");
      $registeredStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
      $registered = array_column($registeredStmt->fetchAll(PDO::FETCH_ASSOC), 'team_id');
      
      foreach ($allTeams as &$team) {
        $team['is_registered'] = in_array($team['team_id'], $registered);
      }
    }
    
    out($allTeams);
  } catch (PDOException $e) {
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
      out(['ok'=>false,'message'=>'Missing tour_id or sports_id']);
    }

    // CRITICAL FIX: Join with tbl_team properly
    $stmt = $pdo->prepare("
      SELECT 
        st.tour_id,
        st.team_id,
        st.sports_id,
        t.team_name,
        t.school_id,
        CONCAT(COALESCE(pc.f_name, ''), ' ', COALESCE(pc.l_name, '')) AS coach_name,
        CONCAT(COALESCE(pac.f_name, ''), ' ', COALESCE(pac.l_name, '')) AS asst_coach_name,
        (SELECT COUNT(*) FROM tbl_team_athletes ta 
         WHERE ta.tour_id = st.tour_id 
         AND ta.team_id = st.team_id 
         AND ta.sports_id = st.sports_id 
         AND ta.is_active = 1) AS num_players
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      LEFT JOIN tbl_person pc ON pc.person_id = st.coach_id
      LEFT JOIN tbl_person pac ON pac.person_id = st.asst_coach_id
      WHERE st.tour_id = :tour_id AND st.sports_id = :sports_id
      ORDER BY t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
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
      $pdo->rollBack();
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing tour_id or sports_id']);
    }

    // Parse team IDs
    $team_ids_array = array_map('intval', explode(',', $team_ids));
    $team_ids_array = array_filter($team_ids_array, function($id) { return $id > 0; });
    
    // Get current teams
    $currentStmt = $pdo->prepare("
      SELECT team_id 
      FROM tbl_sports_team 
      WHERE tour_id = :tour_id AND sports_id = :sports_id
    ");
    $currentStmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    $currentTeams = array_column($currentStmt->fetchAll(PDO::FETCH_ASSOC), 'team_id');
    
    $teamsToAdd = array_diff($team_ids_array, $currentTeams);
    $teamsToRemove = array_diff($currentTeams, $team_ids_array);
    
    $added = 0;
    $removed = 0;
    
    // Remove deselected teams
    foreach ($teamsToRemove as $team_id) {
      $deleteStmt = $pdo->prepare("
        DELETE FROM tbl_sports_team 
        WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
      ");
      $deleteStmt->execute([
        'tour_id' => $tour_id,
        'sports_id' => $sports_id,
        'team_id' => $team_id
      ]);
      $removed++;
    }
    
    // Add new teams
    foreach ($teamsToAdd as $team_id) {
      $insertStmt = $pdo->prepare("
        INSERT INTO tbl_sports_team (
          tour_id, team_id, sports_id, 
          coach_id, asst_coach_id, trainor1_id, trainor2_id, trainor3_id
        ) VALUES (
          :tour_id, :team_id, :sports_id,
          NULL, NULL, NULL, NULL, NULL
        )
      ");
      $insertStmt->execute([
        'tour_id' => $tour_id,
        'team_id' => $team_id,
        'sports_id' => $sports_id
      ]);
      $added++;
    }
    
    $pdo->commit();
    
    $message = "Success: {$added} team(s) added, {$removed} team(s) removed";
    out(['ok'=>true, 'message'=>$message, 'added'=>$added, 'removed'=>$removed]);
    
  } catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}


if ($action === 'remove_tournament_team') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sports_id = (int)($_POST['sports_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);

    if ($tour_id <= 0 || $sports_id <= 0 || $team_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      DELETE FROM tbl_sports_team 
      WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'sports_id' => $sports_id,
      'team_id' => $team_id
    ]);

    out(['ok'=>true,'message'=>'Team removed successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// COACHES & STAFF
// ==========================================

if ($action === 'coaches') {
  try {
    $stmt = $pdo->query("
      SELECT person_id, CONCAT(f_name, ' ', l_name) AS full_name
      FROM tbl_person
      WHERE role_type = 'coach' AND is_active = 1
      ORDER BY l_name, f_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'umpires') {
  try {
    $stmt = $pdo->query("
      SELECT person_id, CONCAT(f_name, ' ', l_name) AS full_name
      FROM tbl_person
      WHERE role_type = 'umpire' AND is_active = 1
      ORDER BY l_name, f_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// VENUES
// ==========================================

if ($action === 'venues') {
  try {
    $stmt = $pdo->query("
      SELECT venue_id, venue_name, venue_building, venue_room, venue_description
      FROM tbl_game_venue
      WHERE is_active = 1
      ORDER BY venue_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    $venue_description = trim($_POST['venue_description'] ?? '');

    if (!$venue_name) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Venue name is required']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_game_venue (venue_name, venue_building, venue_room, venue_description, is_active)
      VALUES (:venue_name, :venue_building, :venue_room, :venue_description, 1)
    ");
    $stmt->execute([
      'venue_name' => $venue_name,
      'venue_building' => $venue_building,
      'venue_room' => $venue_room,
      'venue_description' => $venue_description
    ]);

    out(['ok'=>true,'message'=>'Venue created successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MATCHES
// ==========================================

// ==========================================
// MATCHES - FIXED TO SHOW INDIVIDUAL SPORTS
// ==========================================

if ($action === 'matches') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
    $sports_id = isset($_GET['sports_id']) ? (int)$_GET['sports_id'] : 0;

    // Modified SQL to handle both team and individual sports
    $sql = "
      SELECT 
        m.match_id,
        m.game_no,
        m.sked_date,
        m.sked_time,
        m.match_type,
        m.sports_type,
        m.sports_id,
        s.sports_name,
        m.tour_id,
        tour.tour_name,
        m.team_a_id,
        m.team_b_id,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name,
        m.venue_id,
        v.venue_name,
        m.match_umpire_id,
        CONCAT(COALESCE(pu.f_name, ''), ' ', COALESCE(pu.l_name, '')) AS umpire_name,
        m.winner_team_id,
        m.winner_athlete_id,
        CASE 
          WHEN m.winner_team_id IS NOT NULL THEN m.winner_team_id
          WHEN m.winner_athlete_id IS NOT NULL THEN m.winner_athlete_id
          ELSE NULL
        END AS winner_id,
        CASE 
          WHEN m.winner_team_id IS NOT NULL THEN tw.team_name
          WHEN m.winner_athlete_id IS NOT NULL THEN CONCAT(COALESCE(pw.f_name, ''), ' ', COALESCE(pw.l_name, ''))
          ELSE NULL
        END AS winner_name
      FROM tbl_match m
      -- Check if tournament manager is assigned to this sport in ANY team
      INNER JOIN tbl_sports_team st ON st.tour_id = m.tour_id 
                                     AND st.sports_id = m.sports_id
                                     AND st.tournament_manager_id = :person_id
      LEFT JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_tournament tour ON tour.tour_id = m.tour_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_person pu ON pu.person_id = m.match_umpire_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
      WHERE 1=1
    ";

    $params = ['person_id' => $person_id];
    
    if ($tour_id > 0) {
      $sql .= " AND m.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    if ($sports_id > 0) {
      $sql .= " AND m.sports_id = :sports_id";
      $params['sports_id'] = $sports_id;
    }
    
    // Group by match_id to avoid duplicates when manager is assigned to multiple teams in same sport
    $sql .= " GROUP BY m.match_id";
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC, m.game_no";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// FIXED CREATE MATCH - SUPPORTS BOTH TEAM AND INDIVIDUAL SPORTS
// Replace the create_match action in tournament_manager/api.php
// ==========================================

if ($action === 'create_match') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sports_id = (int)($_POST['sports_id'] ?? 0);
    $game_no = trim($_POST['game_no'] ?? '');
    $sked_date = $_POST['sked_date'] ?? '';
    $sked_time = $_POST['sked_time'] ?? '';
    $venue_id = (int)($_POST['venue_id'] ?? 0);
    $match_umpire_id = (int)($_POST['match_umpire_id'] ?? 0);
    $match_type = trim($_POST['match_type'] ?? '');
    $sports_type = trim($_POST['sports_type'] ?? '');
    $team_a_id = isset($_POST['team_a_id']) && $_POST['team_a_id'] !== '' ? (int)$_POST['team_a_id'] : null;
    $team_b_id = isset($_POST['team_b_id']) && $_POST['team_b_id'] !== '' ? (int)$_POST['team_b_id'] : null;

    if ($tour_id <= 0 || $sports_id <= 0 || !$game_no || !$sked_date || !$sked_time || !$match_type || !$sports_type) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    // Check for duplicate matches
    $duplicateCheck = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE tour_id = ? 
        AND sports_id = ? 
        AND game_no = ? 
        AND sked_date = ? 
        AND sked_time = ?
    ");
    $duplicateCheck->execute([$tour_id, $sports_id, $game_no, $sked_date, $sked_time]);
    $duplicate = $duplicateCheck->fetch();
    
    if ($duplicate['count'] > 0) {
      http_response_code(409);
      out(['ok' => false, 'message' => 'A match with this game number, date, and time already exists for this sport.']);
    }

    // Verify manager is assigned to this sport
    $checkStmt = $pdo->prepare("
      SELECT COUNT(*) as has_access
      FROM tbl_sports_team
      WHERE tour_id = ? AND sports_id = ? AND tournament_manager_id = ?
    ");
    $checkStmt->execute([$tour_id, $sports_id, $person_id]);
    $access = $checkStmt->fetch();
    
    if ($access['has_access'] == 0) {
      http_response_code(403);
      out(['ok' => false, 'message' => 'Access denied. You are not assigned to manage this sport.']);
    }

    if (!in_array($match_type, ['EL', 'QF', 'SF', 'F'])) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid match type']);
    }

    // Validation based on sports type
    if ($sports_type === 'team') {
      // For team sports, both teams are required
      if (!$team_a_id || !$team_b_id) {
        http_response_code(400);
        out(['ok'=>false,'message'=>'Both teams required for team sports']);
      }

      if ($team_a_id === $team_b_id) {
        http_response_code(400);
        out(['ok'=>false,'message'=>'Teams cannot be the same']);
      }
    } else if ($sports_type === 'individual') {
      // For individual sports, teams are optional (will be NULL)
      // Athletes will be registered during scoring phase
      $team_a_id = null;
      $team_b_id = null;
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_match (
        game_no, sked_date, sked_time, venue_id, match_umpire_id, 
        match_sports_manager_id, match_type, sports_id, sports_type, 
        team_a_id, team_b_id, tour_id, winner_team_id, winner_athlete_id
      ) VALUES (
        :game_no, :sked_date, :sked_time, :venue_id, :match_umpire_id,
        NULL, :match_type, :sports_id, :sports_type,
        :team_a_id, :team_b_id, :tour_id, NULL, NULL
      )
    ");
    
    $stmt->execute([
      'game_no' => $game_no,
      'sked_date' => $sked_date,
      'sked_time' => $sked_time,
      'venue_id' => $venue_id > 0 ? $venue_id : null,
      'match_umpire_id' => $match_umpire_id > 0 ? $match_umpire_id : null,
      'match_type' => $match_type,
      'sports_id' => $sports_id,
      'sports_type' => $sports_type,
      'team_a_id' => $team_a_id,
      'team_b_id' => $team_b_id,
      'tour_id' => $tour_id
    ]);

    $message = $sports_type === 'individual' 
      ? 'Individual match created successfully! Athletes can be registered during scoring.'
      : 'Team match created successfully!';

    out(['ok'=>true,'message'=>$message]);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_match') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $match_id = (int)($_POST['match_id'] ?? 0);
    $game_no = trim($_POST['game_no'] ?? '');
    $sked_date = $_POST['sked_date'] ?? '';
    $sked_time = $_POST['sked_time'] ?? '';
    $venue_id = (int)($_POST['venue_id'] ?? 0);
    $match_umpire_id = (int)($_POST['match_umpire_id'] ?? 0);
    $match_type = trim($_POST['match_type'] ?? '');
    $team_a_id = isset($_POST['team_a_id']) && $_POST['team_a_id'] !== '' ? (int)$_POST['team_a_id'] : null;
    $team_b_id = isset($_POST['team_b_id']) && $_POST['team_b_id'] !== '' ? (int)$_POST['team_b_id'] : null;

    if ($match_id <= 0 || !$game_no || !$sked_date || !$sked_time || !$match_type) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_match 
      SET game_no = :game_no,
          sked_date = :sked_date,
          sked_time = :sked_time,
          venue_id = :venue_id,
          match_umpire_id = :match_umpire_id,
          match_type = :match_type,
          team_a_id = :team_a_id,
          team_b_id = :team_b_id
      WHERE match_id = :match_id
    ");
    
    $stmt->execute([
      'match_id' => $match_id,
      'game_no' => $game_no,
      'sked_date' => $sked_date,
      'sked_time' => $sked_time,
      'venue_id' => $venue_id > 0 ? $venue_id : null,
      'match_umpire_id' => $match_umpire_id > 0 ? $match_umpire_id : null,
      'match_type' => $match_type,
      'team_a_id' => $team_a_id,
      'team_b_id' => $team_b_id
    ]);

    out(['ok'=>true,'message'=>'Match updated successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_match') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $match_id = (int)($_POST['match_id'] ?? 0);

    if ($match_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid match ID']);
    }

    // Delete scores first
    $deleteScores = $pdo->prepare("DELETE FROM tbl_comp_score WHERE match_id = :match_id");
    $deleteScores->execute(['match_id' => $match_id]);

    // Delete match
    $stmt = $pdo->prepare("DELETE FROM tbl_match WHERE match_id = :match_id");
    $stmt->execute(['match_id' => $match_id]);

    out(['ok'=>true,'message'=>'Match deleted successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SCORING & STANDINGS
// ==========================================

if ($action === 'match_athletes') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    
    if ($tour_id <= 0 || $sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing parameters']);
    }

    $stmt = $pdo->prepare("
      SELECT DISTINCT
        ta.person_id AS athlete_id,
        CONCAT(p.f_name, ' ', p.l_name) AS athlete_name,
        ta.team_id,
        t.team_name
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      WHERE ta.tour_id = :tour_id 
        AND ta.sports_id = :sports_id
        AND ta.is_active = 1
      ORDER BY t.team_name, p.l_name, p.f_name
    ");
    $stmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'match_scores') {
  try {
    $match_id = (int)($_GET['match_id'] ?? 0);
    
    if ($match_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid match ID']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        cs.competetors_score_id,
        cs.tour_id,
        cs.match_id,
        cs.team_id,
        cs.athlete_id,
        cs.score,
        cs.rank_no,
        cs.medal_type,
        t.team_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS athlete_name
      FROM tbl_comp_score cs
      LEFT JOIN tbl_team t ON t.team_id = cs.team_id
      LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
      WHERE cs.match_id = :match_id
      ORDER BY cs.rank_no ASC, cs.score DESC
    ");
    $stmt->execute(['match_id' => $match_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'save_score') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $match_id = (int)($_POST['match_id'] ?? 0);
    $team_id = isset($_POST['team_id']) && $_POST['team_id'] !== '' ? (int)$_POST['team_id'] : null;
    $score = $_POST['score'] ?? '';
    $rank_no = isset($_POST['rank_no']) ? (int)$_POST['rank_no'] : null;
    $medal_type = trim($_POST['medal_type'] ?? '');

    if ($tour_id <= 0 || $match_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    // Get match details to verify access
    $matchStmt = $pdo->prepare("SELECT sports_id FROM tbl_match WHERE match_id = ?");
    $matchStmt->execute([$match_id]);
    $match = $matchStmt->fetch();
    
    if (!$match) {
      http_response_code(404);
      out(['ok'=>false,'message'=>'Match not found']);
    }
    
    // Verify access
    if (!verifyTournamentManagerAccess($pdo, $person_id, $tour_id, $match['sports_id'])) {
      denyAccess('You are not assigned to manage this sport.');
    }

    // ✅ FIX: Set athlete_id to NULL explicitly (for team sports, we use team_id)
    $athlete_id = null;

    // ✅ FIX: Check if PRIMARY KEY exists in tbl_comp_score
    // If the table has a composite primary key (tour_id, match_id, team_id, athlete_id)
    // we need to handle it properly
    
    $stmt = $pdo->prepare("
      INSERT INTO tbl_comp_score (tour_id, match_id, team_id, athlete_id, score, rank_no, medal_type)
      VALUES (:tour_id, :match_id, :team_id, :athlete_id, :score, :rank_no, :medal_type)
      ON DUPLICATE KEY UPDATE
        score = VALUES(score),
        rank_no = VALUES(rank_no),
        medal_type = VALUES(medal_type)
    ");
    
    $stmt->execute([
      'tour_id' => $tour_id,
      'match_id' => $match_id,
      'team_id' => $team_id,
      'athlete_id' => $athlete_id,  // ✅ Explicitly set to NULL
      'score' => $score,
      'rank_no' => $rank_no,
      'medal_type' => $medal_type
    ]);

    out(['ok'=>true,'message'=>'Score saved successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'standings') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);

    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    // Verify manager has access to this tournament
    $accessCheck = $pdo->prepare("
      SELECT COUNT(DISTINCT sports_id) as has_access
      FROM tbl_sports_team
      WHERE tour_id = ? AND tournament_manager_id = ?
    ");
    $accessCheck->execute([$tour_id, $person_id]);
    $access = $accessCheck->fetch();
    
    if ($access['has_access'] == 0) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied. You are not assigned to manage any sport in this tournament.']);
    }

    // Build query - only show standings for sports this manager is assigned to
    $sql = "
      SELECT 
        ts.tour_id,
        ts.sports_id,
        ts.team_id,
        ts.athlete_id,
        t.team_name,
        s.sports_name,
        ts.no_games_played,
        ts.no_win,
        ts.no_loss,
        ts.no_draw,
        ts.no_gold,
        ts.no_silver,
        ts.no_bronze,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS athlete_name,
        s.team_individual
      FROM tbl_team_standing ts
      INNER JOIN tbl_sports_team st ON st.tour_id = ts.tour_id 
                                     AND st.sports_id = ts.sports_id 
                                     AND st.tournament_manager_id = :person_id
      LEFT JOIN tbl_team t ON t.team_id = ts.team_id
      INNER JOIN tbl_sports s ON s.sports_id = ts.sports_id
      LEFT JOIN tbl_person p ON p.person_id = ts.athlete_id
      WHERE ts.tour_id = :tour_id
    ";

    $params = ['person_id' => $person_id, 'tour_id' => $tour_id];
    
    if ($sports_id > 0) {
      $sql .= " AND ts.sports_id = :sports_id";
      $params['sports_id'] = $sports_id;
    }
    
    // Order by wins, then gold medals
    $sql .= " ORDER BY ts.no_win DESC, ts.no_gold DESC, ts.no_silver DESC, ts.no_bronze DESC, t.team_name, athlete_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no results, return empty array instead of error
    out($results ?: []);
    
  } catch (PDOException $e) {
    error_log("Standings error: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ADDITIONAL ENDPOINTS
// ==========================================

if ($action === 'available_teams_for_sport') {
  try {
    // Get all active teams
    $stmt = $pdo->query("
      SELECT team_id, team_name
      FROM tbl_team
      WHERE is_active = 1
      ORDER BY team_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'team_players') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($tour_id <= 0 || $sports_id <= 0 || $team_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing parameters']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        ta.team_ath_id,
        ta.person_id,
        CONCAT(p.f_name, ' ', COALESCE(p.m_name, ''), ' ', p.l_name) AS player_name,
        ta.is_captain,
        p.college_code,
        p.course
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      WHERE ta.tour_id = :tour_id 
        AND ta.sports_id = :sports_id 
        AND ta.team_id = :team_id
        AND ta.is_active = 1
      ORDER BY ta.is_captain DESC, p.l_name, p.f_name
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'sports_id' => $sports_id,
      'team_id' => $team_id
    ]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
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
      out(['ok'=>false,'message'=>'Missing parameters']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        CONCAT(COALESCE(pc.f_name, ''), ' ', COALESCE(pc.l_name, '')) AS coach_name,
        CONCAT(COALESCE(pac.f_name, ''), ' ', COALESCE(pac.l_name, '')) AS asst_coach_name
      FROM tbl_sports_team st
      LEFT JOIN tbl_person pc ON pc.person_id = st.coach_id
      LEFT JOIN tbl_person pac ON pac.person_id = st.asst_coach_id
      WHERE st.tour_id = :tour_id 
        AND st.sports_id = :sports_id 
        AND st.team_id = :team_id
    ");
    $stmt->execute([
      'tour_id' => $tour_id,
      'sports_id' => $sports_id,
      'team_id' => $team_id
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    out($result ? $result : ['coach_name' => '', 'asst_coach_name' => '']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'sports_managers') {
  try {
    $stmt = $pdo->query("
      SELECT person_id, CONCAT(f_name, ' ', l_name) AS full_name
      FROM tbl_person
      WHERE role_type = 'Tournament manager' AND is_active = 1
      ORDER BY l_name, f_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'tournament_teams_by_sport') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    $sports_id = (int)($_GET['sports_id'] ?? 0);
    
    if ($tour_id <= 0 || $sports_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing parameters']);
    }

    $stmt = $pdo->prepare("
      SELECT DISTINCT
        t.team_id,
        t.team_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      WHERE st.tour_id = :tour_id AND st.sports_id = :sports_id
      ORDER BY t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id, 'sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'available_teams') {
  try {
    $stmt = $pdo->query("
      SELECT team_id, team_name, school_id, is_active
      FROM tbl_team
      WHERE is_active = 1
      ORDER BY team_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'tournament_teams') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid tournament ID']);
    }

    $stmt = $pdo->prepare("
      SELECT DISTINCT
        t.team_id,
        t.team_name,
        s.sports_name
      FROM tbl_sports_team st
      JOIN tbl_team t ON t.team_id = st.team_id
      JOIN tbl_sports s ON s.sports_id = st.sports_id
      WHERE st.tour_id = :tour_id
      ORDER BY s.sports_name, t.team_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
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

    if ($tour_id <= 0 || $sports_id <= 0 || !$team_ids) {
      $pdo->rollBack();
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $team_ids_array = array_map('intval', explode(',', $team_ids));
    $team_ids_array = array_filter($team_ids_array, function($id) { return $id > 0; });
    
    $added = 0;
    foreach ($team_ids_array as $team_id) {
      // Check if already exists
      $checkStmt = $pdo->prepare("
        SELECT 1 FROM tbl_sports_team 
        WHERE tour_id = :tour_id AND sports_id = :sports_id AND team_id = :team_id
      ");
      $checkStmt->execute([
        'tour_id' => $tour_id,
        'sports_id' => $sports_id,
        'team_id' => $team_id
      ]);
      
      if (!$checkStmt->fetch()) {
        $insertStmt = $pdo->prepare("
          INSERT INTO tbl_sports_team (tour_id, team_id, sports_id, coach_id, asst_coach_id, trainor1_id, trainor2_id, trainor3_id)
          VALUES (:tour_id, :team_id, :sports_id, NULL, NULL, NULL, NULL, NULL)
        ");
        $insertStmt->execute([
          'tour_id' => $tour_id,
          'team_id' => $team_id,
          'sports_id' => $sports_id
        ]);
        $added++;
      }
    }
    
    $pdo->commit();
    out(['ok'=>true,'message'=>"Added $added team(s) successfully"]);
  } catch (PDOException $e) {
    $pdo->rollBack();
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
    
    // Verify manager has access to this tournament
    $accessCheck = $pdo->prepare("
      SELECT COUNT(DISTINCT sports_id) as has_access
      FROM tbl_sports_team
      WHERE tour_id = ? AND tournament_manager_id = ?
    ");
    $accessCheck->execute([$tour_id, $person_id]);
    $access = $accessCheck->fetch();
    
    if ($access['has_access'] == 0) {
      http_response_code(403);
      out(['ok'=>false,'message'=>'Access denied. You are not assigned to manage any sport in this tournament.']);
    }

    // Only show medals for sports this manager is assigned to
    // Group by team to get total medals across all sports
    $stmt = $pdo->prepare("
      SELECT 
        t.team_id,
        t.team_name,
        SUM(COALESCE(ts.no_gold, 0)) AS total_gold,
        SUM(COALESCE(ts.no_silver, 0)) AS total_silver,
        SUM(COALESCE(ts.no_bronze, 0)) AS total_bronze,
        (SUM(COALESCE(ts.no_gold, 0)) + 
         SUM(COALESCE(ts.no_silver, 0)) + 
         SUM(COALESCE(ts.no_bronze, 0))) AS total_medals
      FROM tbl_team_standing ts
      INNER JOIN tbl_sports_team st ON st.tour_id = ts.tour_id 
                                     AND st.sports_id = ts.sports_id 
                                     AND st.tournament_manager_id = :person_id
      INNER JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.tour_id = :tour_id
        AND ts.team_id IS NOT NULL
      GROUP BY t.team_id, t.team_name
      HAVING total_medals > 0
      ORDER BY total_gold DESC, total_silver DESC, total_bronze DESC, t.team_name
    ");
    
    $stmt->execute(['tour_id' => $tour_id, 'person_id' => $person_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return empty array if no medals yet
    out($results ?: []);
    
  } catch (PDOException $e) {
    error_log("Medal tally error: " . $e->getMessage());
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

if ($action === 'scores') {
  try {
    $match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;

    $sql = "
      SELECT 
        cs.competetors_score_id AS score_id,
        cs.tour_id,
        cs.match_id,
        cs.team_id,
        cs.athlete_id,
        cs.score,
        cs.rank_no,
        cs.medal_type,
        t.team_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS athlete_name,
        m.game_no,
        s.sports_name
      FROM tbl_comp_score cs
      LEFT JOIN tbl_team t ON t.team_id = cs.team_id
      LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
      LEFT JOIN tbl_match m ON m.match_id = cs.match_id
      LEFT JOIN tbl_sports s ON s.sports_id = m.sports_id
      WHERE 1=1
    ";

    $params = [];
    
    if ($match_id > 0) {
      $sql .= " AND cs.match_id = :match_id";
      $params['match_id'] = $match_id;
    }
    
    $sql .= " ORDER BY cs.rank_no ASC, cs.score DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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

    out(['ok'=>true,'message'=>'Score deleted successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// DECLARE WINNER - SUPPORTS BOTH TEAM AND INDIVIDUAL SPORTS
// Replace the declare_winner action in api.php
// ==========================================

// ==========================================
// FIXED DECLARE WINNER - SUPPORTS BOTH TEAM AND INDIVIDUAL SPORTS
// Replace the declare_winner action in api.php (around line 850)
// ==========================================

// ==========================================
// FIXED DECLARE WINNER WITH BETTER DEBUGGING
// Replace the declare_winner action in api.php (around line 850)
// ==========================================

if ($action === 'declare_winner') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    // Get and validate inputs
    $match_id = isset($_POST['match_id']) ? (int)$_POST['match_id'] : 0;
    $winner_id = isset($_POST['winner_id']) ? (int)$_POST['winner_id'] : 0;
    $winner_type = isset($_POST['winner_type']) ? trim($_POST['winner_type']) : 'team';
    $sports_type = isset($_POST['sports_type']) ? trim($_POST['sports_type']) : 'team';

    // Enhanced debug logging
    error_log("=== DECLARE WINNER DEBUG ===");
    error_log("Raw POST data: " . print_r($_POST, true));
    error_log("Parsed match_id: " . $match_id . " (type: " . gettype($match_id) . ")");
    error_log("Parsed winner_id: " . $winner_id . " (type: " . gettype($winner_id) . ")");
    error_log("Winner type: " . $winner_type);
    error_log("Sports type: " . $sports_type);

    // Validation with detailed error messages
    if ($match_id <= 0) {
      error_log("❌ Invalid match_id: " . $match_id);
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid match ID (received: ' . $match_id . ')']);
    }

    if ($winner_id <= 0) {
      error_log("❌ Invalid winner_id: " . $winner_id);
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid winner ID (received: ' . $winner_id . '). Please select a valid winner.']);
    }

    // Get match details to verify access
    $matchStmt = $pdo->prepare("
      SELECT m.sports_id, m.tour_id, m.sports_type, m.team_a_id, m.team_b_id
      FROM tbl_match m
      WHERE m.match_id = ?
    ");
    $matchStmt->execute([$match_id]);
    $match = $matchStmt->fetch();
    
    if (!$match) {
      error_log("❌ Match not found: " . $match_id);
      http_response_code(404);
      out(['ok'=>false,'message'=>'Match not found']);
    }
    
    error_log("✅ Match found - sports_id: " . $match['sports_id'] . ", tour_id: " . $match['tour_id'] . ", sports_type: " . $match['sports_type']);
    
    // Verify access
    if (!verifyTournamentManagerAccess($pdo, $person_id, $match['tour_id'], $match['sports_id'])) {
      error_log("❌ Access denied for person_id: " . $person_id);
      denyAccess('You are not assigned to manage this sport.');
    }

    error_log("✅ Access verified");

    // === INDIVIDUAL SPORTS ===
    if ($sports_type === 'individual' && $winner_type === 'athlete') {
      error_log("📋 Processing INDIVIDUAL sport winner declaration");
      
      // Verify the athlete is registered for this sport
      $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) as count, team_id
        FROM tbl_team_athletes
        WHERE tour_id = ? AND sports_id = ? AND person_id = ? AND is_active = 1
      ");
      $verifyStmt->execute([$match['tour_id'], $match['sports_id'], $winner_id]);
      $verify = $verifyStmt->fetch();
      
      error_log("Athlete registration check - count: " . $verify['count']);
      
      if ($verify['count'] == 0) {
        error_log("❌ Athlete not registered - tour_id: " . $match['tour_id'] . ", sports_id: " . $match['sports_id'] . ", person_id: " . $winner_id);
        http_response_code(400);
        out(['ok'=>false,'message'=>'Selected athlete (ID: ' . $winner_id . ') is not registered for this sport']);
      }
      
      error_log("✅ Athlete verified");
      
      // Store the athlete's person_id in winner_athlete_id
      $stmt = $pdo->prepare("
        UPDATE tbl_match
        SET winner_athlete_id = :winner_id, winner_team_id = NULL
        WHERE match_id = :match_id
      ");
      $result = $stmt->execute(['winner_id' => $winner_id, 'match_id' => $match_id]);
      
      error_log("Update result: " . ($result ? 'success' : 'failed'));
      error_log("Rows affected: " . $stmt->rowCount());
      
      // Get athlete name for response
      $athleteStmt = $pdo->prepare("
        SELECT CONCAT(f_name, ' ', l_name) as name
        FROM tbl_person
        WHERE person_id = ?
      ");
      $athleteStmt->execute([$winner_id]);
      $athlete = $athleteStmt->fetch();
      $winnerName = $athlete['name'] ?? 'Unknown';
      
      error_log("✅ Individual winner declared: " . $winnerName . " (ID: " . $winner_id . ")");
      out(['ok'=>true,'message'=>"Winner declared: {$winnerName}"]);
      
    } 
    // === TEAM SPORTS ===
    else {
      error_log("📋 Processing TEAM sport winner declaration");
      
      // Verify the team is part of this match
      $verifyStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM tbl_match
        WHERE match_id = ? AND (team_a_id = ? OR team_b_id = ?)
      ");
      $verifyStmt->execute([$match_id, $winner_id, $winner_id]);
      $verify = $verifyStmt->fetch();
      
      error_log("Team verification - count: " . $verify['count'] . ", checking if team " . $winner_id . " is in match");
      
      if ($verify['count'] == 0) {
        error_log("❌ Team not in match - match_id: " . $match_id . ", team_id: " . $winner_id);
        error_log("Match teams - team_a_id: " . $match['team_a_id'] . ", team_b_id: " . $match['team_b_id']);
        http_response_code(400);
        out(['ok'=>false,'message'=>'Selected team (ID: ' . $winner_id . ') is not part of this match']);
      }
      
      error_log("✅ Team verified");
      
      // Store team_id in winner_team_id
      $stmt = $pdo->prepare("
        UPDATE tbl_match
        SET winner_team_id = :winner_id, winner_athlete_id = NULL
        WHERE match_id = :match_id
      ");
      $result = $stmt->execute(['winner_id' => $winner_id, 'match_id' => $match_id]);
      
      error_log("Update result: " . ($result ? 'success' : 'failed'));
      error_log("Rows affected: " . $stmt->rowCount());
      
      // Get team name for response
      $teamStmt = $pdo->prepare("SELECT team_name FROM tbl_team WHERE team_id = ?");
      $teamStmt->execute([$winner_id]);
      $team = $teamStmt->fetch();
      $winnerName = $team['team_name'] ?? 'Unknown';
      
      error_log("✅ Team winner declared: " . $winnerName . " (ID: " . $winner_id . ")");
      out(['ok'=>true,'message'=>"Winner declared: {$winnerName}"]);
    }

  } catch (PDOException $e) {
    error_log("❌ Database error in declare_winner: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  } catch (Exception $e) {
    error_log("❌ General error in declare_winner: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// COMPLETE TEAMS & PLAYERS API ENDPOINTS
// Copy this entire section and paste at the END of your api.php
// (before the final "Unknown action" line)
// ==========================================

// ==========================================
// TEAMS MANAGEMENT
// ==========================================

if ($action === 'get_all_teams') {
  try {
    $stmt = $pdo->query("
      SELECT t.school_id, t.team_id, t.team_name, t.is_active,
             s.school_name
      FROM tbl_team t
      LEFT JOIN tbl_school s ON t.school_id = s.school_id
      ORDER BY t.team_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'get_schools') {
  try {
    $stmt = $pdo->query("
      SELECT school_id, school_name, school_address, school_head, 
             school_sports_director, sports_dir_cp, sports_dir_email
      FROM tbl_school
      ORDER BY school_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'register_team') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $school_id = (int)($_POST['school_id'] ?? 0);
    $team_name = trim($_POST['team_name'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($school_id <= 0 || !$team_name) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    // Check if team already exists for this school
    $check = $pdo->prepare("
      SELECT team_id FROM tbl_team 
      WHERE school_id = :school_id AND team_name = :team_name
    ");
    $check->execute(['school_id' => $school_id, 'team_name' => $team_name]);
    
    if ($check->fetch()) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Team already exists for this school']);
    }

    // Get next GLOBAL team_id (across all schools) - FIX FOR PRIMARY KEY ISSUE
    $maxIdStmt = $pdo->query("SELECT COALESCE(MAX(team_id), 0) as max_id FROM tbl_team");
    $result = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($result['max_id'] ?? 0) + 1;

    // Insert the team
    $stmt = $pdo->prepare("
      INSERT INTO tbl_team (school_id, team_id, team_name, is_active)
      VALUES (:school_id, :team_id, :team_name, :is_active)
    ");
    
    $stmt->execute([
      'school_id' => $school_id,
      'team_id' => $nextId,
      'team_name' => $team_name,
      'is_active' => $is_active
    ]);

    out(['ok'=>true,'message'=>'Team registered successfully', 'team_id' => $nextId]);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  }
}

if ($action === 'update_team') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $old_school_id = (int)($_POST['old_school_id'] ?? 0);
    $old_team_id = (int)($_POST['old_team_id'] ?? 0);
    $team_name = trim($_POST['team_name'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($old_school_id <= 0 || $old_team_id <= 0 || !$team_name) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_team
      SET team_name = :team_name, is_active = :is_active
      WHERE school_id = :school_id AND team_id = :team_id
    ");
    $stmt->execute([
      'team_name' => $team_name,
      'is_active' => $is_active,
      'school_id' => $old_school_id,
      'team_id' => $old_team_id
    ]);

    out(['ok'=>true,'message'=>'Team updated successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_team_status') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $school_id = (int)($_POST['school_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);

    if ($school_id <= 0 || $team_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid team identifiers']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_team
      SET is_active = :is_active
      WHERE school_id = :school_id AND team_id = :team_id
    ");
    $stmt->execute([
      'is_active' => $is_active,
      'school_id' => $school_id,
      'team_id' => $team_id
    ]);

    out(['ok'=>true,'message'=>'Team status updated']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// PLAYERS MANAGEMENT  
// ==========================================

if ($action === 'get_all_players') {
  try {
    $team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
    
    $sql = "
      SELECT p.person_id, p.l_name, p.f_name, p.m_name, p.role_type, 
             p.title, p.date_birth, p.college_code, p.course, 
             p.blood_type, p.is_active,
             c.college_name
      FROM tbl_person p
      LEFT JOIN tbl_college c ON p.college_code = c.college_code
      WHERE p.role_type = 'athlete'
    ";
    
    $params = [];
    if ($team_id > 0) {
      $sql .= " AND p.person_id IN (
        SELECT person_id FROM tbl_team_athletes WHERE team_id = :team_id
      )";
      $params['team_id'] = $team_id;
    }
    
    $sql .= " ORDER BY p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'get_colleges') {
  try {
    $stmt = $pdo->query("
      SELECT college_id, college_code, college_name, college_dean, is_active
      FROM tbl_college
      WHERE is_active = 1
      ORDER BY college_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'register_player') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $l_name = trim($_POST['l_name'] ?? '');
    $f_name = trim($_POST['f_name'] ?? '');
    $m_name = trim($_POST['m_name'] ?? '');
    $role_type = trim($_POST['role_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $date_birth = $_POST['date_birth'] ?? null;
    $college_code = trim($_POST['college_code'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    if (!$l_name || !$f_name || !$role_type) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields (last name, first name, role type)']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO tbl_person (l_name, f_name, m_name, role_type, title, 
                              date_birth, college_code, course, blood_type, is_active)
      VALUES (:l_name, :f_name, :m_name, :role_type, :title, 
              :date_birth, :college_code, :course, :blood_type, :is_active)
    ");
    $stmt->execute([
      'l_name' => $l_name,
      'f_name' => $f_name,
      'm_name' => $m_name,
      'role_type' => $role_type,
      'title' => $title,
      'date_birth' => $date_birth ?: null,
      'college_code' => $college_code ?: null,
      'course' => $course ?: null,
      'blood_type' => $blood_type ?: null,
      'is_active' => $is_active
    ]);

    $person_id = $pdo->lastInsertId();
    
    out(['ok'=>true,'message'=>'Player registered successfully', 'person_id' => $person_id]);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_player') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $person_id = (int)($_POST['person_id'] ?? 0);
    $l_name = trim($_POST['l_name'] ?? '');
    $f_name = trim($_POST['f_name'] ?? '');
    $m_name = trim($_POST['m_name'] ?? '');
    $role_type = trim($_POST['role_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $date_birth = $_POST['date_birth'] ?? null;
    $college_code = trim($_POST['college_code'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');
    $is_active = (int)($_POST['is_active'] ?? 1);

    if ($person_id <= 0 || !$l_name || !$f_name || !$role_type) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_person
      SET l_name = :l_name, f_name = :f_name, m_name = :m_name, 
          role_type = :role_type, title = :title, date_birth = :date_birth,
          college_code = :college_code, course = :course, 
          blood_type = :blood_type, is_active = :is_active
      WHERE person_id = :person_id
    ");
    $stmt->execute([
      'l_name' => $l_name,
      'f_name' => $f_name,
      'm_name' => $m_name,
      'role_type' => $role_type,
      'title' => $title,
      'date_birth' => $date_birth ?: null,
      'college_code' => $college_code ?: null,
      'course' => $course ?: null,
      'blood_type' => $blood_type ?: null,
      'is_active' => $is_active,
      'person_id' => $person_id
    ]);

    out(['ok'=>true,'message'=>'Player updated successfully']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_player_status') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $person_id = (int)($_POST['person_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? 0);

    if ($person_id <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Invalid person ID']);
    }

    $stmt = $pdo->prepare("
      UPDATE tbl_person
      SET is_active = :is_active
      WHERE person_id = :person_id
    ");
    $stmt->execute([
      'is_active' => $is_active,
      'person_id' => $person_id
    ]);

    out(['ok'=>true,'message'=>'Player status updated']);
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENT MANAGER ENDPOINTS
// ==========================================

// Get teams registered in a tournament
if ($action === 'get_tournament_teams') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    
    // Only show teams where this manager is assigned to at least one sport
    $sql = "SELECT DISTINCT
              tt.tour_team_id,
              tt.tour_id,
              tt.team_id,
              t.team_name,
              tt.registration_date,
              tt.is_active,
              (SELECT COUNT(DISTINCT sports_id) 
               FROM tbl_sports_team 
               WHERE tour_id = tt.tour_id AND team_id = tt.team_id 
                 AND tournament_manager_id = ?) as num_sports,
              (SELECT COUNT(DISTINCT person_id) 
               FROM tbl_team_athletes 
               WHERE tour_id = tt.tour_id AND team_id = tt.team_id AND is_active = 1) as num_athletes
            FROM tbl_tournament_teams tt
            JOIN tbl_team t ON t.team_id = tt.team_id
            INNER JOIN tbl_sports_team st ON st.tour_id = tt.tour_id 
                                           AND st.team_id = tt.team_id 
                                           AND st.tournament_manager_id = ?
            WHERE tt.tour_id = ? AND tt.is_active = 1
            ORDER BY t.team_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$person_id, $person_id, $tour_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Get sports for a team in a tournament
if ($action === 'get_team_sports') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    $team_id = (int)$_GET['team_id'];
    
    // Verify access to this tournament
    $checkStmt = $pdo->prepare("
      SELECT COUNT(*) as has_access
      FROM tbl_sports_team
      WHERE tour_id = ? AND tournament_manager_id = ?
    ");
    $checkStmt->execute([$tour_id, $person_id]);
    $access = $checkStmt->fetch();
    
    if ($access['has_access'] == 0) {
      http_response_code(403);
      out(['ok' => false, 'error' => 'Access denied. You are not assigned to this tournament.']);
      return;
    }
    
    // Only show sports where this tournament manager is assigned
    $sql = "SELECT 
              st.tour_id,
              st.team_id,
              st.sports_id,
              s.sports_name,
              s.team_individual,
              s.men_women,
              CONCAT(COALESCE(coach.f_name, ''), ' ', COALESCE(coach.l_name, '')) as coach_name,
              CONCAT(COALESCE(tm.f_name, ''), ' ', COALESCE(tm.l_name, '')) as tournament_manager_name,
              (SELECT COUNT(DISTINCT person_id) 
               FROM tbl_team_athletes 
               WHERE tour_id = st.tour_id 
                 AND team_id = st.team_id 
                 AND sports_id = st.sports_id 
                 AND is_active = 1) as num_athletes
            FROM tbl_sports_team st
            JOIN tbl_sports s ON s.sports_id = st.sports_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            LEFT JOIN tbl_person tm ON tm.person_id = st.tournament_manager_id
            WHERE st.tour_id = ? AND st.team_id = ? AND st.tournament_manager_id = ?
            ORDER BY s.sports_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tour_id, $team_id, $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Get athletes for a sport in a team in a tournament
if ($action === 'get_sport_athletes') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    $team_id = (int)$_GET['team_id'];
    $sports_id = (int)$_GET['sports_id'];
    
    // Verify this tournament manager is assigned to this specific sport
    $checkStmt = $pdo->prepare("
      SELECT COUNT(*) as has_access
      FROM tbl_sports_team
      WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND tournament_manager_id = ?
    ");
    $checkStmt->execute([$tour_id, $team_id, $sports_id, $person_id]);
    $access = $checkStmt->fetch();
    
    if ($access['has_access'] == 0) {
      http_response_code(403);
      out(['ok' => false, 'error' => 'Access denied. You are not assigned to manage this sport.']);
      return;
    }
    
    $sql = "SELECT 
              ta.team_ath_id,
              ta.tour_id,
              ta.team_id,
              ta.sports_id,
              ta.person_id,
              ta.is_captain,
              p.f_name,
              p.l_name,
              p.m_name,
              p.role_type,
              p.college_code,
              p.course,
              CONCAT(p.f_name, ' ', p.l_name) as full_name,
              COALESCE(vs.height, 0) as height,
              COALESCE(vs.weight, 0) as weight,
              ast.scholarship_name
            FROM tbl_team_athletes ta
            JOIN tbl_person p ON p.person_id = ta.person_id
            LEFT JOIN tbl_vital_signs vs ON vs.person_id = p.person_id
            LEFT JOIN tbl_ath_status ast ON ast.person_id = p.person_id
            WHERE ta.tour_id = ? AND ta.team_id = ? AND ta.sports_id = ? AND ta.is_active = 1
            ORDER BY ta.is_captain DESC, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tour_id, $team_id, $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Get all sports (for adding to teams)
if ($action === 'all_sports') {
  try {
    $sql = "SELECT sports_id, sports_name, team_individual, men_women, 
                   num_req_players, num_res_players
            FROM tbl_sports 
            WHERE is_active = 1
            ORDER BY sports_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Get tournament manager's assignments
if ($action === 'my_assignments') {
  try {
    $sql = "SELECT DISTINCT
              t.tour_id,
              t.tour_name,
              tm.team_id,
              team.team_name,
              st.sports_id,
              s.sports_name,
              s.team_individual,
              s.men_women,
              st.tournament_manager_id
            FROM tbl_sports_team st
            JOIN tbl_tournament t ON t.tour_id = st.tour_id
            JOIN tbl_team team ON team.team_id = st.team_id
            JOIN tbl_sports s ON s.sports_id = st.sports_id
            LEFT JOIN tbl_tournament_teams tm ON tm.tour_id = st.tour_id AND tm.team_id = st.team_id
            WHERE st.tournament_manager_id = ?
            ORDER BY t.tour_name, team.team_name, s.sports_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$person_id]);
    
    error_log("My assignments for person_id " . $person_id . ": " . count($stmt->fetchAll()) . " records");
    
    // Re-execute to get the data (fetchAll consumed it)
    $stmt->execute([$person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok'=>false,'message'=>'Unknown action']);