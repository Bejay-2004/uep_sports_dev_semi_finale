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

// Replace the logActivity function in system_administrator/api.php

function logActivity($pdo, $user_id, $person_id, $action, $description, $module = 'System Administration') {
  try {
    $stmt = $pdo->prepare("
      INSERT INTO tbl_logs (user_id, log_event, log_date, module_name) 
      VALUES (?, ?, NOW(), ?)
    ");
    $stmt->execute([$user_id, $description, $module]);
  } catch (PDOException $e) {
    // Log error but don't stop execution
    error_log("LOG_ACTIVITY ERROR: " . $e->getMessage());
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
      SELECT 
        u.user_id,
        u.person_id,
        u.username,
        u.user_role,
        u.is_active,
        p.f_name,
        p.l_name,
        p.m_name,
        p.title,
        p.date_birth,
        p.college_code,
        p.course,
        p.blood_type,
        p.role_type,
        c.college_name
      FROM tbl_users u
      JOIN tbl_person p ON p.person_id = u.person_id
      LEFT JOIN tbl_college c ON c.college_code = p.college_code
      ORDER BY u.user_id DESC
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_user') {
  try {
    // Validate required fields
    if (empty($input['f_name'])) {
      out(['ok' => false, 'error' => 'First name is required']);
    }
    if (empty($input['l_name'])) {
      out(['ok' => false, 'error' => 'Last name is required']);
    }
    if (empty($input['role_type'])) {
      out(['ok' => false, 'error' => 'Role type is required']);
    }
    if (empty($input['user_role'])) {
      out(['ok' => false, 'error' => 'User role is required']);
    }
    if (empty($input['username'])) {
      out(['ok' => false, 'error' => 'Username is required']);
    }
    if (empty($input['password'])) {
      out(['ok' => false, 'error' => 'Password is required']);
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ?");
    $stmt->execute([$input['username']]);
    if ($stmt->fetch()) {
      out(['ok' => false, 'error' => 'Username already exists']);
    }
    
    $pdo->beginTransaction();
    
    // Handle empty college_code (NULL for FK constraint)
    $college_code = !empty($input['college_code']) ? $input['college_code'] : null;
    
    // ==========================================
    // STORE EXACT VALUES - NO NORMALIZATION
    // ==========================================
    
    // Get the EXACT role_type value (as submitted from form)
    $role_type = $input['role_type']; // Store exactly as selected
    
    // Get the EXACT user_role value (as submitted from form)
    $user_role = $input['user_role']; // Store exactly as selected
    
    // 1. INSERT into tbl_person with EXACT role_type
    $stmt = $pdo->prepare("
      INSERT INTO tbl_person 
      (f_name, l_name, m_name, role_type, title, date_birth, college_code, course, blood_type, is_active) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['f_name'],
      $input['l_name'],
      $input['m_name'] ?? null,
      $role_type,  // EXACT value from form
      $input['title'] ?? null,
      $input['date_birth'] ?? null,
      $college_code,
      $input['course'] ?? null,
      $input['blood_type'] ?? null
    ]);
    $new_person_id = $pdo->lastInsertId();
    
    // 2. INSERT into tbl_users with EXACT user_role
    $stmt = $pdo->prepare("
      INSERT INTO tbl_users (username, password, user_role, person_id, is_active) 
      VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['username'],
      password_hash($input['password'], PASSWORD_DEFAULT),
      $user_role,  // EXACT value from form
      $new_person_id
    ]);
    $new_user_id = $pdo->lastInsertId();
    
    // 3. INSERT into tbl_team_athletes (if applicable)
    if (!empty($input['assign_to_team']) && !empty($input['team_assignment'])) {
      $team_data = $input['team_assignment'];
      
      if (!empty($team_data['team_id']) && !empty($team_data['sports_id'])) {
        $stmt = $pdo->prepare("
          INSERT INTO tbl_team_athletes 
          (tour_id, team_id, sports_id, person_id, is_captain, is_active) 
          VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
          $team_data['tour_id'] ?? null,
          $team_data['team_id'],
          $team_data['sports_id'],
          $new_person_id,
          $team_data['is_captain'] ?? 0
        ]);
      }
    }
    
    $pdo->commit();
    
    // Log activity with EXACT role values
    logActivity($pdo, $user_id, $person_id, 'create', 
      "Created user: {$input['username']} (Role: {$role_type}, Access: {$user_role})");
    
    out([
      'ok' => true, 
      'user_id' => $new_user_id, 
      'person_id' => $new_person_id,
      'message' => 'User created successfully'
    ]);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log("CREATE_USER ERROR: " . $e->getMessage());
    out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
  }
}

if ($action === 'update_user') {
  try {
    if (empty($input['user_id'])) {
      out(['ok' => false, 'error' => 'User ID is required']);
    }
    if (empty($input['f_name'])) {
      out(['ok' => false, 'error' => 'First name is required']);
    }
    if (empty($input['l_name'])) {
      out(['ok' => false, 'error' => 'Last name is required']);
    }
    if (empty($input['role_type'])) {
      out(['ok' => false, 'error' => 'Role type is required']);
    }
    if (empty($input['user_role'])) {
      out(['ok' => false, 'error' => 'User role is required']);
    }
    if (empty($input['username'])) {
      out(['ok' => false, 'error' => 'Username is required']);
    }
    
    // Check if username is taken by another user
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ?");
    $stmt->execute([$input['username'], $input['user_id']]);
    if ($stmt->fetch()) {
      out(['ok' => false, 'error' => 'Username already exists']);
    }
    
    $pdo->beginTransaction();
    
    $college_code = !empty($input['college_code']) ? $input['college_code'] : null;
    
    // ==========================================
    // STORE EXACT VALUES - NO NORMALIZATION
    // ==========================================
    
    $role_type = $input['role_type']; // EXACT value
    $user_role = $input['user_role']; // EXACT value
    
    // 1. UPDATE tbl_person with EXACT role_type
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET f_name=?, l_name=?, m_name=?, role_type=?, title=?, date_birth=?, 
          college_code=?, course=?, blood_type=?
      WHERE person_id=(SELECT person_id FROM tbl_users WHERE user_id=?)
    ");
    $stmt->execute([
      $input['f_name'],
      $input['l_name'],
      $input['m_name'] ?? null,
      $role_type,  // EXACT value
      $input['title'] ?? null,
      $input['date_birth'] ?? null,
      $college_code,
      $input['course'] ?? null,
      $input['blood_type'] ?? null,
      $input['user_id']
    ]);
    
    // 2. UPDATE tbl_users with EXACT user_role
    $stmt = $pdo->prepare("
      UPDATE tbl_users 
      SET username=?, user_role=? 
      WHERE user_id=?
    ");
    $stmt->execute([
      $input['username'],
      $user_role,  // EXACT value
      $input['user_id']
    ]);
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'update', 
      "Updated user: {$input['username']}");
    
    out(['ok' => true, 'message' => 'User updated successfully']);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log("UPDATE_USER ERROR: " . $e->getMessage());
    out(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
  }
}

if ($action === 'toggle_user') {
  try {
    $user_id_to_toggle = (int)$input['user_id'];
    $new_status = (int)$input['is_active'];
    
    $pdo->beginTransaction();
    
    // Update both tables
    $stmt = $pdo->prepare("
      UPDATE tbl_users 
      SET is_active = ? 
      WHERE user_id = ?
    ");
    $stmt->execute([$new_status, $user_id_to_toggle]);
    
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET is_active = ? 
      WHERE person_id = (SELECT person_id FROM tbl_users WHERE user_id = ?)
    ");
    $stmt->execute([$new_status, $user_id_to_toggle]);
    
    $pdo->commit();
    
    $action_text = $new_status ? 'activated' : 'deactivated';
    logActivity($pdo, $user_id, $person_id, 'toggle', 
      "User $action_text (ID: $user_id_to_toggle)");
    
    out(['ok' => true, 'message' => "User $action_text successfully"]);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_user') {
  try {
    $user_id_to_delete = (int)$input['user_id'];
    
    $pdo->beginTransaction();
    
    // Get person_id before deletion
    $stmt = $pdo->prepare("SELECT person_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);
    $person_id_to_delete = $stmt->fetchColumn();
    
    // Delete from tbl_users
    $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);
    
    // Delete from tbl_person
    if ($person_id_to_delete) {
      $stmt = $pdo->prepare("DELETE FROM tbl_person WHERE person_id = ?");
      $stmt->execute([$person_id_to_delete]);
      
      // Delete from tbl_team_athletes if exists
      $stmt = $pdo->prepare("DELETE FROM tbl_team_athletes WHERE person_id = ?");
      $stmt->execute([$person_id_to_delete]);
    }
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'delete', 
      "Deleted user (ID: $user_id_to_delete)");
    
    out(['ok' => true, 'message' => 'User deleted successfully']);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// ACTIVITY LOGS
// ==========================================


if ($action === 'colleges') {
  try {
    $stmt = $pdo->query("
      SELECT college_id, college_code, college_name, college_dean, is_active
      FROM tbl_college 
      WHERE is_active = 1
      ORDER BY college_name
    ");
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// Replace the logs and recent_activities sections in system_administrator/api.php

// ==========================================
// ACTIVITY LOGS (Using tbl_logs)
// ==========================================

if ($action === 'logs') {
  try {
    $filter = $_GET['filter'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);
    
    $sql = "
      SELECT 
        l.log_id,
        l.user_id,
        l.log_event as description,
        l.log_date as created_at,
        l.module_name,
        u.username,
        CONCAT(p.f_name, ' ', p.l_name) as user_name,
        'info' as action
      FROM tbl_logs l
      LEFT JOIN tbl_users u ON u.user_id = l.user_id
      LEFT JOIN tbl_person p ON p.person_id = u.person_id
      WHERE 1=1
    ";
    
    // Simple filter based on keywords in log_event
    if ($filter) {
      switch($filter) {
        case 'login':
          $sql .= " AND LOWER(l.log_event) LIKE '%login%'";
          break;
        case 'create':
          $sql .= " AND (LOWER(l.log_event) LIKE '%created%' OR LOWER(l.log_event) LIKE '%added%')";
          break;
        case 'update':
          $sql .= " AND LOWER(l.log_event) LIKE '%updated%'";
          break;
        case 'delete':
          $sql .= " AND LOWER(l.log_event) LIKE '%deleted%'";
          break;
      }
    }
    
    $sql .= " ORDER BY l.log_date DESC LIMIT $limit";
    
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add action type based on log_event content
    foreach ($logs as &$log) {
      $event_lower = strtolower($log['description']);
      if (strpos($event_lower, 'login') !== false) {
        $log['action'] = 'login';
      } elseif (strpos($event_lower, 'logout') !== false) {
        $log['action'] = 'logout';
      } elseif (strpos($event_lower, 'created') !== false || strpos($event_lower, 'added') !== false) {
        $log['action'] = 'create';
      } elseif (strpos($event_lower, 'updated') !== false) {
        $log['action'] = 'update';
      } elseif (strpos($event_lower, 'deleted') !== false) {
        $log['action'] = 'delete';
      } elseif (strpos($event_lower, 'activated') !== false) {
        $log['action'] = 'activate';
      } elseif (strpos($event_lower, 'deactivated') !== false) {
        $log['action'] = 'deactivate';
      }
      
      // Add ip_address field (not in tbl_logs, so set as N/A)
      $log['ip_address'] = 'N/A';
    }
    
    out($logs);
  } catch (PDOException $e) {
    error_log("LOGS ERROR: " . $e->getMessage());
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'recent_activities') {
  try {
    $limit = (int)($_GET['limit'] ?? 10);
    
    $stmt = $pdo->prepare("
      SELECT 
        l.log_id,
        l.user_id,
        l.log_event as description,
        l.log_date as created_at,
        l.module_name,
        CONCAT(p.f_name, ' ', p.l_name) as user_name,
        'info' as action
      FROM tbl_logs l
      LEFT JOIN tbl_users u ON u.user_id = l.user_id
      LEFT JOIN tbl_person p ON p.person_id = u.person_id
      ORDER BY l.log_date DESC
      LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add action type based on log_event content
    foreach ($logs as &$log) {
      $event_lower = strtolower($log['description']);
      if (strpos($event_lower, 'login') !== false) {
        $log['action'] = 'login';
      } elseif (strpos($event_lower, 'logout') !== false) {
        $log['action'] = 'logout';
      } elseif (strpos($event_lower, 'created') !== false || strpos($event_lower, 'added') !== false) {
        $log['action'] = 'create';
      } elseif (strpos($event_lower, 'updated') !== false) {
        $log['action'] = 'update';
      } elseif (strpos($event_lower, 'deleted') !== false) {
        $log['action'] = 'delete';
      } elseif (strpos($event_lower, 'activated') !== false) {
        $log['action'] = 'activate';
      } elseif (strpos($event_lower, 'deactivated') !== false) {
        $log['action'] = 'deactivate';
      }
    }
    
    out($logs);
  } catch (PDOException $e) {
    error_log("RECENT_ACTIVITIES ERROR: " . $e->getMessage());
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
    $sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
    
    $sql = "
      SELECT DISTINCT
        t.team_id,
        t.team_name,
        s.sports_id,
        s.sports_name
      FROM tbl_team t
      LEFT JOIN tbl_sports_team st ON st.team_id = t.team_id
      LEFT JOIN tbl_sports s ON s.sports_id = st.sports_id
      WHERE t.is_active = 1
    ";
    
    $params = [];
    
    if ($sport_id) {
      $sql .= " AND st.sports_id = :sport_id";
      $params['sport_id'] = $sport_id;
    }
    
    $sql .= " ORDER BY t.team_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    out($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    $stmt = $pdo->query("SELECT * FROM tbl_sports ORDER BY is_active DESC, sports_name");
    out($stmt->fetchAll());
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'create_sport') {
  try {
    $stmt = $pdo->prepare("
      INSERT INTO tbl_sports 
      (sports_name, team_individual, weight_class, men_women, num_req_players, num_res_players, is_active) 
      VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['sports_name'],
      $input['team_individual'],
      $input['weight_class'] ?? null,
      $input['men_women'],
      $input['num_req_players'] ?? null,
      $input['num_res_players'] ?? null
    ]);
    
    logActivity($pdo, $user_id, $person_id, 'create', 
      "Created sport: {$input['sports_name']}");
    
    out(['ok' => true, 'sports_id' => $pdo->lastInsertId()]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'update_sport') {
  try {
    $stmt = $pdo->prepare("
      UPDATE tbl_sports 
      SET sports_name=?, team_individual=?, weight_class=?, men_women=?, 
          num_req_players=?, num_res_players=?
      WHERE sports_id=?
    ");
    $stmt->execute([
      $input['sports_name'],
      $input['team_individual'],
      $input['weight_class'] ?? null,
      $input['men_women'],
      $input['num_req_players'] ?? null,
      $input['num_res_players'] ?? null,
      $input['sports_id']
    ]);
    
    logActivity($pdo, $user_id, $person_id, 'update', 
      "Updated sport: {$input['sports_name']}");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'toggle_sport') {
  try {
    $stmt = $pdo->prepare("UPDATE tbl_sports SET is_active=? WHERE sports_id=?");
    $stmt->execute([$input['is_active'], $input['sports_id']]);
    
    $status = $input['is_active'] == 1 ? 'activated' : 'deactivated';
    logActivity($pdo, $user_id, $person_id, $status, "Sport {$status}");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

if ($action === 'delete_sport') {
  try {
    // Check if sport has associated teams/tournaments
    $check = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_sports_team WHERE sports_id=?");
    $check->execute([$input['sports_id']]);
    $count = $check->fetch()['count'];
    
    if ($count > 0) {
      out(['ok' => false, 'error' => 'Cannot delete sport with existing teams. Deactivate instead.']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM tbl_sports WHERE sports_id=?");
    $stmt->execute([$input['sports_id']]);
    
    logActivity($pdo, $user_id, $person_id, 'delete', "Deleted sport");
    
    out(['ok' => true]);
  } catch (PDOException $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

out(['ok' => false, 'message' => 'Unknown action: ' . $action]);