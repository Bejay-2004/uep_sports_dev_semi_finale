<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/guard.php';

// Only admin/administrator/system administrator role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'admin' && 
    $normalized_role !== 'administrator' && 
    $normalized_role !== 'system_administrator') {
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

function logActivity($pdo, $user_id, $person_id, $action, $description) {
  try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("
      INSERT INTO tbl_activity_log (user_id, person_id, action, description, ip_address) 
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $person_id, $action, $description, $ip]);
  } catch (PDOException $e) {
    // Log table might not exist, continue silently
  }
}

// ==========================================
// ADMIN STATISTICS
// ==========================================

if ($action === 'admin_stats') {
  try {
    $total_users = $pdo->query("SELECT COUNT(*) as count FROM tbl_users")->fetch()['count'];
    $active_users = $pdo->query("SELECT COUNT(*) as count FROM tbl_users WHERE is_active=1")->fetch()['count'];
    $total_athletes = $pdo->query("SELECT COUNT(*) as count FROM tbl_person WHERE role_type IN ('athlete','athlete/player') AND is_active=1")->fetch()['count'];
    $active_sports = $pdo->query("SELECT COUNT(*) as count FROM tbl_sports WHERE is_active=1")->fetch()['count'];
    
    out([
      'total_users' => $total_users,
      'active_users' => $active_users,
      'total_athletes' => $total_athletes,
      'active_sports' => $active_sports
    ]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// USER MANAGEMENT
// ==========================================

if ($action === 'users') {
  try {
    $stmt = $pdo->query("
      SELECT u.*, p.f_name, p.l_name, p.m_name, p.college_code, p.course
      FROM tbl_users u
      JOIN tbl_person p ON p.person_id = u.person_id
      ORDER BY u.user_id DESC
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_user') {
  try {
    $pdo->beginTransaction();
    
    // Create person
    $stmt = $pdo->prepare("
      INSERT INTO tbl_person (f_name, l_name, role_type, is_active) 
      VALUES (?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['f_name'],
      $input['l_name'],
      $input['user_role']
    ]);
    $new_person_id = $pdo->lastInsertId();
    
    // Create user
    $stmt = $pdo->prepare("
      INSERT INTO tbl_users (username, password, user_role, person_id, is_active) 
      VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['username'],
      $input['password'], // Should be hashed in production
      $input['user_role'],
      $new_person_id
    ]);
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'create', 
      "Created user: {$input['username']} ({$input['user_role']})");
    
    out(['ok' => true, 'user_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_user') {
  try {
    $pdo->beginTransaction();
    
    // Update person
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET f_name=?, l_name=?, role_type=? 
      WHERE person_id=(SELECT person_id FROM tbl_users WHERE user_id=?)
    ");
    $stmt->execute([
      $input['f_name'],
      $input['l_name'],
      $input['user_role'],
      $input['user_id']
    ]);
    
    // Update user
    $stmt = $pdo->prepare("
      UPDATE tbl_users 
      SET username=?, user_role=? 
      WHERE user_id=?
    ");
    $stmt->execute([
      $input['username'],
      $input['user_role'],
      $input['user_id']
    ]);
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'update', 
      "Updated user: {$input['username']}");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_user') {
  try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE tbl_users SET is_active=? WHERE user_id=?");
    $stmt->execute([$input['is_active'], $input['user_id']]);
    
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET is_active=? 
      WHERE person_id=(SELECT person_id FROM tbl_users WHERE user_id=?)
    ");
    $stmt->execute([$input['is_active'], $input['user_id']]);
    
    $pdo->commit();
    
    $status = $input['is_active'] == 1 ? 'activated' : 'deactivated';
    logActivity($pdo, $user_id, $person_id, $status, "User {$status}");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_user') {
  try {
    $get_person = $pdo->prepare("SELECT person_id, username FROM tbl_users WHERE user_id=?");
    $get_person->execute([$input['user_id']]);
    $user_data = $get_person->fetch();
    
    if (!$user_data) {
      out(['ok' => false, 'error' => 'User not found']);
    }
    
    $pdo->beginTransaction();
    
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id=?");
    $stmt->execute([$input['user_id']]);
    
    // Delete person (if no other references)
    $stmt = $pdo->prepare("DELETE FROM tbl_person WHERE person_id=?");
    $stmt->execute([$user_data['person_id']]);
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'delete', 
      "Deleted user: {$user_data['username']}");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ACTIVITY LOGS
// ==========================================

if ($action === 'logs') {
  try {
    $filter = $_GET['filter'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    
    $sql = "
      SELECT l.*, 
             u.username,
             CONCAT(p.f_name, ' ', p.l_name) as user_name
      FROM tbl_activity_log l
      LEFT JOIN tbl_users u ON u.user_id = l.user_id
      LEFT JOIN tbl_person p ON p.person_id = l.person_id
      WHERE 1=1
    ";
    
    if ($filter) {
      $sql .= " AND l.action = " . $pdo->quote($filter);
    }
    
    $sql .= " ORDER BY l.created_at DESC LIMIT $limit";
    
    $stmt = $pdo->query($sql);
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'recent_activities') {
  try {
    $limit = (int)($_GET['limit'] ?? 10);
    
    $stmt = $pdo->query("
      SELECT l.*,
             CONCAT(p.f_name, ' ', p.l_name) as user_name
      FROM tbl_activity_log l
      LEFT JOIN tbl_person p ON p.person_id = l.person_id
      ORDER BY l.created_at DESC
      LIMIT $limit
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out([]);
  }
}

// ==========================================
// TOURNAMENTS (Admin sees ALL)
// ==========================================

if ($action === 'tournaments') {
  try {
    $stmt = $pdo->query("SELECT * FROM tbl_tournament ORDER BY tour_date DESC");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_tournament') {
  try {
    $stmt = $pdo->prepare("DELETE FROM tbl_tournament WHERE tour_id=?");
    $stmt->execute([$input['tour_id']]);
    
    logActivity($pdo, $user_id, $person_id, 'delete', "Deleted tournament");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// TEAMS (Admin sees ALL)
// ==========================================

if ($action === 'teams') {
  try {
    $stmt = $pdo->query("
      SELECT t.*, s.sports_name, 
             COUNT(DISTINCT ta.person_id) as num_players
      FROM tbl_team t
      LEFT JOIN tbl_sports_team st ON st.team_id = t.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      LEFT JOIN tbl_team_athletes ta ON ta.team_id = t.team_id AND ta.is_active=1
      GROUP BY t.team_id
      ORDER BY s.sports_name, t.team_name
    ");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ATHLETES (Admin sees ALL)
// ==========================================

if ($action === 'athletes') {
  try {
    $stmt = $pdo->query("
      SELECT DISTINCT p.person_id, p.f_name, p.l_name, p.is_active,
             CONCAT(p.f_name, ' ', p.l_name) as athlete_name,
             t.team_name, s.sports_name
      FROM tbl_person p
      LEFT JOIN tbl_team_athletes ta ON ta.person_id = p.person_id
      LEFT JOIN tbl_team t ON t.team_id = ta.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = ta.sports_id
      WHERE p.role_type IN ('athlete', 'athlete/player')
      ORDER BY s.sports_name, p.l_name, p.f_name
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
    $stmt = $pdo->query("SELECT * FROM tbl_sports WHERE is_active=1 ORDER BY sports_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);