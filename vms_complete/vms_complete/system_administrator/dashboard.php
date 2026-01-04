<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept 'admin', 'administrator', or 'system administrator' role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'admin' && 
    $normalized_role !== 'administrator' && 
    $normalized_role !== 'system_administrator') {
    http_response_code(403);
    die('Access denied. This page is for system administrators only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Administrator';
$person_id = (int)$_SESSION['user']['person_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Administrator - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/system_administrator/admin.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">⚙️</div>
    <div class="sidebar-title">
      <h3>System Admin</h3>
      <p><?= htmlspecialchars($full_name) ?></p>
    </div>
  </div>
  
  <nav class="sidebar-nav">
    <button class="nav-link active" data-view="overview">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4zM3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707zM2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10zm9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5zm.754-4.246a.389.389 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.389.389 0 0 0-.029-.518z"/>
        <path d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 1.602-2.932 1.25C11.309 13.488 9.475 13 8 13c-1.474 0-3.31.488-4.615.911-1.087.352-2.49.003-2.932-1.25A7.988 7.988 0 0 1 0 10zm8-7a7 7 0 0 0-6.603 9.329c.203.575.923.876 1.68.63C4.397 12.533 6.358 12 8 12s3.604.532 4.923.96c.757.245 1.477-.056 1.68-.631A7 7 0 0 0 8 3z"/>
      </svg>
      <span>Overview</span>
    </button>
    
    <button class="nav-link" data-view="users">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>
      </svg>
      <span>User Management</span>
    </button>
    
    <button class="nav-link" data-view="roles">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
        <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
      </svg>
      <span>Roles & Permissions</span>
    </button>
    
    <button class="nav-link" data-view="logs">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8.5 5.5a.5.5 0 0 0-1 0v3.362l-1.429 2.38a.5.5 0 1 0 .858.515l1.5-2.5A.5.5 0 0 0 8.5 9V5.5z"/>
        <path d="M6.5 0a.5.5 0 0 0 0 1H7v1.07a7.001 7.001 0 0 0-3.273 12.474l-.602.602a.5.5 0 0 0 .707.708l.746-.746A6.97 6.97 0 0 0 8 16a6.97 6.97 0 0 0 3.422-.892l.746.746a.5.5 0 0 0 .707-.708l-.601-.602A7.001 7.001 0 0 0 9 2.07V1h.5a.5.5 0 0 0 0-1h-3zm1.038 3.018a6.093 6.093 0 0 1 .924 0 6 6 0 1 1-.924 0zM0 3.5c0 .753.333 1.429.86 1.887A8.035 8.035 0 0 1 4.387 1.86 2.5 2.5 0 0 0 0 3.5zM13.5 1c-.753 0-1.429.333-1.887.86a8.035 8.035 0 0 1 3.527 3.527A2.5 2.5 0 0 0 13.5 1z"/>
      </svg>
      <span>Activity Logs</span>
    </button>
    
    <div class="nav-divider"></div>
    
    <button class="nav-link" data-view="tournaments">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5z"/>
      </svg>
      <span>Tournaments</span>
    </button>
    
    <button class="nav-link" data-view="teams">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
      </svg>
      <span>Teams</span>
    </button>
    
    <button class="nav-link" data-view="athletes">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
      </svg>
      <span>Athletes</span>
    </button>
    
    <button class="nav-link" data-view="system">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
      </svg>
      <span>System Settings</span>
    </button>
  </nav>
  
  <div class="sidebar-footer">
    <form method="post" action="<?= BASE_URL ?>/auth/logout.php" id="logoutForm">
      <button type="button" class="logout-link" onclick="confirmLogout()">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
          <path d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
        </svg>
        <span>Logout</span>
      </button>
    </form>
  </div>
</aside>

<!-- Main Content -->
<main class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <h1 id="pageTitle">System Overview</h1>
    <div class="top-bar-actions">
      <span class="admin-badge">🔒 Full Access</span>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <section class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #dc2626;">👥</div>
        <div class="stat-info">
          <div class="stat-value" id="statUsers">0</div>
          <div class="stat-label">Total Users</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #059669;">✓</div>
        <div class="stat-info">
          <div class="stat-value" id="statActiveUsers">0</div>
          <div class="stat-label">Active Users</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #7c3aed;">🏃</div>
        <div class="stat-info">
          <div class="stat-value" id="statAthletes">0</div>
          <div class="stat-label">Total Athletes</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #d97706;">⚽</div>
        <div class="stat-info">
          <div class="stat-value" id="statSports">0</div>
          <div class="stat-label">Active Sports</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>System Summary</h2>
      <div id="overviewContent">
        <div class="loading">Loading overview...</div>
      </div>
    </div>
  </section>

  <!-- USERS VIEW -->
  <section class="content-view" id="users-view">
    <div class="view-header">
      <div class="search-box">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
        </svg>
        <input type="text" placeholder="Search users..." id="userSearch">
      </div>
      <button class="btn btn-primary" onclick="showUserModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add User
      </button>
    </div>
    <div id="usersContent">
      <div class="loading">Loading users...</div>
    </div>
  </section>

  <!-- ROLES VIEW -->
  <section class="content-view" id="roles-view">
    <div class="view-header">
      <h2>Role Management</h2>
    </div>
    <div id="rolesContent">
      <div class="loading">Loading roles...</div>
    </div>
  </section>

  <!-- LOGS VIEW -->
  <section class="content-view" id="logs-view">
    <div class="view-header">
      <div class="filter-group">
        <select id="logFilter" class="filter-select">
          <option value="">All Activities</option>
          <option value="login">Logins</option>
          <option value="create">Created</option>
          <option value="update">Updated</option>
          <option value="delete">Deleted</option>
        </select>
      </div>
    </div>
    <div id="logsContent">
      <div class="loading">Loading logs...</div>
    </div>
  </section>

  <!-- TOURNAMENTS VIEW -->
  <section class="content-view" id="tournaments-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showTournamentModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add Tournament
      </button>
    </div>
    <div id="tournamentsContent">
      <div class="loading">Loading tournaments...</div>
    </div>
  </section>

  <!-- TEAMS VIEW -->
  <section class="content-view" id="teams-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showTeamModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add Team
      </button>
    </div>
    <div id="teamsContent">
      <div class="loading">Loading teams...</div>
    </div>
  </section>

  <!-- ATHLETES VIEW -->
  <section class="content-view" id="athletes-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showAthleteModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add Athlete
      </button>
    </div>
    <div id="athletesContent">
      <div class="loading">Loading athletes...</div>
    </div>
  </section>

  <!-- SYSTEM SETTINGS VIEW -->
  <section class="content-view" id="system-view">
    <div class="settings-grid">
      <div class="setting-card">
        <h3>🔐 Security Settings</h3>
        <p>Manage password policies and session settings</p>
        <button class="btn btn-secondary">Configure</button>
      </div>
      <div class="setting-card">
        <h3>💾 Database Backup</h3>
        <p>Create and restore database backups</p>
        <button class="btn btn-secondary">Backup Now</button>
      </div>
      <div class="setting-card">
        <h3>📧 Email Settings</h3>
        <p>Configure email notifications</p>
        <button class="btn btn-secondary">Configure</button>
      </div>
      <div class="setting-card">
        <h3>🎨 System Appearance</h3>
        <p>Customize system branding</p>
        <button class="btn btn-secondary">Customize</button>
      </div>
    </div>
  </section>

</main>

<!-- Modals will be loaded dynamically -->
<div id="modalContainer"></div>

<!-- Logout Modal -->
<div class="modal" id="logoutModal">
  <div class="modal-content modal-sm">
    <div class="modal-icon">👋</div>
    <h3>Logout?</h3>
    <p>Are you sure you want to logout?</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeLogoutModal()">Cancel</button>
      <button class="btn btn-danger" onclick="proceedLogout()">Logout</button>
    </div>
  </div>
</div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.ADMIN_CONTEXT = {
    person_id: <?= (int)$person_id ?>
  };

  // Logout functions
  function confirmLogout() {
    document.getElementById('logoutModal').classList.add('active');
  }

  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('active');
  }

  function proceedLogout() {
    document.getElementById('logoutForm').submit();
  }

  document.getElementById('logoutModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
  });
</script>

<script src="<?= BASE_URL ?>/system_administrator/admin.js"></script>

</body>
</html>