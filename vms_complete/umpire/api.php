<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Only umpire role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'umpire') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied']);
}

header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// UMPIRE STATISTICS
// ==========================================

if ($action === 'umpire_stats') {
  try {
    // Total assigned matches
    $total_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE match_umpire_id = :person_id
    ");
    $total_stmt->execute(['person_id' => $person_id]);
    $total_matches = $total_stmt->fetch()['count'];
    
    // Upcoming assigned matches
    $upcoming_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE match_umpire_id = :person_id 
      AND sked_date >= CURRENT_DATE()
    ");
    $upcoming_stmt->execute(['person_id' => $person_id]);
    $upcoming_matches = $upcoming_stmt->fetch()['count'];
    
    // Completed assigned matches
    $completed_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE match_umpire_id = :person_id 
      AND winner_id IS NOT NULL
    ");
    $completed_stmt->execute(['person_id' => $person_id]);
    $completed_matches = $completed_stmt->fetch()['count'];
    
    // Active tournaments with assigned matches
    $tournaments_stmt = $pdo->prepare("
      SELECT COUNT(DISTINCT m.tour_id) as count 
      FROM tbl_match m
      JOIN tbl_tournament t ON t.tour_id = m.tour_id
      WHERE m.match_umpire_id = :person_id 
      AND t.is_active = 1
    ");
    $tournaments_stmt->execute(['person_id' => $person_id]);
    $active_tournaments = $tournaments_stmt->fetch()['count'];
    
    out([
      'total_matches' => $total_matches,
      'upcoming_matches' => $upcoming_matches,
      'completed_matches' => $completed_matches,
      'active_tournaments' => $active_tournaments
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// UPCOMING MATCHES
// ==========================================

if ($action === 'upcoming_matches') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        m.match_id,
        m.sked_date,
        m.sked_time,
        m.match_type,
        s.sports_name,
        ta.team_name as team_a_name,
        tb.team_name as team_b_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      JOIN tbl_team ta ON ta.team_id = m.team_a_id
      JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.match_umpire_id = :person_id 
      AND m.sked_date >= CURRENT_DATE()
      ORDER BY m.sked_date, m.sked_time
      LIMIT 10
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// RECENT RESULTS
// ==========================================

if ($action === 'recent_results') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        m.match_id,
        m.sked_date,
        m.sked_time,
        ta.team_name as team_a_name,
        tb.team_name as team_b_name,
        tw.team_name as winner_name
      FROM tbl_match m
      JOIN tbl_team ta ON ta.team_id = m.team_a_id
      JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      WHERE m.match_umpire_id = :person_id 
      AND m.winner_id IS NOT NULL
      ORDER BY m.sked_date DESC, m.sked_time DESC
      LIMIT 10
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ALL MATCHES
// ==========================================

if ($action === 'all_matches') {
  try {
    $tour_id = isset($_GET['tour_id']) && $_GET['tour_id'] !== '' ? (int)$_GET['tour_id'] : null;
    $sports_id = isset($_GET['sports_id']) && $_GET['sports_id'] !== '' ? (int)$_GET['sports_id'] : null;
    
    $sql = "
      SELECT 
        m.match_id,
        m.sked_date,
        m.sked_time,
        m.match_type,
        s.sports_name,
        ta.team_name as team_a_name,
        tb.team_name as team_b_name,
        tw.team_name as winner_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      JOIN tbl_team ta ON ta.team_id = m.team_a_id
      JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.match_umpire_id = :person_id
    ";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id !== null) {
      $sql .= " AND m.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    if ($sports_id !== null) {
      $sql .= " AND m.sports_id = :sports_id";
      $params['sports_id'] = $sports_id;
    }
    
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MATCH RESULTS
// ==========================================

if ($action === 'match_results') {
  try {
    $tour_id = isset($_GET['tour_id']) && $_GET['tour_id'] !== '' ? (int)$_GET['tour_id'] : null;
    $sports_id = isset($_GET['sports_id']) && $_GET['sports_id'] !== '' ? (int)$_GET['sports_id'] : null;
    
    $sql = "
      SELECT 
        m.match_id,
        m.sked_date,
        m.sked_time,
        m.match_type,
        s.sports_name,
        ta.team_name as team_a_name,
        tb.team_name as team_b_name,
        tw.team_name as winner_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      JOIN tbl_team ta ON ta.team_id = m.team_a_id
      JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.match_umpire_id = :person_id 
      AND m.winner_id IS NOT NULL
    ";
    
    $params = ['person_id' => $person_id];
    
    if ($tour_id !== null) {
      $sql .= " AND m.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    if ($sports_id !== null) {
      $sql .= " AND m.sports_id = :sports_id";
      $params['sports_id'] = $sports_id;
    }
    
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STANDINGS
// ==========================================

if ($action === 'standings') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid tournament ID']);
    }

    // Check if umpire has any matches in this tournament
    $check_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE tour_id = :tour_id 
      AND match_umpire_id = :person_id
    ");
    $check_stmt->execute(['tour_id' => $tour_id, 'person_id' => $person_id]);
    
    if ($check_stmt->fetch()['count'] == 0) {
      out(['ok' => false, 'message' => 'No access to this tournament']);
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
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MEDAL TALLY
// ==========================================

if ($action === 'medal_tally') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid tournament ID']);
    }

    // Check if umpire has any matches in this tournament
    $check_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE tour_id = :tour_id 
      AND match_umpire_id = :person_id
    ");
    $check_stmt->execute(['tour_id' => $tour_id, 'person_id' => $person_id]);
    
    if ($check_stmt->fetch()['count'] == 0) {
      out(['ok' => false, 'message' => 'No access to this tournament']);
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
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENTS
// ==========================================

if ($action === 'tournaments') {
  try {
    // Only show tournaments where umpire has assigned matches
    $stmt = $pdo->prepare("
      SELECT DISTINCT 
        t.tour_id, 
        t.tour_name, 
        t.school_year, 
        t.tour_date, 
        t.is_active
      FROM tbl_tournament t
      JOIN tbl_match m ON m.tour_id = t.tour_id
      WHERE m.match_umpire_id = :person_id
      ORDER BY t.tour_date DESC, t.tour_id DESC
    ");
    $stmt->execute(['person_id' => $person_id]);
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
    // Only show sports where umpire has assigned matches
    $stmt = $pdo->prepare("
      SELECT DISTINCT 
        s.sports_id, 
        s.sports_name
      FROM tbl_sports s
      JOIN tbl_match m ON m.sports_id = s.sports_id
      WHERE m.match_umpire_id = :person_id 
      AND s.is_active = 1
      ORDER BY s.sports_name
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);