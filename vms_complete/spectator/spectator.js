const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.SPECTATOR_CONTEXT.person_id;
const sportsId = window.SPECTATOR_CONTEXT.sports_id;

// ==========================================
// NAVIGATION
// ==========================================

// Sidebar navigation
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    // Update nav
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Update views
    const viewId = btn.dataset.view + '-view';
    $$('.content-view').forEach(v => v.classList.remove('active'));
    $(`#${viewId}`).classList.add('active');
    
    // Update page title
    const titles = {
      'overview': 'Dashboard Overview',
      'matches': 'Match Schedule',
      'standings': 'Team Rankings',
      'teams': 'Teams & Players',
      'tournaments': 'All Tournaments',
      'sports': 'All Sports'
    };
    $('#pageTitle').textContent = titles[btn.dataset.view] || 'Dashboard';
    
    // Load data for the view
    if (btn.dataset.view === 'matches') {
      loadMatchesGrouped();
    } else if (btn.dataset.view === 'standings') {
      $('#standingsContent').innerHTML = '<div class="empty-state">Select tournament and sport to view standings</div>';
    } else if (btn.dataset.view === 'teams') {
      loadTeams();
    }
  });
});

// Mobile menu toggle
$('#menuToggle')?.addEventListener('click', () => {
  $('.sidebar').classList.toggle('active');
  $('#sidebarOverlay').classList.toggle('active');
});

$('#sidebarOverlay')?.addEventListener('click', () => {
  $('.sidebar').classList.remove('active');
  $('#sidebarOverlay').classList.remove('active');
});

// ==========================================
// API HELPER - FIXED VERSION
// ==========================================

async function fetchJSON(action, params = {}) {
  try {
    let url = `api.php?action=${encodeURIComponent(action)}`;
    
    // Add additional parameters
    for (const [key, value] of Object.entries(params)) {
      if (value !== null && value !== undefined && value !== '') {
        url += `&${encodeURIComponent(key)}=${encodeURIComponent(value)}`;
      }
    }
    
    console.log('📡 Fetching:', url);
    const res = await fetch(url);
    
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    
    const data = await res.json();
    console.log('✅ Response:', action, data);
    return data;
  } catch (err) {
    console.error('❌ fetchJSON error:', err);
    return [];
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// ==========================================
// LOAD TOURNAMENTS
// ==========================================

async function loadTournaments() {
  try {
    const data = await fetchJSON('tournaments');
    
    // Update stats
    $('#statTournaments').textContent = data.length;
    
    // Populate filters
    const tournamentFilters = [
      '#overviewTournamentFilter',
      '#matchTournamentFilter',
      '#standingTournamentFilter'
    ];
    
    tournamentFilters.forEach(selector => {
      const select = $(selector);
      if (select && data.length > 0) {
        select.innerHTML = '<option value="">All Tournaments</option>';
        const options = data.map(t => 
          `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} - ${escapeHtml(t.school_year)}</option>`
        ).join('');
        select.innerHTML += options;
      }
    });
    
    // Render on Tournaments view
    const tournamentsContent = $('#tournamentsContent');
    if (!data || data.length === 0) {
      tournamentsContent.innerHTML = '<div class="empty-state">No tournaments available</div>';
    } else {
      tournamentsContent.innerHTML = data.map(t => renderTournamentCard(t)).join('');
    }
    
    console.log('✅ Tournaments loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTournaments error:', err);
    $('#tournamentsContent').innerHTML = '<div class="empty-state">Error loading tournaments</div>';
  }
}

function renderTournamentCard(tournament) {
  const date = new Date(tournament.tour_date);
  const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(tournament.tour_name)}</div>
        <span class="match-status active">Active</span>
      </div>
      <div class="data-card-body">
        <div class="data-card-meta">📅 ${formattedDate}</div>
        <div class="data-card-meta">🎓 ${escapeHtml(tournament.school_year)}</div>
        ${tournament.match_count ? `<div class="data-card-meta">🏆 ${tournament.match_count} matches</div>` : ''}
        ${tournament.sports_count ? `<div class="data-card-meta">⚽ ${tournament.sports_count} sports</div>` : ''}
      </div>
    </div>
  `;
}

// ==========================================
// LOAD SPORTS
// ==========================================

async function loadSports() {
  try {
    const data = await fetchJSON('sports');
    
    // Update stats
    $('#statSports').textContent = data.length;
    
    // Populate filters
    const sportFilters = [
      '#matchSportFilter',
      '#standingSportFilter',
      '#teamSportFilter'
    ];
    
    sportFilters.forEach(selector => {
      const select = $(selector);
      if (select && data.length > 0) {
        select.innerHTML = '<option value="">All Sports</option>';
        const options = data.map(s => 
          `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`
        ).join('');
        select.innerHTML += options;
      }
    });
    
    // Render on Sports view
    const sportsContent = $('#sportsContent');
    if (!data || data.length === 0) {
      sportsContent.innerHTML = '<div class="empty-state">No sports available</div>';
    } else {
      sportsContent.innerHTML = data.map(s => renderSportCard(s)).join('');
    }
    
    console.log('✅ Sports loaded:', data.length);
  } catch (err) {
    console.error('❌ loadSports error:', err);
    $('#sportsContent').innerHTML = '<div class="empty-state">Error loading sports</div>';
  }
}

function renderSportCard(sport) {
  const typeBadge = sport.team_individual === 'team' ? 'Team Sport' : 'Individual';
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(sport.sports_name)}</div>
        <span class="match-status upcoming">${typeBadge}</span>
      </div>
      <div class="data-card-body">
        <div class="data-card-meta">${sport.men_women ? escapeHtml(sport.men_women) : 'All Genders'}</div>
        ${sport.team_count ? `<div class="data-card-meta">👥 ${sport.team_count} teams</div>` : ''}
      </div>
    </div>
  `;
}

// ==========================================
// LOAD MATCHES - FIXED
// ==========================================

$('#overviewTournamentFilter')?.addEventListener('change', (e) => {
  loadOverviewMatches(e.target.value);
});

$('#matchTournamentFilter')?.addEventListener('change', loadMatchesGrouped);
$('#matchSportFilter')?.addEventListener('change', loadMatchesGrouped);

async function loadOverviewMatches(tourId = null) {
  try {
    const params = {};
    if (tourId) params.tour_id = tourId;
    
    const data = await fetchJSON('matches', params);
    
    // Update stats - count upcoming matches
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const upcoming = data.filter(m => {
      const matchDate = new Date(m.sked_date);
      matchDate.setHours(0, 0, 0, 0);
      return matchDate >= today;
    });
    $('#statMatches').textContent = upcoming.length;
    
    // Render on Overview (upcoming only, max 5)
    const overviewMatches = $('#overviewMatches');
    if (upcoming.length === 0) {
      overviewMatches.innerHTML = '<div class="empty-state">No upcoming matches</div>';
    } else {
      overviewMatches.innerHTML = upcoming.slice(0, 5).map(m => renderMatchCard(m)).join('');
    }
    
    console.log('✅ Overview matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadOverviewMatches error:', err);
    $('#overviewMatches').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

async function loadMatchesGrouped() {
  try {
    const tourId = $('#matchTournamentFilter')?.value || '';
    const sportId = $('#matchSportFilter')?.value || '';
    
    const params = {};
    if (tourId) params.tour_id = tourId;
    if (sportId) params.sport_id = sportId;
    
    const data = await fetchJSON('matches', params);
    const content = $('#matchesContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No matches found</div>';
      return;
    }
    
    content.innerHTML = data.map(m => renderMatchCard(m, true)).join('');
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatchesGrouped error:', err);
    $('#matchesContent').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

function renderMatchCard(match, detailed = false) {
  const date = new Date(match.sked_date);
  const formattedDate = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  const time = match.sked_time || '';
  
  const matchTypeLabels = {
    'EL': 'Elimination',
    'QF': 'Quarter Finals',
    'SF': 'Semi Finals',
    'F': 'Finals'
  };
  
  const matchTypeLabel = matchTypeLabels[match.match_type] || match.match_type;
  
  let statusClass = 'upcoming';
  let statusText = 'Upcoming';
  if (match.winner_id) {
    statusClass = 'completed';
    statusText = 'Completed';
  }
  
  let scoreDisplay = '';
  if (match.team_a_score !== null && match.team_b_score !== null) {
    scoreDisplay = `
      <div style="text-align: center; margin: 10px 0; font-size: 24px; font-weight: bold; color: #333;">
        ${match.team_a_score} - ${match.team_b_score}
      </div>
    `;
  }
  
  return `
    <div class="match-card">
      <div class="match-header">
        <div>
          <div class="match-sport">${escapeHtml(match.sports_name)}</div>
          ${match.tour_name ? `<div class="match-tournament">${escapeHtml(match.tour_name)}</div>` : ''}
        </div>
        <span class="match-status ${statusClass}">${statusText}</span>
      </div>
      
      <div class="match-teams">
        <div class="team">
          <div class="team-name">${escapeHtml(match.team_a_name || 'TBA')}</div>
        </div>
        <div class="match-vs">VS</div>
        <div class="team">
          <div class="team-name">${escapeHtml(match.team_b_name || 'TBA')}</div>
        </div>
      </div>
      
      ${scoreDisplay}
      
      ${match.winner_name ? `
        <div style="text-align: center; padding: 8px; background: #d4edda; border-radius: 4px; margin-top: 10px;">
          🏆 Winner: <strong>${escapeHtml(match.winner_name)}</strong>
        </div>
      ` : ''}
      
      <div class="match-details">
        <div class="detail-item">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
          </svg>
          ${formattedDate} ${time}
        </div>
        ${match.venue_name ? `
          <div class="detail-item">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/>
              <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
            </svg>
            ${escapeHtml(match.venue_name)}
          </div>
        ` : ''}
        ${match.match_type ? `
          <div class="detail-item">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5zm.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935zm10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935z"/>
            </svg>
            ${matchTypeLabel}
          </div>
        ` : ''}
      </div>
    </div>
  `;
}

// ==========================================
// LOAD STANDINGS - FIXED
// ==========================================

$('#standingTournamentFilter')?.addEventListener('change', loadStandings);
$('#standingSportFilter')?.addEventListener('change', loadStandings);

async function loadStandings() {
  try {
    const tourId = $('#standingTournamentFilter')?.value || '';
    const sportId = $('#standingSportFilter')?.value || '';
    const content = $('#standingsContent');
    
    if (!tourId || !sportId) {
      content.innerHTML = '<div class="empty-state">Please select both tournament and sport to view standings</div>';
      return;
    }
    
    const params = { tour_id: tourId, sport_id: sportId };
    const data = await fetchJSON('standings', params);
    
    // Also load standings for overview
    if ($('#overviewStandings')) {
      const overviewData = data.slice(0, 5); // Top 5 for overview
      if (overviewData.length > 0) {
        $('#overviewStandings').innerHTML = renderStandingsTable(overviewData);
      }
    }
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No standings available for this combination</div>';
      return;
    }
    
    content.innerHTML = renderStandingsTable(data);
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    $('#standingsContent').innerHTML = '<div class="empty-state">Error loading standings</div>';
  }
}

function renderStandingsTable(standings) {
  return `
    <div class="standings-table">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Team</th>
            <th>P</th>
            <th>W</th>
            <th>L</th>
            <th>D</th>
            <th>Pts</th>
            <th>🥇</th>
            <th>🥈</th>
            <th>🥉</th>
          </tr>
        </thead>
        <tbody>
          ${standings.map((team, index) => `
            <tr>
              <td><strong>${index + 1}</strong></td>
              <td><strong>${escapeHtml(team.team_name)}</strong></td>
              <td>${team.no_games_played || 0}</td>
              <td>${team.no_win || 0}</td>
              <td>${team.no_loss || 0}</td>
              <td>${team.no_draw || 0}</td>
              <td><strong>${team.points || 0}</strong></td>
              <td>${team.no_gold || 0}</td>
              <td>${team.no_silver || 0}</td>
              <td>${team.no_bronze || 0}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

// ==========================================
// LOAD TEAMS - FIXED
// ==========================================

$('#teamSportFilter')?.addEventListener('change', loadTeams);

async function loadTeams() {
  try {
    const sportId = $('#teamSportFilter')?.value || '';
    const params = {};
    if (sportId) params.sport_id = sportId;
    
    const data = await fetchJSON('teams', params);
    
    // Update stats
    $('#statTeams').textContent = data.length;
    
    const content = $('#teamsContent');
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No teams found</div>';
      return;
    }
    
    content.innerHTML = data.map(t => renderTeamCard(t)).join('');
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeams error:', err);
    $('#teamsContent').innerHTML = '<div class="empty-state">Error loading teams</div>';
  }
}

function renderTeamCard(team) {
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(team.team_name)}</div>
        ${team.sports_name ? `<span class="match-status active">${escapeHtml(team.sports_name)}</span>` : ''}
      </div>
      <div class="data-card-body">
        ${team.school_name ? `<div class="data-card-meta">🏫 ${escapeHtml(team.school_name)}</div>` : ''}
        ${team.coach_name ? `<div class="data-card-meta">👨‍🏫 Coach: ${escapeHtml(team.coach_name)}</div>` : ''}
        ${team.player_count ? `<div class="data-card-meta">👥 ${team.player_count} players</div>` : ''}
        <div class="data-card-stats">
          <span>W: ${team.total_wins || 0}</span>
          <span>L: ${team.total_losses || 0}</span>
          <span>🥇${team.total_gold || 0}</span>
          <span>🥈${team.total_silver || 0}</span>
          <span>🥉${team.total_bronze || 0}</span>
        </div>
      </div>
    </div>
  `;
}

// ==========================================
// LOAD STATS - NEW
// ==========================================

async function loadStats() {
  try {
    const data = await fetchJSON('stats');
    
    if (data.tournaments !== undefined) {
      $('#statTournaments').textContent = data.tournaments;
    }
    if (data.sports !== undefined) {
      $('#statSports').textContent = data.sports;
    }
    if (data.matches !== undefined) {
      $('#statMatches').textContent = data.matches;
    }
    if (data.teams !== undefined) {
      $('#statTeams').textContent = data.teams;
    }
    
    console.log('✅ Stats loaded:', data);
  } catch (err) {
    console.error('❌ loadStats error:', err);
  }
}

// ==========================================
// INITIALIZE
// ==========================================

document.addEventListener('DOMContentLoaded', () => {
  console.log('🚀 Spectator Dashboard Initializing...');
  
  // Load all initial data
  loadStats();
  loadTournaments();
  loadSports();
  loadOverviewMatches();
  loadTeams();
  
  console.log('✅ Dashboard Initialized');
});