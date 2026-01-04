<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept 'sports_director' or 'sports director' role (database stores as 'sports director')
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'sports_director') {
    http_response_code(403);
    die('Access denied. This page is for sports directors only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Sports Director';
$person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)($_SESSION['user']['sports_id'] ?? 0); // Will be NULL/0 for sports director
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sports Director Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/sports_director/director.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">🏆</div>
    <div class="sidebar-title">
      <h3>Sports Director</h3>
      <p><?= htmlspecialchars($full_name) ?></p>
    </div>
  </div>
  
  <nav class="sidebar-nav">
    <button class="nav-link active" data-view="overview">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
      </svg>
      <span>Overview</span>
    </button>
    
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
    
    <button class="nav-link" data-view="matches">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
      </svg>
      <span>Matches</span>
    </button>
    
    <button class="nav-link" data-view="training">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5V2zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1H4z"/>
      </svg>
      <span>Training</span>
    </button>
    
    <button class="nav-link" data-view="standings">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
      </svg>
      <span>Standings</span>
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
    <h1 id="pageTitle">Overview</h1>
    <div class="top-bar-actions">
      <select id="globalSportFilter" class="filter-select">
        <option value="">All Sports</option>
      </select>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <section class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #3b82f6;">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statTournaments">0</div>
          <div class="stat-label">Active Tournaments</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #8b5cf6;">👥</div>
        <div class="stat-info">
          <div class="stat-value" id="statTeams">0</div>
          <div class="stat-label">Total Teams</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #10b981;">🏃</div>
        <div class="stat-info">
          <div class="stat-value" id="statAthletes">0</div>
          <div class="stat-label">Active Athletes</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f59e0b;">⚡</div>
        <div class="stat-info">
          <div class="stat-value" id="statMatches">0</div>
          <div class="stat-label">Upcoming Matches</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Recent Activity</h2>
      <div id="overviewContent">
        <div class="loading">Loading overview...</div>
      </div>
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

  <!-- MATCHES VIEW -->
  <section class="content-view" id="matches-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showMatchModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Schedule Match
      </button>
    </div>
    <div id="matchesContent">
      <div class="loading">Loading matches...</div>
    </div>
  </section>

  <!-- TRAINING VIEW -->
  <section class="content-view" id="training-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showTrainingModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add Training
      </button>
    </div>
    <div id="trainingContent">
      <div class="loading">Loading training...</div>
    </div>
  </section>

  <!-- STANDINGS VIEW -->
  <section class="content-view" id="standings-view">
    <div id="standingsContent">
      <div class="loading">Loading standings...</div>
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
  window.DIRECTOR_CONTEXT = {
    person_id: <?= (int)$person_id ?>,
    sports_id: <?= (int)$sports_id ?>
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

<script src="<?= BASE_URL ?>/sports_director/director.js"></script>
<script src="<?= BASE_URL ?>/sports_director/director_modals.js"></script>
</body>
</html>