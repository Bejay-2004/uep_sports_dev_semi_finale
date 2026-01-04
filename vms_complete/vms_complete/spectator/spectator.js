const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const sportsId = window.SPECTATOR_CONTEXT.sports_id; // Will be 0 for spectators (view all sports)

// Tab Navigation
$$('.nav-item').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-item').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const tabId = btn.dataset.tab;
    $$('.tab-panel').forEach(p => p.classList.remove('active'));
    $(`#${tabId}`).classList.add('active');
  });
});

// API Helper
async function fetchJSON(action) {
  try {
    const url = `api.php?action=${encodeURIComponent(action)}`;
    console.log('🔍 Fetching:', url);
    const res = await fetch(url);
    
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    
    const data = await res.json();
    console.log('✅ Response:', data);
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
// LOAD TOURNAMENTS (for filters)
// ==========================================

async function loadTournaments() {
  try {
    const data = await fetchJSON('tournaments');
    
    // Populate all tournament filters
    const tournamentFilters = [
      '#homeTournamentFilter',
      '#matchTournamentFilter',
      '#standingTournamentFilter'
    ];
    
    tournamentFilters.forEach(selector => {
      const select = $(selector);
      if (select && data.length > 0) {
        const options = data.map(t => 
          `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} - ${escapeHtml(t.school_year)}</option>`
        ).join('');
        select.innerHTML += options;
      }
    });
    
    console.log('✅ Tournaments loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTournaments error:', err);
  }
}

// ==========================================
// LOAD SPORTS (for filters)
// ==========================================

async function loadSports() {
  try {
    const data = await fetchJSON('sports');
    
    // Populate sport filters
    const sportFilters = [
      '#matchSportFilter',
      '#standingSportFilter',
      '#teamSportFilter'
    ];
    
    sportFilters.forEach(selector => {
      const select = $(selector);
      if (select && data.length > 0) {
        const options = data.map(s => 
          `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`
        ).join('');
        select.innerHTML += options;
      }
    });
    
    console.log('✅ Sports loaded:', data.length);
  } catch (err) {
    console.error('❌ loadSports error:', err);
  }
}

// ==========================================
// LOAD MATCHES (with grouping)
// ==========================================

$('#homeTournamentFilter')?.addEventListener('change', (e) => {
  loadHomeMatches(e.target.value);
});

$('#matchTournamentFilter')?.addEventListener('change', loadMatchesGrouped);
$('#matchSportFilter')?.addEventListener('change', loadMatchesGrouped);

async function loadHomeMatches(tourId = null) {
  try {
    let url = 'matches';
    if (tourId) url += `&tour_id=${tourId}`;
    
    const data = await fetchJSON(url);
    const list = $('#homeMatchesList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No matches scheduled</div>';
    } else {
      // Show only upcoming/live matches on home
      const upcoming = data.filter(m => {
        const matchDate = new Date(m.sked_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return matchDate >= today;
      }).slice(0, 5);
      
      if (upcoming.length === 0) {
        list.innerHTML = '<div class="empty-state">No upcoming matches</div>';
      } else {
        list.innerHTML = upcoming.map(m => renderMatchCard(m)).join('');
      }
    }
    
    console.log('✅ Home matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadHomeMatches error:', err);
    $('#homeMatchesList').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

async function loadMatchesGrouped() {
  try {
    const tourId = $('#matchTournamentFilter')?.value || '';
    const sportId = $('#matchSportFilter')?.value || '';
    
    let url = 'matches';
    const params = [];
    if (tourId) params.push(`tour_id=${tourId}`);
    if (sportId) params.push(`sport_id=${sportId}`);
    if (params.length > 0) url += '&' + params.join('&');
    
    const data = await fetchJSON(url);
    const list = $('#matchesGroupedList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No matches found</div>';
      return;
    }
    
    // Group by tournament, then by sport
    const grouped = {};
    data.forEach(match => {
      const tourKey = match.tour_name || 'No Tournament';
      const sportKey = match.sports_name || 'No Sport';
      
      if (!grouped[tourKey]) grouped[tourKey] = {};
      if (!grouped[tourKey][sportKey]) grouped[tourKey][sportKey] = [];
      
      grouped[tourKey][sportKey].push(match);
    });
    
    // Render grouped matches
    let html = '';
    Object.keys(grouped).sort().forEach(tourName => {
      Object.keys(grouped[tourName]).sort().forEach(sportName => {
        const matches = grouped[tourName][sportName];
        html += `
          <div class="group-header">
            <h4 class="group-title">${escapeHtml(tourName)} • ${escapeHtml(sportName)}</h4>
            <span class="group-badge">${matches.length} match${matches.length !== 1 ? 'es' : ''}</span>
          </div>
          <div class="matches-list">
            ${matches.map(m => renderMatchCard(m)).join('')}
          </div>
        `;
      });
    });
    
    list.innerHTML = html;
    console.log('✅ Grouped matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatchesGrouped error:', err);
    $('#matchesGroupedList').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

function renderMatchCard(match) {
  const date = new Date(match.sked_date);
  const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  
  // Determine match status
  const now = new Date();
  const matchDate = new Date(match.sked_date);
  let status = 'upcoming';
  let statusText = 'Upcoming';
  
  if (match.winner_id) {
    status = 'finished';
    statusText = 'Finished';
  } else if (matchDate.toDateString() === now.toDateString()) {
    status = 'live';
    statusText = 'Today';
  }
  
  return `
    <div class="match-card">
      <div class="match-card-header">
        <div class="match-info">
          <div class="match-tournament">${escapeHtml(match.tour_name || 'Tournament')}</div>
          <div class="match-sport">${escapeHtml(match.sports_name)}</div>
        </div>
        <div class="match-date-badge">
          <div class="match-date">${dateStr}</div>
          <div class="match-time">${match.sked_time || 'TBA'}</div>
        </div>
      </div>
      <div class="match-teams">
        <div class="match-team">
          <div class="match-team-name">${escapeHtml(match.team_a_name || 'TBA')}</div>
          ${match.team_a_score !== null ? `<div class="match-team-score">${match.team_a_score}</div>` : ''}
        </div>
        <div class="match-vs">VS</div>
        <div class="match-team">
          <div class="match-team-name">${escapeHtml(match.team_b_name || 'TBA')}</div>
          ${match.team_b_score !== null ? `<div class="match-team-score">${match.team_b_score}</div>` : ''}
        </div>
      </div>
      <div class="match-details">
        <div class="match-detail">📍 ${escapeHtml(match.venue_name || 'TBA')}</div>
        <div class="match-detail">🏆 ${escapeHtml(match.match_type || 'Match')}</div>
        <span class="match-status ${status}">${statusText}</span>
      </div>
    </div>
  `;
}

// ==========================================
// LOAD STANDINGS (with grouping)
// ==========================================

$('#standingTournamentFilter')?.addEventListener('change', loadStandingsGrouped);
$('#standingSportFilter')?.addEventListener('change', loadStandingsGrouped);

async function loadHomeStandings() {
  try {
    const data = await fetchJSON('standings');
    const list = $('#homeStandingsList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No standings available</div>';
    } else {
      // Show top 5 teams
      list.innerHTML = '<div class="standings-list">' + 
        data.slice(0, 5).map((s, idx) => renderStandingCard(s, idx + 1)).join('') +
        '</div>';
    }
    
    console.log('✅ Home standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadHomeStandings error:', err);
    $('#homeStandingsList').innerHTML = '<div class="empty-state">Error loading standings</div>';
  }
}

async function loadStandingsGrouped() {
  try {
    const tourId = $('#standingTournamentFilter')?.value || '';
    const sportId = $('#standingSportFilter')?.value || '';
    
    if (!tourId || !sportId) {
      $('#standingsGroupedList').innerHTML = '<div class="empty-state">Please select both tournament and sport</div>';
      return;
    }
    
    const url = `standings&tour_id=${tourId}&sport_id=${sportId}`;
    const data = await fetchJSON(url);
    const list = $('#standingsGroupedList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No standings available</div>';
      return;
    }
    
    list.innerHTML = '<div class="standings-list">' +
      data.map((s, idx) => renderStandingCard(s, idx + 1)).join('') +
      '</div>';
    
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandingsGrouped error:', err);
    $('#standingsGroupedList').innerHTML = '<div class="empty-state">Error loading standings</div>';
  }
}

function renderStandingCard(standing, rank) {
  let rankClass = '';
  if (rank === 1) rankClass = 'gold';
  else if (rank === 2) rankClass = 'silver';
  else if (rank === 3) rankClass = 'bronze';
  
  const record = `${standing.no_win || 0}W - ${standing.no_loss || 0}L`;
  const medals = `🥇${standing.no_gold || 0} 🥈${standing.no_silver || 0} 🥉${standing.no_bronze || 0}`;
  
  return `
    <div class="standing-card">
      <div class="standing-rank ${rankClass}">${rank}</div>
      <div class="standing-info">
        <div class="standing-team">${escapeHtml(standing.team_name)}</div>
        <div class="standing-sport">${escapeHtml(standing.sports_name)}</div>
      </div>
      <div class="standing-stats">
        <div class="standing-record">${record}</div>
        <div class="standing-medals">${medals}</div>
      </div>
    </div>
  `;
}

// ==========================================
// LOAD TEAMS (with grouping)
// ==========================================

$('#teamSportFilter')?.addEventListener('change', loadTeamsGrouped);

async function loadTeamsGrouped() {
  try {
    const sportId = $('#teamSportFilter')?.value || '';
    
    let url = 'teams';
    if (sportId) url += `&sport_id=${sportId}`;
    
    const data = await fetchJSON(url);
    const list = $('#teamsGroupedList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No teams found</div>';
      return;
    }
    
    // Group by sport
    const grouped = {};
    data.forEach(team => {
      const sportKey = team.sports_name || 'No Sport';
      if (!grouped[sportKey]) grouped[sportKey] = [];
      grouped[sportKey].push(team);
    });
    
    // Render grouped teams
    let html = '';
    Object.keys(grouped).sort().forEach(sportName => {
      const teams = grouped[sportName];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(sportName)}</h4>
          <span class="group-badge">${teams.length} team${teams.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="teams-list">
          ${teams.map(t => renderTeamCard(t)).join('')}
        </div>
      `;
    });
    
    list.innerHTML = html;
    console.log('✅ Grouped teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeamsGrouped error:', err);
    $('#teamsGroupedList').innerHTML = '<div class="empty-state">Error loading teams</div>';
  }
}

function renderTeamCard(team) {
  return `
    <div class="team-card">
      <div class="team-card-header">
        <div class="team-info">
          <div class="team-name">${escapeHtml(team.team_name)}</div>
          <div class="team-sport">${escapeHtml(team.sports_name)}</div>
        </div>
        <div class="team-badge">${team.num_players || 0} 👥</div>
      </div>
      <div class="team-stats">
        <div class="team-stat">
          <div class="team-stat-value">${team.no_win || 0}</div>
          <div class="team-stat-label">Wins</div>
        </div>
        <div class="team-stat">
          <div class="team-stat-value">${team.no_loss || 0}</div>
          <div class="team-stat-label">Losses</div>
        </div>
        <div class="team-stat">
          <div class="team-stat-value">${(team.no_gold || 0) + (team.no_silver || 0) + (team.no_bronze || 0)}</div>
          <div class="team-stat-label">Medals</div>
        </div>
      </div>
    </div>
  `;
}

// ==========================================
// INITIALIZE
// ==========================================

(async function init() {
  console.log('🚀 Initializing spectator dashboard...');
  console.log('⚽ Sports ID:', sportsId);
  
  try {
    // Load filters first
    await Promise.all([
      loadTournaments(),
      loadSports()
    ]);
    
    // Load initial data
    await Promise.all([
      loadHomeMatches(),
      loadHomeStandings(),
      loadMatchesGrouped(),
      loadTeamsGrouped()
    ]);
    
    console.log('✅ All data loaded');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();

// Add swipe gesture for tabs
let touchStartX = 0;
let touchEndX = 0;

document.addEventListener('touchstart', e => {
  touchStartX = e.changedTouches[0].screenX;
});

document.addEventListener('touchend', e => {
  touchEndX = e.changedTouches[0].screenX;
  handleSwipe();
});

function handleSwipe() {
  const swipeThreshold = 100;
  const diff = touchStartX - touchEndX;
  
  if (Math.abs(diff) < swipeThreshold) return;
  
  const tabs = ['home', 'matches', 'standings', 'teams'];
  const currentTab = $('.tab-panel.active').id;
  const currentIndex = tabs.indexOf(currentTab);
  
  if (diff > 0 && currentIndex < tabs.length - 1) {
    $(`.nav-item[data-tab="${tabs[currentIndex + 1]}"]`).click();
  } else if (diff < 0 && currentIndex > 0) {
    $(`.nav-item[data-tab="${tabs[currentIndex - 1]}"]`).click();
  }
}