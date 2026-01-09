<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept both 'athlete' and 'athlete/player' roles
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'athlete' && $normalized_role !== 'athlete_player') {
    http_response_code(403);
    die('Access denied. This page is for athletes only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Athlete';
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)($_SESSION['user']['sports_id'] ?? 0);

// Get initials for avatar
$names = explode(' ', $full_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$initials = substr($initials, 0, 2);

// Get athlete's basic info
try {
  $stmt = $pdo->prepare("
    SELECT 
      p.person_id,
      CONCAT(p.f_name, ' ', p.l_name) AS full_name,
      p.f_name,
      p.college_code,
      p.course,
      c.college_name
    FROM tbl_person p
    LEFT JOIN tbl_college c ON c.college_code = p.college_code
    WHERE p.person_id = :person_id
    LIMIT 1
  ");
  $stmt->execute(['person_id' => $person_id]);
  $athlete = $stmt->fetch();
} catch (PDOException $e) {
  $athlete = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Athlete Dashboard - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/athlete/athlete.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">🏃</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Athlete Portal</p>
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

    <button class="nav-link" data-view="teams">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>My Teams</span>
    </button>

    <button class="nav-link" data-view="players">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>Team Players</span>
    </button>

    <button class="nav-link" data-view="schedule">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>Match Schedule</span>
    </button>

    <button class="nav-link" data-view="training">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <path d="M12 6v6l4 2"></path>
      </svg>
      <span>Training</span>
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

    <button class="nav-link" data-view="rankings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"></path>
      </svg>
      <span>Rankings</span>
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
        <div class="user-role">Athlete</div>
      </div>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <div class="content-view active" id="overview-view">
    <div class="welcome-card">
      <h2>Welcome back, <?= htmlspecialchars($athlete['f_name'] ?? 'Athlete') ?>! 👋</h2>
      <p class="college-info">
        <?= htmlspecialchars($athlete['college_name'] ?? 'University of Eastern Philippines') ?>
        <?php if ($athlete['course']): ?>
          • <?= htmlspecialchars($athlete['course']) ?>
        <?php endif; ?>
      </p>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">🏆</div>
        <div class="stat-info">
          <div class="stat-value" id="statTeams">0</div>
          <div class="stat-label">My Teams</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statUpcoming">0</div>
          <div class="stat-label">Upcoming Matches</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">💪</div>
        <div class="stat-info">
          <div class="stat-value" id="statTraining">0</div>
          <div class="stat-label">Training Sessions</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">🥇</div>
        <div class="stat-info">
          <div class="stat-value" id="statMedals">0</div>
          <div class="stat-label">Total Medals</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>My Teams</h2>
      <div class="data-grid" id="overviewTeams">
        <div class="loading">Loading teams...</div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Upcoming Matches</h2>
      <div id="overviewMatches">
        <div class="loading">Loading matches...</div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Upcoming Training Sessions</h2>
      <div class="data-grid" id="upcomingSessions">
        <div class="loading">Loading sessions...</div>
      </div>
    </div>
  </div>

  <!-- TEAMS VIEW -->
  <div class="content-view" id="teams-view">
    <div class="view-header">
      <h2>My Teams</h2>
    </div>
    <div class="data-grid" id="teamsContent">
      <div class="loading">Loading teams...</div>
    </div>
  </div>

  <!-- PLAYERS VIEW -->
  <div class="content-view" id="players-view">
    <div class="view-header">
      <h2>Team Players</h2>
    </div>
    

    <div class="table-container">
      <table class="user-table" id="playersTable">
        <thead>
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Sport</th>
            <th>Role</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="4" style="text-align:center;padding:40px;">
              <div class="loading">Loading players...</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SCHEDULE VIEW -->
  <div class="content-view" id="schedule-view">
    <div class="view-header">
      <h2>Match Schedule</h2>
    </div>
    <div id="scheduleContent">
      <div class="loading">Loading schedule...</div>
    </div>
  </div>

  <!-- TRAINING VIEW -->
  <div class="content-view" id="training-view">
    <div class="view-header">
      <h2>Training Schedule</h2>
    </div>
    <div id="trainingContent">
      <div class="loading">Loading training...</div>
    </div>
  </div>

  <!-- ATTENDANCE VIEW -->
  <div class="content-view" id="attendance-view">
    <div class="view-header">
      <h2>Training Attendance</h2>
    </div>
    
    <div class="stats-grid" style="margin-bottom:24px;">
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

  <!-- RANKINGS VIEW -->
  <div class="content-view" id="rankings-view">
    <div class="view-header">
      <h2>Team Rankings</h2>
    </div>
    
    <div class="card" style="margin-bottom:20px;">
      <label>Select Team to View Rankings</label>
      <select id="rankingsTeamSelect" class="form-select">
        <option value="">-- Select Team --</option>
      </select>
    </div>

    <div id="rankingsContent">
      <div class="empty-state">Select a team to view rankings</div>
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.ATHLETE_CONTEXT = {
    person_id: <?= $person_id ?>,
    sports_id: <?= $sports_id ?>
  };
</script>

<script src="<?= BASE_URL ?>/athlete/athlete.js"></script>

</body>
</html>