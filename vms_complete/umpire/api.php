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
$sports_id       = (int)$_SESSION['user']['sports_id'];

$action = $_GET['action'] ?? '';

function out($data) {
  echo json_encode($data);
  exit;
}

// ==========================================
// TRAINEES
// ==========================================

if ($action === 'trainees') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as trainee_name,
        t.team_name,
        tt.semester,
        tt.school_year,
        tt.date_applied,
        tt.is_active
      FROM tbl_team_trainees tt
      JOIN tbl_person p ON p.person_id = tt.trainee_id
      JOIN tbl_team t ON t.team_id = tt.team_id
      WHERE tt.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
      ORDER BY tt.is_active DESC, p.l_name, p.f_name
    ");
    $stmt->execute(['coach_id' => $coach_person_id, 'sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TRAINING ACTIVITIES
// ==========================================

if ($action === 'activities') {
  try {
    $stmt = $pdo->prepare("
      SELECT 
        activity_id,
        activity_name,
        duration,
        repetition,
        is_active
      FROM tbl_training_activity
      WHERE sports_id = :sports_id
      ORDER BY activity_name
    ");
    $stmt->execute(['sports_id' => $sports_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// CREATE/UPDATE ACTIVITY
// ==========================================

if ($action === 'save_activity') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok' => false, 'message' => 'Method not allowed']);
  }

  try {
    $activity_id = (int)($_POST['activity_id'] ?? 0);
    $activity_name = trim($_POST['activity_name'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $repetition = trim($_POST['repetition'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($activity_name)) {
      out(['ok' => false, 'message' => 'Activity name is required']);
    }

    if ($activity_id > 0) {
      // Update existing
      $stmt = $pdo->prepare("
        UPDATE tbl_training_activity
        SET activity_name = :name,
            duration = :duration,
            repetition = :repetition,
            is_active = :active
        WHERE activity_id = :id AND sports_id = :sports_id
      ");
      $stmt->execute([
        'name' => $activity_name,
        'duration' => $duration,
        'repetition' => $repetition,
        'active' => $is_active,
        'id' => $activity_id,
        'sports_id' => $sports_id
      ]);
      out(['ok' => true, 'message' => 'Training activity updated successfully']);
    } else {
      // Insert new
      $stmt = $pdo->prepare("
        INSERT INTO tbl_training_activity 
        (activity_name, sports_id, duration, repetition, is_active)
        VALUES (:name, :sports_id, :duration, :repetition, :active)
      ");
      $stmt->execute([
        'name' => $activity_name,
        'sports_id' => $sports_id,
        'duration' => $duration,
        'repetition' => $repetition,
        'active' => $is_active
      ]);
      out(['ok' => true, 'message' => 'Training activity created successfully']);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  }
}

// ==========================================
// GET ACTIVITY
// ==========================================

if ($action === 'get_activity') {
  try {
    $activity_id = (int)($_GET['activity_id'] ?? 0);
    
    if ($activity_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid activity ID']);
    }
    
    $stmt = $pdo->prepare("
      SELECT activity_id, activity_name, duration, repetition, is_active
      FROM tbl_training_activity
      WHERE activity_id = :id AND sports_id = :sports_id
      LIMIT 1
    ");
    $stmt->execute(['id' => $activity_id, 'sports_id' => $sports_id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activity) {
      out($activity);
    } else {
      out(['ok' => false, 'message' => 'Activity not found']);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// DELETE ACTIVITY
// ==========================================

if ($action === 'delete_activity') {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    out(['ok' => false, 'message' => 'Method not allowed']);
  }

  try {
    $activity_id = (int)($_POST['activity_id'] ?? 0);

    if ($activity_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid activity ID']);
    }

    // Soft delete - just set is_active to 0
    $stmt = $pdo->prepare("
      UPDATE tbl_training_activity
      SET is_active = 0
      WHERE activity_id = :id AND sports_id = :sports_id
    ");
    $stmt->execute(['id' => $activity_id, 'sports_id' => $sports_id]);

    if ($stmt->rowCount() > 0) {
      out(['ok' => true, 'message' => 'Activity deactivated successfully']);
    } else {
      out(['ok' => false, 'message' => 'Activity not found']);
    }
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  }
}

// ==========================================
// REPORTS - ATTENDANCE SUMMARY
// ==========================================

if ($action === 'report_attendance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        ts.sked_id,
        ts.sked_date,
        ts.sked_time,
        t.team_name,
        v.venue_name,
        COUNT(DISTINCT ta.person_id) as total_members,
        SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) as present_count,
        ROUND((SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT ta.person_id)) * 100, 1) as attendance_rate
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = ts.venue_id
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id
      WHERE ts.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
    ";

    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];

    if ($date_from) {
      $sql .= " AND ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }

    if ($date_to) {
      $sql .= " AND ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }

    if ($team_id !== null) {
      $sql .= " AND ts.team_id = :team_id";
      $params['team_id'] = $team_id;
    }

    $sql .= " GROUP BY ts.sked_id, ts.sked_date, ts.sked_time, t.team_name, v.venue_name
              ORDER BY ts.sked_date DESC, ts.sked_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORTS - PERFORMANCE SUMMARY
// ==========================================

if ($action === 'report_performance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as player_name,
        t.team_name,
        COUNT(tp.perf_id) as total_evaluations,
        ROUND(AVG(tp.rating), 2) as avg_rating,
        MAX(tp.rating) as max_rating,
        MIN(tp.rating) as min_rating,
        MAX(tp.date_eval) as last_evaluated
      FROM tbl_train_perf tp
      JOIN tbl_person p ON p.person_id = tp.person_id
      JOIN tbl_team t ON t.team_id = tp.team_id
      WHERE tp.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
    ";

    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];

    if ($date_from) {
      $sql .= " AND tp.date_eval >= :date_from";
      $params['date_from'] = $date_from;
    }

    if ($date_to) {
      $sql .= " AND tp.date_eval <= :date_to";
      $params['date_to'] = $date_to;
    }

    if ($team_id !== null) {
      $sql .= " AND tp.team_id = :team_id";
      $params['team_id'] = $team_id;
    }

    $sql .= " GROUP BY p.person_id, p.f_name, p.l_name, t.team_name
              ORDER BY avg_rating DESC, player_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORTS - TRAINING SESSIONS SUMMARY
// ==========================================

if ($action === 'report_sessions') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        t.team_name,
        COUNT(ts.sked_id) as total_sessions,
        COUNT(DISTINCT DATE_FORMAT(ts.sked_date, '%Y-%m')) as months_active,
        MIN(ts.sked_date) as first_session,
        MAX(ts.sked_date) as last_session
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      WHERE ts.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
    ";

    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];

    if ($date_from) {
      $sql .= " AND ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }

    if ($date_to) {
      $sql .= " AND ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }

    if ($team_id !== null) {
      $sql .= " AND ts.team_id = :team_id";
      $params['team_id'] = $team_id;
    }

    $sql .= " GROUP BY t.team_name
              ORDER BY total_sessions DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// REPORTS - PLAYER ATTENDANCE DETAIL
// ==========================================

if ($action === 'report_player_attendance') {
  try {
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $team_id = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;

    $sql = "
      SELECT 
        p.person_id,
        CONCAT(p.f_name, ' ', p.l_name) as player_name,
        t.team_name,
        COUNT(DISTINCT ts.sked_id) as total_sessions,
        SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) as sessions_attended,
        COUNT(DISTINCT ts.sked_id) - SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) as sessions_missed,
        ROUND((SUM(CASE WHEN ta.is_present = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT ts.sked_id)) * 100, 1) as attendance_rate
      FROM tbl_train_sked ts
      JOIN tbl_team t ON t.team_id = ts.team_id
      CROSS JOIN tbl_person p
      LEFT JOIN tbl_train_attend ta ON ta.sked_id = ts.sked_id AND ta.person_id = p.person_id
      WHERE ts.team_id IN (
        SELECT st.team_id 
        FROM tbl_sports_team st 
        WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
      )
      AND p.person_id IN (
        SELECT person_id FROM tbl_team_athletes 
        WHERE team_id = ts.team_id AND is_active = 1
        UNION
        SELECT trainee_id FROM tbl_team_trainees 
        WHERE team_id = ts.team_id AND is_active = 1
      )
    ";

    $params = ['coach_id' => $coach_person_id, 'sports_id' => $sports_id];

    if ($date_from) {
      $sql .= " AND ts.sked_date >= :date_from";
      $params['date_from'] = $date_from;
    }

    if ($date_to) {
      $sql .= " AND ts.sked_date <= :date_to";
      $params['date_to'] = $date_to;
    }

    if ($team_id !== null) {
      $sql .= " AND ts.team_id = :team_id";
      $params['team_id'] = $team_id;
    }

    $sql .= " GROUP BY p.person_id, p.f_name, p.l_name, t.team_name
              HAVING total_sessions > 0
              ORDER BY attendance_rate DESC, player_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    http_response_code(500);
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
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
    $total_matches = (int)$total_stmt->fetch()['count'];
    
    // Upcoming assigned matches (future dates)
    $upcoming_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE match_umpire_id = :person_id 
      AND sked_date >= CURDATE()
    ");
    $upcoming_stmt->execute(['person_id' => $person_id]);
    $upcoming_matches = (int)$upcoming_stmt->fetch()['count'];
    
    // Completed assigned matches (has winner)
    $completed_stmt = $pdo->prepare("
      SELECT COUNT(*) as count 
      FROM tbl_match 
      WHERE match_umpire_id = :person_id 
      AND (winner_team_id IS NOT NULL OR winner_athlete_id IS NOT NULL)
    ");
    $completed_stmt->execute(['person_id' => $person_id]);
    $completed_matches = (int)$completed_stmt->fetch()['count'];
    
    // Active tournaments with assigned matches
    $tournaments_stmt = $pdo->prepare("
      SELECT COUNT(DISTINCT m.tour_id) as count 
      FROM tbl_match m
      JOIN tbl_tournament t ON t.tour_id = m.tour_id
      WHERE m.match_umpire_id = :person_id 
      AND t.is_active = 1
    ");
    $tournaments_stmt->execute(['person_id' => $person_id]);
    $active_tournaments = (int)$tournaments_stmt->fetch()['count'];
    
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
        m.sports_type,
        s.sports_name,
        COALESCE(ta.team_name, CONCAT(pa.f_name, ' ', pa.l_name)) as team_a_name,
        COALESCE(tb.team_name, CONCAT(pb.f_name, ' ', pb.l_name)) as team_b_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id AND m.sports_type = 'team'
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id AND m.sports_type = 'team'
      LEFT JOIN tbl_person pa ON pa.person_id = m.team_a_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_person pb ON pb.person_id = m.team_b_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.match_umpire_id = :person_id 
      AND m.sked_date >= CURDATE()
      ORDER BY m.sked_date, m.sked_time
      LIMIT 10
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        m.sports_type,
        COALESCE(ta.team_name, CONCAT(pa.f_name, ' ', pa.l_name)) as team_a_name,
        COALESCE(tb.team_name, CONCAT(pb.f_name, ' ', pb.l_name)) as team_b_name,
        COALESCE(tw.team_name, CONCAT(pw.f_name, ' ', pw.l_name)) as winner_name
      FROM tbl_match m
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id AND m.sports_type = 'team'
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id AND m.sports_type = 'team'
      LEFT JOIN tbl_person pa ON pa.person_id = m.team_a_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_person pb ON pb.person_id = m.team_b_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
      WHERE m.match_umpire_id = :person_id 
      AND (m.winner_team_id IS NOT NULL OR m.winner_athlete_id IS NOT NULL)
      ORDER BY m.sked_date DESC, m.sked_time DESC
      LIMIT 10
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        m.sports_type,
        s.sports_name,
        COALESCE(ta.team_name, CONCAT(pa.f_name, ' ', pa.l_name)) as team_a_name,
        COALESCE(tb.team_name, CONCAT(pb.f_name, ' ', pb.l_name)) as team_b_name,
        COALESCE(tw.team_name, CONCAT(pw.f_name, ' ', pw.l_name)) as winner_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id AND m.sports_type = 'team'
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id AND m.sports_type = 'team'
      LEFT JOIN tbl_person pa ON pa.person_id = m.team_a_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_person pb ON pb.person_id = m.team_b_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
        m.sports_type,
        s.sports_name,
        COALESCE(ta.team_name, CONCAT(pa.f_name, ' ', pa.l_name)) as team_a_name,
        COALESCE(tb.team_name, CONCAT(pb.f_name, ' ', pb.l_name)) as team_b_name,
        COALESCE(tw.team_name, CONCAT(pw.f_name, ' ', pw.l_name)) as winner_name,
        v.venue_name
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id AND m.sports_type = 'team'
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id AND m.sports_type = 'team'
      LEFT JOIN tbl_person pa ON pa.person_id = m.team_a_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_person pb ON pb.person_id = m.team_b_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.match_umpire_id = :person_id 
      AND (m.winner_team_id IS NOT NULL OR m.winner_athlete_id IS NOT NULL)
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENTS
// ==========================================

if ($action === 'tournaments') {
  try {
    // Get all tournaments with match details for this umpire
    $stmt = $pdo->prepare("
      SELECT 
        t.tour_id,
        t.tour_name,
        t.school_year,
        t.tour_date,
        t.is_active,
        COUNT(m.match_id) as total_matches,
        SUM(CASE WHEN m.sked_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_matches,
        SUM(CASE WHEN (m.winner_team_id IS NOT NULL OR m.winner_athlete_id IS NOT NULL) THEN 1 ELSE 0 END) as completed_matches
      FROM tbl_tournament t
      JOIN tbl_match m ON m.tour_id = t.tour_id
      WHERE m.match_umpire_id = :person_id
      GROUP BY t.tour_id, t.tour_name, t.school_year, t.tour_date, t.is_active
      ORDER BY t.is_active DESC, t.tour_date DESC
    ");
    $stmt->execute(['person_id' => $person_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TOURNAMENT MATCHES
// ==========================================

if ($action === 'tournament_matches') {
  try {
    $tour_id = (int)($_GET['tour_id'] ?? 0);
    
    if ($tour_id <= 0) {
      out(['ok' => false, 'message' => 'Invalid tournament ID']);
    }

    $stmt = $pdo->prepare("
      SELECT 
        m.match_id,
        m.game_no,
        m.sked_date,
        m.sked_time,
        m.match_type,
        m.sports_type,
        s.sports_name,
        COALESCE(ta.team_name, CONCAT(pa.f_name, ' ', pa.l_name)) as team_a_name,
        COALESCE(tb.team_name, CONCAT(pb.f_name, ' ', pb.l_name)) as team_b_name,
        COALESCE(tw.team_name, CONCAT(pw.f_name, ' ', pw.l_name)) as winner_name,
        v.venue_name,
        v.venue_building,
        v.venue_room,
        CASE 
          WHEN m.sked_date > CURDATE() THEN 'upcoming'
          WHEN m.sked_date = CURDATE() THEN 'today'
          WHEN (m.winner_team_id IS NOT NULL OR m.winner_athlete_id IS NOT NULL) THEN 'completed'
          ELSE 'pending'
        END as match_status
      FROM tbl_match m
      JOIN tbl_sports s ON s.sports_id = m.sports_id
      LEFT JOIN tbl_team ta ON ta.team_id = m.team_a_id AND m.sports_type = 'team'
      LEFT JOIN tbl_team tb ON tb.team_id = m.team_b_id AND m.sports_type = 'team'
      LEFT JOIN tbl_person pa ON pa.person_id = m.team_a_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_person pb ON pb.person_id = m.team_b_id AND m.sports_type = 'individual'
      LEFT JOIN tbl_team tw ON tw.team_id = m.winner_team_id
      LEFT JOIN tbl_person pw ON pw.person_id = m.winner_athlete_id
      LEFT JOIN tbl_game_venue v ON v.venue_id = m.venue_id
      WHERE m.tour_id = :tour_id 
      AND m.match_umpire_id = :person_id
      ORDER BY m.sked_date, m.sked_time, m.game_no
    ");
    $stmt->execute(['tour_id' => $tour_id, 'person_id' => $person_id]);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);