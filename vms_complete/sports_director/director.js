const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.DIRECTOR_CONTEXT.person_id;

// Filter state
let currentFilters = {
  school_year: '',
  tournament: '',
  team: '',
  sport: ''
};

let currentContext = {
  tour_id: null,
  team_id: null,
  sports_id: null
};

$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    $$('.content-view').forEach(v => v.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
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
    
    loadViewData(view);
  });
});

// Initialize filters immediately
(async function() {
  await initializeFilters();
  // Load initial view
  loadViewData('overview');
})();

async function initializeFilters() {
  try {
    // Load initial filter data
    const [tournaments, teams, sports] = await Promise.all([
      fetchAPI('tournaments'),
      fetchAPI('teams'),
      fetchAPI('sports')
    ]);
    
    // Populate school years from tournaments
    const schoolYears = [...new Set(tournaments.map(t => t.school_year))].sort().reverse();
    const schoolYearSelect = $('#filterSchoolYear');
    if (schoolYearSelect) {
      schoolYearSelect.innerHTML = '<option value="">All School Years</option>' +
        schoolYears.map(sy => `<option value="${sy}">${sy}</option>`).join('');
    }
    
    // Populate tournaments
    const tournamentSelect = $('#filterTournament');
    if (tournamentSelect) {
      tournamentSelect.innerHTML = '<option value="">All Tournaments</option>' +
        tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`).join('');
    }
    
    // Populate teams
    const teamSelect = $('#filterTeam');
    if (teamSelect) {
      teamSelect.innerHTML = '<option value="">All Teams</option>' +
        teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
    }
    
    // Populate sports
    const sportSelect = $('#filterSport');
    if (sportSelect) {
      sportSelect.innerHTML = '<option value="">All Sports</option>' +
        sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
    }
    
    // Add event listeners
    $('#filterSchoolYear')?.addEventListener('change', handleFilterChange);
    $('#filterTournament')?.addEventListener('change', handleFilterChange);
    $('#filterTeam')?.addEventListener('change', handleFilterChange);
    $('#filterSport')?.addEventListener('change', handleFilterChange);
    $('#clearFilters')?.addEventListener('click', clearAllFilters);
    
  } catch (error) {
    console.error('Error initializing filters:', error);
  }
}

function handleFilterChange(e) {
  const filterType = e.target.id.replace('filter', '').toLowerCase();
  currentFilters[filterType === 'schoolyear' ? 'school_year' : filterType] = e.target.value;
  
  // Show/hide clear button
  const hasActiveFilters = Object.values(currentFilters).some(v => v !== '');
  const clearBtn = $('#clearFilters');
  if (clearBtn) {
    clearBtn.style.display = hasActiveFilters ? 'inline-flex' : 'none';
  }
  
  // If school year is selected, filter tournaments
  if (filterType === 'schoolyear') {
    updateTournamentFilter();
  }
  
  // If tournament is selected, filter teams
  if (filterType === 'tournament') {
    updateTeamFilter();
  }
  
  // Reload current view with filters
  const currentView = $('.content-view.active').id.replace('-view', '');
  loadViewData(currentView);
}

async function updateTournamentFilter() {
  const schoolYear = currentFilters.school_year;
  const tournamentSelect = $('#filterTournament');
  
  if (!schoolYear) {
    // Reset to all tournaments
    const tournaments = await fetchAPI('tournaments');
    tournamentSelect.innerHTML = '<option value="">All Tournaments</option>' +
      tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`).join('');
  } else {
    // Filter tournaments by school year
    const tournaments = await fetchAPI('tournaments');
    const filtered = tournaments.filter(t => t.school_year === schoolYear);
    tournamentSelect.innerHTML = '<option value="">All Tournaments</option>' +
      filtered.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`).join('');
  }
  
  // Reset tournament selection if it's no longer in the list
  if (currentFilters.tournament && !Array.from(tournamentSelect.options).some(opt => opt.value === currentFilters.tournament)) {
    currentFilters.tournament = '';
    tournamentSelect.value = '';
  }
}

async function updateTeamFilter() {
  const tournamentId = currentFilters.tournament;
  const teamSelect = $('#filterTeam');
  
  if (!tournamentId) {
    // Reset to all teams
    const teams = await fetchAPI('teams');
    teamSelect.innerHTML = '<option value="">All Teams</option>' +
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  } else {
    // Filter teams by tournament
    const tournamentTeams = await fetchAPI('get_tournament_teams', { tour_id: tournamentId });
    teamSelect.innerHTML = '<option value="">All Teams</option>' +
      tournamentTeams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  }
  
  // Reset team selection if it's no longer in the list
  if (currentFilters.team && !Array.from(teamSelect.options).some(opt => opt.value === currentFilters.team)) {
    currentFilters.team = '';
    teamSelect.value = '';
  }
}

function clearAllFilters() {
  currentFilters = {
    school_year: '',
    tournament: '',
    team: '',
    sport: ''
  };
  
  $('#filterSchoolYear').value = '';
  $('#filterTournament').value = '';
  $('#filterTeam').value = '';
  $('#filterSport').value = '';
  $('#clearFilters').style.display = 'none';
  
  // Reload filters
  initializeFilters();
  
  // Reload current view
  const currentView = $('.content-view.active').id.replace('-view', '');
  loadViewData(currentView);
}

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
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.tournament) params.tour_id = currentFilters.tournament;
    if (currentFilters.team) params.team_id = currentFilters.team;
    if (currentFilters.school_year) params.school_year = currentFilters.school_year;
    
    const data = await fetchAPI('stats', params);
    
    if (data) {
      $('#statTournaments').textContent = data.tournaments || 0;
      $('#statTeams').textContent = data.teams || 0;
      $('#statAthletes').textContent = data.athletes || 0;
      $('#statMatches').textContent = data.upcoming_matches || 0;
    }
    
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
    
    console.log('✅ Overview loaded with filters:', currentFilters);
  } catch (err) {
    console.error('❌ loadOverview error:', err);
  }
}

// ==========================================
// TOURNAMENTS
// ==========================================
async function loadTournaments() {
  try {
    let data = await fetchAPI('tournaments');
    const content = $('#tournamentsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No tournaments found. Click "Add Tournament" to create one.</div>';
      return;
    }
    
    // Apply school year filter if selected
    if (currentFilters.school_year) {
      data = data.filter(t => t.school_year === currentFilters.school_year);
    }
    
    if (data.length === 0) {
      content.innerHTML = '<div class="empty-state">No tournaments found matching the selected filters.</div>';
      return;
    }
    
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
        📅 ${escapeHtml(t.tour_date || 'No date')} • ${escapeHtml(t.school_year)}<br>
        👥 ${t.num_teams || 0} team${t.num_teams !== 1 ? 's' : ''} • 
        ⚽ ${t.num_sports || 0} sport${t.num_sports !== 1 ? 's' : ''} • 
        🏃 ${t.num_athletes || 0} athlete${t.num_athletes !== 1 ? 's' : ''}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm btn-primary" onclick="showComprehensiveTournamentView(${t.tour_id})" title="View complete tournament overview">
          📋 Full Overview
        </button>
        <button class="btn btn-sm btn-success" onclick="viewTournamentDetail(${t.tour_id})" title="Manage teams and athletes">
          👥 Manage
        </button>
        <button class="btn btn-sm btn-secondary" onclick="editTournament(${t.tour_id})">
          ✏️ Edit
        </button>
        <button class="btn btn-sm btn-${t.is_active == 1 ? 'danger' : 'success'}" 
                onclick="toggleTournament(${t.tour_id}, ${t.is_active})">
          ${t.is_active == 1 ? '❌ Deactivate' : '✅ Activate'}
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
    tour_date: $('#tour_date').value
  };
  
  if (id) data.tour_id = id;
  
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
  const result = await fetchAPI('toggle_tournament', { tour_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    loadTournaments();
  }
}

function editTournament(id) {
  showTournamentModal(id);
}

// ==========================================
// TOURNAMENT DETAIL - VIEW TEAMS
// ==========================================
async function viewTournamentDetail(tourId) {
  currentContext.tour_id = tourId;
  
  const tournaments = await fetchAPI('tournaments');
  const tournament = tournaments.find(t => t.tour_id == tourId);
  
  const modalHTML = `
    <div class="modal active" id="tournamentDetailModal">
      <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
          <h3>${escapeHtml(tournament.tour_name)} - Teams</h3>
          <button class="modal-close" onclick="closeModal('tournamentDetailModal')">×</button>
        </div>
        <div class="modal-body">
          <div style="margin-bottom: 12px;">
            <button class="btn btn-primary" onclick="showAddTeamToTournamentModal(${tourId})">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
              </svg>
              Add Team to Tournament
            </button>
          </div>
          <div id="tournamentTeamsList">
            <div class="loading">Loading teams...</div>
          </div>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  loadTournamentTeams(tourId);
}

async function loadTournamentTeams(tourId) {
  let teams = await fetchAPI('get_tournament_teams', { tour_id: tourId });
  const container = $('#tournamentTeamsList');
  
  if (!teams || teams.length === 0) {
    container.innerHTML = '<div class="empty-state">No teams added yet. Click "Add Team to Tournament" to get started.</div>';
    return;
  }
  
  // Apply team filter if selected
  if (currentFilters.team) {
    teams = teams.filter(t => t.team_id == currentFilters.team);
    if (teams.length === 0) {
      container.innerHTML = '<div class="empty-state">No teams match the selected filter.</div>';
      return;
    }
  }
  
  container.innerHTML = `
    <div class="data-grid">
      ${teams.map(tt => `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(tt.team_name)}</div>
          </div>
          <div class="data-card-meta">
            🏅 ${tt.num_sports || 0} sport${tt.num_sports !== 1 ? 's' : ''}<br>
            👥 ${tt.num_athletes || 0} athlete${tt.num_athletes !== 1 ? 's' : ''}
          </div>
          <div class="data-card-actions">
            <button class="btn btn-sm btn-primary" onclick="viewTeamSports(${tourId}, ${tt.team_id})">Manage Sports</button>
            <button class="btn btn-sm btn-danger" onclick="removeTeamFromTournament(${tourId}, ${tt.team_id})">Remove</button>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

async function showAddTeamToTournamentModal(tourId) {
  const teams = await fetchAPI('teams');
  
  const modalHTML = `
    <div class="modal active" id="addTeamModal">
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h3>Add Team to Tournament</h3>
          <button class="modal-close" onclick="closeModal('addTeamModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="addTeamForm" onsubmit="addTeamToTournament(event, ${tourId})">
            <div class="form-group">
              <label class="form-label">Select Existing Team</label>
              <select class="form-control" id="select_team_id" required>
                <option value="">Choose a team...</option>
                ${teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('')}
              </select>
            </div>
            
            <div style="text-align: center; margin: 15px 0;">
              <span style="color: #9ca3af;">— OR —</span>
            </div>
            
            <button type="button" class="btn btn-success" onclick="closeModal('addTeamModal'); showCreateTeamModal(${tourId})" style="width: 100%;">
              ➕ Create New Team
            </button>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('addTeamModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#addTeamForm').requestSubmit()">Add Existing Team</button>
        </div>
      </div>
    </div>
  `;
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modalHTML;
  document.body.appendChild(tempDiv.firstElementChild);
}

async function addTeamToTournament(e, tourId) {
  e.preventDefault();
  
  const teamId = $('#select_team_id').value;
  const result = await fetchAPI('add_team_to_tournament', { tour_id: tourId, team_id: teamId }, 'POST');
  
  if (result && result.ok) {
    closeModal('addTeamModal');
    loadTournamentTeams(tourId);
    showToast('Team added to tournament!', 'success');
  } else {
    showToast(result.error || 'Error adding team', 'error');
  }
}

async function removeTeamFromTournament(tourId, teamId) {
  if (!confirm('Remove this team from the tournament?')) return;
  
  const result = await fetchAPI('remove_team_from_tournament', { tour_id: tourId, team_id: teamId }, 'POST');
  
  if (result && result.ok) {
    loadTournamentTeams(tourId);
    showToast('Team removed from tournament', 'success');
  }
}

// ==========================================
// TEAM SPORTS MANAGEMENT
// ==========================================
async function viewTeamSports(tourId, teamId) {
  currentContext.tour_id = tourId;
  currentContext.team_id = teamId;
  
  const teams = await fetchAPI('get_tournament_teams', { tour_id: tourId });
  const team = teams.find(t => t.team_id == teamId);
  
  const modalHTML = `
    <div class="modal active" id="teamSportsModal">
      <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
          <h3>${escapeHtml(team.team_name)} - Sports</h3>
          <button class="modal-close" onclick="closeModal('teamSportsModal'); viewTournamentDetail(${tourId})">×</button>
        </div>
        <div class="modal-body">
          <div style="margin-bottom: 12px;">
            <button class="btn btn-primary" onclick="showAddSportToTeamModal(${tourId}, ${teamId})">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
              </svg>
              Add Sport
            </button>
          </div>
          <div id="teamSportsList">
            <div class="loading">Loading sports...</div>
          </div>
        </div>
      </div>
    </div>
  `;
  
  closeModal('tournamentDetailModal');
  $('#modalContainer').innerHTML = modalHTML;
  loadTeamSports(tourId, teamId);
}

async function loadTeamSports(tourId, teamId) {
  let sports = await fetchAPI('get_team_sports', { tour_id: tourId, team_id: teamId });
  const container = $('#teamSportsList');
  
  if (!sports || sports.length === 0) {
    container.innerHTML = '<div class="empty-state">No sports added yet. Click "Add Sport" to get started.</div>';
    return;
  }
  
  // Apply sport filter if selected
  if (currentFilters.sport) {
    sports = sports.filter(s => s.sports_id == currentFilters.sport);
    if (sports.length === 0) {
      container.innerHTML = '<div class="empty-state">No sports match the selected filter.</div>';
      return;
    }
  }
  
  container.innerHTML = `
    <div class="data-grid">
      ${sports.map(st => `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(st.sports_name)}</div>
          </div>
          <div class="data-card-meta">
            👨‍🏫 Coach: ${escapeHtml(st.coach_name || 'Not assigned')}<br>
            🎯 Manager: ${escapeHtml(st.tournament_manager_name || 'Not assigned')}<br>
            👥 ${st.num_athletes || 0} athlete${st.num_athletes !== 1 ? 's' : ''}
          </div>
          <div class="data-card-actions">
            <button class="btn btn-sm btn-primary" onclick="viewSportAthletes(${tourId}, ${teamId}, ${st.sports_id})">Manage Athletes</button>
            <button class="btn btn-sm btn-secondary" onclick="assignStaffToSport(${tourId}, ${teamId}, ${st.sports_id})">Assign Staff</button>
            <button class="btn btn-sm btn-danger" onclick="removeSportFromTeam(${tourId}, ${teamId}, ${st.sports_id})">Remove</button>
          </div>
        </div>
      `).join('')}
    </div>
  `;
}

async function showAddSportToTeamModal(tourId, teamId) {
  const sports = await fetchAPI('sports');
  
  const modalHTML = `
    <div class="modal active" id="addSportModal">
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h3>Add Sport to Team</h3>
          <button class="modal-close" onclick="closeModal('addSportModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="addSportForm" onsubmit="addSportToTeam(event, ${tourId}, ${teamId})">
            <div class="form-group">
              <label class="form-label">Select Sport *</label>
              <select class="form-control" id="select_sport_id" required>
                <option value="">Choose a sport...</option>
                ${sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('')}
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('addSportModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#addSportForm').requestSubmit()">Add Sport</button>
        </div>
      </div>
    </div>
  `;
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modalHTML;
  document.body.appendChild(tempDiv.firstElementChild);
}

async function addSportToTeam(e, tourId, teamId) {
  e.preventDefault();
  
  const sportsId = $('#select_sport_id').value;
  const result = await fetchAPI('add_sport_to_team', { tour_id: tourId, team_id: teamId, sports_id: sportsId }, 'POST');
  
  if (result && result.ok) {
    closeModal('addSportModal');
    loadTeamSports(tourId, teamId);
    showToast('Sport added to team!', 'success');
  } else {
    showToast(result.error || 'Error adding sport', 'error');
  }
}

async function removeSportFromTeam(tourId, teamId, sportsId) {
  if (!confirm('Remove this sport from the team?')) return;
  
  const result = await fetchAPI('remove_sport_from_team', { tour_id: tourId, team_id: teamId, sports_id: sportsId }, 'POST');
  
  if (result && result.ok) {
    loadTeamSports(tourId, teamId);
    showToast('Sport removed from team', 'success');
  }
}

async function assignStaffToSport(tourId, teamId, sportsId) {
  const coaches = await fetchAPI('staff', { role: 'coach' });
  const managers = await fetchAPI('staff', { role: 'tournament_manager' });
  const trainors = await fetchAPI('staff', { role: 'trainor' });
  
  const sports = await fetchAPI('get_team_sports', { tour_id: tourId, team_id: teamId });
  const sport = sports.find(s => s.sports_id == sportsId);
  
  const modalHTML = `
    <div class="modal active" id="assignStaffModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Assign Staff - ${escapeHtml(sport.sports_name)}</h3>
          <button class="modal-close" onclick="closeModal('assignStaffModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="assignStaffForm" onsubmit="saveStaffAssignment(event, ${tourId}, ${teamId}, ${sportsId})">
            <div class="form-group">
              <label class="form-label">Head Coach</label>
              <select class="form-control" id="coach_id">
                <option value="">Select Coach</option>
                ${coaches.map(c => `<option value="${c.person_id}" ${sport.coach_id == c.person_id ? 'selected' : ''}>${escapeHtml(c.full_name)}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Assistant Coach</label>
              <select class="form-control" id="asst_coach_id">
                <option value="">Select Assistant Coach</option>
                ${coaches.map(c => `<option value="${c.person_id}" ${sport.asst_coach_id == c.person_id ? 'selected' : ''}>${escapeHtml(c.full_name)}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tournament Manager</label>
              <select class="form-control" id="tournament_manager_id">
                <option value="">Select Tournament Manager</option>
                ${managers.map(m => `<option value="${m.person_id}" ${sport.tournament_manager_id == m.person_id ? 'selected' : ''}>${escapeHtml(m.full_name)}</option>`).join('')}
              </select>
            </div>
            <h4 style="margin:16px 0 8px 0;font-size:13px;font-weight:600;">Trainors (Optional)</h4>
            <div class="form-group">
              <label class="form-label">Trainor 1</label>
              <select class="form-control" id="trainor1_id">
                <option value="">Select Trainor</option>
                ${trainors.map(t => `<option value="${t.person_id}" ${sport.trainor1_id == t.person_id ? 'selected' : ''}>${escapeHtml(t.full_name)}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Trainor 2</label>
              <select class="form-control" id="trainor2_id">
                <option value="">Select Trainor</option>
                ${trainors.map(t => `<option value="${t.person_id}" ${sport.trainor2_id == t.person_id ? 'selected' : ''}>${escapeHtml(t.full_name)}</option>`).join('')}
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Trainor 3</label>
              <select class="form-control" id="trainor3_id">
                <option value="">Select Trainor</option>
                ${trainors.map(t => `<option value="${t.person_id}" ${sport.trainor3_id == t.person_id ? 'selected' : ''}>${escapeHtml(t.full_name)}</option>`).join('')}
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('assignStaffModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#assignStaffForm').requestSubmit()">Save Assignments</button>
        </div>
      </div>
    </div>
  `;
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modalHTML;
  document.body.appendChild(tempDiv.firstElementChild);
}

async function saveStaffAssignment(e, tourId, teamId, sportsId) {
  e.preventDefault();
  
  const data = {
    tour_id: tourId,
    team_id: teamId,
    sports_id: sportsId,
    coach_id: $('#coach_id').value || null,
    asst_coach_id: $('#asst_coach_id').value || null,
    tournament_manager_id: $('#tournament_manager_id').value || null,
    trainor1_id: $('#trainor1_id').value || null,
    trainor2_id: $('#trainor2_id').value || null,
    trainor3_id: $('#trainor3_id').value || null
  };
  
  const result = await fetchAPI('update_sport_staff', data, 'POST');
  
  if (result && result.ok) {
    closeModal('assignStaffModal');
    loadTeamSports(tourId, teamId);
    showToast('Staff assignments updated!', 'success');
  } else {
    showToast(result.error || 'Error updating staff', 'error');
  }
}

// ==========================================
// SPORT ATHLETES MANAGEMENT
// ==========================================
async function viewSportAthletes(tourId, teamId, sportsId) {
  currentContext.tour_id = tourId;
  currentContext.team_id = teamId;
  currentContext.sports_id = sportsId;
  
  const sports = await fetchAPI('get_team_sports', { tour_id: tourId, team_id: teamId });
  const sport = sports.find(s => s.sports_id == sportsId);
  
  const modalHTML = `
    <div class="modal active" id="sportAthletesModal">
      <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
          <h3>${escapeHtml(sport.sports_name)} - Athletes</h3>
          <button class="modal-close" onclick="closeModal('sportAthletesModal'); viewTeamSports(${tourId}, ${teamId})">×</button>
        </div>
        <div class="modal-body">
          <div style="margin-bottom: 12px; display: flex; gap: 8px;">
            <button class="btn btn-primary" onclick="showAddExistingAthleteModal(${tourId}, ${teamId}, ${sportsId})">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
              </svg>
              Add Existing Athlete
            </button>
            <button class="btn btn-success" onclick="showAthleteModal(null, ${tourId}, ${teamId}, ${sportsId})">
              <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
              </svg>
              Create New Athlete
            </button>
          </div>
          <div id="sportAthletesList">
            <div class="loading">Loading athletes...</div>
          </div>
        </div>
      </div>
    </div>
  `;
  
  closeModal('teamSportsModal');
  $('#modalContainer').innerHTML = modalHTML;
  loadSportAthletes(tourId, teamId, sportsId);
}

async function showAddExistingAthleteModal(tourId, teamId, sportsId) {
  try {
    // Fetch all athletes
    const athletes = await fetchAPI('athletes');
    
    if (!athletes || athletes.length === 0) {
      showToast('⚠️ No athletes found in the system', 'warning');
      return;
    }
    
    const modalHTML = `
      <div class="modal active" id="addExistingAthleteModal" style="z-index: 10000;">
        <div class="modal-content modal-sm">
          <div class="modal-header">
            <h3>👥 Add Existing Athlete</h3>
            <button class="modal-close" onclick="closeModal('addExistingAthleteModal')">×</button>
          </div>
          <form onsubmit="submitAddExistingAthlete(event, ${tourId}, ${teamId}, ${sportsId})" id="addExistingAthleteForm">
            <div class="modal-body">
              <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 13px;">
                Select an athlete from the list below to add to this sport.
              </p>
              
              <div class="form-group">
                <label class="form-label">Select Athlete *</label>
                <select class="form-control" name="person_id" required style="max-height: 300px;">
                  <option value="">Choose an athlete...</option>
                  ${athletes.map(a => `
                    <option value="${a.person_id}">
                      ${escapeHtml(a.athlete_name)} - ${escapeHtml(a.college_code || 'N/A')} - ${escapeHtml(a.course || 'N/A')}
                    </option>
                  `).join('')}
                </select>
                <small style="color: #6b7280; font-size: 11px; margin-top: 4px; display: block;">
                  🔍 Showing all ${athletes.length} registered athlete(s)
                </small>
              </div>
              
              <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px;">
                  <input type="checkbox" name="is_captain" style="width: 18px; height: 18px; cursor: pointer;">
                  <span style="font-weight: 600; color: #92400e;">⭐ Designate as Team Captain</span>
                </label>
              </div>
            </div>
            
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal('addExistingAthleteModal')">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Athlete</button>
            </div>
          </form>
        </div>
      </div>
    `;
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = modalHTML;
    document.body.appendChild(tempDiv.firstElementChild);
    
  } catch (error) {
    console.error('Error loading athletes:', error);
    showToast('❌ Error loading athletes list', 'error');
  }
}

async function submitAddExistingAthlete(event, tourId, teamId, sportsId) {
  event.preventDefault();
  
  const form = event.target;
  const personId = form.person_id.value;
  const isCaptain = form.is_captain.checked ? 1 : 0;
  
  if (!personId) {
    showToast('❌ Please select an athlete', 'error');
    return;
  }
  
  try {
    const result = await fetchAPI('add_existing_athlete', {
      person_id: personId,
      tour_id: tourId,
      team_id: teamId,
      sports_id: sportsId,
      is_captain: isCaptain
    }, 'POST');
    
    if (result && result.ok !== false) {
      closeModal('addExistingAthleteModal');
      loadSportAthletes(tourId, teamId, sportsId);
      showToast('✅ Athlete added successfully', 'success');
    } else {
      showToast('❌ ' + (result?.error || 'Error adding athlete'), 'error');
    }
  } catch (error) {
    console.error('Error adding athlete:', error);
    showToast('❌ Error adding athlete', 'error');
  }
}

async function loadSportAthletes(tourId, teamId, sportsId) {
  const athletes = await fetchAPI('get_sport_athletes', { tour_id: tourId, team_id: teamId, sports_id: sportsId });
  const container = $('#sportAthletesList');
  
  if (!athletes || athletes.length === 0) {
    container.innerHTML = '<div class="empty-state">No athletes added yet. Click "Add Athlete" to get started.</div>';
    return;
  }
  
  container.innerHTML = `
    <div style="margin-bottom: 12px; display: flex; gap: 8px; align-items: center; padding: 10px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
      <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
        <input type="checkbox" id="selectAllAthletes" onchange="toggleSelectAllAthletes()" style="width: 18px; height: 18px; cursor: pointer;">
        <span>Select All</span>
      </label>
      <div style="flex: 1;"></div>
      <div id="bulkActionsContainer" style="display: none; gap: 8px;">
        <span id="selectedCount" style="color: #6b7280; font-size: 13px; font-weight: 600;"></span>
        <button class="btn btn-sm btn-danger" onclick="bulkRemoveAthletes(${tourId}, ${teamId}, ${sportsId})">
          🗑️ Remove Selected
        </button>
      </div>
    </div>
    
    <table>
      <thead>
        <tr>
          <th style="width: 40px;">
            <input type="checkbox" style="width: 18px; height: 18px; cursor: pointer; opacity: 0; pointer-events: none;">
          </th>
          <th>Name</th>
          <th>Role</th>
          <th>College</th>
          <th>Course</th>
          <th>Physical</th>
          <th>Scholarship</th>
          <th>Captain</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        ${athletes.map(a => `
          <tr>
            <td>
              <input type="checkbox" class="athlete-checkbox" data-athlete-id="${a.team_ath_id}" onchange="updateBulkActions()" style="width: 18px; height: 18px; cursor: pointer;">
            </td>
            <td><strong>${escapeHtml(a.full_name)}</strong></td>
            <td>
              <span style="display: inline-block; padding: 3px 8px; background: #dbeafe; color: #1e40af; border-radius: 6px; font-size: 11px; font-weight: 600;">
                ${escapeHtml(a.role_type || 'athlete')}
              </span>
            </td>
            <td>${escapeHtml(a.college_code || 'N/A')}</td>
            <td>${escapeHtml(a.course || 'N/A')}</td>
            <td>
              ${a.height ? `📏 ${a.height}cm` : ''}
              ${a.weight ? ` ⚖️ ${a.weight}kg` : ''}
              ${!a.height && !a.weight ? 'N/A' : ''}
            </td>
            <td>${a.scholarship_name ? `🎓 ${escapeHtml(a.scholarship_name)}` : '-'}</td>
            <td>${a.is_captain ? '⭐ Captain' : ''}</td>
            <td>
              <button class="btn btn-sm btn-secondary" onclick="editAthlete(${a.person_id}, ${a.team_ath_id}, ${tourId}, ${teamId}, ${sportsId})">Edit</button>
              <button class="btn btn-sm btn-danger" onclick="removeAthleteFromSport(${a.team_ath_id}, ${tourId}, ${teamId}, ${sportsId})">Remove</button>
            </td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function toggleSelectAllAthletes() {
  const selectAll = document.getElementById('selectAllAthletes');
  const checkboxes = document.querySelectorAll('.athlete-checkbox');
  
  checkboxes.forEach(cb => {
    cb.checked = selectAll.checked;
  });
  
  updateBulkActions();
}

function updateBulkActions() {
  const checkboxes = document.querySelectorAll('.athlete-checkbox');
  const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
  const bulkActionsContainer = document.getElementById('bulkActionsContainer');
  const selectedCount = document.getElementById('selectedCount');
  const selectAll = document.getElementById('selectAllAthletes');
  
  if (checkedBoxes.length > 0) {
    bulkActionsContainer.style.display = 'flex';
    selectedCount.textContent = `${checkedBoxes.length} selected`;
  } else {
    bulkActionsContainer.style.display = 'none';
  }
  
  // Update "Select All" checkbox state
  if (checkedBoxes.length === checkboxes.length && checkboxes.length > 0) {
    selectAll.checked = true;
    selectAll.indeterminate = false;
  } else if (checkedBoxes.length > 0) {
    selectAll.checked = false;
    selectAll.indeterminate = true;
  } else {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  }
}

async function bulkRemoveAthletes(tourId, teamId, sportsId) {
  const checkboxes = document.querySelectorAll('.athlete-checkbox:checked');
  const athleteIds = Array.from(checkboxes).map(cb => cb.dataset.athleteId);
  
  if (athleteIds.length === 0) {
    showToast('⚠️ No athletes selected', 'warning');
    return;
  }
  
  if (!confirm(`Remove ${athleteIds.length} athlete(s) from this sport?`)) {
    return;
  }
  
  try {
    // Remove each athlete
    let successCount = 0;
    for (const athleteId of athleteIds) {
      const result = await fetchAPI('remove_athlete_from_sport', { team_ath_id: athleteId }, 'POST');
      if (result && result.ok) {
        successCount++;
      }
    }
    
    if (successCount > 0) {
      showToast(`✅ Removed ${successCount} athlete(s)`, 'success');
      loadSportAthletes(tourId, teamId, sportsId);
    } else {
      showToast('❌ Failed to remove athletes', 'error');
    }
  } catch (error) {
    console.error('Error removing athletes:', error);
    showToast('❌ Error removing athletes', 'error');
  }
}

async function removeAthleteFromSport(teamAthId, tourId, teamId, sportsId) {
  if (!confirm('Remove this athlete from the sport?')) return;
  
  const result = await fetchAPI('remove_athlete_from_sport', { team_ath_id: teamAthId }, 'POST');
  
  if (result && result.ok) {
    loadSportAthletes(tourId, teamId, sportsId);
    showToast('Athlete removed', 'success');
  }
}

async function viewAthleteProfile(personId) {
  alert('Athlete profile view - personId: ' + personId);
}

// NEW: Edit athlete function
async function editAthlete(personId, teamAthId, tourId, teamId, sportsId) {
  // Fetch athlete details
  const athletes = await fetchAPI('get_sport_athletes', { tour_id: tourId, team_id: teamId, sports_id: sportsId });
  const athlete = athletes.find(a => a.person_id == personId);
  
  if (athlete) {
    athlete.team_ath_id = teamAthId;
    showAthleteModal(athlete, tourId, teamId, sportsId);
  }
}

// ADD: After initialization, add print styles
const printStyles = document.createElement('style');
printStyles.textContent = `
  @media print {
    .sidebar, .top-bar, .modal-header, .modal-footer, .btn {
      display: none !important;
    }
    .modal-content {
      max-width: 100% !important;
      box-shadow: none !important;
      border: none !important;
      padding: 0 !important;
    }
    .modal {
      background: white !important;
    }
    body {
      font-size: 12pt;
    }
  }
`;
document.head.appendChild(printStyles);

// ==========================================
// TEAMS (Legacy view)
// ==========================================
async function loadTeams() {
  try {
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.tournament) params.tour_id = currentFilters.tournament;
    
    const data = await fetchAPI('teams', params);
    const content = $('#teamsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No teams found. Click "Add Team" to create one.</div>';
      return;
    }
    
    let html = '<div class="data-grid">';
    data.forEach(t => {
      const statusClass = t.is_active == 1 ? 'active' : 'inactive';
      const statusText = t.is_active == 1 ? 'Active' : 'Inactive';
      
      html += `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(t.team_name)}</div>
            <span class="badge badge-${statusClass}">${statusText}</span>
          </div>
          <div class="data-card-meta">
            Team ID: ${t.team_id}
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
    });
    html += '</div>';
    
    content.innerHTML = html;
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeams error:', err);
  }
}

function showTeamModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit Team' : 'Add Team';
  
  // For new teams, show the comprehensive modal
  if (!isEdit) {
    showComprehensiveTeamModal();
    return;
  }
  
  // For editing, show simple modal
  const modalHTML = `
    <div class="modal active" id="teamModal">
      <div class="modal-content modal-sm">
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
  
  if (isEdit) {
    loadTeamData(id);
  }
}

async function showComprehensiveTeamModal() {
  const schools = await fetchAPI('schools') || [];
  
  const modalHTML = `
    <div class="modal active" id="comprehensiveTeamModal">
      <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
          <h3>➕ Create New Team</h3>
          <button class="modal-close" onclick="closeModal('comprehensiveTeamModal')">×</button>
        </div>
        <form onsubmit="saveComprehensiveTeam(event)" id="comprehensiveTeamForm">
          <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            
            <!-- Team Basic Information -->
            <h4 class="modal-section-title">🏆 Team Information</h4>
            
            <div class="form-group">
              <label class="form-label">Team Name *</label>
              <input type="text" class="form-control" name="team_name" placeholder="e.g., Phoenix Warriors" required autofocus>
              <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 4px;">
                Enter a unique and descriptive name for the team
              </small>
            </div>
            
            ${schools.length > 0 ? `
              <div class="form-group">
                <label class="form-label">School/Institution</label>
                <select class="form-control" name="school_id">
                  <option value="">Select School (Optional)</option>
                  ${schools.map(s => `<option value="${s.school_id}">${escapeHtml(s.school_name)}</option>`).join('')}
                </select>
                <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 4px;">
                  Associate this team with a specific school if applicable
                </small>
              </div>
            ` : ''}
            
            <!-- Team Manager/Contact Information -->
            <h4 class="modal-section-title">👤 Team Management</h4>
            
            <div style="padding: 12px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 16px;">
              <p style="font-size: 12px; color: #6b7280; margin: 0; line-height: 1.5;">
                <strong>Note:</strong> Coaches, managers, and other staff can be assigned when you add this team to a tournament and assign sports.
              </p>
            </div>
            
            <!-- Team Status -->
            <h4 class="modal-section-title">⚙️ Team Status</h4>
            
            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f0fdf4; border-radius: 8px; border: 1px solid #86efac;">
                <input type="checkbox" name="is_active" checked style="width: 20px; height: 20px; cursor: pointer;">
                <div>
                  <div style="font-weight: 600; color: #065f46;">Active Team</div>
                  <div style="font-size: 11px; color: #047857; margin-top: 2px;">Check this to make the team immediately active and visible</div>
                </div>
              </label>
            </div>
            
            <!-- Next Steps Notice -->
            <div style="padding: 14px; background: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 6px; margin-top: 16px;">
              <div style="display: flex; gap: 10px;">
                <span style="font-size: 20px;">ℹ️</span>
                <div>
                  <strong style="color: #1e40af; font-size: 13px; display: block; margin-bottom: 4px;">Next Steps</strong>
                  <p style="font-size: 12px; color: #1e3a8a; margin: 0; line-height: 1.5;">
                    After creating this team, you can add it to tournaments, assign sports, coaches, and register athletes.
                  </p>
                </div>
              </div>
            </div>
            
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('comprehensiveTeamModal')">Cancel</button>
            <button type="submit" class="btn btn-primary" style="min-width: 150px;">
              ➕ Create Team
            </button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
}

async function saveComprehensiveTeam(event) {
  event.preventDefault();
  const formData = new FormData(event.target);
  
  const data = {
    team_name: formData.get('team_name').trim(),
    school_id: formData.get('school_id') || null,
    is_active: formData.get('is_active') ? 1 : 0
  };
  
  if (!data.team_name) {
    showToast('Please enter a team name', 'error');
    return;
  }
  
  try {
    const result = await fetchAPI('create_team', data, 'POST');
    
    if (result && result.ok) {
      closeModal('comprehensiveTeamModal');
      loadTeams();
      showToast(`✅ Team "${data.team_name}" created successfully!`, 'success');
    } else {
      showToast('❌ Error creating team: ' + (result?.error || 'Unknown error'), 'error');
    }
  } catch (error) {
    console.error('Error creating team:', error);
    showToast('❌ Error creating team', 'error');
  }
}

async function loadTeamData(id) {
  const data = await fetchAPI('teams');
  const team = data.find(t => t.team_id == id);
  
  if (team) {
    $('#team_name').value = team.team_name || '';
  }
}

async function saveTeam(e, id) {
  e.preventDefault();
  
  const data = {
    team_name: $('#team_name').value
  };
  
  if (id) data.team_id = id;
  
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
  const result = await fetchAPI('toggle_team', { team_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    loadTeams();
  }
}

function editTeam(id) {
  showTeamModal(id);
}

// ==========================================
// ATHLETES (Legacy view)
// ==========================================
async function loadAthletes() {
  try {
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.tournament) params.tour_id = currentFilters.tournament;
    if (currentFilters.team) params.team_id = currentFilters.team;
    
    const data = await fetchAPI('athletes', params);
    const content = $('#athletesContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No athletes found.</div>';
      return;
    }
    
    let html = `
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>College</th>
            <th>Course</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
    `;
    
    data.forEach(a => {
      const statusClass = a.is_active == 1 ? 'active' : 'inactive';
      const statusText = a.is_active == 1 ? 'Active' : 'Inactive';
      
      html += `
        <tr>
          <td><strong>${escapeHtml(a.athlete_name)}</strong></td>
          <td>${escapeHtml(a.college_code || 'N/A')}</td>
          <td>${escapeHtml(a.course || 'N/A')}</td>
          <td><span class="badge badge-${statusClass}">${statusText}</span></td>
          <td>
            <button class="btn btn-sm btn-secondary" onclick="viewAthleteProfile(${a.person_id})">View</button>
          </td>
        </tr>
      `;
    });
    
    html += '</tbody></table>';
    content.innerHTML = html;
    console.log('✅ Athletes loaded:', data.length);
  } catch (err) {
    console.error('❌ loadAthletes error:', err);
  }
}

// ==========================================
// MATCHES
// ==========================================
async function loadMatches() {
  try {
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.tournament) params.tour_id = currentFilters.tournament;
    if (currentFilters.team) params.team_id = currentFilters.team;
    
    const data = await fetchAPI('matches', params);
    const content = $('#matchesContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No matches scheduled yet.</div>';
      return;
    }
    
    content.innerHTML = `
      <table>
        <thead>
          <tr>
            <th>Match</th>
            <th>Sport</th>
            <th>Tournament</th>
            <th>Date & Time</th>
            <th>Venue</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(m => `
            <tr>
              <td><strong>${escapeHtml(m.team_a_name || 'TBD')} vs ${escapeHtml(m.team_b_name || 'TBD')}</strong></td>
              <td>${escapeHtml(m.sports_name)}</td>
              <td>${escapeHtml(m.tour_name || 'N/A')}</td>
              <td>${escapeHtml(m.sked_date)} ${escapeHtml(m.sked_time)}</td>
              <td>${escapeHtml(m.venue_name || 'N/A')}</td>
              <td>
                <button class="btn btn-sm btn-secondary" onclick="editMatch(${m.match_id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteMatch(${m.match_id})">Delete</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
  }
}

function showMatchModal() {
  alert('Match scheduling - to be implemented');
}

function editMatch(id) {
  alert('Edit match - to be implemented');
}

async function deleteMatch(id) {
  if (!confirm('Delete this match?')) return;
  
  const result = await fetchAPI('delete_match', { match_id: id }, 'POST');
  
  if (result && result.ok) {
    loadMatches();
  }
}

// ==========================================
// TRAINING
// ==========================================
async function loadTraining() {
  try {
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.team) params.team_id = currentFilters.team;
    
    const data = await fetchAPI('training', params);
    const content = $('#trainingContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No training sessions scheduled.</div>';
      return;
    }
    
    content.innerHTML = `
      <table>
        <thead>
          <tr>
            <th>Team</th>
            <th>Date & Time</th>
            <th>Venue</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(t => `
            <tr>
              <td><strong>${escapeHtml(t.team_name)}</strong></td>
              <td>${escapeHtml(t.sked_date)} ${escapeHtml(t.sked_time)}</td>
              <td>${escapeHtml(t.venue_name || 'N/A')}</td>
              <td><span class="badge badge-${t.is_active == 1 ? 'active' : 'inactive'}">${t.is_active == 1 ? 'Active' : 'Cancelled'}</span></td>
              <td>
                <button class="btn btn-sm btn-secondary" onclick="editTraining(${t.sked_id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteTraining(${t.sked_id})">Cancel</button>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
    
    console.log('✅ Training loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTraining error:', err);
  }
}

function showTrainingModal() {
  alert('Training scheduling - to be implemented');
}

function editTraining(id) {
  alert('Edit training - to be implemented');
}

async function deleteTraining(id) {
  if (!confirm('Cancel this training session?')) return;
  
  const result = await fetchAPI('delete_training', { sked_id: id }, 'POST');
  
  if (result && result.ok) {
    loadTraining();
  }
}

// ==========================================
// STANDINGS
// ==========================================
async function loadStandings() {
  try {
    const params = {};
    if (currentFilters.sport) params.sport_id = currentFilters.sport;
    if (currentFilters.tournament) params.tour_id = currentFilters.tournament;
    
    const data = await fetchAPI('standings', params);
    const content = $('#standingsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No standings data available</div>';
      return;
    }
    
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
function closeModal(modalId) {
  const modal = $(`#${modalId}`);
  if (modal) {
    modal.remove();
  }
}

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

function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    font-size: 14px;
    font-weight: 600;
    animation: slideIn 0.3s ease-out;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(400px); opacity: 0; }
  }
`;
document.head.appendChild(style);

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