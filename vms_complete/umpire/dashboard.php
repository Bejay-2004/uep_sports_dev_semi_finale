<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept 'umpire' role
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'umpire') {
    http_response_code(403);
    die('Access denied. This page is for umpires only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Umpire';
$person_id = (int)$_SESSION['user']['person_id'];

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
  <title>Umpire Dashboard - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/umpire/umpire.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">⚖️</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Umpire Portal</p>
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
      <span>Match Schedule</span>
    </button>

    <button class="nav-link" data-view="results">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
      </svg>
      <span>Match Results</span>
    </button>

    <button class="nav-link" data-view="rankings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 16v-2a4 4 0 0 0-8 0v2"></path>
        <rect x="4" y="16" width="16" height="6"></rect>
        <circle cx="8" cy="9" r="2"></circle>
        <circle cx="16" cy="9" r="2"></circle>
      </svg>
      <span>Rankings & Standings</span>
    </button>

    <button class="nav-link" data-view="medals">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="8" r="7"></circle>
        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
      </svg>
      <span>Medal Tally</span>
    </button>

    <button class="nav-link" data-view="tournaments">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 2v20"></path>
        <path d="M18 2v20"></path>
        <path d="M6 8h12"></path>
        <path d="M6 16h12"></path>
      </svg>
      <span>Tournaments</span>
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
        <div class="user-role">Umpire</div>
      </div>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <div class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">🏅</div>
        <div class="stat-info">
          <div class="stat-value" id="statMatches">0</div>
          <div class="stat-label">Total Matches</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statUpcoming">0</div>
          <div class="stat-label">Upcoming Matches</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);">✅</div>
        <div class="stat-info">
          <div class="stat-value" id="statCompleted">0</div>
          <div class="stat-label">Completed Matches</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">🏆</div>
        <div class="stat-info">
          <div class="stat-value" id="statTournaments">0</div>
          <div class="stat-label">Active Tournaments</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Upcoming Matches</h2>
      <div class="data-grid" id="upcomingMatches">
        <div class="loading">Loading matches...</div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Recent Results</h2>
      <div id="recentResults">
        <div class="loading">Loading results...</div>
      </div>
    </div>
  </div>

  <!-- SCHEDULE VIEW -->
  <div class="content-view" id="schedule-view">
    <div class="view-header">
      <h2>Match Schedule</h2>
      <div class="filter-group">
        <select id="scheduleTournamentFilter" class="form-select">
          <option value="">All Tournaments</option>
        </select>
        <select id="scheduleSportFilter" class="form-select">
          <option value="">All Sports</option>
        </select>
      </div>
    </div>
    <div id="scheduleContent">
      <div class="loading">Loading schedule...</div>
    </div>
  </div>

  <!-- RESULTS VIEW -->
  <div class="content-view" id="results-view">
    <div class="view-header">
      <h2>Match Results</h2>
      <div class="filter-group">
        <select id="resultsTournamentFilter" class="form-select">
          <option value="">All Tournaments</option>
        </select>
        <select id="resultsSportFilter" class="form-select">
          <option value="">All Sports</option>
        </select>
      </div>
    </div>
    <div class="table-container">
      <table class="user-table" id="resultsTable">
        <thead>
          <tr>
            <th>Date</th>
            <th>Sport</th>
            <th>Match Type</th>
            <th>Team A</th>
            <th>Team B</th>
            <th>Winner</th>
            <th>Venue</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;">
              <div class="loading">Loading results...</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- RANKINGS VIEW -->
  <div class="content-view" id="rankings-view">
    <div class="view-header">
      <h2>Rankings & Standings</h2>
      <select id="rankingsTournamentFilter" class="form-select">
        <option value="">Select Tournament</option>
      </select>
    </div>
    <div id="rankingsContent">
      <div class="empty-state">Select a tournament to view standings</div>
    </div>
  </div>

  <!-- MEDALS VIEW -->
  <div class="content-view" id="medals-view">
    <div class="view-header">
      <h2>Medal Tally</h2>
      <select id="medalsTournamentFilter" class="form-select">
        <option value="">Select Tournament</option>
      </select>
    </div>
    <div id="medalsContent">
      <div class="empty-state">Select a tournament to view medal tally</div>
    </div>
  </div>

  <!-- TOURNAMENTS VIEW -->
  <div class="content-view" id="tournaments-view">
    <div class="view-header">
      <h2>Tournaments</h2>
    </div>
    <div class="data-grid" id="tournamentsContent">
      <div class="loading">Loading tournaments...</div>
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.UMPIRE_CONTEXT = {
    person_id: <?= $person_id ?>
  };
</script>

<script src="<?= BASE_URL ?>/umpire/umpire.js"></script>

</body>
</html>