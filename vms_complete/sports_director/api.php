<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'sports_director') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied']);
}

header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// COMPREHENSIVE TOURNAMENT VIEW (NEW)
// ==========================================
if ($action === 'get_tournament_comprehensive') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    
    // Get tournament info
    $stmt = $pdo->prepare("SELECT * FROM tbl_tournament WHERE tour_id = ?");
    $stmt->execute([$tour_id]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) {
      out(['ok' => false, 'error' => 'Tournament not found']);
    }
    
    // Get all teams in tournament with their sports
    $stmt = $pdo->prepare("
      SELECT DISTINCT 
        t.team_id,
        t.team_name,
        tt.registration_date
      FROM tbl_tournament_teams tt
      JOIN tbl_team t ON t.team_id = tt.team_id
      WHERE tt.tour_id = ? AND tt.is_active = 1
      ORDER BY t.team_name
    ");
    $stmt->execute([$tour_id]);
    $teams = $stmt->fetchAll();
    
    // For each team, get their sports and details
    foreach ($teams as &$team) {
      // Get sports for this team in this tournament
      $stmt = $pdo->prepare("
        SELECT 
          st.*,
          s.sports_name,
          s.team_individual,
          CONCAT(coach.f_name, ' ', coach.l_name) as coach_name,
          coach.person_id as coach_id,
          CONCAT(asst.f_name, ' ', asst.l_name) as asst_coach_name,
          asst.person_id as asst_coach_id,
          CONCAT(tm.f_name, ' ', tm.l_name) as tournament_manager_name,
          tm.person_id as tournament_manager_id,
          CONCAT(t1.f_name, ' ', t1.l_name) as trainor1_name,
          t1.person_id as trainor1_person_id,
          CONCAT(t2.f_name, ' ', t2.l_name) as trainor2_name,
          t2.person_id as trainor2_person_id,
          CONCAT(t3.f_name, ' ', t3.l_name) as trainor3_name,
          t3.person_id as trainor3_person_id
        FROM tbl_sports_team st
        JOIN tbl_sports s ON s.sports_id = st.sports_id
        LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
        LEFT JOIN tbl_person asst ON asst.person_id = st.asst_coach_id
        LEFT JOIN tbl_person tm ON tm.person_id = st.tournament_manager_id
        LEFT JOIN tbl_person t1 ON t1.person_id = st.trainor1_id
        LEFT JOIN tbl_person t2 ON t2.person_id = st.trainor2_id
        LEFT JOIN tbl_person t3 ON t3.person_id = st.trainor3_id
        WHERE st.tour_id = ? AND st.team_id = ?
        ORDER BY s.sports_name
      ");
      $stmt->execute([$tour_id, $team['team_id']]);
      $team['sports'] = $stmt->fetchAll();
      
      // For each sport, get athletes
      foreach ($team['sports'] as &$sport) {
        $stmt = $pdo->prepare("
          SELECT 
            p.person_id,
            CONCAT(p.f_name, ' ', p.l_name) as athlete_name,
            p.f_name,
            p.l_name,
            p.m_name,
            p.college_code,
            p.course,
            p.date_birth,
            p.blood_type,
            ta.is_captain,
            ta.team_ath_id,
            COALESCE(vs.height, 0) as height,
            COALESCE(vs.weight, 0) as weight,
            ast.scholarship_name
          FROM tbl_team_athletes ta
          JOIN tbl_person p ON p.person_id = ta.person_id
          LEFT JOIN tbl_vital_signs vs ON vs.person_id = p.person_id
          LEFT JOIN tbl_ath_status ast ON ast.person_id = p.person_id
          WHERE ta.tour_id = ? 
            AND ta.team_id = ? 
            AND ta.sports_id = ? 
            AND ta.is_active = 1
          ORDER BY ta.is_captain DESC, p.l_name, p.f_name
        ");
        $stmt->execute([$tour_id, $team['team_id'], $sport['sports_id']]);
        $sport['athletes'] = $stmt->fetchAll();
      }
    }
    
    out([
      'ok' => true,
      'tournament' => $tournament,
      'teams' => $teams
    ]);
      
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STATISTICS
// ==========================================
if ($action === 'stats') {
  try {
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
    
    $tournamentsSql = "SELECT COUNT(*) as count FROM tbl_tournament WHERE is_active=1";
    $teamsSql = "SELECT COUNT(DISTINCT t.team_id) as count FROM tbl_team t WHERE t.is_active=1";
    $athletesSql = "SELECT COUNT(DISTINCT p.person_id) as count FROM tbl_person p WHERE p.role_type IN ('athlete','athlete/player') AND p.is_active=1";
    $matchesSql = "SELECT COUNT(*) as count FROM tbl_match m WHERE m.sked_date >= CURDATE()";
    
    if ($tour_id) {
      $teamsSql .= " AND EXISTS (SELECT 1 FROM tbl_tournament_teams tt WHERE tt.team_id=t.team_id AND tt.tour_id=$tour_id)";
      $matchesSql .= " AND m.tour_id=$tour_id";
    }
    
    if ($sport_id) {
      $teamsSql .= " AND EXISTS (SELECT 1 FROM tbl_sports_team st WHERE st.team_id=t.team_id AND st.sports_id=$sport_id)";
      $athletesSql .= " AND EXISTS (SELECT 1 FROM tbl_team_athletes ta WHERE ta.person_id=p.person_id AND ta.sports_id=$sport_id)";
      $matchesSql .= " AND m.sports_id=$sport_id";
    }
    
    $tournaments = $pdo->query($tournamentsSql)->fetch()['count'];
    $teams = $pdo->query($teamsSql)->fetch()['count'];
    $athletes = $pdo->query($athletesSql)->fetch()['count'];
    $matches = $pdo->query($matchesSql)->fetch()['count'];
    
    out([
      'tournaments' => $tournaments,
      'teams' => $teams,
      'athletes' => $athletes,
      'upcoming_matches' => $matches
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// COLLEGES
// ==========================================
if ($action === 'colleges') {
  try {
    $stmt = $pdo->query("SELECT college_code, college_name FROM tbl_college ORDER BY college_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SPORTS
// ==========================================
if ($action === 'sports') {
  try {
    $stmt = $pdo->query("SELECT * FROM tbl_sports WHERE is_active=1 ORDER BY sports_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// VENUES
// ==========================================
if ($action === 'venues') {
  try {
    $stmt = $pdo->query("SELECT venue_id, venue_name, venue_building, is_active FROM tbl_game_venue ORDER BY venue_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENTS
// ==========================================
if ($action === 'tournaments') {
  try {
    $sql = "SELECT t.*, 
            (SELECT COUNT(DISTINCT tt.team_id) FROM tbl_tournament_teams tt WHERE tt.tour_id=t.tour_id AND tt.is_active=1) as num_teams,
            (SELECT COUNT(DISTINCT st.sports_id) FROM tbl_sports_team st WHERE st.tour_id=t.tour_id) as num_sports,
            (SELECT COUNT(DISTINCT ta.person_id) FROM tbl_team_athletes ta WHERE ta.tour_id=t.tour_id AND ta.is_active=1) as num_athletes
            FROM tbl_tournament t 
            ORDER BY t.tour_date DESC, t.school_year DESC";
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_tournament') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_tournament (tour_name, school_year, tour_date, is_active) VALUES (?, ?, ?, 1)");
    $stmt->execute([$input['tour_name'], $input['school_year'], $input['tour_date']]);
    out(['ok' => true, 'tour_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_tournament') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_tournament SET tour_name=?, school_year=?, tour_date=? WHERE tour_id=?");
    $stmt->execute([$input['tour_name'], $input['school_year'], $input['tour_date'], $input['tour_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_tournament') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_tournament SET is_active=? WHERE tour_id=?");
    $stmt->execute([$input['is_active'], $input['tour_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENT TEAMS
// ==========================================
if ($action === 'get_tournament_teams') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    $sql = "SELECT tt.*, t.team_name, t.is_active as team_is_active,
            (SELECT COUNT(DISTINCT st.sports_id) FROM tbl_sports_team st WHERE st.team_id=t.team_id AND st.tour_id=tt.tour_id) as num_sports,
            (SELECT COUNT(DISTINCT ta.person_id) FROM tbl_team_athletes ta WHERE ta.team_id=t.team_id AND ta.tour_id=tt.tour_id AND ta.is_active=1) as num_athletes
            FROM tbl_tournament_teams tt
            JOIN tbl_team t ON t.team_id = tt.team_id
            WHERE tt.tour_id = ? AND tt.is_active=1
            ORDER BY t.team_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tour_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'add_team_to_tournament') {
  try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_tournament_teams WHERE tour_id=? AND team_id=?");
    $stmt->execute([$input['tour_id'], $input['team_id']]);
    $exists = $stmt->fetch()['cnt'] > 0;
    
    if ($exists) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Team already exists in this tournament']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO tbl_tournament_teams (tour_id, team_id, is_active) VALUES (?, ?, 1)");
    $stmt->execute([$input['tour_id'], $input['team_id']]);
    
    $pdo->commit();
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'remove_team_from_tournament') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_tournament_teams SET is_active=0 WHERE tour_id=? AND team_id=?");
    $stmt->execute([$input['tour_id'], $input['team_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAM SPORTS
// ==========================================
if ($action === 'get_team_sports') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    $team_id = (int)$_GET['team_id'];
    
    $sql = "SELECT st.*, s.sports_name,
            CONCAT(coach.f_name, ' ', coach.l_name) as coach_name,
            CONCAT(asst.f_name, ' ', asst.l_name) as asst_coach_name,
            CONCAT(tm.f_name, ' ', tm.l_name) as tournament_manager_name,
            (SELECT COUNT(*) FROM tbl_team_athletes ta WHERE ta.team_id=st.team_id AND ta.sports_id=st.sports_id AND ta.tour_id=st.tour_id AND ta.is_active=1) as num_athletes
            FROM tbl_sports_team st
            JOIN tbl_sports s ON s.sports_id = st.sports_id
            LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
            LEFT JOIN tbl_person asst ON asst.person_id = st.asst_coach_id
            LEFT JOIN tbl_person tm ON tm.person_id = st.tournament_manager_id
            WHERE st.tour_id = ? AND st.team_id = ?
            ORDER BY s.sports_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tour_id, $team_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'add_sport_to_team') {
  try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_sports_team WHERE tour_id=? AND team_id=? AND sports_id=?");
    $stmt->execute([$input['tour_id'], $input['team_id'], $input['sports_id']]);
    $exists = $stmt->fetch()['cnt'] > 0;
    
    if ($exists) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Sport already exists for this team in this tournament']);
    }
    
    $stmt = $pdo->prepare("INSERT INTO tbl_sports_team (tour_id, team_id, sports_id) VALUES (?, ?, ?)");
    $stmt->execute([$input['tour_id'], $input['team_id'], $input['sports_id']]);
    
    $pdo->commit();
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_sport_staff') {
  try {
    $fields = [];
    $values = [];
    
    if (isset($input['coach_id'])) {
      $fields[] = "coach_id = ?";
      $values[] = $input['coach_id'] ?: null;
    }
    if (isset($input['asst_coach_id'])) {
      $fields[] = "asst_coach_id = ?";
      $values[] = $input['asst_coach_id'] ?: null;
    }
    if (isset($input['tournament_manager_id'])) {
      $fields[] = "tournament_manager_id = ?";
      $values[] = $input['tournament_manager_id'] ?: null;
    }
    if (isset($input['trainor1_id'])) {
      $fields[] = "trainor1_id = ?";
      $values[] = $input['trainor1_id'] ?: null;
    }
    if (isset($input['trainor2_id'])) {
      $fields[] = "trainor2_id = ?";
      $values[] = $input['trainor2_id'] ?: null;
    }
    if (isset($input['trainor3_id'])) {
      $fields[] = "trainor3_id = ?";
      $values[] = $input['trainor3_id'] ?: null;
    }
    
    if (empty($fields)) {
      out(['ok' => false, 'error' => 'No fields to update']);
    }
    
    $values[] = $input['tour_id'];
    $values[] = $input['team_id'];
    $values[] = $input['sports_id'];
    
    $sql = "UPDATE tbl_sports_team SET " . implode(", ", $fields) . " WHERE tour_id=? AND team_id=? AND sports_id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'remove_sport_from_team') {
  try {
    $stmt = $pdo->prepare("DELETE FROM tbl_sports_team WHERE tour_id=? AND team_id=? AND sports_id=?");
    $stmt->execute([$input['tour_id'], $input['team_id'], $input['sports_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS
// ==========================================
// ==========================================
// TEAMS
// ==========================================
if ($action === 'teams') {
  try {
    $sql = "SELECT DISTINCT t.team_id, t.team_name, t.school_id, t.is_active,
            s.school_name
            FROM tbl_team t 
            LEFT JOIN tbl_school s ON s.school_id = t.school_id
            WHERE t.is_active=1
            ORDER BY t.team_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'schools') {
  try {
    $sql = "SELECT school_id, school_name, school_address, 
            school_head, school_sports_director
            FROM tbl_school 
            ORDER BY school_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_team') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_team (team_name, school_id, is_active) VALUES (?, ?, ?)");
    $stmt->execute([
      $input['team_name'], 
      $input['school_id'] ?? null,
      $input['is_active'] ?? 1
    ]);
    out(['ok' => true, 'team_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_team') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_team SET team_name=? WHERE team_id=?");
    $stmt->execute([$input['team_name'], $input['team_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_team') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_team SET is_active=? WHERE team_id=?");
    $stmt->execute([$input['is_active'], $input['team_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETES
// ==========================================
if ($action === 'get_sport_athletes') {
  try {
    $tour_id = (int)$_GET['tour_id'];
    $team_id = (int)$_GET['team_id'];
    $sports_id = (int)$_GET['sports_id'];
    
    $sql = "SELECT p.*, ta.is_captain, ta.team_ath_id,
            CONCAT(p.f_name, ' ', p.l_name) as full_name,
            COALESCE(vs.height, 0) as height,
            COALESCE(vs.weight, 0) as weight,
            ast.scholarship_name
            FROM tbl_team_athletes ta
            JOIN tbl_person p ON p.person_id = ta.person_id
            LEFT JOIN tbl_vital_signs vs ON vs.person_id = p.person_id
            LEFT JOIN tbl_ath_status ast ON ast.person_id = p.person_id
            WHERE ta.tour_id = ? AND ta.team_id = ? AND ta.sports_id = ? AND ta.is_active=1
            ORDER BY ta.is_captain DESC, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tour_id, $team_id, $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'athletes') {
  try {
    $sql = "SELECT DISTINCT p.person_id, p.f_name, p.l_name, p.m_name, p.college_code, p.course, p.is_active,
            CONCAT(p.f_name, ' ', p.l_name) as athlete_name
            FROM tbl_person p
            WHERE p.role_type IN ('athlete', 'athlete/player')";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND EXISTS (SELECT 1 FROM tbl_team_athletes ta WHERE ta.person_id = p.person_id AND ta.sports_id = " . (int)$_GET['sport_id'] . ")";
    }
    
    $sql .= " ORDER BY p.l_name, p.f_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'add_existing_athlete') {
  try {
    $pdo->beginTransaction();
    
    // Validate that the person exists and is an athlete
    $stmt = $pdo->prepare("SELECT person_id, role_type FROM tbl_person WHERE person_id = ?");
    $stmt->execute([$input['person_id']]);
    $person = $stmt->fetch();
    
    if (!$person) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Athlete not found']);
    }
    
    if (!in_array($person['role_type'], ['athlete', 'athlete/player', 'trainee'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Selected person is not an athlete']);
    }
    
    // Check if athlete is already added to this sport
    $stmt = $pdo->prepare("
      SELECT team_ath_id FROM tbl_team_athletes 
      WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND person_id = ? AND is_active = 1
    ");
    $stmt->execute([
      $input['tour_id'], 
      $input['team_id'], 
      $input['sports_id'], 
      $input['person_id']
    ]);
    
    if ($stmt->fetch()) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Athlete is already registered in this sport']);
    }
    
    // Add athlete to tbl_team_athletes
    $stmt = $pdo->prepare("
      INSERT INTO tbl_team_athletes (
        tour_id, team_id, sports_id, person_id, is_captain, is_active
      ) VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['tour_id'], 
      $input['team_id'], 
      $input['sports_id'],
      $input['person_id'],
      $input['is_captain'] ?? 0
    ]);
    
    $pdo->commit();
    out(['ok' => true, 'team_ath_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_athlete') {
  try {
    $pdo->beginTransaction();
    
    // Validate college_code exists
    if (!empty($input['college_code'])) {
      $stmt = $pdo->prepare("SELECT college_code FROM tbl_college WHERE college_code = ?");
      $stmt->execute([$input['college_code']]);
      if (!$stmt->fetch()) {
        $pdo->rollBack();
        out(['ok' => false, 'error' => 'Invalid college code. Please select a valid college from the list.']);
      }
    }
    
    // 1. Insert into tbl_person
    $stmt = $pdo->prepare("
      INSERT INTO tbl_person (
        l_name, f_name, m_name, title, date_birth,
        college_code, course, blood_type, role_type, is_active
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'athlete', 1)
    ");
    $stmt->execute([
      $input['l_name'], $input['f_name'], $input['m_name'],
      $input['title'], $input['date_birth'], $input['college_code'],
      $input['course'], $input['blood_type']
    ]);
    $person_id = $pdo->lastInsertId();
    
    // 2. Insert into tbl_team_athletes
    $stmt = $pdo->prepare("
      INSERT INTO tbl_team_athletes (
        tour_id, team_id, sports_id, person_id, is_captain, is_active
      ) VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['tour_id'], $input['team_id'], $input['sports_id'],
      $person_id, $input['is_captain'] ?? 0
    ]);
    
    // 3. Insert vital signs if provided
    if (!empty($input['height']) || !empty($input['weight'])) {
      $stmt = $pdo->prepare("
        INSERT INTO tbl_vital_signs (person_id, height, weight, date_taken) 
        VALUES (?, ?, ?, NOW())
      ");
      $stmt->execute([
        $person_id,
        $input['height'] ?? null,
        $input['weight'] ?? null
      ]);
    }
    
    // 4. Insert athlete status if provided
    if (!empty($input['scholarship_name'])) {
      // If semester is not provided but scholarship is, use a default
      $semester = $input['semester'] ?? '1st Semester'; // Default to 1st Semester
      
      $stmt = $pdo->prepare("
        INSERT INTO tbl_ath_status (person_id, scholarship_name, semester, school_year) 
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([
        $person_id,
        $input['scholarship_name'],
        $semester,
        $input['school_year'] ?? null
      ]);
    }
    
    $pdo->commit();
    out(['ok' => true, 'person_id' => $person_id]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_athlete') {
  try {
    $pdo->beginTransaction();
    
    // Update person
    $stmt = $pdo->prepare("UPDATE tbl_person SET f_name=?, l_name=?, m_name=?, title=?, date_birth=?, college_code=?, course=?, blood_type=? WHERE person_id=?");
    $stmt->execute([
      $input['f_name'], $input['l_name'], $input['m_name'],
      $input['title'], $input['date_birth'], $input['college_code'],
      $input['course'], $input['blood_type'], $input['person_id']
    ]);
    
    // Update captain status
    if (isset($input['is_captain']) && isset($input['team_ath_id'])) {
      $stmt = $pdo->prepare("UPDATE tbl_team_athletes SET is_captain=? WHERE team_ath_id=?");
      $stmt->execute([$input['is_captain'], $input['team_ath_id']]);
    }
    
    // Update vital signs
    if (isset($input['height']) || isset($input['weight'])) {
      $stmt = $pdo->prepare("
        INSERT INTO tbl_vital_signs (person_id, height, weight, date_taken) 
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE height=?, weight=?, date_taken=NOW()
      ");
      $stmt->execute([
        $input['person_id'],
        $input['height'] ?? null,
        $input['weight'] ?? null,
        $input['height'] ?? null,
        $input['weight'] ?? null
      ]);
    }
    
    $pdo->commit();
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_athlete') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_person SET is_active=? WHERE person_id=?");
    $stmt->execute([$input['is_active'], $input['person_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'remove_athlete_from_sport') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_team_athletes SET is_active=0 WHERE team_ath_id=?");
    $stmt->execute([$input['team_ath_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STAFF
// ==========================================
if ($action === 'staff') {
  try {
    $role = $_GET['role'] ?? '';
    $stmt = $pdo->prepare("
      SELECT person_id, CONCAT(f_name, ' ', l_name) as full_name, role_type
      FROM tbl_person 
      WHERE role_type = ? AND is_active=1
      ORDER BY l_name, f_name
    ");
    $stmt->execute([$role]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MATCHES, TRAINING, STANDINGS (keep existing)
// ==========================================
if ($action === 'matches') {
  try {
    $sql = "SELECT m.*, s.sports_name, tour.tour_name, tour.school_year,
            ta.team_name as team_a_name, tb.team_name as team_b_name,
            v.venue_name
            FROM tbl_match m
            JOIN tbl_sports s ON s.sports_id = m.sports_id
            LEFT JOIN tbl_tournament tour ON tour.tour_id = m.tour_id
            LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
            LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
            LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
            WHERE 1=1";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND m.sports_id = " . (int)$_GET['sport_id'];
    }
    
    if (isset($_GET['tour_id']) && $_GET['tour_id']) {
      $sql .= " AND m.tour_id = " . (int)$_GET['tour_id'];
    }
    
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'training') {
  try {
    $sql = "SELECT ts.*, t.team_name, v.venue_name
            FROM tbl_train_sked ts
            JOIN tbl_team t ON t.team_id = ts.team_id
            LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
            WHERE 1=1";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND EXISTS (SELECT 1 FROM tbl_sports_team st WHERE st.team_id = ts.team_id AND st.sports_id = " . (int)$_GET['sport_id'] . ")";
    }
    
    $sql .= " ORDER BY ts.sked_date DESC, ts.sked_time DESC";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'standings') {
  try {
    $sql = "SELECT ts.*, t.team_name, s.sports_name
            FROM tbl_team_standing ts
            JOIN tbl_team t ON t.team_id = ts.team_id
            JOIN tbl_sports s ON s.sports_id = ts.sports_id
            WHERE 1=1";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND ts.sports_id = " . (int)$_GET['sport_id'];
    }
    
    if (isset($_GET['tour_id']) && $_GET['tour_id']) {
      $sql .= " AND ts.tour_id = " . (int)$_GET['tour_id'];
    }
    
    $sql .= " ORDER BY s.sports_name, ts.no_win DESC, ts.no_gold DESC";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'recent_activity') {
  try {
    $activities = [];
    
    $matches = $pdo->query("SELECT CONCAT(ta.team_name, ' vs ', tb.team_name) as title, 'Match' as type, CONCAT('Scheduled for ', sked_date) as description FROM tbl_match m LEFT JOIN tbl_team ta ON ta.team_id=m.team_a_id LEFT JOIN tbl_team tb ON tb.team_id=m.team_b_id ORDER BY m.match_id DESC LIMIT 3")->fetchAll();
    foreach($matches as $m) $activities[] = $m;
    
    $training = $pdo->query("SELECT CONCAT(t.team_name, ' Training') as title, 'Training' as type, CONCAT('Scheduled for ', ts.sked_date) as description FROM tbl_train_sked ts JOIN tbl_team t ON t.team_id=ts.team_id WHERE ts.is_active=1 ORDER BY ts.sked_id DESC LIMIT 3")->fetchAll();
    foreach($training as $tr) $activities[] = $tr;
    
    out($activities);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);