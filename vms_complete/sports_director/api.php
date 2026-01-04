<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Accept 'sports director' role (database stores as 'sports director' with space)
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

// STATISTICS
if ($action === 'stats') {
  try {
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $tournamentsSql = "SELECT COUNT(*) as count FROM tbl_tournament WHERE is_active=1";
    $teamsSql = "SELECT COUNT(DISTINCT t.team_id) as count FROM tbl_team t WHERE t.is_active=1";
    $athletesSql = "SELECT COUNT(DISTINCT p.person_id) as count FROM tbl_person p WHERE p.role_type IN ('athlete','athlete/player') AND p.is_active=1";
    $matchesSql = "SELECT COUNT(*) as count FROM tbl_match m WHERE m.sked_date >= CURDATE()";
    
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

// SPORTS
if ($action === 'sports') {
  try {
    $stmt = $pdo->query("SELECT sports_id, sports_name FROM tbl_sports WHERE is_active=1 ORDER BY sports_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// VENUES
if ($action === 'venues') {
  try {
    $stmt = $pdo->query("SELECT venue_id, venue_name, venue_building, is_active FROM tbl_game_venue ORDER BY venue_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// TOURNAMENTS
if ($action === 'tournaments') {
  try {
    $stmt = $pdo->query("SELECT * FROM tbl_tournament ORDER BY tour_date DESC, school_year DESC");
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

// TEAMS
if ($action === 'teams') {
  try {
    $sql = "SELECT t.*, s.sports_name, COUNT(DISTINCT ta.person_id) as num_players 
            FROM tbl_team t 
            LEFT JOIN tbl_sports_team st ON st.team_id = t.team_id
            LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
            LEFT JOIN tbl_team_athletes ta ON ta.team_id = t.team_id AND ta.is_active=1
            WHERE 1=1";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND st.sports_id = " . (int)$_GET['sport_id'];
    }
    
    $sql .= " GROUP BY t.team_id, s.sports_name ORDER BY s.sports_name, t.team_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_team') {
  try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO tbl_team (team_name, is_active) VALUES (?, 1)");
    $stmt->execute([$input['team_name']]);
    $team_id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("INSERT INTO tbl_sports_team (team_id, sports_id) VALUES (?, ?)");
    $stmt->execute([$team_id, $input['sports_id']]);
    
    $pdo->commit();
    out(['ok' => true, 'team_id' => $team_id]);
  } catch (PDOException $e) {
    $pdo->rollBack();
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

// ATHLETES
if ($action === 'athletes') {
  try {
    $sql = "SELECT DISTINCT p.person_id, p.f_name, p.l_name, p.m_name, p.college_code, p.course, p.is_active,
            CONCAT(p.f_name, ' ', p.l_name) as athlete_name,
            t.team_name, s.sports_name
            FROM tbl_person p
            LEFT JOIN tbl_team_athletes ta ON ta.person_id = p.person_id AND ta.is_active=1
            LEFT JOIN tbl_team t ON t.team_id = ta.team_id
            LEFT JOIN tbl_sports s ON s.sports_id = ta.sports_id
            WHERE p.role_type IN ('athlete', 'athlete/player')";
    
    if (isset($_GET['sport_id']) && $_GET['sport_id']) {
      $sql .= " AND ta.sports_id = " . (int)$_GET['sport_id'];
    }
    
    $sql .= " ORDER BY s.sports_name, p.l_name, p.f_name";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_athlete') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_person (f_name, l_name, m_name, college_code, course, role_type, is_active) VALUES (?, ?, ?, ?, ?, 'athlete', 1)");
    $stmt->execute([$input['f_name'], $input['l_name'], $input['m_name'], $input['college_code'], $input['course']]);
    out(['ok' => true, 'person_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_athlete') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_person SET f_name=?, l_name=?, m_name=?, college_code=?, course=? WHERE person_id=?");
    $stmt->execute([$input['f_name'], $input['l_name'], $input['m_name'], $input['college_code'], $input['course'], $input['person_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
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

// Get coaches
if ($action === 'staff') {
  $role = $_GET['role'] ?? '';
  $stmt = $pdo->prepare("
    SELECT person_id, 
           CONCAT(f_name, ' ', l_name) as full_name
    FROM tbl_person 
    WHERE role_type = ? 
    AND is_active = 1
    ORDER BY l_name, f_name
  ");
  $stmt->execute([$role]);
  out($stmt->fetchAll());
}

// Create athlete with full fields
if ($action === 'create_athlete') {
  $pdo->beginTransaction();
  
  // 1. Insert into tbl_person
  $stmt = $pdo->prepare("
    INSERT INTO tbl_person (
      l_name, f_name, m_name, title, date_birth,
      college_code, course, blood_type, role_type, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'athlete/player', 1)
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
      team_id, sports_id, person_id, is_captain, is_active
    ) VALUES (?, ?, ?, ?, 1)
  ");
  $stmt->execute([
    $input['team_id'], $input['sports_id'], 
    $person_id, $input['is_captain']
  ]);
  
  $pdo->commit();
  out(['ok' => true, 'person_id' => $person_id]);
}

// Create team with coaching staff
if ($action === 'create_team') {
  $pdo->beginTransaction();
  
  // 1. Insert into tbl_team
  $stmt = $pdo->prepare("
    INSERT INTO tbl_team (team_name, is_active) 
    VALUES (?, 1)
  ");
  $stmt->execute([$input['team_name']]);
  $team_id = $pdo->lastInsertId();
  
  // 2. Insert into tbl_sports_team with staff
  $stmt = $pdo->prepare("
    INSERT INTO tbl_sports_team (
      team_id, sports_id, coach_id, asst_coach_id,
      trainor1_id, trainor2_id, trainor3_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $team_id, $input['sports_id'], 
    $input['coach_id'], $input['asst_coach_id'],
    $input['trainor1_id'], $input['trainor2_id'], $input['trainor3_id']
  ]);
  
  $pdo->commit();
  out(['ok' => true, 'team_id' => $team_id]);
}

// MATCHES
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
    
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_match') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_match (tour_id, sports_id, team_a_id, team_b_id, sked_date, sked_time, venue_id, match_type, sports_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'team')");
    $stmt->execute([
      $input['tour_id'], 
      $input['sports_id'], 
      $input['team_a_id'], 
      $input['team_b_id'], 
      $input['sked_date'], 
      $input['sked_time'], 
      $input['venue_id'], 
      $input['match_type']
    ]);
    out(['ok' => true, 'match_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_match') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_match SET tour_id=?, sports_id=?, team_a_id=?, team_b_id=?, sked_date=?, sked_time=?, venue_id=?, match_type=? WHERE match_id=?");
    $stmt->execute([
      $input['tour_id'], 
      $input['sports_id'], 
      $input['team_a_id'], 
      $input['team_b_id'], 
      $input['sked_date'], 
      $input['sked_time'], 
      $input['venue_id'], 
      $input['match_type'],
      $input['match_id']
    ]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_match') {
  try {
    $stmt = $pdo->prepare("DELETE FROM tbl_match WHERE match_id=?");
    $stmt->execute([$input['match_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// TRAINING
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

if ($action === 'create_training') {
  try {
    $stmt = $pdo->prepare("INSERT INTO tbl_train_sked (team_id, sked_date, sked_time, venue_id, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$input['team_id'], $input['sked_date'], $input['sked_time'], $input['venue_id']]);
    out(['ok' => true, 'sked_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_training') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_train_sked SET team_id=?, sked_date=?, sked_time=?, venue_id=? WHERE sked_id=?");
    $stmt->execute([$input['team_id'], $input['sked_date'], $input['sked_time'], $input['venue_id'], $input['sked_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_training') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_train_sked SET is_active=0 WHERE sked_id=?");
    $stmt->execute([$input['sked_id']]);
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// STANDINGS
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
    
    $sql .= " ORDER BY s.sports_name, ts.no_win DESC, ts.no_gold DESC";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// RECENT ACTIVITY
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