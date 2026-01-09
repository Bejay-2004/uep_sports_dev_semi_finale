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

function logActivity($pdo, $user_id, $person_id, $action_type, $description, $module = 'System Administration', $options = []) {
  try {
    // Get user info for detailed logging
    $stmt = $pdo->prepare("
      SELECT u.username, u.user_role, p.f_name, p.l_name, p.role_type 
      FROM tbl_users u 
      JOIN tbl_person p ON p.person_id = u.person_id 
      WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
      $full_name = trim(($user['f_name'] ?? '') . ' ' . ($user['l_name'] ?? ''));
      $user_role = $user['user_role'] ?? 'Unknown';
      
      // Build enhanced log event with full context
      $enhanced_description = "[{$user_role}] {$full_name} {$description}";
    } else {
      $enhanced_description = $description;
    }
    
    // Insert enhanced log
    $stmt = $pdo->prepare("
      INSERT INTO tbl_logs 
      (user_id, log_event, log_date, module_name, action_type, target_table, target_id, old_data, new_data, can_revert) 
      VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
      $user_id,
      $enhanced_description,
      $module,
      $action_type,
      $options['target_table'] ?? null,
      $options['target_id'] ?? null,
      isset($options['old_data']) ? json_encode($options['old_data']) : null,
      isset($options['new_data']) ? json_encode($options['new_data']) : null,
      $options['can_revert'] ?? 0
    ]);
    
    return $pdo->lastInsertId();
  } catch (PDOException $e) {
    error_log("LOG_ACTIVITY ERROR: " . $e->getMessage());
    return false;
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
    // ... existing validation code ...
    
    $pdo->beginTransaction();
    
    $college_code = !empty($input['college_code']) ? $input['college_code'] : null;
    $role_type = $input['role_type'];
    $user_role = $input['user_role'];
    
    // Capture the data being created
    $person_data = [
      'f_name' => $input['f_name'],
      'l_name' => $input['l_name'],
      'm_name' => $input['m_name'] ?? null,
      'role_type' => $role_type,
      'title' => $input['title'] ?? null,
      'date_birth' => $input['date_birth'] ?? null,
      'college_code' => $college_code,
      'course' => $input['course'] ?? null,
      'blood_type' => $input['blood_type'] ?? null,
      'is_active' => 1
    ];
    
    // 1. INSERT into tbl_person
    $stmt = $pdo->prepare("
      INSERT INTO tbl_person 
      (f_name, l_name, m_name, role_type, title, date_birth, college_code, course, blood_type, is_active) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['f_name'],
      $input['l_name'],
      $input['m_name'] ?? null,
      $role_type,
      $input['title'] ?? null,
      $input['date_birth'] ?? null,
      $college_code,
      $input['course'] ?? null,
      $input['blood_type'] ?? null
    ]);
    $new_person_id = $pdo->lastInsertId();
    
    // 2. INSERT into tbl_users
    $stmt = $pdo->prepare("
      INSERT INTO tbl_users (username, password, user_role, person_id, is_active) 
      VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([
      $input['username'],
      password_hash($input['password'], PASSWORD_DEFAULT),
      $user_role,
      $new_person_id
    ]);
    $new_user_id = $pdo->lastInsertId();
    
    // 3. Team assignment if applicable
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
    
    // Enhanced logging with full details
    $college_text = $college_code ? " from college '{$college_code}'" : '';
    logActivity($pdo, $user_id, $person_id, 'create', 
      "created new user account '{$input['username']}' with role type '{$role_type}' and system access as '{$user_role}' for {$input['f_name']} {$input['l_name']}{$college_text}",
      'User Management',
      [
        'target_table' => 'tbl_users',
        'target_id' => $new_user_id,
        'new_data' => [
          'user_id' => $new_user_id,
          'person_id' => $new_person_id,
          'username' => $input['username'],
          'user_role' => $user_role,
          'person_data' => $person_data
        ],
        'can_revert' => 1
      ]
    );
    
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
    // ... existing validation code ...
    
    $pdo->beginTransaction();
    
    // Get OLD data before update
    $stmt = $pdo->prepare("
      SELECT u.*, p.* 
      FROM tbl_users u 
      JOIN tbl_person p ON p.person_id = u.person_id 
      WHERE u.user_id = ?
    ");
    $stmt->execute([$input['user_id']]);
    $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $college_code = !empty($input['college_code']) ? $input['college_code'] : null;
    $role_type = $input['role_type'];
    $user_role = $input['user_role'];
    
    // Update tbl_person
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
      $role_type,
      $input['title'] ?? null,
      $input['date_birth'] ?? null,
      $college_code,
      $input['course'] ?? null,
      $input['blood_type'] ?? null,
      $input['user_id']
    ]);
    
    // Update tbl_users
    $stmt = $pdo->prepare("
      UPDATE tbl_users 
      SET username=?, user_role=? 
      WHERE user_id=?
    ");
    $stmt->execute([
      $input['username'],
      $user_role,
      $input['user_id']
    ]);
    
    $pdo->commit();
    
    // Build detailed change description
    $changes = [];
    if ($old_data['username'] !== $input['username']) {
      $changes[] = "username from '{$old_data['username']}' to '{$input['username']}'";
    }
    if ($old_data['user_role'] !== $user_role) {
      $changes[] = "system role from '{$old_data['user_role']}' to '{$user_role}'";
    }
    if ($old_data['role_type'] !== $role_type) {
      $changes[] = "role type from '{$old_data['role_type']}' to '{$role_type}'";
    }
    if ($old_data['f_name'] !== $input['f_name'] || $old_data['l_name'] !== $input['l_name']) {
      $changes[] = "name from '{$old_data['f_name']} {$old_data['l_name']}' to '{$input['f_name']} {$input['l_name']}'";
    }
    
    $change_text = !empty($changes) ? 'Changed: ' . implode(', ', $changes) : 'Updated user details';
    
    logActivity($pdo, $user_id, $person_id, 'update',
      "updated user account '{$input['username']}'. {$change_text}",
      'User Management',
      [
        'target_table' => 'tbl_users',
        'target_id' => $input['user_id'],
        'old_data' => $old_data,
        'new_data' => $input,
        'can_revert' => 1
      ]
    );
    
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
    
    // Get user info
    $stmt = $pdo->prepare("
      SELECT u.username, CONCAT(p.f_name, ' ', p.l_name) as full_name, u.user_role
      FROM tbl_users u 
      JOIN tbl_person p ON p.person_id = u.person_id 
      WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id_to_toggle]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update both tables
    $stmt = $pdo->prepare("UPDATE tbl_users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([$new_status, $user_id_to_toggle]);
    
    $stmt = $pdo->prepare("
      UPDATE tbl_person 
      SET is_active = ? 
      WHERE person_id = (SELECT person_id FROM tbl_users WHERE user_id = ?)
    ");
    $stmt->execute([$new_status, $user_id_to_toggle]);
    
    $pdo->commit();
    
    $action_text = $new_status ? 'activated' : 'deactivated';
    $action_type = $new_status ? 'activate' : 'deactivate';
    
    logActivity($pdo, $user_id, $person_id, $action_type,
      "{$action_text} user account '{$user_info['username']}' ({$user_info['full_name']}) with role '{$user_info['user_role']}'",
      'User Management',
      [
        'target_table' => 'tbl_users',
        'target_id' => $user_id_to_toggle,
        'old_data' => ['is_active' => $new_status ? 0 : 1],
        'new_data' => ['is_active' => $new_status],
        'can_revert' => 1
      ]
    );
    
    out(['ok' => true, 'message' => "User {$action_text} successfully"]);
    
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
    
    // Get complete user data before deletion
    $stmt = $pdo->prepare("
      SELECT u.*, p.*, CONCAT(p.f_name, ' ', p.l_name) as full_name
      FROM tbl_users u 
      JOIN tbl_person p ON p.person_id = u.person_id 
      WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id_to_delete]);
    $deleted_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete from tbl_users
    $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id_to_delete]);
    
    // Delete from tbl_person
    if ($deleted_user['person_id']) {
      $stmt = $pdo->prepare("DELETE FROM tbl_person WHERE person_id = ?");
      $stmt->execute([$deleted_user['person_id']]);
      
      // Delete from tbl_team_athletes if exists
      $stmt = $pdo->prepare("DELETE FROM tbl_team_athletes WHERE person_id = ?");
      $stmt->execute([$deleted_user['person_id']]);
    }
    
    $pdo->commit();
    
    logActivity($pdo, $user_id, $person_id, 'delete',
      "permanently deleted user account '{$deleted_user['username']}' ({$deleted_user['full_name']}) with role '{$deleted_user['user_role']}' and role type '{$deleted_user['role_type']}'",
      'User Management',
      [
        'target_table' => 'tbl_users',
        'target_id' => $user_id_to_delete,
        'old_data' => $deleted_user,
        'can_revert' => 1
      ]
    );
    
    out(['ok' => true, 'message' => 'User deleted successfully']);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    out(['ok' => false, 'error' => $e->getMessage()]);
  }
}

// ==========================================
// NEW: revert_action endpoint
// ==========================================

if ($action === 'revert_action') {
  try {
    $log_id = (int)$input['log_id'];
    
    $pdo->beginTransaction();
    
    // Get log details
    $stmt = $pdo->prepare("
      SELECT * FROM tbl_logs 
      WHERE log_id = ? AND can_revert = 1 AND reverted_at IS NULL
    ");
    $stmt->execute([$log_id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$log) {
      out(['ok' => false, 'error' => 'Action cannot be reverted or already reverted']);
    }
    
    $old_data = json_decode($log['old_data'], true);
    $new_data = json_decode($log['new_data'], true);
    
    // Revert based on action type
    switch ($log['action_type']) {
      case 'create':
        // Delete the created record
        if ($log['target_table'] === 'tbl_users') {
          $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id = ?");
          $stmt->execute([$log['target_id']]);
          
          if (isset($new_data['person_id'])) {
            $stmt = $pdo->prepare("DELETE FROM tbl_person WHERE person_id = ?");
            $stmt->execute([$new_data['person_id']]);
          }
        }
        break;
        
      case 'delete':
        // Restore the deleted record
        if ($log['target_table'] === 'tbl_users' && $old_data) {
          // Restore person first
          $stmt = $pdo->prepare("
            INSERT INTO tbl_person 
            (person_id, f_name, l_name, m_name, role_type, title, date_birth, 
             college_code, course, blood_type, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
          ");
          $stmt->execute([
            $old_data['person_id'], $old_data['f_name'], $old_data['l_name'],
            $old_data['m_name'], $old_data['role_type'], $old_data['title'],
            $old_data['date_birth'], $old_data['college_code'], $old_data['course'],
            $old_data['blood_type'], $old_data['is_active']
          ]);
          
          // Restore user
          $stmt = $pdo->prepare("
            INSERT INTO tbl_users 
            (user_id, username, password, user_role, person_id, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)
          ");
          $stmt->execute([
            $log['target_id'], $old_data['username'], $old_data['password'],
            $old_data['user_role'], $old_data['person_id'], $old_data['is_active']
          ]);
        }
        break;
        
      case 'update':
        // Restore old values
        if ($log['target_table'] === 'tbl_users' && $old_data) {
          $stmt = $pdo->prepare("
            UPDATE tbl_person 
            SET f_name=?, l_name=?, m_name=?, role_type=?, title=?, 
                date_birth=?, college_code=?, course=?, blood_type=?
            WHERE person_id=?
          ");
          $stmt->execute([
            $old_data['f_name'], $old_data['l_name'], $old_data['m_name'],
            $old_data['role_type'], $old_data['title'], $old_data['date_birth'],
            $old_data['college_code'], $old_data['course'], $old_data['blood_type'],
            $old_data['person_id']
          ]);
          
          $stmt = $pdo->prepare("
            UPDATE tbl_users SET username=?, user_role=? WHERE user_id=?
          ");
          $stmt->execute([
            $old_data['username'], $old_data['user_role'], $log['target_id']
          ]);
        }
        break;
        
      case 'activate':
      case 'deactivate':
        // Toggle back
        $restore_status = $old_data['is_active'];
        $stmt = $pdo->prepare("UPDATE tbl_users SET is_active=? WHERE user_id=?");
        $stmt->execute([$restore_status, $log['target_id']]);
        
        $stmt = $pdo->prepare("
          UPDATE tbl_person SET is_active=? 
          WHERE person_id=(SELECT person_id FROM tbl_users WHERE user_id=?)
        ");
        $stmt->execute([$restore_status, $log['target_id']]);
        break;
    }
    
    // Mark as reverted
    $stmt = $pdo->prepare("
      UPDATE tbl_logs 
      SET reverted_at = NOW(), reverted_by = ? 
      WHERE log_id = ?
    ");
    $stmt->execute([$user_id, $log_id]);
    
    $pdo->commit();
    
    // Log the revert action
    logActivity($pdo, $user_id, $person_id, 'revert',
      "reverted action from log #{$log_id}: \"{$log['log_event']}\"",
      'System Administration',
      [
        'target_table' => 'tbl_logs',
        'target_id' => $log_id,
        'can_revert' => 0
      ]
    );
    
    out(['ok' => true, 'message' => 'Action reverted successfully']);
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    out(['ok' => false, 'error' => 'Revert failed: ' . $e->getMessage()]);
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
        l.action_type,
        l.target_table,
        l.target_id,
        l.old_data,
        l.new_data,
        l.can_revert,
        l.reverted_at,
        l.reverted_by,
        u.username,
        CONCAT(p.f_name, ' ', p.l_name) as user_name
      FROM tbl_logs l
      LEFT JOIN tbl_users u ON u.user_id = l.user_id
      LEFT JOIN tbl_person p ON p.person_id = u.person_id
      WHERE 1=1
    ";
    
    // Filter based on action type
    if ($filter) {
      switch($filter) {
        case 'login':
          $sql .= " AND l.action_type = 'login'";
          break;
        case 'create':
          $sql .= " AND l.action_type = 'create'";
          break;
        case 'update':
          $sql .= " AND l.action_type = 'update'";
          break;
        case 'delete':
          $sql .= " AND l.action_type = 'delete'";
          break;
        case 'activate':
          $sql .= " AND l.action_type IN ('activate', 'deactivate')";
          break;
      }
    }
    
    $sql .= " ORDER BY l.log_date DESC LIMIT $limit";
    
    $stmt = $pdo->query($sql);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set action for compatibility if null
    foreach ($logs as &$log) {
      if (!$log['action_type']) {
        $event_lower = strtolower($log['description']);
        if (strpos($event_lower, 'login') !== false) {
          $log['action_type'] = 'login';
        } elseif (strpos($event_lower, 'logout') !== false) {
          $log['action_type'] = 'logout';
        } elseif (strpos($event_lower, 'created') !== false || strpos($event_lower, 'added') !== false) {
          $log['action_type'] = 'create';
        } elseif (strpos($event_lower, 'updated') !== false) {
          $log['action_type'] = 'update';
        } elseif (strpos($event_lower, 'deleted') !== false) {
          $log['action_type'] = 'delete';
        } elseif (strpos($event_lower, 'activated') !== false) {
          $log['action_type'] = 'activate';
        } elseif (strpos($event_lower, 'deactivated') !== false) {
          $log['action_type'] = 'deactivate';
        }
      }
      $log['action'] = $log['action_type']; // For frontend compatibility
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
        l.action_type,
        CONCAT(p.f_name, ' ', p.l_name) as user_name
      FROM tbl_logs l
      LEFT JOIN tbl_users u ON u.user_id = l.user_id
      LEFT JOIN tbl_person p ON p.person_id = u.person_id
      ORDER BY l.log_date DESC
      LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set action for compatibility
    foreach ($logs as &$log) {
      $log['action'] = $log['action_type'] ?? 'info';
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