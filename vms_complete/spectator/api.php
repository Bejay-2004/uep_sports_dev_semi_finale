<?php
// spectator/api.php - FIXED VERSION

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
        t.tour_id,
        t.tour_name,
        t.school_year,
        t.tour_date,
        t.is_active,
        COUNT(DISTINCT m.match_id) as match_count,
        COUNT(DISTINCT tss.sports_id) as sports_count
      FROM tbl_tournament t
      LEFT JOIN tbl_match m ON m.tour_id = t.tour_id
      LEFT JOIN tbl_tournament_sports_selection tss ON tss.tour_id = t.tour_id
      WHERE t.is_active = 1
      GROUP BY t.tour_id, t.tour_name, t.school_year, t.tour_date, t.is_active
      ORDER BY t.tour_date DESC
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    $stmt = $pdo->query("
      SELECT 
        s.sports_id,
        s.sports_name,
        s.team_individual,
        s.men_women,
        s.num_req_players,
        s.num_res_players,
        s.weight_class,
        COUNT(DISTINCT st.team_id) as team_count
      FROM tbl_sports s
      LEFT JOIN tbl_sports_team st ON st.sports_id = s.sports_id
      WHERE s.is_active = 1
      GROUP BY s.sports_id, s.sports_name, s.team_individual, s.men_women, 
               s.num_req_players, s.num_res_players, s.weight_class
      ORDER BY s.sports_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    $tour_id = isset($_GET['tour_id']) && $_GET['tour_id'] !== '' ? (int)$_GET['tour_id'] : null;
    $sport_id = isset($_GET['sport_id']) && $_GET['sport_id'] !== '' ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT 
        m.match_id,
        m.game_no,
        m.sked_date,
        m.sked_time,
        m.match_type,
        m.sports_id,
        m.tour_id,
        m.sports_type,
        s.sports_name,
        tour.tour_name,
        tour.school_year,
        m.team_a_id,
        m.team_b_id,
        ta.team_name AS team_a_name,
        tb.team_name AS team_b_name,
        v.venue_name,
        v.venue_building,
        v.venue_room,
        m.winner_id,
        tw.team_name AS winner_name,
        CONCAT(ump.f_name, ' ', ump.l_name) AS umpire_name,
        CONCAT(sm.f_name, ' ', sm.l_name) AS sports_manager_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_tournament tour ON tour.tour_id = m.tour_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_id
      LEFT JOIN tbl_person ump ON ump.person_id = m.match_umpire_id
      LEFT JOIN tbl_person sm ON sm.person_id = m.match_sports_manager_id
      WHERE 1=1
    ";
    
    $params = [];
    
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
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get scores for each match
    foreach ($matches as &$match) {
      $score_stmt = $pdo->prepare("
        SELECT 
          cs.team_id,
          cs.athlete_id,
          cs.score,
          cs.rank_no,
          cs.medal_type,
          t.team_name,
          CONCAT(p.f_name, ' ', p.l_name) AS athlete_name
        FROM tbl_comp_score cs
        LEFT JOIN tbl_team t ON t.team_id = cs.team_id
        LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
        WHERE cs.match_id = :match_id
        ORDER BY cs.rank_no ASC, cs.score DESC
      ");
      $score_stmt->execute(['match_id' => $match['match_id']]);
      $match['scores'] = $score_stmt->fetchAll(PDO::FETCH_ASSOC);
      
      // Calculate team scores if team sport
      if ($match['sports_type'] === 'team' && count($match['scores']) > 0) {
        $team_a_score = 0;
        $team_b_score = 0;
        foreach ($match['scores'] as $score) {
          if ($score['team_id'] == $match['team_a_id']) {
            $team_a_score += (float)$score['score'];
          } elseif ($score['team_id'] == $match['team_b_id']) {
            $team_b_score += (float)$score['score'];
          }
        }
        $match['team_a_score'] = $team_a_score;
        $match['team_b_score'] = $team_b_score;
      } else {
        $match['team_a_score'] = null;
        $match['team_b_score'] = null;
      }
    }
    
    out($matches);
  } catch (PDOException $e) {
    error_log("MATCHES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STANDINGS - Get team standings
// ==========================================

if ($action === 'standings') {
  try {
    $tour_id = isset($_GET['tour_id']) && $_GET['tour_id'] !== '' ? (int)$_GET['tour_id'] : null;
    $sport_id = isset($_GET['sport_id']) && $_GET['sport_id'] !== '' ? (int)$_GET['sport_id'] : null;
    
    if (!$tour_id || !$sport_id) {
      out([]);
    }
    
    $sql = "
      SELECT 
        ts.team_id,
        t.team_name,
        s.sports_name,
        ts.tour_id,
        ts.sports_id,
        ts.no_games_played,
        ts.no_win,
        ts.no_loss,
        ts.no_draw,
        ts.no_gold,
        ts.no_silver,
        ts.no_bronze,
        (ts.no_win * 3 + ts.no_draw * 1) as points,
        COUNT(DISTINCT ta.person_id) as player_count
      FROM tbl_team_standing ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      JOIN tbl_sports s ON s.sports_id = ts.sports_id
      LEFT JOIN tbl_team_athletes ta ON ta.team_id = ts.team_id 
        AND ta.sports_id = ts.sports_id 
        AND ta.is_active = 1
      WHERE ts.tour_id = :tour_id
        AND ts.sports_id = :sport_id
      GROUP BY ts.team_id, t.team_name, s.sports_name, ts.tour_id, ts.sports_id,
               ts.no_games_played, ts.no_win, ts.no_loss, ts.no_draw,
               ts.no_gold, ts.no_silver, ts.no_bronze
      ORDER BY points DESC, ts.no_win DESC, ts.no_gold DESC, 
               ts.no_silver DESC, ts.no_bronze DESC, t.team_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      'tour_id' => $tour_id,
      'sport_id' => $sport_id
    ]);
    
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("STANDINGS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS - Get teams with stats
// ==========================================

if ($action === 'teams') {
  try {
    $sport_id = isset($_GET['sport_id']) && $_GET['sport_id'] !== '' ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT DISTINCT
        t.team_id,
        t.team_name,
        t.school_id,
        sch.school_name,
        s.sports_id,
        s.sports_name,
        s.team_individual,
        COUNT(DISTINCT ta.person_id) AS player_count,
        COUNT(DISTINCT CASE WHEN ta.is_captain = 1 THEN ta.person_id END) AS captain_count,
        COALESCE(SUM(ts.no_win), 0) AS total_wins,
        COALESCE(SUM(ts.no_loss), 0) AS total_losses,
        COALESCE(SUM(ts.no_gold), 0) AS total_gold,
        COALESCE(SUM(ts.no_silver), 0) AS total_silver,
        COALESCE(SUM(ts.no_bronze), 0) AS total_bronze,
        CONCAT(coach.f_name, ' ', coach.l_name) AS coach_name,
        CONCAT(asst.f_name, ' ', asst.l_name) AS asst_coach_name
      FROM tbl_team t
      LEFT JOIN tbl_school sch ON sch.school_id = t.school_id
      LEFT JOIN tbl_sports_team st ON st.team_id = t.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      LEFT JOIN tbl_team_athletes ta ON ta.team_id = t.team_id 
        AND ta.sports_id = st.sports_id 
        AND ta.is_active = 1
      LEFT JOIN tbl_team_standing ts ON ts.team_id = t.team_id 
        AND ts.sports_id = st.sports_id
      LEFT JOIN tbl_person coach ON coach.person_id = st.coach_id
      LEFT JOIN tbl_person asst ON asst.person_id = st.asst_coach_id
      WHERE t.is_active = 1
    ";
    
    $params = [];
    
    // Filter by sport
    if ($sport_id) {
      $sql .= " AND st.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " GROUP BY t.team_id, t.team_name, t.school_id, sch.school_name,
                       s.sports_id, s.sports_name, s.team_individual,
                       coach.f_name, coach.l_name, asst.f_name, asst.l_name";
    $sql .= " ORDER BY s.sports_name, t.team_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("TEAMS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// PLAYERS - Get all players/athletes
// ==========================================

if ($action === 'players') {
  try {
    $team_id = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;
    $sport_id = isset($_GET['sport_id']) && $_GET['sport_id'] !== '' ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT DISTINCT
        p.person_id,
        p.f_name,
        p.l_name,
        CONCAT(p.f_name, ' ', p.l_name) AS player_name,
        p.college_code,
        p.course,
        col.college_name,
        t.team_id,
        t.team_name,
        s.sports_id,
        s.sports_name,
        ta.is_captain,
        ta.tour_id,
        tour.tour_name
      FROM tbl_person p
      JOIN tbl_team_athletes ta ON ta.person_id = p.person_id
      JOIN tbl_team t ON t.team_id = ta.team_id
      JOIN tbl_sports s ON s.sports_id = ta.sports_id
      LEFT JOIN tbl_college col ON col.college_code = p.college_code
      LEFT JOIN tbl_tournament tour ON tour.tour_id = ta.tour_id
      WHERE p.is_active = 1
        AND ta.is_active = 1
        AND p.role_type IN ('athlete/player', 'athlete', 'player')
    ";
    
    $params = [];
    
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
    
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("PLAYERS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// SCORES - Get competition scores
// ==========================================

if ($action === 'scores') {
  try {
    $match_id = isset($_GET['match_id']) && $_GET['match_id'] !== '' ? (int)$_GET['match_id'] : null;
    $tour_id = isset($_GET['tour_id']) && $_GET['tour_id'] !== '' ? (int)$_GET['tour_id'] : null;
    $sport_id = isset($_GET['sport_id']) && $_GET['sport_id'] !== '' ? (int)$_GET['sport_id'] : null;
    
    $sql = "
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
        CONCAT(p.f_name, ' ', p.l_name) AS athlete_name,
        p.college_code,
        col.college_name,
        s.sports_name,
        s.team_individual,
        m.sked_date,
        m.sked_time,
        m.match_type,
        tour.tour_name,
        tour.school_year
      FROM tbl_comp_score cs
      LEFT JOIN tbl_team t ON t.team_id = cs.team_id
      LEFT JOIN tbl_person p ON p.person_id = cs.athlete_id
      LEFT JOIN tbl_college col ON col.college_code = p.college_code
      JOIN tbl_match m ON m.match_id = cs.match_id
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_tournament tour ON tour.tour_id = cs.tour_id
      WHERE 1=1
    ";
    
    $params = [];
    
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
    
    // Filter by sport
    if ($sport_id) {
      $sql .= " AND m.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " ORDER BY cs.rank_no ASC, cs.score DESC, m.sked_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    error_log("SCORES ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// STATISTICS - Get dashboard stats
// ==========================================

if ($action === 'stats') {
  try {
    // Count active tournaments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_tournament WHERE is_active = 1");
    $tournaments_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count active sports
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_sports WHERE is_active = 1");
    $sports_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count upcoming matches (future dates)
    $stmt = $pdo->query("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE sked_date >= CURDATE()
    ");
    $matches_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count active teams
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tbl_team WHERE is_active = 1");
    $teams_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    out([
      'tournaments' => $tournaments_count,
      'sports' => $sports_count,
      'matches' => $matches_count,
      'teams' => $teams_count
    ]);
  } catch (PDOException $e) {
    error_log("STATS ERROR: " . $e->getMessage());
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

http_response_code(404);
out(['ok' => false, 'message' => 'Unknown action: ' . $action]);