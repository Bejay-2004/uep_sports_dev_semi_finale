<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";

// Accept both 'athlete' and 'athlete/player' roles
$user_role = $_SESSION['user']['user_role'] ?? '';
$normalized_role = strtolower(str_replace(['/', ' '], '_', trim($user_role)));

if ($normalized_role !== 'athlete' && $normalized_role !== 'athlete_player') {
    // Not an athlete - deny access
    http_response_code(403);
    die('Access denied. This page is for athletes only.');
}

$full_name = $_SESSION['user']['full_name'] ?? 'Unknown User';
$person_id = (int)$_SESSION['user']['person_id'];

// Get athlete's basic info
try {
  $stmt = $pdo->prepare("
    SELECT 
      p.person_id,
      CONCAT(p.f_name, ' ', p.l_name) AS full_name,
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
  <title>Player Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <link rel="stylesheet" href="<?= BASE_URL ?>/athlete/athlete.css">
</head>
<body>

<!-- Mobile Header -->
<header class="mobile-header">
  <div class="header-top">
    <div class="user-info">
      <div class="avatar-mobile">
        <span><?= substr($full_name, 0, 1) ?></span>
      </div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($full_name) ?></div>
        <div class="user-role">Athlete</div>
      </div>
    </div>
    <form method="post" action="<?= BASE_URL ?>/auth/logout.php" style="margin:0;" id="logoutForm">
      <button class="logout-btn" type="button" onclick="confirmLogout()">
        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
          <path d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
        </svg>
      </button>
    </form>
  </div>
</header>

<!-- Bottom Navigation -->
<nav class="bottom-nav">
  <button class="nav-item active" data-tab="home">
    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
      <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5ZM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5 5 5Z"/>
    </svg>
    <span>Home</span>
  </button>
  <button class="nav-item" data-tab="teams">
    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
      <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
    </svg>
    <span>Teams</span>
  </button>
  <button class="nav-item" data-tab="schedule">
    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
      <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-5 3a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
      <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
    </svg>
    <span>Schedule</span>
  </button>
  <button class="nav-item" data-tab="stats">
    <svg width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
      <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
    </svg>
    <span>Stats</span>
  </button>
</nav>

<!-- Main Content -->
<main class="mobile-content">
  
  <!-- HOME TAB -->
  <section class="tab-panel active" id="home">
    <div class="welcome-card">
      <h2>Welcome back, <?= htmlspecialchars($athlete['f_name'] ?? 'Athlete') ?>! 👋</h2>
      <p class="college-info">
        <?= htmlspecialchars($athlete['college_name'] ?? '') ?>
        <?php if ($athlete['course']): ?>
          • <?= htmlspecialchars($athlete['course']) ?>
        <?php endif; ?>
      </p>
    </div>

    <div class="quick-stats">
      <div class="stat-card">
        <div class="stat-icon">🏆</div>
        <div class="stat-value" id="homeTeamsCount">-</div>
        <div class="stat-label">My Teams</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-value" id="homeUpcomingCount">-</div>
        <div class="stat-label">Upcoming</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💪</div>
        <div class="stat-value" id="homeTrainingCount">-</div>
        <div class="stat-label">Training</div>
      </div>
    </div>

    <div class="section-header">
      <h3>My Teams</h3>
    </div>
    <div class="teams-list" id="homeTeamsList">
      <div class="loading">Loading teams...</div>
    </div>

    <div class="section-header">
      <h3>Upcoming Matches</h3>
    </div>
    <div class="matches-list" id="homeMatchesList">
      <div class="loading">Loading matches...</div>
    </div>
  </section>

  <!-- TEAMS TAB -->
  <section class="tab-panel" id="teams">
    <div class="section-header">
      <h3>My Teams</h3>
    </div>
    <div class="teams-list" id="teamsTabList">
      <div class="loading">Loading teams...</div>
    </div>

    <div class="section-header">
      <h3>Team Players</h3>
    </div>
    <div class="filter-group">
      <select id="teamFilterSelect" class="mobile-select">
        <option value="">All Teams</option>
      </select>
    </div>
    <div class="players-list" id="playersTabList">
      <div class="loading">Loading players...</div>
    </div>
  </section>

  <!-- SCHEDULE TAB -->
  <section class="tab-panel" id="schedule">
    <div class="section-header">
      <h3>Match Schedule</h3>
    </div>
    <div class="matches-list" id="scheduleMatchesList">
      <div class="loading">Loading schedule...</div>
    </div>

    <div class="section-header">
      <h3>Training Schedule</h3>
    </div>
    <div class="training-list" id="scheduleTrainingList">
      <div class="loading">Loading training...</div>
    </div>
  </section>

  <!-- STATS TAB -->
  <section class="tab-panel" id="stats">
    <div class="section-header">
      <h3>Team Rankings</h3>
    </div>
    <div class="filter-group">
      <select id="statsTeamSelect" class="mobile-select">
        <option value="">Select Team</option>
      </select>
    </div>
    <div class="rankings-list" id="statsRankingsList">
      <div class="empty-state">Select a team to view rankings</div>
    </div>
  </section>

</main>

<!-- Logout Confirmation Modal -->
<div class="logout-modal" id="logoutModal">
  <div class="logout-modal-content">
    <div class="logout-modal-header">
      <div class="logout-modal-icon">👋</div>
      <h3 class="logout-modal-title">Logout?</h3>
      <p class="logout-modal-message">Are you sure you want to logout?</p>
    </div>
    <div class="logout-modal-actions">
      <button class="logout-modal-btn logout-modal-cancel" onclick="closeLogoutModal()">
        Cancel
      </button>
      <button class="logout-modal-btn logout-modal-confirm" onclick="proceedLogout()">
        Logout
      </button>
    </div>
  </div>
</div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.ATHLETE_CONTEXT = {
    person_id: <?= (int)$person_id ?>
  };

  // Logout modal functions
  function confirmLogout() {
    document.getElementById('logoutModal').classList.add('active');
  }

  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('active');
  }

  function proceedLogout() {
    document.getElementById('logoutForm').submit();
  }

  // Close modal when clicking outside
  document.getElementById('logoutModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
      closeLogoutModal();
    }
  });
</script>

<script src="<?= BASE_URL ?>/athlete/athlete.js"></script>

</body>
</html>