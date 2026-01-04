<?php
// spectator/api.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Accept 'Spectator' role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'spectator') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied. Spectators only.']);
}

header("Content-Type: application/json; charset=utf-8");

$user_id = (int)$_SESSION['user']['user_id'];
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)($_SESSION['user']['sports_id'] ?? 0);

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// TOURNAMENTS - Get all active tournaments
// ==========================================

if ($action === 'tournaments') {
  try {
    $stmt = $pdo->query("
      SELECT 
        tour_id,
        tour_name,
        school_year,
        tour_date,
        is_active
      FROM tbl_tournament
      WHERE is_active = 1
      ORDER BY tour_date DESC
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SPORTS - Get all active sports
// ==========================================

if ($action === 'sports') {
  try {
    $sql = "
      SELECT 
        sports_id,
        sports_name,
        team_individual,
        men_women
      FROM tbl_sports
      WHERE is_active = 1
      ORDER BY sports_name
    ";
    
    // Note: Spectators can view ALL sports (sports_id will be 0/NULL)
    // No filtering by sports_id for spectators
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// MATCHES - Get matches with optional filters
// ==========================================

if ($action === 'matches') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT 
        m.match_id,
        m.sked_date,
        m.sked_time,
        m.match_type,
        m.sports_id,
        m.tour_id,
        s.sports_name,
        tour.tour_name,
        tour.school_year,
        m.team_a_id,
        m.team_b_id,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name,
        v.venue_name,
        m.winner_id,
        tw.team_name AS winner_name,
        csa.score AS team_a_score,
        csb.score AS team_b_score
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_tournament tour ON tour.tour_id = m.tour_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      LEFT JOIN tbl_comp_score csa ON csa.match_id = m.match_id AND csa.team_id = m.team_a_id
      LEFT JOIN tbl_comp_score csb ON csb.match_id = m.match_id AND csb.team_id = m.team_b_id
      WHERE 1=1
    ";
    
    $params = [];
    
    // Note: Spectators can view ALL sports (sports_id = 0/NULL)
    // No automatic filtering by user's sports_id
    
    // Filter by tournament
    if ($tour_id) {
      $sql .= " AND m.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    // Filter by sport
    if ($sport_id) {
      $sql .= " AND m.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " ORDER BY m.sked_date DESC, m.sked_time DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STANDINGS - Get team standings
// ==========================================

if ($action === 'standings') {
  try {
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT 
        ts.team_id,
        t.team_name,
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
      WHERE 1=1
    ";
    
    $params = [];
    
    // Note: Spectators can view ALL sports (sports_id = 0/NULL)
    // No automatic filtering by user's sports_id
    
    // Filter by tournament
    if ($tour_id) {
      $sql .= " AND ts.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    // Filter by sport
    if ($sport_id) {
      $sql .= " AND ts.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " ORDER BY ts.no_win DESC, ts.no_gold DESC, ts.no_silver DESC, ts.no_bronze DESC";
    $sql .= " LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS - Get teams with stats
// ==========================================

if ($action === 'teams') {
  try {
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT 
        t.team_id,
        t.team_name,
        s.sports_name,
        COUNT(DISTINCT ta.person_id) AS num_players,
        COALESCE(SUM(ts.no_win), 0) AS no_win,
        COALESCE(SUM(ts.no_loss), 0) AS no_loss,
        COALESCE(SUM(ts.no_gold), 0) AS no_gold,
        COALESCE(SUM(ts.no_silver), 0) AS no_silver,
        COALESCE(SUM(ts.no_bronze), 0) AS no_bronze
      FROM tbl_team t
      LEFT JOIN tbl_sports_team st ON st.team_id = t.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      LEFT JOIN tbl_team_athletes ta ON ta.team_id = t.team_id AND ta.is_active = 1
      LEFT JOIN tbl_team_standing ts ON ts.team_id = t.team_id AND ts.sports_id = st.sports_id
      WHERE t.is_active = 1
    ";
    
    $params = [];
    
    // Note: Spectators can view ALL sports (sports_id = 0/NULL)
    // No automatic filtering by user's sports_id
    
    // Filter by sport (from dropdown filter only)
    if ($sport_id) {
      $sql .= " AND st.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " GROUP BY t.team_id, t.team_name, s.sports_name";
    $sql .= " ORDER BY s.sports_name, t.team_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// PLAYERS - Get all players
// ==========================================

if ($action === 'players') {
  try {
    $team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT DISTINCT
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) AS player_name,
        t.team_name,
        s.sports_name,
        ta.is_captain
      FROM tbl_person p
      JOIN tbl_team_athletes ta ON ta.person_id = p.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      WHERE p.is_active = 1
        AND ta.is_active = 1
    ";
    
    $params = [];
    
    // Note: Spectators can view ALL sports (sports_id = 0/NULL)
    // No automatic filtering by user's sports_id
    
    // Filter by team
    if ($team_id) {
      $sql .= " AND ta.team_id = :team_id";
      $params['team_id'] = $team_id;
    }
    
    // Filter by sport
    if ($sport_id) {
      $sql .= " AND ta.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " ORDER BY s.sports_name, t.team_name, p.l_name, p.f_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SCORES - Get competition scores
// ==========================================

if ($action === 'scores') {
  try {
    $match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : null;
    $tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
    
    $sql = "
      SELECT 
        cs.competetors_score_id,
        cs.score,
        cs.rank_no,
        cs.medal_type,
        t.team_name,
        CONCAT(p.f_name, ' ', p.l_name) AS athlete_name,
        s.sports_name,
        m.sked_date,
        tour.tour_name
      FROM tbl_comp_score cs
      LEFT JOIN tbl_team t ON t.team_id = cs.team_id
      LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
      JOIN tbl_match m ON m.match_id = cs.match_id
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_tournament tour ON tour.tour_id = cs.tour_id
      WHERE 1=1
    ";
    
    $params = [];
    
    // Note: Spectators can view ALL sports (sports_id = 0/NULL)
    // No automatic filtering by user's sports_id
    
    // Filter by match
    if ($match_id) {
      $sql .= " AND cs.match_id = :match_id";
      $params['match_id'] = $match_id;
    }
    
    // Filter by tournament
    if ($tour_id) {
      $sql .= " AND cs.tour_id = :tour_id";
      $params['tour_id'] = $tour_id;
    }
    
    $sql .= " ORDER BY cs.rank_no ASC, cs.score DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok' => false, 'message' => 'Unknown action']);