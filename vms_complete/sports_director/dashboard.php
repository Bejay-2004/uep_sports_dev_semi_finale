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

    <button class="nav-link" data-view="colleges">
  <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
    <path d="M6.5 1A1.5 1.5 0 0 0 5 2.5V3H1.5A1.5 1.5 0 0 0 0 4.5v8A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-8A1.5 1.5 0 0 0 14.5 3H11v-.5A1.5 1.5 0 0 0 9.5 1h-3zm0 1h3a.5.5 0 0 1 .5.5V3H6v-.5a.5.5 0 0 1 .5-.5z"/>
  </svg>
  <span>Colleges</span>
</button>

<button class="nav-link" data-view="departments">
  <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
    <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"/>
  </svg>
  <span>Departments</span>
</button>

<button class="nav-link" data-view="courses">
  <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
    <path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811V2.828zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492V2.687zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783z"/>
  </svg>
  <span>Courses</span>
</button>


<button class="nav-link" data-view="venues">
  <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
    <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L8 2.207l6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5Z"/>
    <path d="m8 3.293 6 6V13.5a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 2 13.5V9.293l6-6Z"/>
  </svg>
  <span>Venues</span>
</button>

<button class="nav-link" data-view="equipment">
  <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
    <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
    <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319z"/>
  </svg>
  <span>Equipment</span>
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
      <select id="filterSchoolYear" class="filter-select">
        <option value="">All School Years</option>
      </select>
      <select id="filterTournament" class="filter-select">
        <option value="">All Tournaments</option>
      </select>
      <select id="filterTeam" class="filter-select">
        <option value="">All Teams</option>
      </select>
      <select id="filterSport" class="filter-select">
        <option value="">All Sports</option>
      </select>
      <button id="clearFilters" class="btn btn-secondary" style="display: none;">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
          <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
        </svg>
        Clear
      </button>
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
      <button class="btn btn-primary" onclick="showAthleteContextModal()">
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

  <!-- COLLEGES VIEW -->
<section class="content-view" id="colleges-view">
  <div class="view-header">
    <button class="btn btn-primary" onclick="showCollegeModal()">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
      </svg>
      Add College
    </button>
  </div>
  <div id="collegesContent">
    <div class="loading">Loading colleges...</div>
  </div>
</section>

<!-- DEPARTMENTS VIEW -->
<section class="content-view" id="departments-view">
  <div class="view-header">
    <button class="btn btn-primary" onclick="showDepartmentModal()">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
      </svg>
      Add Department
    </button>
  </div>
  <div id="departmentsContent">
    <div class="loading">Loading departments...</div>
  </div>
</section>

<!-- COURSES VIEW -->
<section class="content-view" id="courses-view">
  <div class="view-header">
    <button class="btn btn-primary" onclick="showCourseModal()">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
      </svg>
      Add Course
    </button>
  </div>
  <div id="coursesContent">
    <div class="loading">Loading courses...</div>
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

<!-- EQUIPMENT VIEW -->
<section class="content-view" id="equipment-view">
  <div class="view-header">
    <button class="btn btn-primary" onclick="showEquipmentModal()">
      <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
      </svg>
      Add Equipment
    </button>
  </div>
  <div id="equipmentContent">
    <div class="loading">Loading equipment...</div>
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
    sports_id: <?= (int)$sports_id ?>,
    full_name: "<?= htmlspecialchars($full_name) ?>"
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