<?php
// athlete/api.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Accept both 'athlete' and 'athlete/player' roles
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'athlete' && $normalized_role !== 'athlete_player') {
    http_response_code(403);
    out(['ok' => false, 'message' => 'Access denied. Athletes only.']);
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
// MY TEAMS - Get teams where athlete is a member
// ==========================================

if ($action === 'my_teams') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        ta.team_id,
        ta.sports_id,
        ta.is_captain,
        t.team_name,
        s.sports_name,
        CONCAT(COALESCE(p.f_name, ''), ' ', COALESCE(p.l_name, '')) AS coach_name
      FROM tbl_team_athletes ta
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      LEFT JOIN tbl_sports_team st ON st.team_id = ta.team_id AND st.sports_id = ta.sports_id
      LEFT JOIN tbl_person p ON p.person_id = st.coach_id
      WHERE ta.person_id = :person_id
        AND ta.is_active = 1
      ORDER BY t.team_name, s.sports_name
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAM PLAYERS - Get all players, optionally filtered by team
// ==========================================

if ($action === 'team_players') {
  try {
    $team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        ta.person_id,
        CONCAT(p.f_name, ' ', p.l_name) AS player_name,
        t.team_name,
        ta.is_captain,
        s.sports_name
      FROM tbl_team_athletes ta
      JOIN tbl_person p ON p.person_id = ta.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      WHERE ta.is_active = 1
    ";

    if ($team_id) {
      $sql .= " AND ta.team_id = :team_id";
    }

    $sql .= " ORDER BY ta.is_captain DESC, p.l_name, p.f_name";

    $stmt = $pdo->prepare($sql);
    
    if ($team_id) {
      $stmt->execute(['team_id' => $team_id]);
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
// MY MATCHES - Get matches for athlete's teams
// ==========================================

if ($action === 'my_matches') {
  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        m.match_id,
        m.sked_date,
        m.sked_time,
        m.match_type,
        m.sports_id,
        s.sports_name,
        m.team_a_id,
        m.team_b_id,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name,
        v.venue_name,
        m.winner_id,
        tw.team_name AS winner_name
      FROM tbl_team_athletes athlete
      JOIN tbl_match m ON (m.team_a_id = athlete.team_id OR m.team_b_id = athlete.team_id)
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      WHERE athlete.person_id = :person_id
        AND athlete.is_active = 1
      ORDER BY m.sked_date DESC, m.sked_time DESC
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING SCHEDULE - Get training schedules for athlete's teams
// ==========================================

if ($action === 'training_schedule') {
  try {
    $stmt = $pdo->prepare("
      SELECT DISTINCT
        ts.sked_id,
        ts.team_id,
        ts.sked_date,
        ts.sked_time,
        t.team_name,
        v.venue_name,
        v.venue_building,
        v.venue_room,
        ta_attend.is_present
      FROM tbl_team_athletes athlete
      JOIN tbl_train_sked ts ON ts.team_id = athlete.team_id
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_attend ta_attend ON ta_attend.sked_id = ts.sked_id 
        AND ta_attend.person_id = :person_id
      WHERE athlete.person_id = :person_id
        AND athlete.is_active = 1
        AND ts.is_active = 1
      ORDER BY ts.sked_date DESC, ts.sked_time DESC
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// RANKINGS - Get team standings for a specific team
// ==========================================

if ($action === 'rankings') {
  try {
    $team_id = (int)($_GET['team_id'] ?? 0);
    
    if ($team_id <= 0) {
      http_response_code(400);
      out(['ok' => false, 'message' => 'Invalid team ID']);
    }

    // Get the sports_id for this team
    $sport_stmt = $pdo->prepare("
      SELECT DISTINCT sports_id 
      FROM tbl_team_athletes 
      WHERE team_id = :team_id 
      LIMIT 1
    ");
    $sport_stmt->execute(['team_id' => $team_id]);
    $sport = $sport_stmt->fetch();

    if (!$sport) {
      out([]);
      return;
    }

    $sports_id = $sport['sports_id'];

    // Get standings for this sport
    $stmt = $pdo->prepare("
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
      WHERE ts.sports_id = :sports_id
      ORDER BY ts.no_win DESC, ts.no_gold DESC, ts.no_silver DESC, ts.no_bronze DESC
      LIMIT 10
    ");
    $stmt->execute(['sports_id' => $sports_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok' => false, 'message' => 'Unknown action']);