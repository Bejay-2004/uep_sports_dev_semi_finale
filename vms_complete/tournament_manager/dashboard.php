<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";
require_role("tournament_manager");

$full_name = $_SESSION['user']['full_name'] ?? 'Tournament Manager';
$person_id = (int)$_SESSION['user']['person_id'];
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

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">🏆</div>
    <div class="sidebar-title">
      <h3>Tournament Manager</h3>
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
    
    <button class="nav-link" data-view="tournaments">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5z"/>
      </svg>
      <span>Tournaments</span>
    </button>
    
    <button class="nav-link" data-view="sports-select">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
        <path d="M7 11.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5z"/>
      </svg>
      <span>Select Sports</span>
    </button>
    
    <button class="nav-link" data-view="team-select">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
      </svg>
      <span>Select Teams</span>
    </button>
    
    <button class="nav-link" data-view="matches">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1zm-3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
      </svg>
      <span>Matches</span>
    </button>
    
    <button class="nav-link" data-view="scoring">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z"/>
      </svg>
      <span>Scoring</span>
    </button>
    
    <button class="nav-link" data-view="standings">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z"/>
      </svg>
      <span>Standings</span>
    </button>
    
    <button class="nav-link" data-view="medals">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M9.669.864 8 0 6.331.864l-1.858.282-.842 1.68-1.337 1.32L2.6 6l-.306 1.854 1.337 1.32.842 1.68 1.858.282L8 12l1.669-.864 1.858-.282.842-1.68 1.337-1.32L13.4 6l.306-1.854-1.337-1.32-.842-1.68L9.669.864zm1.196 1.193.684 1.365 1.086 1.072L12.387 6l.248 1.506-1.086 1.072-.684 1.365-1.51.229L8 10.874l-1.355-.702-1.51-.229-.684-1.365-1.086-1.072L3.614 6l-.25-1.506 1.087-1.072.684-1.365 1.51-.229L8 1.126l1.356.702 1.509.229z"/>
        <path d="M4 11.794V16l4-1 4 1v-4.206l-2.018.306L8 13.126 6.018 12.1 4 11.794z"/>
      </svg>
      <span>Medal Tally</span>
    </button>
    
    <button class="nav-link" data-view="venues">
      <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path d="M6.5 14.5v-3.505c0-.245.25-.495.5-.495h2c.25 0 .5.25.5.5v3.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.146-.354L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.354 1.146a.5.5 0 0 0-.708 0l-6 6A.5.5 0 0 0 1.5 7.5v7a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 .5-.5z"/>
      </svg>
      <span>Venues</span>
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
      <button class="btn btn-primary" onclick="openPrintModal()" style="display:flex;align-items:center;gap:6px;">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
          <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
        </svg>
        Print Report
      </button>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <section class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background: #3b82f6;">🏆</div>
        <div class="stat-info">
          <div class="stat-value" id="statTournaments">0</div>
          <div class="stat-label">Active Tournaments</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #8b5cf6;">⚽</div>
        <div class="stat-info">
          <div class="stat-value" id="statSports">0</div>
          <div class="stat-label">Sports Included</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #10b981;">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statMatches">0</div>
          <div class="stat-label">Scheduled Matches</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background: #f59e0b;">✓</div>
        <div class="stat-info">
          <div class="stat-value" id="statCompleted">0</div>
          <div class="stat-label">Completed Matches</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Quick Actions</h2>
      <div class="data-grid" style="margin-top:12px;">
        <div class="data-card" onclick="setTab('tournaments')" style="cursor:pointer;">
          <div class="data-card-header">
            <div class="data-card-title">Create Tournament</div>
            <span style="font-size:24px;">➕</span>
          </div>
          <div class="data-card-meta">Set up a new tournament with sports and teams</div>
        </div>
        <div class="data-card" onclick="setTab('matches')" style="cursor:pointer;">
          <div class="data-card-header">
            <div class="data-card-title">Schedule Match</div>
            <span style="font-size:24px;">📅</span>
          </div>
          <div class="data-card-meta">Create and manage tournament matches</div>
        </div>
        <div class="data-card" onclick="setTab('scoring')" style="cursor:pointer;">
          <div class="data-card-header">
            <div class="data-card-title">Enter Scores</div>
            <span style="font-size:24px;">⭐</span>
          </div>
          <div class="data-card-meta">Record match results and declare winners</div>
        </div>
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
        Create Tournament
      </button>
    </div>
    <div id="tournamentsContent">
      <div class="loading">Loading tournaments...</div>
    </div>
  </section>

  <!-- SPORTS SELECTION VIEW -->
  <section class="content-view" id="sports-select-view">
    <div class="card">
      <div class="form-group">
        <label class="form-label">Select Tournament</label>
        <select id="sportsTourSelect" class="form-control">
          <option value="">-- Select Tournament --</option>
        </select>
      </div>

      <div id="selectedSportsArea" style="margin-top:20px;">
        <h4 style="font-size:15px;font-weight:600;margin-bottom:12px;">Sports in This Tournament</h4>
        <div id="tournamentSportsList">
          <p class="empty-state">Select a tournament to view its sports</p>
        </div>
      </div>

      <div id="sportsSelectionArea" style="display:none;margin-top:24px;">
        <h4 style="font-size:15px;font-weight:600;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid var(--border);">Available Sports - Select to Add</h4>
        <div id="sportsList" class="sports-grid"></div>
        <button class="btn btn-success" id="confirmSportsBtn" style="margin-top:16px;">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
          </svg>
          Confirm Selected Sports
        </button>
        <div class="msg" id="sportsMsg"></div>
      </div>
    </div>
  </section>

  <!-- TEAM SELECTION VIEW -->
  <section class="content-view" id="team-select-view">
    <div class="card">
      <div class="form-group">
        <label class="form-label">Select Tournament</label>
        <select id="teamsTourSelect" class="form-control">
          <option value="">-- Select Tournament --</option>
        </select>
      </div>

      <div id="teamsSelectionArea" style="display:none;margin-top:20px;">
        <div id="sportTeamsContainer"></div>
      </div>
    </div>
  </section>

  <!-- MATCHES VIEW -->
  <section class="content-view" id="matches-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showScheduleMatchModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Schedule Match
      </button>
    </div>

    <div style="margin-bottom:16px;">
      <label class="form-label">Filter by Tournament</label>
      <select id="matchesFilterTour" class="form-control" style="max-width:400px;">
        <option value="">All Tournaments</option>
      </select>
    </div>

    <div id="matchesContent">
      <table class="table" id="matchesTable">
        <thead>
          <tr>
            <th>Game No.</th>
            <th>Date</th>
            <th>Time</th>
            <th>Sport</th>
            <th>Type</th>
            <th>Team A</th>
            <th>Team B</th>
            <th>Venue</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="9" class="loading">Loading matches...</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- SCORING VIEW -->
  <section class="content-view" id="scoring-view">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
      <!-- Enter Score Card -->
      <div class="card">
        <h3 class="card-title">Enter Score</h3>
        <form id="scoreForm" class="form">
          <div class="form-group">
            <label class="form-label">Select Match *</label>
            <select id="scoreMatchSelect" class="form-control" required>
              <option value="">-- Select Match --</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Competitor (Team/Athlete) *</label>
            <select id="scoreCompetitorSelect" class="form-control" required>
              <option value="">-- Select Competitor --</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Score/Time/Points *</label>
            <input type="text" id="score_value" class="form-control" placeholder="e.g., 95, 10.5s, 3 sets" required>
          </div>

          <div class="form-group">
            <label class="form-label">Rank *</label>
            <input type="number" id="rank_no" class="form-control" min="1" placeholder="1, 2, 3..." required>
          </div>

          <div class="form-group">
            <label class="form-label">Medal (if applicable)</label>
            <select id="medal_type" class="form-control">
              <option value="">No Medal</option>
              <option value="gold">Gold</option>
              <option value="silver">Silver</option>
              <option value="bronze">Bronze</option>
            </select>
          </div>

          <button class="btn btn-success" type="submit">Save Score</button>
          <div class="msg" id="scoreMsg"></div>
        </form>
      </div>

      <!-- Recent Scores Card -->
      <div class="card">
        <h3 class="card-title">Recent Scores</h3>
        <div style="max-height:500px;overflow-y:auto;">
          <table class="table" id="scoresTable">
            <thead>
              <tr>
                <th>Match</th>
                <th>Competitor</th>
                <th>Score</th>
                <th>Rank</th>
                <th>Medal</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr><td colspan="6" class="loading">Loading scores...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Declare Winner Section -->
    <div class="card">
      <h3 class="card-title">Declare Match Winner</h3>
      <form id="winnerForm" class="form">
        <div style="display:grid;grid-template-columns:2fr 1fr 120px;gap:12px;align-items:end;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Select Match *</label>
            <select id="winnerMatchSelect" class="form-control" required>
              <option value="">-- Select Match --</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Winner *</label>
            <select id="winner_team_id" class="form-control" required>
              <option value="">-- Select Winner --</option>
            </select>
          </div>
          <button class="btn btn-success" type="submit" style="height:38px;">Declare Winner</button>
        </div>
        <div class="msg" id="winnerMsg"></div>
      </form>
    </div>
  </section>

  <!-- STANDINGS VIEW -->
  <section class="content-view" id="standings-view">
    <div class="card">
      <div class="form-group">
        <label class="form-label">Select Tournament</label>
        <select id="standingsTourSelect" class="form-control" style="max-width:400px;">
          <option value="">-- Select Tournament --</option>
        </select>
      </div>

      <div style="margin-top:16px;">
        <table class="table" id="standingsTable">
          <thead>
            <tr>
              <th>Team</th>
              <th>Sport</th>
              <th>GP</th>
              <th>W</th>
              <th>L</th>
              <th>D</th>
              <th>🥇 Gold</th>
              <th>🥈 Silver</th>
              <th>🥉 Bronze</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="9" class="empty-state">Select a tournament to view standings</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- MEDALS VIEW -->
  <section class="content-view" id="medals-view">
    <div class="card">
      <div class="form-group">
        <label class="form-label">Select Tournament</label>
        <select id="medalsTourSelect" class="form-control" style="max-width:400px;">
          <option value="">-- Select Tournament --</option>
        </select>
      </div>

      <div style="margin-top:16px;">
        <table class="table" id="medalsTable">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Team</th>
              <th>🥇 Gold</th>
              <th>🥈 Silver</th>
              <th>🥉 Bronze</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="6" class="empty-state">Select a tournament to view medal tally</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- VENUES VIEW -->
  <section class="content-view" id="venues-view">
    <div class="view-header">
      <button class="btn btn-primary" onclick="showVenueModal()">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
        </svg>
        Add Venue
      </button>
    </div>
    <div id="venuesContent">
      <div class="loading">Loading venues...</div>
    </div>
  </section>

</main>

<!-- Modals -->
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

<!-- PRINT MODAL -->
<div id="printModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Print Tournament Report</h2>
      <span class="close-modal" onclick="closePrintModal()">&times;</span>
    </div>
    
    <div class="modal-body">
      <div class="form">
        <div class="form-group">
          <label class="form-label">Select Tournament to Print</label>
          <select id="printTourSelect" class="form-control" required>
            <option value="">-- Select Tournament --</option>
          </select>
        </div>

        <div id="printOptions" style="display:none; margin-top:16px;">
          <label class="form-label" style="margin-bottom:12px; display:block;">Select Report Sections to Include:</label>
          
          <div class="checkbox-group">
            <label class="checkbox-label">
              <input type="checkbox" id="print_overview" checked>
              <span>Tournament Overview & Details</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_sports" checked>
              <span>Sports & Categories</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_teams" checked>
              <span>Participating Teams</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_players" checked>
              <span>Team Rosters (Players)</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_coaches" checked>
              <span>Coaches & Team Officials</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_matches" checked>
              <span>Match Schedule</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_results" checked>
              <span>Match Results & Scores</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_standings" checked>
              <span>Team Standings</span>
            </label>
            
            <label class="checkbox-label">
              <input type="checkbox" id="print_medals" checked>
              <span>Medal Tally</span>
            </label>

            <label class="checkbox-label">
              <input type="checkbox" id="print_officials" checked>
              <span>Tournament Officials & Signatures</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closePrintModal()">Cancel</button>
      <button class="btn btn-primary" id="generatePrintBtn" onclick="generatePrintReport()" disabled>Generate Report</button>
    </div>
  </div>
</div>

<!-- PRINT PREVIEW AREA (Hidden until generated) -->
<div id="printPreview" style="display:none;"></div>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  
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

<script src="<?= BASE_URL ?>/tournament_manager/tournament.js"></script>
</body>
</html>