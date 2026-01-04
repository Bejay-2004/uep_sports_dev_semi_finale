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
    out($stmt->fetchAll());
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
// MODULE 1: SPORTS SELECTION
// ==========================================

if ($action === 'all_sports') {
  try {
    $stmt = $pdo->query("
      SELECT sports_id, sports_name
      FROM tbl_sports
      WHERE is_active = 1
      ORDER BY sports_name
    ");
    out($stmt->fetchAll());
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

    // Get distinct sports from matches for this tournament
    $stmt = $pdo->prepare("
      SELECT DISTINCT s.sports_id, s.sports_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      WHERE m.tour_id = :tour_id
      ORDER BY s.sports_name
    ");
    $stmt->execute(['tour_id' => $tour_id]);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

if ($action === 'add_tournament_sports') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok'=>false,'message'=>'Method not allowed']);
  }

  try {
    $tour_id = (int)($_POST['tour_id'] ?? 0);
    $sport_ids = $_POST['sport_ids'] ?? '';

    if ($tour_id <= 0 || !$sport_ids) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $sport_ids_array = array_map('intval', explode(',', $sport_ids));

    // Create placeholder matches for each sport
    // This establishes the tournament-sport relationship
    foreach ($sport_ids_array as $sport_id) {
      if ($sport_id <= 0) continue;

      // Check if already exists
      $chk = $pdo->prepare("
        SELECT 1 FROM tbl_match
        WHERE tour_id = :tour_id AND sports_id = :sports_id
        LIMIT 1
      ");
      $chk->execute(['tour_id' => $tour_id, 'sports_id' => $sport_id]);
      
      if ($chk->fetch()) continue; // Already added

      // Insert placeholder match to establish link
      $stmt = $pdo->prepare("
        INSERT INTO tbl_match (tour_id, sports_id, sports_type, match_type, team_a_id, team_b_id, sked_date, sked_time)
        VALUES (:tour_id, :sports_id, 'team', 'Placeholder', NULL, NULL, CURDATE(), '00:00:00')
      ");
      $stmt->execute(['tour_id' => $tour_id, 'sports_id' => $sport_id]);
    }

    out(['ok'=>true,'message'=>'Sports added to tournament']);
  } catch (PDOException $e) {
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
      WHERE st.sports_id = :sports_id
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
    $match_id = (int)($_POST['match_id'] ?? 0);
    $team_id = (int)($_POST['team_id'] ?? 0);
    $score_value = trim($_POST['score_value'] ?? '');
    $rank_no = (int)($_POST['rank_no'] ?? 0);
    $medal_type = trim($_POST['medal_type'] ?? '');

    if ($match_id <= 0 || $team_id <= 0 || !$score_value || $rank_no <= 0) {
      http_response_code(400);
      out(['ok'=>false,'message'=>'Missing required fields']);
    }

    $medal_type = $medal_type ?: NULL;

    $stmt = $pdo->prepare("
      INSERT INTO tbl_comp_score (match_id, team_id, score_value, rank_no, medal_type)
      VALUES (:match_id, :team_id, :score_value, :rank_no, :medal_type)
    ");
    $stmt->execute([
      'match_id' => $match_id,
      'team_id' => $team_id,
      'score_value' => $score_value,
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
        cs.score_id,
        cs.match_id,
        cs.team_id,
        cs.score_value,
        cs.rank_no,
        cs.medal_type,
        t.team_name,
        CONCAT(s.sports_name, ' - ', m.match_type, ' (', m.sked_date, ')') AS match_info
      FROM tbl_comp_score cs
      JOIN tbl_match m ON m.match_id = cs.match_id
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      JOIN tbl_team t ON t.team_id = cs.team_id
      ORDER BY cs.score_id DESC
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

    $stmt = $pdo->prepare("DELETE FROM tbl_comp_score WHERE score_id = :score_id");
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