<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept 'trainee' role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'trainee') {
    http_response_code(403);
    die('Access denied. This page is for trainees only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Trainee';
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
  <title>Trainee Dashboard - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/trainee/trainee.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">🏃</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Trainee Portal</p>
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

    <button class="nav-link" data-view="attendance">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="9 11 12 14 22 4"></polyline>
        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
      </svg>
      <span>Attendance</span>
    </button>

    <button class="nav-link" data-view="programs">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 6v6l4 2"></path>
      </svg>
      <span>Programs</span>
    </button>

    <button class="nav-link" data-view="performance">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
      </svg>
      <span>Performance</span>
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
        <div class="user-role">Trainee</div>
      </div>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <div class="content-view active" id="overview-view">
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">📅</div>
    <div class="stat-info">
      <div class="stat-value" id="statSessions">0</div>
      <div class="stat-label">Sessions This Month</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">📊</div>
    <div class="stat-info">
      <div class="stat-value" id="statRate">0%</div>
      <div class="stat-label">Attendance Rate</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">🔥</div>
    <div class="stat-info">
      <div class="stat-value" id="statStreak">0</div>
      <div class="stat-label">Current Streak</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">✅</div>
    <div class="stat-info">
      <div class="stat-value" id="statTotal">0</div>
      <div class="stat-label">Total Sessions</div>
    </div>
  </div>
</div>

    <div class="overview-section">
      <h2>Upcoming Training</h2>
      <div class="data-grid" id="upcomingSessions">
        <div class="loading">Loading sessions...</div>
      </div>
    </div>
  </div>

  <!-- SCHEDULE VIEW -->
  <div class="content-view" id="schedule-view">
    <div class="view-header">
      <h2>Training Schedule</h2>
    </div>
    <div id="scheduleContent">
      <div class="loading">Loading schedule...</div>
    </div>
  </div>

  <!-- ATTENDANCE VIEW -->
  <div class="content-view" id="attendance-view">
    <div class="view-header">
      <h2>Attendance History</h2>
    </div>
    <div class="table-container">
      <table class="user-table" id="attendanceTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Training Type</th>
            <th>Duration</th>
            <th>Trainor</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="5" style="text-align:center;padding:40px;">
              <div class="loading">Loading attendance...</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PROGRAMS VIEW -->
  <div class="content-view" id="programs-view">
    <div class="view-header">
      <h2>Training Programs</h2>
    </div>
    <div id="programsContent">
      <div class="loading">Loading programs...</div>
    </div>
  </div>

  <!-- PERFORMANCE VIEW -->
  <div class="content-view" id="performance-view">
    <div class="view-header">
      <h2>Performance Tracking</h2>
    </div>
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">📈 Performance Metrics</div>
      </div>
      <div class="data-card-meta">Track your improvements and personal bests</div>
      <div style="text-align:center;padding:40px 20px;color:var(--muted);">
        Performance tracking coming soon...
      </div>
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.TRAINEE_CONTEXT = {
    person_id: <?= $person_id ?>,
    sports_id: <?= $sports_id ?>
  };
</script>

<script src="<?= BASE_URL ?>/trainee/trainee.js"></script>

</body>
</html>