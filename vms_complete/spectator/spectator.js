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
    
    // Render on Overview (upcoming only, max 6)
    const overviewMatches = $('#overviewMatches');
    if (upcoming.length === 0) {
      overviewMatches.innerHTML = `
        <div class="matches-empty">
          <p>No upcoming matches</p>
        </div>
      `;
    } else {
      overviewMatches.innerHTML = `
        <div class="matches-grid">
          ${upcoming.slice(0, 6).map(m => renderMatchCard(m)).join('')}
        </div>
      `;
    }
    
    console.log('✅ Overview matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadOverviewMatches error:', err);
    $('#overviewMatches').innerHTML = `
      <div class="matches-empty">
        <p>Error loading matches</p>
      </div>
    `;
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
      content.innerHTML = `
        <div class="matches-empty">
          <p>No matches found with the selected filters</p>
        </div>
      `;
      return;
    }
    
    // Group matches by status
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const completed = [];
    const live = [];
    const upcoming = [];
    
    data.forEach(match => {
      const matchDate = new Date(match.sked_date);
      matchDate.setHours(0, 0, 0, 0);
      
      if (match.winner_id || match.winner_team_id || match.winner_athlete_id) {
        completed.push(match);
      } else if (matchDate.toDateString() === today.toDateString()) {
        live.push(match);
      } else if (matchDate >= today) {
        upcoming.push(match);
      } else {
        completed.push(match);
      }
    });
    
    let html = '';
    
    // Live matches
    if (live.length > 0) {
      html += `
        <div class="group-header">
          <div class="group-title">🔴 Live Now</div>
          <div class="group-badge">${live.length}</div>
        </div>
        <div class="matches-grid">
          ${live.map(m => renderMatchCard(m, true)).join('')}
        </div>
      `;
    }
    
    // Upcoming matches
    if (upcoming.length > 0) {
      html += `
        <div class="group-header">
          <div class="group-title">📅 Upcoming</div>
          <div class="group-badge">${upcoming.length}</div>
        </div>
        <div class="matches-grid">
          ${upcoming.map(m => renderMatchCard(m, true)).join('')}
        </div>
      `;
    }
    
    // Completed matches
    if (completed.length > 0) {
      html += `
        <div class="group-header">
          <div class="group-title">✅ Completed</div>
          <div class="group-badge">${completed.length}</div>
        </div>
        <div class="matches-grid">
          ${completed.map(m => renderMatchCard(m, true)).join('')}
        </div>
      `;
    }
    
    content.innerHTML = html;
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatchesGrouped error:', err);
    $('#matchesContent').innerHTML = `
      <div class="matches-empty">
        <p>Error loading matches</p>
      </div>
    `;
  }
}

function renderMatchCard(match, detailed = false) {
  const date = new Date(match.sked_date);
  const formattedDate = date.toLocaleDateString('en-US', { 
    weekday: 'short',
    month: 'short', 
    day: 'numeric'
  });
  
  const time = match.sked_time ? formatTime(match.sked_time) : '';
  
  // Match type labels
  const matchTypeLabels = {
    'EL': { text: 'Elimination', class: 'elimination' },
    'QF': { text: 'Quarter Finals', class: 'quarterfinals' },
    'SF': { text: 'Semi Finals', class: 'semifinals' },
    'F': { text: 'Finals', class: 'finals' }
  };
  
  const matchType = matchTypeLabels[match.match_type] || { text: match.match_type, class: '' };
  
  // Determine match status
  let statusClass = 'upcoming';
  let statusText = 'Upcoming';
  
  const today = new Date();
  const matchDate = new Date(match.sked_date);
  
  if (match.winner_id || match.winner_team_id || match.winner_athlete_id) {
    statusClass = 'completed';
    statusText = 'Completed';
  } else if (matchDate.toDateString() === today.toDateString()) {
    statusClass = 'live';
    statusText = 'Today';
  }
  
  // Determine winner
  let teamAIsWinner = false;
  let teamBIsWinner = false;
  
  if (match.sports_type === 'team' && match.winner_team_id) {
    teamAIsWinner = match.winner_team_id == match.team_a_id;
    teamBIsWinner = match.winner_team_id == match.team_b_id;
  } else if (match.winner_athlete_id) {
    // For individual sports, check if winner belongs to team A or B
    if (match.scores && match.scores.length > 0) {
      const winnerScore = match.scores.find(s => s.athlete_id == match.winner_athlete_id);
      if (winnerScore) {
        teamAIsWinner = winnerScore.team_id == match.team_a_id;
        teamBIsWinner = winnerScore.team_id == match.team_b_id;
      }
    }
  }
  
  // Build the card
  let html = `
    <div class="match-card">
      <!-- Header -->
      <div class="match-header">
        <div class="match-info-top">
          <div class="match-sport">
            ${escapeHtml(match.sports_name)}
            ${match.match_type ? `<span class="match-type-badge ${matchType.class}">${matchType.text}</span>` : ''}
          </div>
          ${match.tour_name ? `<div class="match-tournament">${escapeHtml(match.tour_name)} • ${escapeHtml(match.school_year || '')}</div>` : ''}
        </div>
        <span class="match-status ${statusClass}">${statusText}</span>
      </div>
      
      <!-- Teams -->
      <div class="match-teams">
        <div class="match-team ${teamAIsWinner ? 'winner' : ''}">
          <div class="match-team-name">${escapeHtml(match.team_a_name || 'TBA')}</div>
          ${match.team_a_score !== null && match.team_a_score !== undefined ? 
            `<div class="match-team-score">${match.team_a_score}</div>` : 
            ''}
        </div>
        
        <div class="match-vs">VS</div>
        
        <div class="match-team ${teamBIsWinner ? 'winner' : ''}">
          <div class="match-team-name">${escapeHtml(match.team_b_name || 'TBA')}</div>
          ${match.team_b_score !== null && match.team_b_score !== undefined ? 
            `<div class="match-team-score">${match.team_b_score}</div>` : 
            ''}
        </div>
      </div>
  `;
  
  // Winner banner
  if (match.winner_name || match.winner_athlete_name) {
    const winnerName = match.winner_name || match.winner_athlete_name;
    html += `
      <div class="match-winner-banner">
        Winner: ${escapeHtml(winnerName)}
      </div>
    `;
  }
  
  // Match details footer
  html += `
    <div class="match-details">
      <div class="match-detail-item">
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
        </svg>
        <span>${formattedDate}</span>
      </div>
      
      ${time ? `
        <div class="match-detail-item">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
          </svg>
          <span>${time}</span>
        </div>
      ` : ''}
      
      ${match.venue_name ? `
        <div class="match-detail-item">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/>
            <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
          </svg>
          <span>${escapeHtml(match.venue_name)}</span>
        </div>
      ` : ''}
      
      ${match.game_no ? `
        <div class="match-detail-item">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
          </svg>
          <span>Game #${match.game_no}</span>
        </div>
      ` : ''}
    </div>
  `;
  
  // Individual sport scores (show top performers)
  if (detailed && match.scores && match.scores.length > 0 && match.sports_type === 'individual') {
    html += `
      <div class="match-scores-section">
        <div class="match-scores-title">Top Performers</div>
        <div class="score-list">
    `;
    
    match.scores.slice(0, 5).forEach((score, index) => {
      let rankClass = '';
      let medal = '';
      
      if (score.rank_no == 1 || score.medal_type === 'Gold') {
        rankClass = 'gold';
        medal = '🥇';
      } else if (score.rank_no == 2 || score.medal_type === 'Silver') {
        rankClass = 'silver';
        medal = '🥈';
      } else if (score.rank_no == 3 || score.medal_type === 'Bronze') {
        rankClass = 'bronze';
        medal = '🥉';
      }
      
      html += `
        <div class="score-item">
          <div class="score-player">
            <div class="score-rank ${rankClass}">${score.rank_no || index + 1}</div>
            <div class="score-name">${escapeHtml(score.athlete_name || score.team_name || 'Unknown')}</div>
          </div>
          <div class="score-value">${score.score || 0}</div>
          ${medal ? `<div class="score-medal">${medal}</div>` : ''}
        </div>
      `;
    });
    
    html += `
        </div>
      </div>
    `;
  }
  
  html += `</div>`;
  
  return html;
}

// Helper function to format time
function formatTime(timeString) {
  if (!timeString) return '';
  
  // Handle HH:MM:SS format
  const parts = timeString.split(':');
  if (parts.length >= 2) {
    let hours = parseInt(parts[0]);
    const minutes = parts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12 || 12;
    return `${hours}:${minutes} ${ampm}`;
  }
  
  return timeString;
}

// ==========================================
// LOAD STANDINGS - FIXED
// ==========================================

$('#standingTournamentFilter')?.addEventListener('change', loadStandings);
$('#standingSportFilter')?.addEventListener('change', loadStandings);

// Replace the loadStandings function in spectator.js

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
    
    // Debug: Log the actual data structure
    console.log('📊 Standings data received:', data);
    console.log('📊 First team:', data[0]);
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No standings available for this combination</div>';
      return;
    }
    
    // Render standings
    content.innerHTML = renderStandingsTable(data);
    
    // Also update overview standings (top 5)
    const overviewStandings = $('#overviewStandings');
    if (overviewStandings) {
      const topTeams = data.slice(0, 5);
      if (topTeams.length > 0) {
        overviewStandings.innerHTML = renderStandingsTable(topTeams);
      }
    }
    
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    $('#standingsContent').innerHTML = '<div class="empty-state">Error loading standings</div>';
  }
}

// Replace the renderStandingsTable function in spectator.js

// Replace the renderStandingsTable function in spectator.js with this enhanced version

// Replace the renderStandingsTable function in spectator.js with this FIXED version

function renderStandingsTable(standings) {
  if (!standings || standings.length === 0) {
    return `
      <div class="empty-state">
        <p>No standings data available</p>
        <small style="display:block;margin-top:8px;opacity:0.7;">Try selecting a different tournament or sport</small>
      </div>
    `;
  }
  
  return `
    <div class="standings-table-wrapper">
      <div class="standings-table">
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Team</th>
              <th>P</th>
              <th>W</th>
              <th>L</th>
              <th>D</th>
              <th>Points</th>
              <th>🥇</th>
              <th>🥈</th>
              <th>🥉</th>
            </tr>
          </thead>
          <tbody>
            ${standings.map((team, index) => {
              const rank = index + 1;
              const teamName = team.team_name || 'Unknown Team';
              const points = parseInt(team.points) || 0;
              const wins = parseInt(team.no_win) || 0;
              const losses = parseInt(team.no_loss) || 0;
              const draws = parseInt(team.no_draw) || 0;
              const played = parseInt(team.no_games_played) || 0;
              const gold = parseInt(team.no_gold) || 0;
              const silver = parseInt(team.no_silver) || 0;
              const bronze = parseInt(team.no_bronze) || 0;
              
              console.log('Team data:', team); // Debug log
              
              return `
                <tr>
                  <td><strong>${rank}</strong></td>
                  <td>${escapeHtml(teamName)}</td>
                  <td>${played}</td>
                  <td>${wins}</td>
                  <td>${losses}</td>
                  <td>${draws}</td>
                  <td>${points}</td>
                  <td>${gold}</td>
                  <td>${silver}</td>
                  <td>${bronze}</td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
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

// Optional: Add this function to spectator.js for card-based standings view
// You can switch between table and card view based on screen size or user preference

function renderStandingsCards(standings) {
  if (!standings || standings.length === 0) {
    return `
      <div class="empty-state">
        <p>No standings data available</p>
        <small style="display:block;margin-top:8px;opacity:0.7;">Try selecting a different tournament or sport</small>
      </div>
    `;
  }
  
  return `
    <div class="standings-cards">
      ${standings.map((team, index) => {
        const rank = index + 1;
        const points = team.points || 0;
        const wins = team.no_win || 0;
        const losses = team.no_loss || 0;
        const draws = team.no_draw || 0;
        const played = team.no_games_played || 0;
        const gold = team.no_gold || 0;
        const silver = team.no_silver || 0;
        const bronze = team.no_bronze || 0;
        
        const rankClass = rank <= 3 ? `rank-${rank}` : '';
        
        return `
          <div class="standing-card-modern ${rankClass}">
            <div class="standing-card-header">
              <div class="standing-rank-badge">${rank}</div>
              <div class="standing-team-info">
                <div class="standing-team-name">${escapeHtml(team.team_name)}</div>
                <div class="standing-team-sport">${escapeHtml(team.sports_name || 'Sports')}</div>
              </div>
            </div>
            
            <div class="standing-stats-grid">
              <div class="standing-stat-item">
                <span class="standing-stat-value">${played}</span>
                <span class="standing-stat-label">Played</span>
              </div>
              <div class="standing-stat-item">
                <span class="standing-stat-value" style="color:#059669;">${wins}</span>
                <span class="standing-stat-label">Wins</span>
              </div>
              <div class="standing-stat-item">
                <span class="standing-stat-value" style="color:#dc2626;">${losses}</span>
                <span class="standing-stat-label">Losses</span>
              </div>
              <div class="standing-stat-item">
                <span class="standing-stat-value" style="color:#6b7280;">${draws}</span>
                <span class="standing-stat-label">Draws</span>
              </div>
            </div>
            
            <div class="standing-points-highlight">
              <span class="standing-points-value">${points}</span>
              <span class="standing-points-label">Total Points</span>
            </div>
            
            <div class="standing-medals">
              <div class="standing-medal-item">
                <div class="standing-medal-icon">🥇</div>
                <div class="standing-medal-count">${gold}</div>
              </div>
              <div class="standing-medal-item">
                <div class="standing-medal-icon">🥈</div>
                <div class="standing-medal-count">${silver}</div>
              </div>
              <div class="standing-medal-item">
                <div class="standing-medal-icon">🥉</div>
                <div class="standing-medal-count">${bronze}</div>
              </div>
            </div>
          </div>
        `;
      }).join('')}
    </div>
  `;
}

// Optional: Auto-switch between table and cards based on screen size
function renderStandingsResponsive(standings) {
  if (window.innerWidth < 768) {
    return renderStandingsCards(standings);
  } else {
    return renderStandingsTable(standings);
  }
}

// If you want to use the responsive version, update the loadStandings function:
// Change this line in loadStandings():
// content.innerHTML = renderStandingsTable(data);
// To this:
// content.innerHTML = renderStandingsResponsive(data);

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