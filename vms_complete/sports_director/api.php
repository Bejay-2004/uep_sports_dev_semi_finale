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
if ($action === 'get_colleges') {
  try {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM tbl_department d WHERE d.college_id = c.college_id AND d.is_active = 1) as dept_count,
            (SELECT COUNT(DISTINCT p.person_id) 
             FROM tbl_person p 
             WHERE p.college_code = c.college_code 
             AND p.role_type IN ('athlete', 'athlete/player') 
             AND p.is_active = 1) as student_count
            FROM tbl_college c 
            ORDER BY c.college_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_college') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_college (college_code, college_name, college_dean, description, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([
      strtoupper($input['college_code']),
      $input['college_name'],
      $input['college_dean'] ?? null,
      $input['description'] ?? null
    ]);
    out(['ok' => true, 'college_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_college') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_college SET college_code=?, college_name=?, college_dean=?, description=? WHERE college_id=?");
    $stmt->execute([
      strtoupper($input['college_code']),
      $input['college_name'],
      $input['college_dean'] ?? null,
      $input['description'] ?? null,
      $input['college_id']
    ]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_college') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_college SET is_active=? WHERE college_id=?");
    $stmt->execute([$input['is_active'], $input['college_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// DEPARTMENTS
// ==========================================
if ($action === 'get_departments') {
  try {
    $sql = "SELECT d.*, c.college_name, c.college_code,
            (SELECT COUNT(*) FROM tbl_course co WHERE co.dept_id = d.dept_id) as course_count
            FROM tbl_department d
            LEFT JOIN tbl_college c ON c.college_id = d.college_id
            ORDER BY c.college_name, d.dept_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_department') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_department (dept_code, dept_name, college_id, dept_head, description, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([
      strtoupper($input['dept_code']),
      $input['dept_name'],
      $input['college_id'],
      $input['dept_head'] ?? null,
      $input['description'] ?? null
    ]);
    out(['ok' => true, 'dept_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_department') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_department SET dept_code=?, dept_name=?, college_id=?, dept_head=?, description=? WHERE dept_id=?");
    $stmt->execute([
      strtoupper($input['dept_code']),
      $input['dept_name'],
      $input['college_id'],
      $input['dept_head'] ?? null,
      $input['description'] ?? null,
      $input['dept_id']
    ]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_department') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_department SET is_active=? WHERE dept_id=?");
    $stmt->execute([$input['is_active'], $input['dept_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// COURSES
// ==========================================
if ($action === 'get_courses') {
  try {
    $sql = "SELECT co.*, d.dept_name, d.dept_code, c.college_name, c.college_code
            FROM tbl_course co
            JOIN tbl_department d ON d.dept_id = co.dept_id
            JOIN tbl_college c ON c.college_id = d.college_id
            ORDER BY c.college_name, d.dept_name, co.course_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_course') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_course (course_code, course_name, dept_id, course_type, num_years, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      strtoupper($input['course_code']),
      $input['course_name'],
      $input['dept_id'],
      $input['course_type'] ?? null,
      $input['num_years'] ?? null,
      $input['description'] ?? null
    ]);
    out(['ok' => true, 'course_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_course') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_course SET course_code=?, course_name=?, dept_id=?, course_type=?, num_years=?, description=? WHERE course_id=?");
    $stmt->execute([
      strtoupper($input['course_code']),
      $input['course_name'],
      $input['dept_id'],
      $input['course_type'] ?? null,
      $input['num_years'] ?? null,
      $input['description'] ?? null,
      $input['course_id']
    ]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_course') {
  try {
    // Check if course is being used by any students
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_person WHERE course = (SELECT course_code FROM tbl_course WHERE course_id = ?)");
    $stmt->execute([$input['course_id']]);
    $count = $stmt->fetch()['cnt'];
    
    if ($count > 0) {
      out(['ok' => false, 'error' => "Cannot delete course. It is currently assigned to {$count} student(s)."]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM tbl_course WHERE course_id=?");
    $stmt->execute([$input['course_id']]);
    out(['ok' => true]);
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
if ($action === 'get_venues') {
  try {
    $sql = "SELECT v.*,
            (SELECT COUNT(*) FROM tbl_match m WHERE m.venue_id = v.venue_id) as match_count,
            (SELECT COUNT(*) FROM tbl_train_sked ts WHERE ts.venue_id = v.venue_id) as training_count
            FROM tbl_game_venue v
            ORDER BY v.venue_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_venue') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_game_venue (venue_name, venue_building, venue_room, venue_description, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([
      $input['venue_name'],
      $input['venue_building'] ?? null,
      $input['venue_room'] ?? null,
      $input['venue_description'] ?? null
    ]);
    out(['ok' => true, 'venue_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_venue') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_game_venue SET venue_name=?, venue_building=?, venue_room=?, venue_description=? WHERE venue_id=?");
    $stmt->execute([
      $input['venue_name'],
      $input['venue_building'] ?? null,
      $input['venue_room'] ?? null,
      $input['venue_description'] ?? null,
      $input['venue_id']
    ]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_venue') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_game_venue SET is_active=? WHERE venue_id=?");
    $stmt->execute([$input['is_active'], $input['venue_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_venue') {
  try {
    // Check if venue is being used
    $stmt = $pdo->prepare("SELECT 
      (SELECT COUNT(*) FROM tbl_match WHERE venue_id = ?) as match_count,
      (SELECT COUNT(*) FROM tbl_train_sked WHERE venue_id = ?) as training_count
    ");
    $stmt->execute([$input['venue_id'], $input['venue_id']]);
    $usage = $stmt->fetch();
    
    if ($usage['match_count'] > 0 || $usage['training_count'] > 0) {
      out(['ok' => false, 'error' => "Cannot delete venue. It is being used in {$usage['match_count']} match(es) and {$usage['training_count']} training session(s)."]);
    }
    
    $stmt = $pdo->prepare("DELETE FROM tbl_game_venue WHERE venue_id=?");
    $stmt->execute([$input['venue_id']]);
    out(['ok' => true]);
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
// EQUIPMENT MANAGEMENT
// ==========================================

if ($action === 'get_equipment') {
  try {
    $sql = "SELECT e.*, 
            (SELECT SUM(CASE WHEN ei.trans_type = 'in' THEN ei.quantity ELSE -ei.quantity END)
             FROM tbl_equip_inventory ei 
             WHERE ei.equip_id = e.equip_id) as current_stock,
            (SELECT COUNT(*) FROM tbl_equip_inventory ei WHERE ei.equip_id = e.equip_id) as transaction_count
            FROM tbl_team_equipment e
            ORDER BY e.date_acquired DESC, e.equip_name";
    
    $stmt = $pdo->query($sql);
    $equipment = $stmt->fetchAll();
    
    // Add image URLs
    foreach ($equipment as &$item) {
      $item['image_url'] = $item['equip_image'] ? BASE_URL . '/uploads/equipment/' . $item['equip_image'] : null;
      $item['current_stock'] = $item['current_stock'] ?? $item['quantity'];
    }
    
    out($equipment);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'get_equipment_detail') {
  try {
    $equip_id = (int)$_GET['equip_id'];
    
    // Get equipment details
    $stmt = $pdo->prepare("SELECT e.*, 
            (SELECT SUM(CASE WHEN ei.trans_type = 'in' THEN ei.quantity ELSE -ei.quantity END)
             FROM tbl_equip_inventory ei 
             WHERE ei.equip_id = e.equip_id) as current_stock
            FROM tbl_team_equipment e
            WHERE e.equip_id = ?");
    $stmt->execute([$equip_id]);
    $equipment = $stmt->fetch();
    
    if (!$equipment) {
      out(['ok' => false, 'error' => 'Equipment not found']);
    }
    
    $equipment['image_url'] = $equipment['equip_image'] ? BASE_URL . '/uploads/equipment/' . $equipment['equip_image'] : null;
    $equipment['current_stock'] = $equipment['current_stock'] ?? $equipment['quantity'];
    
    // Get transaction history
    $stmt = $pdo->prepare("SELECT ei.*, 
            CONCAT(p.f_name, ' ', p.l_name) as trans_by_name
            FROM tbl_equip_inventory ei
            LEFT JOIN tbl_person p ON p.person_id = ei.trans_by
            WHERE ei.equip_id = ?
            ORDER BY ei.transdate DESC, ei.inv_id DESC");
    $stmt->execute([$equip_id]);
    $transactions = $stmt->fetchAll();
    
    out([
      'ok' => true,
      'equipment' => $equipment,
      'transactions' => $transactions
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_equipment') {
  try {
    $pdo->beginTransaction();
    
    // Handle file upload
    $imageName = null;
    if (isset($_FILES['equip_image']) && $_FILES['equip_image']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../uploads/equipment/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
      
      $fileExt = strtolower(pathinfo($_FILES['equip_image']['name'], PATHINFO_EXTENSION));
      $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      
      if (in_array($fileExt, $allowedExts)) {
        $imageName = 'equip_' . time() . '_' . uniqid() . '.' . $fileExt;
        move_uploaded_file($_FILES['equip_image']['tmp_name'], $uploadDir . $imageName);
      }
    }
    
    // Get form data
    $data = $_POST;
    
    // Insert equipment
    $stmt = $pdo->prepare("INSERT INTO tbl_team_equipment 
      (equip_name, date_acquired, description, is_functional, quantity, equip_image) 
      VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $data['equip_name'],
      $data['date_acquired'],
      $data['description'] ?? null,
      $data['is_functional'] ?? 1,
      $data['quantity'] ?? 0,
      $imageName
    ]);
    
    $equip_id = $pdo->lastInsertId();
    
    // Create initial inventory transaction if quantity > 0
    if (!empty($data['quantity']) && $data['quantity'] > 0) {
      $stmt = $pdo->prepare("INSERT INTO tbl_equip_inventory 
        (equip_id, trans_type, transdate, trans_by, rec_rel_by, equip_cond, quantity) 
        VALUES (?, 'in', NOW(), ?, ?, ?, ?)");
      $stmt->execute([
        $equip_id,
        $user_id,
        $data['received_by'] ?? null,
        $data['condition'] ?? 'Good',
        $data['quantity']
      ]);
    }
    
    $pdo->commit();
    out(['ok' => true, 'equip_id' => $equip_id, 'image_name' => $imageName]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_equipment') {
  try {
    $pdo->beginTransaction();
    
    $data = $_POST;
    $equip_id = (int)$data['equip_id'];
    
    // Handle file upload for update
    $imageName = $data['current_image'] ?? null;
    if (isset($_FILES['equip_image']) && $_FILES['equip_image']['error'] === UPLOAD_ERR_OK) {
      $uploadDir = __DIR__ . '/../uploads/equipment/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
      
      $fileExt = strtolower(pathinfo($_FILES['equip_image']['name'], PATHINFO_EXTENSION));
      $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      
      if (in_array($fileExt, $allowedExts)) {
        // Delete old image if exists
        if ($imageName && file_exists($uploadDir . $imageName)) {
          unlink($uploadDir . $imageName);
        }
        
        $imageName = 'equip_' . time() . '_' . uniqid() . '.' . $fileExt;
        move_uploaded_file($_FILES['equip_image']['tmp_name'], $uploadDir . $imageName);
      }
    }
    
    // Update equipment
    $stmt = $pdo->prepare("UPDATE tbl_team_equipment 
      SET equip_name = ?, date_acquired = ?, description = ?, is_functional = ?, equip_image = ?
      WHERE equip_id = ?");
    $stmt->execute([
      $data['equip_name'],
      $data['date_acquired'],
      $data['description'] ?? null,
      $data['is_functional'] ?? 1,
      $imageName,
      $equip_id
    ]);
    
    $pdo->commit();
    out(['ok' => true, 'image_name' => $imageName]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_equipment') {
  try {
    $pdo->beginTransaction();
    
    $equip_id = (int)$input['equip_id'];
    
    // Check if equipment has transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM tbl_equip_inventory WHERE equip_id = ?");
    $stmt->execute([$equip_id]);
    $count = $stmt->fetch()['cnt'];
    
    if ($count > 0) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => "Cannot delete equipment with existing transactions. Archive it instead."]);
    }
    
    // Get image name to delete file
    $stmt = $pdo->prepare("SELECT equip_image FROM tbl_team_equipment WHERE equip_id = ?");
    $stmt->execute([$equip_id]);
    $image = $stmt->fetch()['equip_image'] ?? null;
    
    // Delete equipment
    $stmt = $pdo->prepare("DELETE FROM tbl_team_equipment WHERE equip_id = ?");
    $stmt->execute([$equip_id]);
    
    // Delete image file if exists
    if ($image) {
      $imagePath = __DIR__ . '/../uploads/equipment/' . $image;
      if (file_exists($imagePath)) {
        unlink($imagePath);
      }
    }
    
    $pdo->commit();
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_equipment_status') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_team_equipment SET is_functional = ? WHERE equip_id = ?");
    $stmt->execute([$input['is_functional'], $input['equip_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// EQUIPMENT INVENTORY TRANSACTIONS
// ==========================================

if ($action === 'add_inventory_transaction') {
  try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO tbl_equip_inventory 
      (equip_id, trans_type, transdate, trans_by, rec_rel_by, equip_cond, quantity) 
      VALUES (?, ?, NOW(), ?, ?, ?, ?)");
    $stmt->execute([
      $input['equip_id'],
      $input['trans_type'],
      $user_id,
      $input['rec_rel_by'] ?? null,
      $input['equip_cond'] ?? 'Good',
      $input['quantity'] ?? 1
    ]);
    
    $pdo->commit();
    out(['ok' => true, 'inv_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'get_equipment_stats') {
  try {
    $stmt = $pdo->query("SELECT 
      COUNT(*) as total_items,
      SUM(CASE WHEN is_functional = 1 THEN 1 ELSE 0 END) as functional_items,
      SUM(CASE WHEN is_functional = 0 THEN 1 ELSE 0 END) as non_functional_items,
      (SELECT COUNT(*) FROM tbl_equip_inventory WHERE trans_type = 'in' AND DATE(transdate) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as recent_acquisitions,
      (SELECT COUNT(*) FROM tbl_equip_inventory WHERE trans_type = 'out' AND DATE(transdate) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as recent_releases
      FROM tbl_team_equipment");
    
    out($stmt->fetch());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STAFF/PERSONNEL
// ==========================================
// Replace the staff action in api.php with this:

if ($action === 'staff') {
  try {
    $role = $_GET['role'] ?? '';
    
    $sql = "SELECT DISTINCT p.person_id, 
            CONCAT(p.f_name, ' ', p.l_name) as full_name,
            p.role_type
            FROM tbl_person p
            WHERE p.is_active = 1";
    
    if ($role === 'coach') {
      $sql .= " AND p.role_type IN ('coach', 'head coach', 'assistant coach')";
    } elseif ($role === 'tournament_manager' || $role === 'tournament_manager') {
      // Accept both formats for backwards compatibility
      $sql .= " AND p.role_type IN ('Tournament manager', 'tournament_manager', 'manager')";
    } elseif ($role === 'trainor') {
      $sql .= " AND p.role_type IN ('trainor', 'trainer')";
    }
    
    $sql .= " ORDER BY p.l_name, p.f_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETE VITAL SIGNS
// Add this to api.php
// ==========================================

if ($action === 'get_athlete_vitals') {
  try {
    $person_id = (int)$_GET['person_id'];
    
    $sql = "SELECT vs.*, 
            CONCAT(p.f_name, ' ', p.l_name) as athlete_name
            FROM tbl_vital_signs vs
            JOIN tbl_person p ON p.person_id = vs.person_id
            WHERE vs.person_id = ?
            ORDER BY vs.date_taken DESC, vs.vital_id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$person_id]);
    $vitals = $stmt->fetchAll();
    
    out($vitals);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETE COMPREHENSIVE PROFILE
// Get all athlete data in one call
// ==========================================

if ($action === 'get_athlete_profile') {
  try {
    $person_id = (int)$_GET['person_id'];
    
    // Get basic athlete info
    $stmt = $pdo->prepare("
      SELECT p.*, 
      CONCAT(p.f_name, ' ', p.l_name) as full_name,
      c.college_name
      FROM tbl_person p
      LEFT JOIN tbl_college c ON c.college_code = p.college_code
      WHERE p.person_id = ?
    ");
    $stmt->execute([$person_id]);
    $athlete = $stmt->fetch();
    
    if (!$athlete) {
      out(['ok' => false, 'error' => 'Athlete not found']);
    }
    
    // Get vital signs
    $stmt = $pdo->prepare("
      SELECT * FROM tbl_vital_signs 
      WHERE person_id = ? 
      ORDER BY date_taken DESC
    ");
    $stmt->execute([$person_id]);
    $vitals = $stmt->fetchAll();
    
    // Get scholarship info
    $stmt = $pdo->prepare("
      SELECT * FROM tbl_ath_status 
      WHERE person_id = ? 
      ORDER BY status_id DESC 
      LIMIT 1
    ");
    $stmt->execute([$person_id]);
    $scholarship = $stmt->fetch();
    
    // Get tournament history
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        t.tour_id,
        t.tour_name,
        t.school_year,
        t.tour_date,
        tm.team_id,
        tm.team_name,
        ta.is_captain,
        s.sports_id,
        s.sports_name
      FROM tbl_team_athletes ta
      JOIN tbl_tournament t ON t.tour_id = ta.tour_id
      JOIN tbl_team tm ON tm.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      WHERE ta.person_id = ? AND ta.is_active = 1
      ORDER BY t.tour_date DESC, t.school_year DESC
    ");
    $stmt->execute([$person_id]);
    $history = $stmt->fetchAll();
    
    // Group history by tournament
    $grouped_history = [];
    foreach ($history as $record) {
      $key = $record['tour_id'] . '_' . $record['team_id'];
      if (!isset($grouped_history[$key])) {
        $grouped_history[$key] = [
          'tournament_id' => $record['tour_id'],
          'tournament_name' => $record['tour_name'],
          'school_year' => $record['school_year'],
          'tour_date' => $record['tour_date'],
          'team_id' => $record['team_id'],
          'team_name' => $record['team_name'],
          'is_captain' => $record['is_captain'],
          'sports' => []
        ];
      }
      $grouped_history[$key]['sports'][] = [
        'sports_id' => $record['sports_id'],
        'sports_name' => $record['sports_name']
      ];
    }
    
    out([
      'ok' => true,
      'athlete' => $athlete,
      'vitals' => $vitals,
      'scholarship' => $scholarship,
      'history' => array_values($grouped_history)
    ]);
    
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}


// ==========================================
// COACH-BASED ATHLETE AUTO-ASSIGNMENT
// Add these endpoints to api.php
// ==========================================

// Get athletes coached by a specific person in a specific sport
if ($action === 'get_coach_athletes') {
  try {
    $coach_id = (int)$_GET['coach_id'];
    $sports_id = isset($_GET['sports_id']) ? (int)$_GET['sports_id'] : null;
    
    // Build query to find athletes coached by this person
    $sql = "SELECT DISTINCT 
            p.person_id,
            CONCAT(p.f_name, ' ', p.l_name) as athlete_name,
            p.f_name,
            p.l_name,
            p.m_name,
            p.college_code,
            p.course,
            p.date_birth,
            p.blood_type,
            COALESCE(vs.height, 0) as height,
            COALESCE(vs.weight, 0) as weight,
            ast.scholarship_name,
            s.sports_name,
            st.sports_id
            FROM tbl_sports_team st
            JOIN tbl_team_athletes ta ON ta.tour_id = st.tour_id 
                AND ta.team_id = st.team_id 
                AND ta.sports_id = st.sports_id
            JOIN tbl_person p ON p.person_id = ta.person_id
            JOIN tbl_sports s ON s.sports_id = st.sports_id
            LEFT JOIN tbl_vital_signs vs ON vs.person_id = p.person_id
            LEFT JOIN tbl_ath_status ast ON ast.person_id = p.person_id
            WHERE (st.coach_id = ? OR st.asst_coach_id = ?)
            AND ta.is_active = 1
            AND p.is_active = 1";
    
    $params = [$coach_id, $coach_id];
    
    if ($sports_id) {
      $sql .= " AND st.sports_id = ?";
      $params[] = $sports_id;
    }
    
    $sql .= " ORDER BY s.sports_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $athletes = $stmt->fetchAll();
    
    out([
      'ok' => true,
      'athletes' => $athletes,
      'count' => count($athletes)
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Auto-assign athletes when coach is assigned
if ($action === 'assign_staff_with_athletes') {
  try {
    $pdo->beginTransaction();
    
    $tour_id = (int)$input['tour_id'];
    $team_id = (int)$input['team_id'];
    $sports_id = (int)$input['sports_id'];
    $coach_id = !empty($input['coach_id']) ? (int)$input['coach_id'] : null;
    $asst_coach_id = !empty($input['asst_coach_id']) ? (int)$input['asst_coach_id'] : null;
    $auto_assign_athletes = isset($input['auto_assign_athletes']) ? (bool)$input['auto_assign_athletes'] : false;
    
    // Update staff assignments
    $stmt = $pdo->prepare("
      UPDATE tbl_sports_team 
      SET coach_id = ?, 
          asst_coach_id = ?,
          tournament_manager_id = ?,
          trainor1_id = ?,
          trainor2_id = ?,
          trainor3_id = ?
      WHERE tour_id = ? AND team_id = ? AND sports_id = ?
    ");
    
    $stmt->execute([
      $coach_id,
      $asst_coach_id,
      !empty($input['tournament_manager_id']) ? (int)$input['tournament_manager_id'] : null,
      !empty($input['trainor1_id']) ? (int)$input['trainor1_id'] : null,
      !empty($input['trainor2_id']) ? (int)$input['trainor2_id'] : null,
      !empty($input['trainor3_id']) ? (int)$input['trainor3_id'] : null,
      $tour_id,
      $team_id,
      $sports_id
    ]);
    
    $athletes_added = 0;
    $athletes_skipped = 0;
    $added_athletes = [];
    
    // If auto-assign is enabled and we have a coach
    if ($auto_assign_athletes && $coach_id) {
      // Find all athletes this coach has coached in this sport (from other tournaments)
      $stmt = $pdo->prepare("
        SELECT DISTINCT ta.person_id, CONCAT(p.f_name, ' ', p.l_name) as athlete_name
        FROM tbl_sports_team st
        JOIN tbl_team_athletes ta ON ta.tour_id = st.tour_id 
            AND ta.team_id = st.team_id 
            AND ta.sports_id = st.sports_id
        JOIN tbl_person p ON p.person_id = ta.person_id
        WHERE (st.coach_id = ? OR st.asst_coach_id = ?)
        AND st.sports_id = ?
        AND ta.is_active = 1
        AND p.is_active = 1
        AND p.role_type IN ('athlete', 'athlete/player', 'trainee')
      ");
      
      $stmt->execute([$coach_id, $coach_id, $sports_id]);
      $potential_athletes = $stmt->fetchAll();
      
      foreach ($potential_athletes as $athlete) {
        $person_id = $athlete['person_id'];
        
        // Check if athlete is already in this team/sport/tournament
        $stmt = $pdo->prepare("
          SELECT team_ath_id, is_active 
          FROM tbl_team_athletes 
          WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND person_id = ?
        ");
        $stmt->execute([$tour_id, $team_id, $sports_id, $person_id]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['is_active'] == 1) {
          // Already active, skip
          $athletes_skipped++;
          continue;
        } elseif ($existing && $existing['is_active'] == 0) {
          // Reactivate
          $stmt = $pdo->prepare("
            UPDATE tbl_team_athletes 
            SET is_active = 1 
            WHERE team_ath_id = ?
          ");
          $stmt->execute([$existing['team_ath_id']]);
          $athletes_added++;
          $added_athletes[] = $athlete['athlete_name'];
        } else {
          // Add new
          $stmt = $pdo->prepare("
            INSERT INTO tbl_team_athletes 
            (tour_id, team_id, sports_id, person_id, is_captain, is_active) 
            VALUES (?, ?, ?, ?, 0, 1)
          ");
          $stmt->execute([$tour_id, $team_id, $sports_id, $person_id]);
          $athletes_added++;
          $added_athletes[] = $athlete['athlete_name'];
        }
      }
    }
    
    $pdo->commit();
    
    out([
      'ok' => true,
      'message' => 'Staff assigned successfully',
      'athletes_added' => $athletes_added,
      'athletes_skipped' => $athletes_skipped,
      'added_athlete_names' => $added_athletes
    ]);
    
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Get coach's previous athletes for preview
if ($action === 'preview_coach_athletes') {
  try {
    $coach_id = (int)$_GET['coach_id'];
    $sports_id = (int)$_GET['sports_id'];
    $tour_id = (int)$_GET['tour_id'];
    $team_id = (int)$_GET['team_id'];
    
    // Find athletes this coach has coached in this sport
    $stmt = $pdo->prepare("
      SELECT DISTINCT 
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as athlete_name,
        p.college_code,
        p.course,
        COUNT(DISTINCT ta.tour_id) as tournaments_together,
        MAX(t.school_year) as last_together,
        -- Check if already in current tournament
        EXISTS(
          SELECT 1 FROM tbl_team_athletes ta2 
          WHERE ta2.person_id = p.person_id 
          AND ta2.tour_id = ?
          AND ta2.team_id = ?
          AND ta2.sports_id = ?
          AND ta2.is_active = 1
        ) as already_added
      FROM tbl_sports_team st
      JOIN tbl_team_athletes ta ON ta.tour_id = st.tour_id 
          AND ta.team_id = st.team_id 
          AND ta.sports_id = st.sports_id
      JOIN tbl_person p ON p.person_id = ta.person_id
      JOIN tbl_tournament t ON t.tour_id = st.tour_id
      WHERE (st.coach_id = ? OR st.asst_coach_id = ?)
      AND st.sports_id = ?
      AND ta.is_active = 1
      AND p.is_active = 1
      AND p.role_type IN ('athlete', 'athlete/player', 'trainee')
      GROUP BY p.person_id
      ORDER BY tournaments_together DESC, p.l_name, p.f_name
    ");
    
    $stmt->execute([$tour_id, $team_id, $sports_id, $coach_id, $coach_id, $sports_id]);
    $athletes = $stmt->fetchAll();
    
    out([
      'ok' => true,
      'athletes' => $athletes,
      'total' => count($athletes),
      'new_athletes' => count(array_filter($athletes, fn($a) => !$a['already_added']))
    ]);
    
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Bulk import athletes from another tournament
if ($action === 'import_athletes_from_tournament') {
  try {
    $pdo->beginTransaction();
    
    $source_tour_id = (int)$input['source_tour_id'];
    $source_team_id = (int)$input['source_team_id'];
    $source_sports_id = (int)$input['source_sports_id'];
    $target_tour_id = (int)$input['target_tour_id'];
    $target_team_id = (int)$input['target_team_id'];
    $target_sports_id = (int)$input['target_sports_id'];
    
    // Get athletes from source
    $stmt = $pdo->prepare("
      SELECT person_id, is_captain 
      FROM tbl_team_athletes 
      WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND is_active = 1
    ");
    $stmt->execute([$source_tour_id, $source_team_id, $source_sports_id]);
    $source_athletes = $stmt->fetchAll();
    
    $added = 0;
    $skipped = 0;
    
    foreach ($source_athletes as $athlete) {
      // Check if already exists
      $stmt = $pdo->prepare("
        SELECT team_ath_id, is_active 
        FROM tbl_team_athletes 
        WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND person_id = ?
      ");
      $stmt->execute([$target_tour_id, $target_team_id, $target_sports_id, $athlete['person_id']]);
      $existing = $stmt->fetch();
      
      if ($existing && $existing['is_active'] == 1) {
        $skipped++;
        continue;
      } elseif ($existing) {
        // Reactivate
        $stmt = $pdo->prepare("UPDATE tbl_team_athletes SET is_active = 1 WHERE team_ath_id = ?");
        $stmt->execute([$existing['team_ath_id']]);
        $added++;
      } else {
        // Add new
        $stmt = $pdo->prepare("
          INSERT INTO tbl_team_athletes 
          (tour_id, team_id, sports_id, person_id, is_captain, is_active) 
          VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
          $target_tour_id, 
          $target_team_id, 
          $target_sports_id, 
          $athlete['person_id'],
          $athlete['is_captain']
        ]);
        $added++;
      }
    }
    
    $pdo->commit();
    
    out([
      'ok' => true,
      'athletes_added' => $added,
      'athletes_skipped' => $skipped,
      'message' => "Imported {$added} athlete(s)"
    ]);
    
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

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
// ==========================================
// COMPLETE ATHLETES FIX - Replace in api.php
// ==========================================

if ($action === 'add_existing_athlete') {
  try {
    $pdo->beginTransaction();
    
    // Validate required parameters
    if (empty($input['person_id']) || empty($input['tour_id']) || empty($input['team_id']) || empty($input['sports_id'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Missing required parameters']);
    }
    
    // Validate that the person exists and is an athlete
    $stmt = $pdo->prepare("SELECT person_id, role_type FROM tbl_person WHERE person_id = ? AND is_active = 1");
    $stmt->execute([$input['person_id']]);
    $person = $stmt->fetch();
    
    if (!$person) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Athlete not found or inactive']);
    }
    
    if (!in_array(strtolower($person['role_type']), ['athlete', 'athlete/player', 'trainee'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Selected person is not an athlete. Role: ' . $person['role_type']]);
    }
    
    // Check if athlete is already added to this sport in this tournament
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
      out(['ok' => false, 'error' => 'Athlete is already registered in this sport for this tournament']);
    }
    
    // If athlete was previously removed, reactivate them
    $stmt = $pdo->prepare("
      SELECT team_ath_id FROM tbl_team_athletes 
      WHERE tour_id = ? AND team_id = ? AND sports_id = ? AND person_id = ? AND is_active = 0
    ");
    $stmt->execute([
      $input['tour_id'], 
      $input['team_id'], 
      $input['sports_id'], 
      $input['person_id']
    ]);
    
    $existing = $stmt->fetch();
    
    if ($existing) {
      // Reactivate existing record
      $stmt = $pdo->prepare("
        UPDATE tbl_team_athletes 
        SET is_active = 1, is_captain = ?
        WHERE team_ath_id = ?
      ");
      $stmt->execute([
        $input['is_captain'] ?? 0,
        $existing['team_ath_id']
      ]);
      $team_ath_id = $existing['team_ath_id'];
    } else {
      // Add new athlete to tbl_team_athletes
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
      $team_ath_id = $pdo->lastInsertId();
    }
    
    $pdo->commit();
    out(['ok' => true, 'team_ath_id' => $team_ath_id, 'message' => 'Athlete added successfully']);
  } catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error adding existing athlete: " . $e->getMessage());
    out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
  }
}

if ($action === 'create_athlete') {
  try {
    $pdo->beginTransaction();
    
    // Validate required parameters
    if (empty($input['tour_id']) || empty($input['team_id']) || empty($input['sports_id'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Missing tournament, team, or sport information']);
    }
    
    if (empty($input['l_name']) || empty($input['f_name'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Last name and first name are required']);
    }
    
    // Get tournament school year
    $stmt = $pdo->prepare("SELECT school_year FROM tbl_tournament WHERE tour_id = ?");
    $stmt->execute([$input['tour_id']]);
    $tournament = $stmt->fetch();
    
    if (!$tournament) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Tournament not found']);
    }
    
    $tournament_school_year = $tournament['school_year'];
    
    // Validate college_code if provided
    if (!empty($input['college_code'])) {
      $stmt = $pdo->prepare("SELECT college_code FROM tbl_college WHERE college_code = ? AND is_active = 1");
      $stmt->execute([strtoupper($input['college_code'])]);
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
      trim($input['l_name']), 
      trim($input['f_name']), 
      trim($input['m_name'] ?? ''),
      trim($input['title'] ?? ''), 
      $input['date_birth'] ?? null, 
      !empty($input['college_code']) ? strtoupper(trim($input['college_code'])) : null,
      trim($input['course'] ?? ''), 
      trim($input['blood_type'] ?? '')
    ]);
    $person_id = $pdo->lastInsertId();
    
    if (!$person_id) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Failed to create athlete profile']);
    }
    
    // 2. Insert into tbl_team_athletes
    $stmt = $pdo->prepare("
      INSERT INTO tbl_team_athletes (
        tour_id, team_id, sports_id, person_id, is_captain, is_active
      ) VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['tour_id'], 
      $input['team_id'], 
      $input['sports_id'],
      $person_id, 
      $input['is_captain'] ?? 0
    ]);
    
    $team_ath_id = $pdo->lastInsertId();
    
    if (!$team_ath_id) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Failed to register athlete to team']);
    }
    
    // 3. Insert vital signs if provided
    if (!empty($input['height']) || !empty($input['weight'])) {
      $stmt = $pdo->prepare("
        INSERT INTO tbl_vital_signs (person_id, height, weight, date_taken) 
        VALUES (?, ?, ?, NOW())
      ");
      $stmt->execute([
        $person_id,
        !empty($input['height']) ? floatval($input['height']) : null,
        !empty($input['weight']) ? floatval($input['weight']) : null
      ]);
    }
    
    // 4. Insert athlete status if scholarship is provided
    // IMPORTANT: Always use the tournament's school year
    if (!empty($input['scholarship_name']) && !empty($tournament_school_year)) {
      $semester = $input['semester'] ?? '1st Semester';
      
      $stmt = $pdo->prepare("
        INSERT INTO tbl_ath_status (person_id, scholarship_name, semester, school_year) 
        VALUES (?, ?, ?, ?)
      ");
      $stmt->execute([
        $person_id,
        trim($input['scholarship_name']),
        $semester,
        $tournament_school_year
      ]);
    }
    
    $pdo->commit();
    
    out([
      'ok' => true, 
      'person_id' => $person_id,
      'team_ath_id' => $team_ath_id,
      'message' => 'Athlete created and registered successfully'
    ]);
    
  } catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error creating athlete: " . $e->getMessage());
    out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
  }
}

if ($action === 'update_athlete') {
  try {
    $pdo->beginTransaction();
    
    if (empty($input['person_id'])) {
      $pdo->rollBack();
      out(['ok' => false, 'error' => 'Person ID is required']);
    }
    
    // Get tournament school year if tour_id is provided
    $tournament_school_year = null;
    if (!empty($input['tour_id'])) {
      $stmt = $pdo->prepare("SELECT school_year FROM tbl_tournament WHERE tour_id = ?");
      $stmt->execute([$input['tour_id']]);
      $tournament = $stmt->fetch();
      $tournament_school_year = $tournament['school_year'] ?? null;
    }
    
    // Validate college_code if being changed
    if (isset($input['college_code']) && !empty($input['college_code'])) {
      $stmt = $pdo->prepare("SELECT college_code FROM tbl_college WHERE college_code = ? AND is_active = 1");
      $stmt->execute([strtoupper($input['college_code'])]);
      if (!$stmt->fetch()) {
        $pdo->rollBack();
        out(['ok' => false, 'error' => 'Invalid college code. Please select a valid college from the list.']);
      }
    }
    
    // Update person
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET f_name=?, l_name=?, m_name=?, title=?, date_birth=?, college_code=?, course=?, blood_type=? 
      WHERE person_id=?
    ");
    $stmt->execute([
      trim($input['f_name']), 
      trim($input['l_name']), 
      trim($input['m_name'] ?? ''),
      trim($input['title'] ?? ''), 
      $input['date_birth'] ?? null, 
      !empty($input['college_code']) ? strtoupper(trim($input['college_code'])) : null,
      trim($input['course'] ?? ''), 
      trim($input['blood_type'] ?? ''), 
      $input['person_id']
    ]);
    
    // Update captain status if provided
    if (isset($input['is_captain']) && isset($input['team_ath_id'])) {
      $stmt = $pdo->prepare("UPDATE tbl_team_athletes SET is_captain=? WHERE team_ath_id=?");
      $stmt->execute([$input['is_captain'], $input['team_ath_id']]);
    }
    
    // Update vital signs
    if (isset($input['height']) || isset($input['weight'])) {
      // Check if vital signs record exists
      $stmt = $pdo->prepare("SELECT vital_id FROM tbl_vital_signs WHERE person_id = ? ORDER BY vital_id DESC LIMIT 1");
      $stmt->execute([$input['person_id']]);
      $existing = $stmt->fetch();
      
      if ($existing) {
        $stmt = $pdo->prepare("
          UPDATE tbl_vital_signs 
          SET height=?, weight=?, date_taken=NOW() 
          WHERE vital_id=?
        ");
        $stmt->execute([
          !empty($input['height']) ? floatval($input['height']) : null,
          !empty($input['weight']) ? floatval($input['weight']) : null,
          $existing['vital_id']
        ]);
      } else {
        $stmt = $pdo->prepare("
          INSERT INTO tbl_vital_signs (person_id, height, weight, date_taken) 
          VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
          $input['person_id'],
          !empty($input['height']) ? floatval($input['height']) : null,
          !empty($input['weight']) ? floatval($input['weight']) : null
        ]);
      }
    }
    
    // Update scholarship if provided AND we have a school year
    if (isset($input['scholarship_name']) && !empty($input['scholarship_name'])) {
      // If we don't have school year from tournament, try to get it from existing status
      if (!$tournament_school_year) {
        $stmt = $pdo->prepare("SELECT school_year FROM tbl_ath_status WHERE person_id = ? ORDER BY status_id DESC LIMIT 1");
        $stmt->execute([$input['person_id']]);
        $existing_status = $stmt->fetch();
        $tournament_school_year = $existing_status['school_year'] ?? null;
      }
      
      // Only update if we have a school year
      if ($tournament_school_year) {
        $stmt = $pdo->prepare("SELECT status_id FROM tbl_ath_status WHERE person_id = ? ORDER BY status_id DESC LIMIT 1");
        $stmt->execute([$input['person_id']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
          $stmt = $pdo->prepare("
            UPDATE tbl_ath_status 
            SET scholarship_name=?, semester=?, school_year=? 
            WHERE status_id=?
          ");
          $stmt->execute([
            trim($input['scholarship_name']),
            $input['semester'] ?? '1st Semester',
            $tournament_school_year,
            $existing['status_id']
          ]);
        } else {
          $stmt = $pdo->prepare("
            INSERT INTO tbl_ath_status (person_id, scholarship_name, semester, school_year) 
            VALUES (?, ?, ?, ?)
          ");
          $stmt->execute([
            $input['person_id'],
            trim($input['scholarship_name']),
            $input['semester'] ?? '1st Semester',
            $tournament_school_year
          ]);
        }
      }
    }
    
    $pdo->commit();
    out(['ok' => true, 'message' => 'Athlete updated successfully']);
  } catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error updating athlete: " . $e->getMessage());
    out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
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