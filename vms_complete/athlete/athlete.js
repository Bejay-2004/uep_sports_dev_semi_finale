const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.ATHLETE_CONTEXT.person_id;

// Tab Navigation
$$('.nav-item').forEach(btn => {
  btn.addEventListener('click', () => {
    // Update nav
    $$('.nav-item').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Update panels
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
// LOAD MY TEAMS
// ==========================================

async function loadMyTeams() {
  try {
    const data = await fetchJSON('my_teams');
    
    // Update home stats
    $('#homeTeamsCount').textContent = data.length;
    
    // Render on Home tab
    const homeList = $('#homeTeamsList');
    if (!data || data.length === 0) {
      homeList.innerHTML = '<div class="empty-state">No teams assigned</div>';
    } else {
      homeList.innerHTML = data.slice(0, 3).map(t => renderTeamCard(t)).join('');
    }
    
    // Render on Teams tab
    const teamsTabList = $('#teamsTabList');
    if (!data || data.length === 0) {
      teamsTabList.innerHTML = '<div class="empty-state">No teams assigned</div>';
    } else {
      teamsTabList.innerHTML = data.map(t => renderTeamCard(t)).join('');
    }
    
    // Populate filters
    populateTeamFilters(data);
    
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMyTeams error:', err);
    $('#homeTeamsList').innerHTML = '<div class="empty-state">Error loading teams</div>';
  }
}

function renderTeamCard(team) {
  const isCaptain = team.is_captain == 1;
  return `
    <div class="team-card">
      <div class="team-card-header">
        <div>
          <div class="team-name">${escapeHtml(team.team_name)}</div>
          <div class="team-sport">${escapeHtml(team.sports_name)}</div>
        </div>
        ${isCaptain ? '<span class="team-badge badge-captain">Captain</span>' : ''}
      </div>
      <div class="team-coach">
        Coach: ${escapeHtml(team.coach_name || 'TBA')}
      </div>
    </div>
  `;
}

function populateTeamFilters(teams) {
  const select = $('#teamFilterSelect');
  if (teams && teams.length > 0) {
    select.innerHTML = '<option value="">All Teams</option>' + 
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  }
  
  const statsSelect = $('#statsTeamSelect');
  if (teams && teams.length > 0) {
    statsSelect.innerHTML = '<option value="">Select Team</option>' + 
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  }
}

// ==========================================
// LOAD TEAM PLAYERS
// ==========================================

$('#teamFilterSelect')?.addEventListener('change', (e) => {
  const teamId = e.target.value;
  loadTeamPlayers(teamId);
});

async function loadTeamPlayers(teamId = null) {
  try {
    let url = 'team_players';
    if (teamId) url += `&team_id=${teamId}`;
    
    const data = await fetchJSON(url);
    const list = $('#playersTabList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No players found</div>';
    } else {
      list.innerHTML = data.map(p => renderPlayerCard(p)).join('');
    }
    
    console.log('✅ Players loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeamPlayers error:', err);
    $('#playersTabList').innerHTML = '<div class="empty-state">Error loading players</div>';
  }
}

function renderPlayerCard(player) {
  const initial = player.player_name ? player.player_name[0].toUpperCase() : '?';
  const isCaptain = player.is_captain == 1;
  
  return `
    <div class="player-card">
      <div class="player-card-header">
        <div class="player-avatar">${initial}</div>
        <div class="player-info">
          <div class="player-name">
            ${escapeHtml(player.player_name)}
            ${isCaptain ? ' <span class="team-badge badge-captain">Captain</span>' : ''}
          </div>
          <div class="player-team">${escapeHtml(player.team_name)}</div>
        </div>
      </div>
    </div>
  `;
}

// ==========================================
// LOAD MATCHES
// ==========================================

async function loadMatches() {
  try {
    const data = await fetchJSON('my_matches');
    
    // Update home stats
    const upcoming = data.filter(m => new Date(m.sked_date) >= new Date());
    $('#homeUpcomingCount').textContent = upcoming.length;
    
    // Render on Home tab (upcoming only, max 3)
    const homeList = $('#homeMatchesList');
    if (upcoming.length === 0) {
      homeList.innerHTML = '<div class="empty-state">No upcoming matches</div>';
    } else {
      homeList.innerHTML = upcoming.slice(0, 3).map(m => renderMatchCard(m)).join('');
    }
    
    // Render on Schedule tab (all matches)
    const scheduleList = $('#scheduleMatchesList');
    if (!data || data.length === 0) {
      scheduleList.innerHTML = '<div class="empty-state">No matches scheduled</div>';
    } else {
      scheduleList.innerHTML = data.map(m => renderMatchCard(m)).join('');
    }
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
    $('#homeMatchesList').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

function renderMatchCard(match) {
  const date = new Date(match.sked_date);
  const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  
  return `
    <div class="match-card">
      <div class="match-card-header">
        <div class="match-date">📅 ${dateStr} • ${match.sked_time}</div>
        <div class="match-sport">${escapeHtml(match.sports_name)}</div>
      </div>
      <div class="match-teams">
        <div class="match-team">${escapeHtml(match.team_a_name)}</div>
        <div class="match-vs">VS</div>
        <div class="match-team">${escapeHtml(match.team_b_name)}</div>
      </div>
      <div class="match-details">
        <div class="match-detail">
          📍 ${escapeHtml(match.venue_name || 'TBA')}
        </div>
        <div class="match-detail">
          🏆 ${escapeHtml(match.match_type)}
        </div>
      </div>
    </div>
  `;
}

// ==========================================
// LOAD TRAINING SCHEDULE
// ==========================================

async function loadTrainingSchedule() {
  try {
    const data = await fetchJSON('training_schedule');
    
    // Update home stats
    $('#homeTrainingCount').textContent = data.length;
    
    // Render on Schedule tab
    const list = $('#scheduleTrainingList');
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No training scheduled</div>';
    } else {
      list.innerHTML = data.map(t => renderTrainingCard(t)).join('');
    }
    
    console.log('✅ Training loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTrainingSchedule error:', err);
    $('#scheduleTrainingList').innerHTML = '<div class="empty-state">Error loading training</div>';
  }
}

function renderTrainingCard(training) {
  const date = new Date(training.sked_date);
  const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  
  return `
    <div class="training-card">
      <div class="training-card-header">
        <div class="training-team">${escapeHtml(training.team_name)}</div>
        <span class="team-badge badge-active">Active</span>
      </div>
      <div class="training-datetime">
        <span>📅 ${dateStr}</span>
        <span>🕐 ${training.sked_time}</span>
      </div>
      <div class="training-venue">
        📍 ${escapeHtml(training.venue_name || 'TBA')}
      </div>
    </div>
  `;
}

// ==========================================
// LOAD RANKINGS
// ==========================================

$('#statsTeamSelect')?.addEventListener('change', (e) => {
  const teamId = e.target.value;
  if (teamId) {
    loadRankings(teamId);
  } else {
    $('#statsRankingsList').innerHTML = '<div class="empty-state">Select a team to view rankings</div>';
  }
});

async function loadRankings(teamId) {
  try {
    const data = await fetchJSON(`rankings&team_id=${teamId}`);
    const list = $('#statsRankingsList');
    
    if (!data || data.length === 0) {
      list.innerHTML = '<div class="empty-state">No rankings available</div>';
    } else {
      list.innerHTML = data.map((r, idx) => renderRankingCard(r, idx + 1)).join('');
    }
    
    console.log('✅ Rankings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadRankings error:', err);
    $('#statsRankingsList').innerHTML = '<div class="empty-state">Error loading rankings</div>';
  }
}

function renderRankingCard(ranking, position) {
  let positionClass = '';
  if (position === 1) positionClass = 'gold';
  else if (position === 2) positionClass = 'silver';
  else if (position === 3) positionClass = 'bronze';
  
  const record = `${ranking.no_win || 0}W - ${ranking.no_loss || 0}L`;
  const medals = `🥇${ranking.no_gold || 0} 🥈${ranking.no_silver || 0} 🥉${ranking.no_bronze || 0}`;
  
  return `
    <div class="ranking-card">
      <div class="ranking-position ${positionClass}">${position}</div>
      <div class="ranking-info">
        <div class="ranking-team">${escapeHtml(ranking.team_name)}</div>
        <div class="ranking-sport">${escapeHtml(ranking.sports_name)}</div>
      </div>
      <div class="ranking-stats">
        <div class="ranking-record">${record}</div>
        <div class="ranking-medals">${medals}</div>
      </div>
    </div>
  `;
}

// ==========================================
// INITIALIZE
// ==========================================

(async function init() {
  console.log('🚀 Initializing athlete dashboard...');
  console.log('👤 Person ID:', personId);
  
  try {
    await Promise.all([
      loadMyTeams(),
      loadMatches(),
      loadTrainingSchedule(),
      loadTeamPlayers()
    ]);
    console.log('✅ All data loaded');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();

// Add swipe gesture for tabs (optional enhancement)
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
  
  const tabs = ['home', 'teams', 'schedule', 'stats'];
  const currentTab = $('.tab-panel.active').id;
  const currentIndex = tabs.indexOf(currentTab);
  
  if (diff > 0 && currentIndex < tabs.length - 1) {
    // Swipe left - next tab
    $(`.nav-item[data-tab="${tabs[currentIndex + 1]}"]`).click();
  } else if (diff < 0 && currentIndex > 0) {
    // Swipe right - previous tab
    $(`.nav-item[data-tab="${tabs[currentIndex - 1]}"]`).click();
  }
}