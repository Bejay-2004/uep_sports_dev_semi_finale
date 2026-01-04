<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";
require_role("tournament_manager");

$full_name = $_SESSION['user']['full_name'] ?? 'Unknown User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Tournament Manager Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/tournament_manager/tournament.css">
</head>
<body>

<header class="topbar">
  <div class="profile">
    <div class="avatar"></div>
    <div class="meta">
      <div class="name"><?= htmlspecialchars($full_name) ?></div>
      <div class="sub">Role: Tournament Manager</div>
    </div>
  </div>

  <form method="post" action="<?= BASE_URL ?>/auth/logout.php">
    <button class="btn danger" type="submit">Logout</button>
  </form>
</header>

<main class="wrap">
  <nav class="tabs">
    <button class="tab active" data-tab="tournaments">Tournaments</button>
    <button class="tab" data-tab="sports-select">Select Sports</button>
    <button class="tab" data-tab="matches">Matches & Schedule</button>
    <button class="tab" data-tab="scoring">Scoring</button>
    <button class="tab" data-tab="standings">Standings</button>
    <button class="tab" data-tab="medals">Medal Tally</button>
    <button class="tab" data-tab="venues">Venues</button>
  </nav>

  <!-- MODULE 1: TOURNAMENT CREATION -->
  <section class="panel active" id="tournaments">
    <div class="panel-head">
      <h2>Tournament Management</h2>
      <small class="hint">Create and manage tournaments</small>
    </div>

    <div class="grid2">
      <div class="card">
        <h3 class="card-title">Create New Tournament</h3>
        <form id="tournamentForm" class="form">
          <label>Tournament Name</label>
          <input type="text" id="tour_name" placeholder="e.g., Inter-College Championship 2025" required>

          <label>School Year</label>
          <input type="text" id="school_year" placeholder="e.g., 2024-2025" required>

          <label>Tournament Date</label>
          <input type="date" id="tour_date" required>

          <button class="btn" type="submit">Create Tournament</button>
          <div class="msg" id="tournamentMsg"></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title">Active Tournaments</h3>
        <table class="table" id="tournamentsTable">
          <thead><tr><th>Name</th><th>School Year</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
          <tbody><tr><td colspan="5">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- MODULE 1: SPORT SELECTION -->
  <section class="panel" id="sports-select">
    <div class="panel-head">
      <h2>Select Sports for Tournament</h2>
      <small class="hint">Choose which sports will be included in the tournament</small>
    </div>

    <div class="card">
      <label>Select Tournament</label>
      <select id="sportsTourSelect" class="form-select">
        <option value="">-- Select Tournament --</option>
      </select>

      <div id="sportsSelectionArea" style="display:none;margin-top:20px;">
        <h4>Available Sports</h4>
        <div id="sportsList" class="sports-grid"></div>
        <button class="btn" id="confirmSportsBtn" style="margin-top:16px;">Confirm Selected Sports</button>
        <div class="msg" id="sportsMsg"></div>
      </div>

      <div id="selectedSportsArea" style="margin-top:20px;">
        <h4>Sports in This Tournament</h4>
        <div id="tournamentSportsList"></div>
      </div>
    </div>
  </section>

  <!-- MODULE 2: MATCHES & SCHEDULE -->
  <section class="panel" id="matches">
    <div class="panel-head">
      <h2>Matches & Schedule Management</h2>
      <small class="hint">Create and manage tournament matches</small>
    </div>

    <div class="grid2">
      <div class="card">
        <h3 class="card-title">Create Match</h3>
        <form id="matchForm" class="form">
          <label>Tournament</label>
          <select id="matchTourSelect" required>
            <option value="">-- Select Tournament --</option>
          </select>

          <label>Sport</label>
          <select id="matchSportSelect" required>
            <option value="">-- Select Sport --</option>
          </select>

          <label>Sport Type</label>
          <select id="sports_type" required>
            <option value="">-- Select Type --</option>
            <option value="individual">Individual</option>
            <option value="team">Team</option>
          </select>

          <label>Match Type</label>
          <input type="text" id="match_type" placeholder="e.g., Quarterfinal, Semifinal" required>

          <label>Team A</label>
          <select id="team_a_id" required>
            <option value="">-- Select Team A --</option>
          </select>

          <label>Team B</label>
          <select id="team_b_id" required>
            <option value="">-- Select Team B --</option>
          </select>

          <label>Date</label>
          <input type="date" id="match_date" required>

          <label>Time</label>
          <input type="time" id="match_time" required>

          <label>Venue</label>
          <select id="match_venue" required>
            <option value="">-- Select Venue --</option>
          </select>

          <button class="btn" type="submit">Create Match</button>
          <div class="msg" id="matchMsg"></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title">Scheduled Matches</h3>
        <label>Filter by Tournament</label>
        <select id="matchesFilterTour" class="form-select">
          <option value="">All Tournaments</option>
        </select>
        <table class="table" id="matchesTable" style="margin-top:12px;">
          <thead><tr><th>Date</th><th>Time</th><th>Sport</th><th>Type</th><th>Team A</th><th>Team B</th><th>Venue</th><th>Winner</th></tr></thead>
          <tbody><tr><td colspan="8">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- MODULE 3: SCORING -->
  <section class="panel" id="scoring">
    <div class="panel-head">
      <h2>Score Management</h2>
      <small class="hint">Encode scores and declare winners</small>
    </div>

    <div class="grid2">
      <div class="card">
        <h3 class="card-title">Enter Score</h3>
        <form id="scoreForm" class="form">
          <label>Select Match</label>
          <select id="scoreMatchSelect" required>
            <option value="">-- Select Match --</option>
          </select>

          <label>Competitor (Team/Athlete)</label>
          <select id="scoreCompetitorSelect" required>
            <option value="">-- Select Competitor --</option>
          </select>

          <label>Score/Time/Points</label>
          <input type="text" id="score_value" placeholder="e.g., 95, 10.5s, 3 sets" required>

          <label>Rank</label>
          <input type="number" id="rank_no" min="1" placeholder="1, 2, 3..." required>

          <label>Medal (if applicable)</label>
          <select id="medal_type">
            <option value="">No Medal</option>
            <option value="gold">Gold</option>
            <option value="silver">Silver</option>
            <option value="bronze">Bronze</option>
          </select>

          <button class="btn" type="submit">Save Score</button>
          <div class="msg" id="scoreMsg"></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title">Recent Scores</h3>
        <table class="table" id="scoresTable">
          <thead><tr><th>Match</th><th>Competitor</th><th>Score</th><th>Rank</th><th>Medal</th><th>Action</th></tr></thead>
          <tbody><tr><td colspan="6">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="card" style="margin-top:20px;">
      <h3 class="card-title">Declare Match Winner</h3>
      <form id="winnerForm" class="form">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;align-items:end;">
          <div>
            <label>Select Match</label>
            <select id="winnerMatchSelect" required>
              <option value="">-- Select Match --</option>
            </select>
          </div>
          <div>
            <label>Winner</label>
            <select id="winner_team_id" required>
              <option value="">-- Select Winner --</option>
            </select>
          </div>
          <button class="btn success" type="submit">Declare Winner</button>
        </div>
        <div class="msg" id="winnerMsg"></div>
      </form>
    </div>
  </section>

  <!-- MODULE 3: STANDINGS -->
  <section class="panel" id="standings">
    <div class="panel-head">
      <h2>Team Standings</h2>
      <small class="hint">View tournament standings (auto-computed)</small>
    </div>

    <div class="card">
      <label>Select Tournament</label>
      <select id="standingsTourSelect" class="form-select">
        <option value="">-- Select Tournament --</option>
      </select>

      <table class="table" id="standingsTable" style="margin-top:12px;">
        <thead>
          <tr><th>Team</th><th>Sport</th><th>GP</th><th>W</th><th>L</th><th>D</th><th>Gold</th><th>Silver</th><th>Bronze</th></tr>
        </thead>
        <tbody><tr><td colspan="9">Select a tournament</td></tr></tbody>
      </table>
    </div>
  </section>

  <!-- MODULE 3: MEDAL TALLY -->
  <section class="panel" id="medals">
    <div class="panel-head">
      <h2>Medal Tally</h2>
      <small class="hint">Overall medal count per team</small>
    </div>

    <div class="card">
      <label>Select Tournament</label>
      <select id="medalsTourSelect" class="form-select">
        <option value="">-- Select Tournament --</option>
      </select>

      <table class="table" id="medalsTable" style="margin-top:12px;">
        <thead><tr><th>Rank</th><th>Team</th><th>🥇 Gold</th><th>🥈 Silver</th><th>🥉 Bronze</th><th>Total</th></tr></thead>
        <tbody><tr><td colspan="6">Select a tournament</td></tr></tbody>
      </table>
    </div>
  </section>

  <!-- MODULE 2: VENUES -->
  <section class="panel" id="venues">
    <div class="panel-head">
      <h2>Venue Management</h2>
      <small class="hint">Create and manage game venues</small>
    </div>

    <div class="grid2">
      <div class="card">
        <h3 class="card-title">Create/Edit Venue</h3>
        <form id="venueForm" class="form">
          <input type="hidden" id="venue_id">
          
          <label>Venue Name</label>
          <input type="text" id="venue_name" placeholder="e.g., Main Gymnasium" required>

          <label>Building</label>
          <input type="text" id="venue_building" placeholder="e.g., Sports Complex">

          <label>Room/Court Number</label>
          <input type="text" id="venue_room" placeholder="e.g., Court 1">

          <button class="btn" type="submit">Save Venue</button>
          <button class="btn" type="button" id="clearVenueBtn" style="background:#6b7280;">Clear Form</button>
          <div class="msg" id="venueMsg"></div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title">All Venues</h3>
        <table class="table" id="venuesTable">
          <thead><tr><th>Name</th><th>Building</th><th>Room</th><th>Status</th><th>Action</th></tr></thead>
          <tbody><tr><td colspan="5">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </section>

</main>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
</script>

<script src="<?= BASE_URL ?>/tournament_manager/tournament.js"></script>

</body>
</html>