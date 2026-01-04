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
    // Total matches
    $total_stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_match");
    $total_matches = $total_stmt->fetch()['count'];
    
    // Upcoming matches
    $upcoming_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE sked_date >= CURRENT_DATE()
    ");
    $upcoming_stmt->execute();
    $upcoming_matches = $upcoming_stmt->fetch()['count'];
    
    // Completed matches
    $completed_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE winner_id IS NOT NULL
    ");
    $completed_stmt->execute();
    $completed_matches = $completed_stmt->fetch()['count'];
    
    // Active tournaments
    $tournaments_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_tournament 
      WHERE is_active = 1
    ");
    $tournaments_stmt->execute();
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
      WHERE m.sked_date >= CURRENT_DATE()
      ORDER BY m.sked_date, m.sked_time
      LIMIT 10
    ");
    $stmt->execute();
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
      WHERE m.winner_id IS NOT NULL
      ORDER BY m.sked_date DESC, m.sked_time DESC
      LIMIT 10
    ");
    $stmt->execute();
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
      WHERE 1=1
    ";
    
    $params = [];
    
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
      WHERE m.winner_id IS NOT NULL
    ";
    
    $params = [];
    
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
    $stmt = $pdo->query("
      SELECT tour_id, tour_name, school_year, tour_date, is_active
      FROM tbl_tournament
      ORDER BY tour_date DESC, tour_id DESC
    ");
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
    $stmt = $pdo->query("
      SELECT sports_id, sports_name
      FROM tbl_sports
      WHERE is_active = 1
      ORDER BY sports_name
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);