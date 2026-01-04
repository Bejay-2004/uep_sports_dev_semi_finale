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

http_response_code(404);
out(['ok'=>false,'message'=>'Unknown action']);