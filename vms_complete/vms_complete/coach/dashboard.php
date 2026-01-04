<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";
require_role("coach");

$full_name = $_SESSION['user']['full_name'] ?? 'Unknown User';
$sports_id  = (int)($_SESSION['user']['sports_id'] ?? 0);

// Get the sport name from database
$sports_name = 'Unknown Sport';
try {
  $stmt = $pdo->prepare("SELECT sports_name FROM tbl_sports WHERE sports_id = :sports_id LIMIT 1");
  $stmt->execute(['sports_id' => $sports_id]);
  $sport = $stmt->fetch();
  if ($sport) {
    $sports_name = $sport['sports_name'];
  }
} catch (PDOException $e) {
  $sports_name = "Sport #$sports_id";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coach Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/coach/coach.css">
</head>
<body>

<header class="topbar">
  <div class="profile">
    <div class="avatar"></div>
    <div class="meta">
      <div class="name"><?= htmlspecialchars($full_name) ?></div>
      <div class="sub">Role: Coach • Sport: <?= htmlspecialchars($sports_name) ?></div>
    </div>
  </div>

  <form method="post" action="<?= BASE_URL ?>/auth/logout.php">
    <button class="btn danger" type="submit">Logout</button>
  </form>
</header>

<main class="wrap">
  <nav class="tabs">
    <button class="tab active" data-tab="players">Players</button>
    <button class="tab" data-tab="teams">Teams</button>
    <button class="tab" data-tab="standings">Team Stats & Rankings</button>
    <button class="tab" data-tab="tournaments">Tournament Schedules</button>
    <button class="tab" data-tab="training">Training Schedules</button>
  </nav>

  <section class="panel active" id="players">
    <div class="panel-head">
      <h2>Players</h2>
      <input class="search" id="playersSearch" placeholder="Search player/team...">
    </div>
    <div class="card">
      <table class="table" id="playersTable">
        <thead><tr><th>Player</th><th>Team</th><th>Captain</th></tr></thead>
        <tbody><tr><td colspan="3">Loading...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="panel" id="teams">
    <div class="panel-head">
      <h2>Teams</h2>
      <input class="search" id="teamsSearch" placeholder="Search team...">
    </div>
    <div class="card">
      <table class="table" id="teamsTable">
        <thead><tr><th>Team</th><th>Tournament</th><th>Coach</th><th>Assistant Coach</th></tr></thead>
        <tbody><tr><td colspan="4">Loading...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="panel" id="standings">
    <div class="panel-head">
      <h2>Team Statistics & Rankings</h2>
      <small class="hint">Based on <b>tbl_team_standing</b> for your selected sport.</small>
    </div>
    <div class="card">
      <table class="table" id="standingsTable">
        <thead>
          <tr>
            <th>Tournament</th><th>Team</th><th>GP</th><th>W</th><th>L</th><th>D</th>
            <th>Gold</th><th>Silver</th><th>Bronze</th>
          </tr>
        </thead>
        <tbody><tr><td colspan="9">Loading...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="panel" id="tournaments">
    <div class="panel-head">
      <h2>Tournament Schedules</h2>
      <small class="hint">From <b>tbl_match</b> + <b>tbl_tournament</b> + <b>tbl_game_venue</b>.</small>
    </div>
    <div class="card">
      <table class="table" id="matchesTable">
        <thead><tr><th>Date</th><th>Time</th><th>Game #</th><th>Type</th><th>Venue</th><th>Team A</th><th>Team B</th></tr></thead>
        <tbody><tr><td colspan="7">Loading...</td></tr></tbody>
      </table>
    </div>
  </section>

  <section class="panel" id="training">
    <div class="panel-head">
      <h2>Training Schedules</h2>
      <small class="hint">Coach can set schedules via <b>tbl_train_sked</b>.</small>
    </div>

    <div class="grid2">
      <div class="card">
        <h3 class="card-title">Create Training Schedule</h3>
        <form id="trainingForm" class="form">
          <label>Team</label>
          <select id="teamSelect" required>
            <option value="">-- Select Team --</option>
          </select>

          <label>Date</label>
          <input type="date" id="sked_date" required>

          <label>Time</label>
          <input type="time" id="sked_time" required>

          <label for="venueSelect">Venue</label>
          <select id="venueSelect" required>
            <option value="">-- Select Venue --</option>
          </select>

          <button class="btn" type="submit">Save Schedule</button>
          <div class="msg" id="trainingMsg"></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title">My Training Schedules</h3>
        <table class="table" id="trainingTable">
          <thead><tr><th>Team</th><th>Date</th><th>Time</th><th>Venue</th><th>Status</th></tr></thead>
          <tbody><tr><td colspan="5">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

  <footer class="footnote">
    Coach dashboard has no scoring features (no access to tbl_comp_score).
  </footer>
</main>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.COACH_CONTEXT = { 
    sports_id: <?= (int)$sports_id ?>,
    sports_name: "<?= htmlspecialchars($sports_name) ?>"
  };
</script>

<script src="<?= BASE_URL ?>/coach/coach.js"></script>

</body>
</html>