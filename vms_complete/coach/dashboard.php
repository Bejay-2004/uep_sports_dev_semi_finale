<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";
require_role("coach");

$full_name = $_SESSION['user']['full_name'] ?? 'Unknown User';
$coach_person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)($_SESSION['user']['sports_id'] ?? 0);

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
  <title>Coach Dashboard - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/coach/coach.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">⚽</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Coach Portal</p>
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

    <button class="nav-link" data-view="players">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
      <span>Players</span>
    </button>

    <button class="nav-link" data-view="teams">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="7" height="7"></rect>
        <rect x="14" y="3" width="7" height="7"></rect>
        <rect x="14" y="14" width="7" height="7"></rect>
        <rect x="3" y="14" width="7" height="7"></rect>
      </svg>
      <span>Teams</span>
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

    <button class="nav-link" data-view="performance">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 20V10"></path>
        <path d="M12 20V4"></path>
        <path d="M6 20v-6"></path>
      </svg>
      <span>Performance</span>
    </button>

    <button class="nav-link" data-view="standings">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
      </svg>
      <span>Statistics</span>
    </button>

    <button class="nav-link" data-view="tournaments">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <path d="M16 10a4 4 0 0 1-8 0"></path>
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
        <div class="user-role">Coach • <?= htmlspecialchars($sports_name) ?></div>
      </div>
    </div>
  </div>

  <!-- OVERVIEW VIEW -->
  <div class="content-view active" id="overview-view">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">👥</div>
        <div class="stat-info">
          <div class="stat-value" id="statPlayers">0</div>
          <div class="stat-label">Active Players</div>
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
        <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);">🏆</div>
        <div class="stat-info">
          <div class="stat-value" id="statTeams">0</div>
          <div class="stat-label">My Teams</div>
        </div>
      </div>
    </div>

    <div class="overview-section">
      <h2>Upcoming Training Sessions</h2>
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

  <!-- PLAYERS VIEW -->
  <div class="content-view" id="players-view">
    <div class="view-header">
      <h2>My Players</h2>
      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; flex: 1;">
        <input class="search" id="playersSearch" placeholder="Search player/team..." style="flex: 1;">
        <a href="<?= BASE_URL ?>/coach/add_player.php" class="btn">+ Add Player</a>
      </div>
    </div>
    <div class="table-container">
      <table class="table" id="playersTable">
        <thead><tr><th>Player</th><th>Team</th><th>Captain</th><th>Actions</th></tr></thead>
        <tbody><tr><td colspan="4">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- TEAMS VIEW -->
  <div class="content-view" id="teams-view">
    <div class="view-header">
      <h2>My Teams</h2>
      <input class="search" id="teamsSearch" placeholder="Search team...">
    </div>
    <div class="table-container">
      <table class="table" id="teamsTable">
        <thead><tr><th>Team</th><th>Tournament</th><th>Coach</th><th>Assistant Coach</th></tr></thead>
        <tbody><tr><td colspan="4">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- TRAINING VIEW -->
  <div class="content-view" id="training-view">
    <div class="view-header">
      <h2>Training Sessions</h2>
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

  <!-- PERFORMANCE VIEW -->
  <div class="content-view" id="performance-view">
    <div class="view-header">
      <h2>Performance Ratings</h2>
      <button class="btn btn-primary" id="addPerformanceBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Add Rating
      </button>
    </div>

    <!-- Performance Form -->
    <div class="card" id="performanceFormCard" style="display:none;margin-bottom:20px;">
      <h3>Rate Player Performance</h3>
      <form id="performanceForm" class="form-grid">
        <input type="hidden" id="perf_id">
        
        <div class="form-group full-width">
          <label>Player *</label>
          <select id="perf_person_id" required>
            <option value="">-- Select Player --</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label>Training Activity *</label>
          <select id="perf_activity_id" required>
            <option value="">-- Select Activity --</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label>Team *</label>
          <select id="perf_team_id" required>
            <option value="">-- Select Team --</option>
          </select>
        </div>

        <div class="form-group full-width">
          <label>Rating (1-10) *</label>
          <input type="number" id="perf_rating" min="1" max="10" step="0.1" required placeholder="Enter rating between 1-10">
          <small style="color:var(--muted);margin-top:4px;">1 = Poor, 5 = Average, 10 = Excellent</small>
        </div>

        <div class="form-group full-width">
          <label>Evaluation Date *</label>
          <input type="date" id="perf_date_eval" required>
        </div>

        <div class="form-actions full-width">
          <button type="button" class="btn btn-secondary" id="cancelPerfBtn">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Rating</button>
        </div>
        
        <div class="msg" id="perfMsg"></div>
      </form>
    </div>

    <!-- Performance List -->
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3>Performance History</h3>
        <input class="search" id="perfSearch" placeholder="Search by player...">
      </div>
      <div class="table-container">
        <table class="table" id="performanceTable">
          <thead>
            <tr>
              <th>Player</th>
              <th>Activity</th>
              <th>Team</th>
              <th>Rating</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="6">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- STANDINGS VIEW -->
  <div class="content-view" id="standings-view">
    <div class="view-header">
      <h2>Team Statistics & Rankings</h2>
    </div>
    <div class="card">
      <p class="hint" style="margin-bottom: 16px;">Based on tournament standings for your sport.</p>
    </div>
    <div class="table-container">
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
  </div>

  <!-- TOURNAMENTS VIEW -->
  <div class="content-view" id="tournaments-view">
    <div class="view-header">
      <h2>Tournament Schedules</h2>
    </div>
    <div class="card">
      <p class="hint" style="margin-bottom: 16px;">Upcoming and recent tournament matches.</p>
    </div>
    <div class="table-container">
      <table class="table" id="matchesTable">
        <thead><tr><th>Date</th><th>Time</th><th>Game #</th><th>Type</th><th>Venue</th><th>Team A</th><th>Team B</th></tr></thead>
        <tbody><tr><td colspan="7">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Update Player Modal -->
<div class="modal-overlay" id="updatePlayerModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Update Player Information</h3>
      <button class="modal-close" onclick="closeUpdateModal()">×</button>
    </div>
    <div class="modal-body">
      <form id="updatePlayerForm" class="form">
        <input type="hidden" id="update_person_id">
        
        <div class="form-section">
          <h4>Personal Information</h4>
          <label>First Name *</label>
          <input type="text" id="update_f_name" required>
          
          <label>Last Name *</label>
          <input type="text" id="update_l_name" required>
          
          <label>Middle Name</label>
          <input type="text" id="update_m_name">
          
          <label>Date of Birth *</label>
          <input type="date" id="update_date_birth" required>
          
          <label>Blood Type</label>
          <select id="update_blood_type">
            <option value="">-- Select --</option>
            <option value="A+">A+</option>
            <option value="A-">A-</option>
            <option value="B+">B+</option>
            <option value="B-">B-</option>
            <option value="AB+">AB+</option>
            <option value="AB-">AB-</option>
            <option value="O+">O+</option>
            <option value="O-">O-</option>
          </select>
        </div>
        
        <div class="form-section">
          <h4>Academic Information</h4>
          <label>Course/Program</label>
          <input type="text" id="update_course">
        </div>
        
        <div class="form-section">
          <h4>Vital Signs</h4>
          <div class="form-grid">
            <div>
              <label>Height (cm)</label>
              <input type="number" id="update_height" step="0.1" min="0" max="300">
            </div>
            <div>
              <label>Weight (kg)</label>
              <input type="number" id="update_weight" step="0.1" min="0" max="300">
            </div>
          </div>
          
          <label>Blood Pressure</label>
          <input type="text" id="update_b_pressure">
          
          <label>Blood Sugar</label>
          <input type="text" id="update_b_sugar">
          
          <label>Cholesterol</label>
          <input type="text" id="update_b_choles">
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
          <button type="submit" class="btn">Update Player</button>
        </div>
        
        <div class="msg" id="updateMsg" style="display:none;"></div>
      </form>
    </div>
  </div>
</div>

<style>
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  padding: 20px;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--line);
  display: flex;
  align-items: center;
  gap: 16px;
  transition: all 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.stat-info {
  flex: 1;
  min-width: 0;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--text);
  line-height: 1;
  word-break: break-word;
}

.stat-label {
  font-size: 13px;
  color: var(--muted);
  margin-top: 6px;
  font-weight: 600;
}

.overview-section {
  margin-top: 24px;
}

.overview-section h2 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 16px;
  color: var(--text);
}

.data-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.data-card {
  background: white;
  border-radius: var(--radius);
  padding: 20px;
  box-shadow: var(--shadow);
  border: 1px solid var(--line);
  transition: all 0.2s;
}

.data-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.data-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
  gap: 12px;
}

.data-card-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.3;
}

.data-card-meta {
  font-size: 13px;
  color: var(--muted);
  margin-bottom: 12px;
  line-height: 1.5;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group.full-width {
  grid-column: 1 / -1;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
}

.form-group input,
.form-group select,
.form-select {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: 14px;
  color: var(--text);
  background: white;
  transition: all 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

.badge {
  display: inline-block;
  padding: 5px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.badge.active, .badge.present {
  background: #d1fae5;
  color: #047857;
  border: 1px solid #6ee7b7;
}

.badge.inactive, .badge.absent {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fca5a5;
}

.rating-display {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
}

.rating-excellent {
  background: #d1fae5;
  color: #047857;
}

.rating-good {
  background: #dbeafe;
  color: #1e40af;
}

.rating-average {
  background: #fef3c7;
  color: #92400e;
}

.rating-poor {
  background: #fee2e2;
  color: #991b1b;
}

.attendance-checkbox {
  width: 24px;
  height: 24px;
  cursor: pointer;
  accent-color: var(--success);
}

.btn-icon {
  padding: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  border: 1px solid var(--line);
  background: white;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-icon:hover {
  background: #f9fafb;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
  font-size: 14px;
}

.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 2000;
  align-items: center;
  justify-content: center;
  padding: 16px;
  overflow-y: auto;
}

.modal-overlay.active {
  display: flex;
}

.modal-content {
  background: white;
  border-radius: var(--radius);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  margin: auto;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--line);
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
}

.modal-close {
  width: 32px;
  height: 32px;
  border: none;
  background: #f3f4f6;
  border-radius: 8px;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: var(--muted);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.modal-close:hover {
  background: #e5e7eb;
  color: var(--text);
}

.modal-body {
  padding: 24px;
}

.form-section {
  margin-bottom: 24px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--line);
}

.form-section:last-of-type {
  border-bottom: none;
}

.form-section h4 {
  margin: 0 0 16px 0;
  font-size: 14px;
  font-weight: 700;
  color: var(--primary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.modal-actions .btn {
  flex: 1;
}

@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .data-grid {
    grid-template-columns: 1fr;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
  
  .stat-card {
    padding: 16px;
  }
  
  .modal-content {
    max-height: 95vh;
  }
  
  .modal-header,
  .modal-body {
    padding: 16px;
  }
  
  .modal-actions {
    flex-direction: column-reverse;
  }
  
  .modal-actions .btn {
    width: 100%;
  }
}
</style>

<script>
  window.BASE_URL = "<?= BASE_URL ?>";
  window.COACH_CONTEXT = { 
    person_id: <?= (int)$coach_person_id ?>,
    sports_id: <?= (int)$sports_id ?>,
    sports_name: "<?= htmlspecialchars($sports_name) ?>"
  };
</script>

<script src="<?= BASE_URL ?>/coach/coach.js"></script>

</body>
</html> 