<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept 'trainor' role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'trainor') {
    http_response_code(403);
    die('Access denied. This page is for trainors only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Trainor';
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)$_SESSION['user']['sports_id'];

// Get initials for avatar
$names = explode(' ', $full_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Trainor Dashboard - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/trainor/trainor.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">💪</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Trainor Portal</p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <button class="nav-link active" data-view="overview">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"></rect>
        <rect x="14" y="3" width="7" height="7"></rect>
        <rect x="14" y="14" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect>
      </svg>
      <span>Overview</span>
    </button>

    <button class="nav-link" data-view="schedule">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>Schedule</span>
    </button>

    <button class="nav-link" data-view="sessions">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2v20M2 12h20"></path>
      </svg>
      <span>Manage Sessions</span>
    </button>

    <button class="nav-link" data-view="attendance">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 11 12 14 22 4"></polyline>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      <span>Attendance</span>
    </button>

    <button class="nav-link" data-view="trainees">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>My Trainees</span>
    </button>

    <button class="nav-link" data-view="activities">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 6v6l4 2"></path>
      </svg>
      <span>Activities</span>
    </button>

    <button class="nav-link" data-view="reports">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
      <span>Reports</span>
    </button>
  </nav>

  <div class="sidebar-footer">
    <form method="post" action="<?= BASE_URL ?>/auth/logout.php" style="margin:0;width:100%;">
      <button type="submit" class="logout-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>Logout</span>
      </button>
    </form>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <button class="menu-toggle" id="menuToggle">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    
    <h1 id="pageTitle">Dashboard Overview</h1>
    
    <div class="user-info">
      <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($full_name) ?></div>
        <div class="user-role">Trainor</div>
      </div>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <div class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">👥</div>
        <div class="stat-info">
          <div class="stat-value" id="statTrainees">0</div>
          <div class="stat-label">Active Trainees</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statSessions">0</div>
          <div class="stat-label">Sessions This Month</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">📊</div>
        <div class="stat-info">
          <div class="stat-value" id="statAttendance">0%</div>
          <div class="stat-label">Avg Attendance Rate</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">⏱️</div>
        <div class="stat-info">
          <div class="stat-value" id="statHours">0h</div>
          <div class="stat-label">Total Hours</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Upcoming Sessions</h2>
      <div class="data-grid" id="upcomingSessions">
        <div class="loading">Loading sessions...</div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Recent Activity</h2>
      <div id="recentActivity">
        <div class="loading">Loading activity...</div>
      </div>
    </div>
  </div>

  <!-- SCHEDULE VIEW -->
  <div class="content-view" id="schedule-view">
    <div class="view-header">
      <h2>Training Schedule</h2>
      <button class="btn btn-primary" id="addSessionBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Add Session
      </button>
    </div>
    <div id="scheduleContent">
      <div class="loading">Loading schedule...</div>
    </div>
  </div>

  <!-- SESSIONS VIEW -->
  <div class="content-view" id="sessions-view">
    <div class="view-header">
      <h2>Manage Training Sessions</h2>
      <button class="btn btn-primary" id="createSessionBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Create New Session
      </button>
    </div>
    
    <div class="session-form card" id="sessionFormCard" style="display:none;">
      <h3>Schedule Training Session</h3>
      <form id="sessionForm" class="form-grid">
        <input type="hidden" id="session_sked_id">
        
        <div class="form-group full-width">
          <label>Team *</label>
          <select id="session_team_id" required>
            <option value="">-- Select Team --</option>
          </select>
        </div>

        <div class="form-group">
          <label>Date *</label>
          <input type="date" id="training_date" required>
        </div>

        <div class="form-group">
          <label>Time *</label>
          <input type="time" id="start_time" required>
        </div>

        <div class="form-group full-width">
          <label>Venue *</label>
          <select id="session_venue_id" required>
            <option value="">-- Select Venue --</option>
          </select>
        </div>

        <div class="form-actions full-width">
          <button type="button" class="btn btn-secondary" id="cancelSessionBtn">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Session</button>
        </div>
        
        <div class="msg" id="sessionMsg"></div>
      </form>
    </div>

    <div id="sessionsListContent">
      <div class="loading">Loading sessions...</div>
    </div>
  </div>

  <!-- ATTENDANCE VIEW -->
  <div class="content-view" id="attendance-view">
    <div class="view-header">
      <h2>Session Attendance</h2>
    </div>
    
    <div class="card" style="margin-bottom:20px;">
      <label>Select Session</label>
      <select id="attendanceSessionSelect" class="form-select">
        <option value="">-- Select Session --</option>
      </select>
    </div>

    <div id="attendanceContent">
      <div class="empty-state">Select a session to view/mark attendance</div>
    </div>
  </div>

  <!-- TRAINEES VIEW -->
  <div class="content-view" id="trainees-view">
    <div class="view-header">
      <h2>My Trainees</h2>
    </div>
    <div class="table-container">
      <table class="user-table" id="traineesTable">
        <thead>
          <tr>
            <th>Name</th>
            <th>Team</th>
            <th>Attendance Rate</th>
            <th>Sessions Attended</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="6" style="text-align:center;padding:40px;">
              <div class="loading">Loading trainees...</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ACTIVITIES VIEW -->
  <div class="content-view" id="activities-view">
    <div class="view-header">
      <h2>Training Activities</h2>
    </div>
    <div id="activitiesContent">
      <div class="loading">Loading activities...</div>
    </div>
  </div>

  <!-- REPORTS VIEW -->
  <div class="content-view" id="reports-view">
    <div class="view-header">
      <h2>Training Reports</h2>
    </div>
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">📊 Generate Reports</div>
      </div>
      <div class="data-card-meta">Export attendance, performance, and training statistics</div>
      <div style="text-align:center;padding:40px 20px;color:var(--muted);">
        Report generation coming soon...
      </div>
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.TRAINOR_CONTEXT = {
    person_id: <?= $person_id ?>,
    sports_id: <?= $sports_id ?>
  };
</script>

<script src="<?= BASE_URL ?>/trainor/trainor.js"></script>

</body>
</html>