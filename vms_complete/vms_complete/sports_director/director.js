const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.DIRECTOR_CONTEXT.person_id;
let currentSportFilter = '';

// Navigation
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    $$('.content-view').forEach(v => v.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
    // Update page title
    const titles = {
      overview: 'Overview',
      tournaments: 'Tournaments',
      teams: 'Teams',
      athletes: 'Athletes',
      matches: 'Matches',
      training: 'Training',
      standings: 'Standings'
    };
    $('#pageTitle').textContent = titles[view] || 'Dashboard';
    
    // Load data for view
    loadViewData(view);
  });
});

// Global Sport Filter
$('#globalSportFilter')?.addEventListener('change', (e) => {
  currentSportFilter = e.target.value;
  const currentView = $('.content-view.active').id.replace('-view', '');
  loadViewData(currentView);
});

// API Helper
async function fetchAPI(action, data = null, method = 'GET') {
  try {
    const options = {
      method,
      headers: { 'Content-Type': 'application/json' }
    };
    
    let url = `api.php?action=${action}`;
    
    if (method === 'POST' && data) {
      options.body = JSON.stringify(data);
    } else if (method === 'GET' && data) {
      const params = new URLSearchParams(data);
      url += '&' + params.toString();
    }
    
    console.log('🔍 API:', method, url);
    const res = await fetch(url, options);
    
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    
    const result = await res.json();
    console.log('✅ Response:', result);
    return result;
  } catch (err) {
    console.error('❌ API Error:', err);
    alert('Error: ' + err.message);
    return null;
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// Load View Data
function loadViewData(view) {
  switch(view) {
    case 'overview': loadOverview(); break;
    case 'tournaments': loadTournaments(); break;
    case 'teams': loadTeams(); break;
    case 'athletes': loadAthletes(); break;
    case 'matches': loadMatches(); break;
    case 'training': loadTraining(); break;
    case 'standings': loadStandings(); break;
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('stats', params);
    
    if (data) {
      $('#statTournaments').textContent = data.tournaments || 0;
      $('#statTeams').textContent = data.teams || 0;
      $('#statAthletes').textContent = data.athletes || 0;
      $('#statMatches').textContent = data.upcoming_matches || 0;
    }
    
    // Load recent activity
    const activity = await fetchAPI('recent_activity', params);
    const content = $('#overviewContent');
    
    if (!activity || activity.length === 0) {
      content.innerHTML = '<div class="empty-state">No recent activity</div>';
    } else {
      content.innerHTML = activity.map(a => `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(a.title)}</div>
            <span class="badge badge-${a.type}">${escapeHtml(a.type)}</span>
          </div>
          <div class="data-card-meta">${escapeHtml(a.description)}</div>
        </div>
      `).join('');
    }
    
    console.log('✅ Overview loaded');
  } catch (err) {
    console.error('❌ loadOverview error:', err);
  }
}

// ==========================================
// TOURNAMENTS
// ==========================================

async function loadTournaments() {
  try {
    const data = await fetchAPI('tournaments');
    const content = $('#tournamentsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No tournaments found. Click "Add Tournament" to create one.</div>';
      return;
    }
    
    // Group by school year
    const grouped = {};
    data.forEach(t => {
      const year = t.school_year || 'No Year';
      if (!grouped[year]) grouped[year] = [];
      grouped[year].push(t);
    });
    
    let html = '';
    Object.keys(grouped).sort().reverse().forEach(year => {
      const tournaments = grouped[year];
      html += `
        <div class="group-header">
          <h4 class="group-title">School Year: ${escapeHtml(year)}</h4>
          <span class="group-badge">${tournaments.length} tournament${tournaments.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="data-grid">
          ${tournaments.map(t => renderTournamentCard(t)).join('')}
        </div>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Tournaments loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTournaments error:', err);
    $('#tournamentsContent').innerHTML = '<div class="empty-state">Error loading tournaments</div>';
  }
}

function renderTournamentCard(t) {
  const statusClass = t.is_active == 1 ? 'active' : 'inactive';
  const statusText = t.is_active == 1 ? 'Active' : 'Inactive';
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(t.tour_name)}</div>
        <span class="badge badge-${statusClass}">${statusText}</span>
      </div>
      <div class="data-card-meta">
        📅 ${escapeHtml(t.tour_date || 'No date')} • ${escapeHtml(t.school_year)}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-secondary" onclick="editTournament(${t.tour_id})">Edit</button>
        <button class="btn btn-sm btn-${t.is_active == 1 ? 'danger' : 'success'}" 
                onclick="toggleTournament(${t.tour_id}, ${t.is_active})">
          ${t.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
      </div>
    </div>
  `;
}

function showTournamentModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Tournament' : 'Add Tournament';
  
  const modalHTML = `
    <div class="modal active" id="tournamentModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('tournamentModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="tournamentForm" onsubmit="saveTournament(event, ${id})">
            <div class="form-group">
              <label class="form-label">Tournament Name *</label>
              <input type="text" class="form-control" id="tour_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">School Year *</label>
              <input type="text" class="form-control" id="school_year" placeholder="2024-2025" required>
            </div>
            <div class="form-group">
              <label class="form-label">Tournament Date</label>
              <input type="date" class="form-control" id="tour_date">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('tournamentModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#tournamentForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Create'} Tournament
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  
  if (isEdit) {
    loadTournamentData(id);
  }
}

async function loadTournamentData(id) {
  const data = await fetchAPI('tournaments');
  const tournament = data.find(t => t.tour_id == id);
  
  if (tournament) {
    $('#tour_name').value = tournament.tour_name || '';
    $('#school_year').value = tournament.school_year || '';
    $('#tour_date').value = tournament.tour_date || '';
  }
}

async function saveTournament(e, id) {
  e.preventDefault();
  
  const data = {
    tour_name: $('#tour_name').value,
    school_year: $('#school_year').value,
    tour_date: $('#tour_date').value || null
  };
  
  if (id) {
    data.tour_id = id;
  }
  
  const action = id ? 'update_tournament' : 'create_tournament';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'Tournament updated!' : 'Tournament created!');
    closeModal('tournamentModal');
    loadTournaments();
  }
}

async function toggleTournament(id, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this tournament?`)) return;
  
  const result = await fetchAPI('toggle_tournament', { tour_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    alert(`Tournament ${action}d!`);
    loadTournaments();
  }
}

function editTournament(id) {
  showTournamentModal(id);
}

// ==========================================
// TEAMS
// ==========================================

async function loadTeams() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('teams', params);
    const content = $('#teamsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No teams found. Click "Add Team" to create one.</div>';
      return;
    }
    
    // Group by sport
    const grouped = {};
    data.forEach(t => {
      const sport = t.sports_name || 'No Sport';
      if (!grouped[sport]) grouped[sport] = [];
      grouped[sport].push(t);
    });
    
    let html = '';
    Object.keys(grouped).sort().forEach(sport => {
      const teams = grouped[sport];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(sport)}</h4>
          <span class="group-badge">${teams.length} team${teams.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="data-grid">
          ${teams.map(t => renderTeamCard(t)).join('')}
        </div>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeams error:', err);
  }
}

function renderTeamCard(t) {
  const statusClass = t.is_active == 1 ? 'active' : 'inactive';
  const statusText = t.is_active == 1 ? 'Active' : 'Inactive';
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(t.team_name)}</div>
        <span class="badge badge-${statusClass}">${statusText}</span>
      </div>
      <div class="data-card-meta">
        ⚽ ${escapeHtml(t.sports_name)} • 👥 ${t.num_players || 0} players
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-secondary" onclick="editTeam(${t.team_id})">Edit</button>
        <button class="btn btn-sm btn-${t.is_active == 1 ? 'danger' : 'success'}" 
                onclick="toggleTeam(${t.team_id}, ${t.is_active})">
          ${t.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
      </div>
    </div>
  `;
}

function showTeamModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Team' : 'Add Team';
  
  const modalHTML = `
    <div class="modal active" id="teamModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('teamModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="teamForm" onsubmit="saveTeam(event, ${id})">
            <div class="form-group">
              <label class="form-label">Team Name *</label>
              <input type="text" class="form-control" id="team_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Sport *</label>
              <select class="form-control" id="team_sport_id" required>
                <option value="">Select Sport</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('teamModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#teamForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Create'} Team
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  loadSportsOptions('team_sport_id');
  
  if (isEdit) {
    loadTeamData(id);
  }
}

async function loadTeamData(id) {
  const data = await fetchAPI('teams');
  const team = data.find(t => t.team_id == id);
  
  if (team) {
    $('#team_name').value = team.team_name || '';
    $('#team_sport_id').value = team.sports_id || '';
  }
}

async function saveTeam(e, id) {
  e.preventDefault();
  
  const data = {
    team_name: $('#team_name').value,
    sports_id: $('#team_sport_id').value
  };
  
  if (id) {
    data.team_id = id;
  }
  
  const action = id ? 'update_team' : 'create_team';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'Team updated!' : 'Team created!');
    closeModal('teamModal');
    loadTeams();
  }
}

async function toggleTeam(id, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this team?`)) return;
  
  const result = await fetchAPI('toggle_team', { team_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    alert(`Team ${action}d!`);
    loadTeams();
  }
}

function editTeam(id) {
  showTeamModal(id);
}

// ==========================================
// ATHLETES
// ==========================================

async function loadAthletes() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('athletes', params);
    const content = $('#athletesContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No athletes found. Click "Add Athlete" to register one.</div>';
      return;
    }
    
    // Group by sport
    const grouped = {};
    data.forEach(a => {
      const sport = a.sports_name || 'Unassigned';
      if (!grouped[sport]) grouped[sport] = [];
      grouped[sport].push(a);
    });
    
    let html = '';
    Object.keys(grouped).sort().forEach(sport => {
      const athletes = grouped[sport];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(sport)}</h4>
          <span class="group-badge">${athletes.length} athlete${athletes.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="data-grid">
          ${athletes.map(a => renderAthleteCard(a)).join('')}
        </div>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Athletes loaded:', data.length);
  } catch (err) {
    console.error('❌ loadAthletes error:', err);
  }
}

function renderAthleteCard(a) {
  const statusClass = a.is_active == 1 ? 'active' : 'inactive';
  const statusText = a.is_active == 1 ? 'Active' : 'Inactive';
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(a.athlete_name)}</div>
        <span class="badge badge-${statusClass}">${statusText}</span>
      </div>
      <div class="data-card-meta">
        👥 ${escapeHtml(a.team_name || 'No team')} • ⚽ ${escapeHtml(a.sports_name || 'No sport')}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-secondary" onclick="editAthlete(${a.person_id})">Edit</button>
        <button class="btn btn-sm btn-${a.is_active == 1 ? 'danger' : 'success'}" 
                onclick="toggleAthlete(${a.person_id}, ${a.is_active})">
          ${a.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
      </div>
    </div>
  `;
}

function showAthleteModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Athlete' : 'Add Athlete';
  
  const modalHTML = `
    <div class="modal active" id="athleteModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('athleteModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="athleteForm" onsubmit="saveAthlete(event, ${id})">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" id="f_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" id="l_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" id="m_name">
            </div>
            <div class="form-group">
              <label class="form-label">College</label>
              <input type="text" class="form-control" id="college_code" placeholder="e.g., CECS">
            </div>
            <div class="form-group">
              <label class="form-label">Course</label>
              <input type="text" class="form-control" id="course" placeholder="e.g., BSCS">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('athleteModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#athleteForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Register'} Athlete
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  
  if (isEdit) {
    loadAthleteData(id);
  }
}

async function loadAthleteData(id) {
  const data = await fetchAPI('athletes');
  const athlete = data.find(a => a.person_id == id);
  
  if (athlete) {
    $('#f_name').value = athlete.f_name || '';
    $('#l_name').value = athlete.l_name || '';
    $('#m_name').value = athlete.m_name || '';
    $('#college_code').value = athlete.college_code || '';
    $('#course').value = athlete.course || '';
  }
}

async function saveAthlete(e, id) {
  e.preventDefault();
  
  const data = {
    f_name: $('#f_name').value,
    l_name: $('#l_name').value,
    m_name: $('#m_name').value || null,
    college_code: $('#college_code').value || null,
    course: $('#course').value || null
  };
  
  if (id) {
    data.person_id = id;
  }
  
  const action = id ? 'update_athlete' : 'create_athlete';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'Athlete updated!' : 'Athlete registered!');
    closeModal('athleteModal');
    loadAthletes();
  }
}

async function toggleAthlete(id, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this athlete?`)) return;
  
  const result = await fetchAPI('toggle_athlete', { person_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    alert(`Athlete ${action}d!`);
    loadAthletes();
  }
}

function editAthlete(id) {
  showAthleteModal(id);
}

// ==========================================
// MATCHES
// ==========================================

async function loadMatches() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('matches', params);
    const content = $('#matchesContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No matches found. Click "Schedule Match" to create one.</div>';
      return;
    }
    
    // Group by tournament + sport
    const grouped = {};
    data.forEach(m => {
      const key = `${m.tour_name || 'No Tournament'} • ${m.sports_name}`;
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(m);
    });
    
    let html = '';
    Object.keys(grouped).sort().forEach(key => {
      const matches = grouped[key];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(key)}</h4>
          <span class="group-badge">${matches.length} match${matches.length !== 1 ? 'es' : ''}</span>
        </div>
        <div class="data-grid">
          ${matches.map(m => renderMatchCard(m)).join('')}
        </div>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
  }
}

function renderMatchCard(m) {
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)}</div>
        <span class="badge badge-active">${escapeHtml(m.match_type)}</span>
      </div>
      <div class="data-card-meta">
        📅 ${escapeHtml(m.sked_date)} ${escapeHtml(m.sked_time || '')} • 📍 ${escapeHtml(m.venue_name || 'TBA')}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-secondary" onclick="editMatch(${m.match_id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteMatch(${m.match_id})">Cancel</button>
      </div>
    </div>
  `;
}

function showMatchModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Match' : 'Schedule Match';
  
  const modalHTML = `
    <div class="modal active" id="matchModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('matchModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="matchForm" onsubmit="saveMatch(event, ${id})">
            <div class="form-group">
              <label class="form-label">Tournament *</label>
              <select class="form-control" id="match_tour_id" required>
                <option value="">Select Tournament</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Sport *</label>
              <select class="form-control" id="match_sport_id" required onchange="loadTeamsForMatch()">
                <option value="">Select Sport</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Team A *</label>
              <select class="form-control" id="team_a_id" required>
                <option value="">Select Team A</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Team B *</label>
              <select class="form-control" id="team_b_id" required>
                <option value="">Select Team B</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date *</label>
              <input type="date" class="form-control" id="sked_date" required>
            </div>
            <div class="form-group">
              <label class="form-label">Time</label>
              <input type="time" class="form-control" id="sked_time">
            </div>
            <div class="form-group">
              <label class="form-label">Venue *</label>
              <select class="form-control" id="match_venue_id" required>
                <option value="">Select Venue</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Match Type</label>
              <select class="form-control" id="match_type">
                <option value="EL">Elimination</option>
                <option value="QF">Quarter Final</option>
                <option value="SF">Semi Final</option>
                <option value="F">Final</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('matchModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#matchForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Schedule'} Match
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  loadTournamentsOptions('match_tour_id');
  loadSportsOptions('match_sport_id');
  loadVenuesOptions('match_venue_id');
  
  if (isEdit) {
    loadMatchData(id);
  }
}

async function loadTeamsForMatch() {
  const sportId = $('#match_sport_id').value;
  if (!sportId) return;
  
  const teams = await fetchAPI('teams', { sport_id: sportId });
  
  const teamASelect = $('#team_a_id');
  const teamBSelect = $('#team_b_id');
  
  const options = teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  
  teamASelect.innerHTML = '<option value="">Select Team A</option>' + options;
  teamBSelect.innerHTML = '<option value="">Select Team B</option>' + options;
}

async function loadMatchData(id) {
  const data = await fetchAPI('matches');
  const match = data.find(m => m.match_id == id);
  
  if (match) {
    $('#match_tour_id').value = match.tour_id || '';
    $('#match_sport_id').value = match.sports_id || '';
    await loadTeamsForMatch();
    $('#team_a_id').value = match.team_a_id || '';
    $('#team_b_id').value = match.team_b_id || '';
    $('#sked_date').value = match.sked_date || '';
    $('#sked_time').value = match.sked_time || '';
    $('#match_venue_id').value = match.venue_id || '';
    $('#match_type').value = match.match_type || 'EL';
  }
}

async function saveMatch(e, id) {
  e.preventDefault();
  
  const data = {
    tour_id: $('#match_tour_id').value,
    sports_id: $('#match_sport_id').value,
    team_a_id: $('#team_a_id').value,
    team_b_id: $('#team_b_id').value,
    sked_date: $('#sked_date').value,
    sked_time: $('#sked_time').value || null,
    venue_id: $('#match_venue_id').value,
    match_type: $('#match_type').value
  };
  
  if (id) {
    data.match_id = id;
  }
  
  const action = id ? 'update_match' : 'create_match';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'Match updated!' : 'Match scheduled!');
    closeModal('matchModal');
    loadMatches();
  }
}

async function deleteMatch(id) {
  if (!confirm('Are you sure you want to cancel this match?')) return;
  
  const result = await fetchAPI('delete_match', { match_id: id }, 'POST');
  
  if (result && result.ok) {
    alert('Match cancelled!');
    loadMatches();
  }
}

function editMatch(id) {
  showMatchModal(id);
}

// ==========================================
// TRAINING
// ==========================================

async function loadTraining() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('training', params);
    const content = $('#trainingContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No training sessions found. Click "Add Training" to create one.</div>';
      return;
    }
    
    // Group by team
    const grouped = {};
    data.forEach(t => {
      const team = t.team_name || 'No Team';
      if (!grouped[team]) grouped[team] = [];
      grouped[team].push(t);
    });
    
    let html = '';
    Object.keys(grouped).sort().forEach(team => {
      const sessions = grouped[team];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(team)}</h4>
          <span class="group-badge">${sessions.length} session${sessions.length !== 1 ? 's' : ''}</span>
        </div>
        <div class="data-grid">
          ${sessions.map(t => renderTrainingCard(t)).join('')}
        </div>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Training loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTraining error:', err);
  }
}

function renderTrainingCard(t) {
  const statusClass = t.is_active == 1 ? 'active' : 'inactive';
  const statusText = t.is_active == 1 ? 'Active' : 'Cancelled';
  
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(t.team_name)}</div>
        <span class="badge badge-${statusClass}">${statusText}</span>
      </div>
      <div class="data-card-meta">
        📅 ${escapeHtml(t.sked_date)} ${escapeHtml(t.sked_time || '')} • 📍 ${escapeHtml(t.venue_name || 'TBA')}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-secondary" onclick="editTraining(${t.sked_id})">Edit</button>
        <button class="btn btn-sm btn-danger" onclick="deleteTraining(${t.sked_id})">Cancel</button>
      </div>
    </div>
  `;
}

function showTrainingModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Training' : 'Add Training';
  
  const modalHTML = `
    <div class="modal active" id="trainingModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('trainingModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="trainingForm" onsubmit="saveTraining(event, ${id})">
            <div class="form-group">
              <label class="form-label">Team *</label>
              <select class="form-control" id="training_team_id" required>
                <option value="">Select Team</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Date *</label>
              <input type="date" class="form-control" id="training_date" required>
            </div>
            <div class="form-group">
              <label class="form-label">Time *</label>
              <input type="time" class="form-control" id="training_time" required>
            </div>
            <div class="form-group">
              <label class="form-label">Venue *</label>
              <select class="form-control" id="training_venue_id" required>
                <option value="">Select Venue</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('trainingModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#trainingForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Schedule'} Training
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  loadTeamsOptions('training_team_id');
  loadVenuesOptions('training_venue_id');
  
  if (isEdit) {
    loadTrainingData(id);
  }
}

async function loadTrainingData(id) {
  const data = await fetchAPI('training');
  const training = data.find(t => t.sked_id == id);
  
  if (training) {
    $('#training_team_id').value = training.team_id || '';
    $('#training_date').value = training.sked_date || '';
    $('#training_time').value = training.sked_time || '';
    $('#training_venue_id').value = training.venue_id || '';
  }
}

async function saveTraining(e, id) {
  e.preventDefault();
  
  const data = {
    team_id: $('#training_team_id').value,
    sked_date: $('#training_date').value,
    sked_time: $('#training_time').value,
    venue_id: $('#training_venue_id').value
  };
  
  if (id) {
    data.sked_id = id;
  }
  
  const action = id ? 'update_training' : 'create_training';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'Training updated!' : 'Training scheduled!');
    closeModal('trainingModal');
    loadTraining();
  }
}

async function deleteTraining(id) {
  if (!confirm('Are you sure you want to cancel this training session?')) return;
  
  const result = await fetchAPI('delete_training', { sked_id: id }, 'POST');
  
  if (result && result.ok) {
    alert('Training cancelled!');
    loadTraining();
  }
}

function editTraining(id) {
  showTrainingModal(id);
}

// ==========================================
// STANDINGS
// ==========================================

async function loadStandings() {
  try {
    const params = currentSportFilter ? { sport_id: currentSportFilter } : {};
    const data = await fetchAPI('standings', params);
    const content = $('#standingsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No standings data available</div>';
      return;
    }
    
    // Group by sport
    const grouped = {};
    data.forEach(s => {
      const sport = s.sports_name || 'Unknown Sport';
      if (!grouped[sport]) grouped[sport] = [];
      grouped[sport].push(s);
    });
    
    let html = '';
    Object.keys(grouped).sort().forEach(sport => {
      const standings = grouped[sport];
      html += `
        <div class="group-header">
          <h4 class="group-title">${escapeHtml(sport)}</h4>
          <span class="group-badge">Top ${standings.length}</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Rank</th>
              <th>Team</th>
              <th>Played</th>
              <th>Won</th>
              <th>Lost</th>
              <th>Medals</th>
            </tr>
          </thead>
          <tbody>
            ${standings.map((s, idx) => `
              <tr>
                <td><strong>${idx + 1}</strong></td>
                <td>${escapeHtml(s.team_name)}</td>
                <td>${s.no_games_played || 0}</td>
                <td>${s.no_win || 0}</td>
                <td>${s.no_loss || 0}</td>
                <td>🥇${s.no_gold || 0} 🥈${s.no_silver || 0} 🥉${s.no_bronze || 0}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      `;
    });
    
    content.innerHTML = html;
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandings error:', err);
  }
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

async function loadSportsOptions(selectId) {
  const sports = await fetchAPI('sports');
  const select = $(`#${selectId}`);
  
  if (sports && select) {
    const options = sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
    select.innerHTML += options;
  }
}

async function loadTournamentsOptions(selectId) {
  const tournaments = await fetchAPI('tournaments');
  const select = $(`#${selectId}`);
  
  if (tournaments && select) {
    const options = tournaments.filter(t => t.is_active == 1).map(t => 
      `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} - ${escapeHtml(t.school_year)}</option>`
    ).join('');
    select.innerHTML += options;
  }
}

async function loadTeamsOptions(selectId) {
  const teams = await fetchAPI('teams');
  const select = $(`#${selectId}`);
  
  if (teams && select) {
    const options = teams.filter(t => t.is_active == 1).map(t => 
      `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`
    ).join('');
    select.innerHTML += options;
  }
}

async function loadVenuesOptions(selectId) {
  const venues = await fetchAPI('venues');
  const select = $(`#${selectId}`);
  
  if (venues && select) {
    const options = venues.filter(v => v.is_active == 1).map(v => 
      `<option value="${v.venue_id}">${escapeHtml(v.venue_name)}</option>`
    ).join('');
    select.innerHTML += options;
  }
}

function closeModal(modalId) {
  const modal = $(`#${modalId}`);
  if (modal) {
    modal.remove();
  }
}

// ==========================================
// LOAD SPORTS FOR GLOBAL FILTER
// ==========================================

async function loadGlobalSportFilter() {
  const sports = await fetchAPI('sports');
  const select = $('#globalSportFilter');
  
  if (sports && select) {
    const options = sports.map(s => 
      `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`
    ).join('');
    select.innerHTML = '<option value="">All Sports</option>' + options;
  }
}

// ==========================================
// INITIALIZATION
// ==========================================

(async function init() {
  console.log('🚀 Initializing Sports Director dashboard...');
  
  try {
    await loadGlobalSportFilter();
    await loadOverview();
    
    console.log('✅ Dashboard initialized');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();