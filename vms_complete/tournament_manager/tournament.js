// Tournament Manager Dashboard - Updated for Sidebar Design

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

let selectedSports = new Set();
let currentTournamentForSports = null;
let currentTournamentForTeams = null;
let sportTeamSelections = {};
let isSubmittingMatch = false;

// ==========================================
// NAVIGATION
// ==========================================

function setTab(tabId) {
  // Update sidebar navigation
  $$('.nav-link').forEach(b => b.classList.toggle('active', b.dataset.view === tabId));
  
  // Update content views
  $$('.content-view').forEach(v => v.classList.toggle('active', v.id === `${tabId}-view`));
  
  // Update page title
const titles = {
  'overview': 'Overview',
  'tournaments': 'Tournaments',
  'teams': 'Teams',
  'sports': 'Sports',
  'athletes': 'Athletes',
  'matches': 'Matches & Schedule',
  'scoring': 'Scoring',
  'standings': 'Team Standings',
  'medals': 'Medal Tally',
  'venues': 'Venues'
};
  $('#pageTitle').textContent = titles[tabId] || 'Dashboard';
  
  // Load data for the view
  loadViewData(tabId);
}

// Setup navigation click handlers
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    setTab(btn.dataset.view);
  });
});

function loadViewData(view) {
  switch(view) {
    case 'overview': 
      loadOverview(); 
      break;
    case 'tournaments': 
      loadTournaments(); 
      loadTournamentsForFilters();
      break;
    case 'teams':
      loadTournamentsForFilters();
      break;
    case 'sports':
      loadTournamentsForFilters();
      break;
    case 'athletes':
      loadTournamentsForFilters();
      break;
    case 'matches': 
      loadMatches(); 
      break;
    case 'scoring': 
      loadScores(); 
      break;
    case 'standings':
      // Load standings if you have that functionality
      break;
    case 'medals':
      // Load medals if you have that functionality
      break;
    case 'venues': 
      loadVenuesTable(); 
      break;
  }
}

// ==========================================
// API HELPER
// ==========================================

async function fetchJSON(action, options = {}) {
  try {
    let url = `api.php?action=${encodeURIComponent(action)}`;
    let fetchOptions = {
      method: 'GET'
    };
    
    // Check if this is a POST request by looking for method, body, or headers properties
    const hasMethod = options.hasOwnProperty('method');
    const hasBody = options.hasOwnProperty('body');
    const hasHeaders = options.hasOwnProperty('headers');
    
    if (hasMethod || hasBody || hasHeaders) {
      // This is a fetch options object for POST/PUT/DELETE
      fetchOptions = {
        method: options.method || 'POST',
        headers: options.headers || {},
        body: options.body
      };
      console.log('📡 POST request:', url, fetchOptions.method);
    } else if (options && typeof options === 'object' && Object.keys(options).length > 0) {
      // This is a GET parameters object
      const queryParams = new URLSearchParams(options).toString();
      url += `&${queryParams}`;
      console.log('📡 GET request:', url);
    } else {
      console.log('📡 GET request:', url);
    }
    
    const res = await fetch(url, fetchOptions);
    
    if (!res.ok) {
      const text = await res.text();
      console.error('❌ HTTP error:', res.status, text);
      throw new Error(`HTTP ${res.status}`);
    }
    
    const data = await res.json();
    console.log('✅ Response:', data);
    return data;
  } catch (err) {
    console.error('❌ fetchJSON error:', err);
    throw err;
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    const [tournaments, matches, sports] = await Promise.all([
      fetchJSON('tournaments'),
      fetchJSON('matches'),
      fetchJSON('all_sports')
    ]);
    
    const activeTournaments = tournaments.filter(t => t.is_active == 1);
    const completedMatches = matches.filter(m => m.winner_id);
    
    $('#statTournaments').textContent = activeTournaments.length || 0;
    $('#statSports').textContent = sports.length || 0;
    $('#statMatches').textContent = matches.length || 0;
    $('#statCompleted').textContent = completedMatches.length || 0;
    
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
    const data = await fetchJSON('tournaments');
    const content = $('#tournamentsContent');
    
    if (!Array.isArray(data) || data.length === 0) {
      content.innerHTML = `
        <div class="empty-state">
          <p style="font-size: 16px; margin-bottom: 12px;">📋 No Tournaments Assigned</p>
          <p>You have not been assigned to manage any tournaments yet.</p>
          <p style="margin-top: 8px; font-size: 12px; color: #6b7280;">
            The Sports Director needs to assign you as a Tournament Manager for specific sports in a tournament.
          </p>
        </div>
      `;
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
    
    // Populate tournament dropdowns
    populateTournamentDropdowns(data);
    
    console.log('✅ Tournaments loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTournaments error:', err);
    $('#tournamentsContent').innerHTML = '<div class="empty-state" style="color:red;">Error loading tournaments</div>';
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
        <button class="btn btn-sm btn-${t.is_active == 1 ? 'warning' : 'success'}" 
                onclick="toggleTournament(${t.tour_id}, ${t.is_active})">
          ${t.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
      </div>
    </div>
  `;
}

function populateTournamentDropdowns(tournaments) {
  const active = tournaments.filter(t => t.is_active == 1);
  const opts = active.map(t => 
    `<option value="${t.tour_id}" data-tour-name="${escapeHtml(t.tour_name)}" data-school-year="${escapeHtml(t.school_year)}" data-tour-date="${t.tour_date}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`
  ).join('');
  
  const selects = [
    '#teamsFilterTournament',
    '#sportsFilterTournament',
    '#athletesFilterTournament',
    '#matchTourSelect',
    '#matchesFilterTour',
    '#standingsTourSelect',
    '#medalsTourSelect',
    '#printTourSelect'
  ];
  
  selects.forEach(selector => {
    const el = $(selector);
    if (el) {
      if (selector === '#matchesFilterTour') {
        el.innerHTML = '<option value="">All Tournaments</option>' + opts;
      } else {
        el.innerHTML = '<option value="">-- Select Tournament --</option>' + opts;
      }
    }
  });
}

// Load tournaments for filter dropdowns
async function loadTournamentsForFilters() {
  try {
    const tournaments = await fetchJSON('tournaments');
    populateTournamentDropdowns(tournaments);
  } catch (err) {
    console.error('Error loading tournaments for filters:', err);
  }
}

// ==========================================
// TEAMS VIEW - Cascading Filters
// ==========================================
async function loadTeamsForTournament(tourId) {
  const content = $('#teamsContent');
  
  if (!tourId) {
    content.innerHTML = '<div class="empty-state">Select a tournament to view teams</div>';
    return;
  }
  
  try {
    content.innerHTML = '<div class="loading">Loading teams...</div>';
    const teams = await fetchJSON('get_tournament_teams', { tour_id: tourId });
    
    if (!teams || teams.length === 0) {
      content.innerHTML = '<div class="empty-state">No teams registered in this tournament yet.</div>';
      return;
    }
    
    let html = `<div class="data-grid">`;
    teams.forEach(team => {
      html += `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(team.team_name)}</div>
          </div>
          <div class="data-card-meta">
            🏅 ${team.num_sports || 0} sport(s)<br>
            👥 ${team.num_athletes || 0} athlete(s)
          </div>
          <div class="data-card-actions">
            <button class="btn btn-sm btn-primary" onclick="viewTeamDetails(${tourId}, ${team.team_id})">View Details</button>
          </div>
        </div>
      `;
    });
    html += '</div>';
    content.innerHTML = html;
    
  } catch (err) {
    console.error('Error loading teams:', err);
    content.innerHTML = '<div class="empty-state" style="color:red;">Error loading teams</div>';
  }
}

// Teams filter - Tournament selection
$('#teamsFilterTournament')?.addEventListener('change', (e) => {
  loadTeamsForTournament(e.target.value);
});

// ==========================================
// SPORTS VIEW - Cascading Filters
// ==========================================
async function loadSportsForTeam(tourId, teamId) {
  const content = $('#sportsContent');
  
  if (!tourId || !teamId) {
    content.innerHTML = '<div class="empty-state">Select tournament and team to view sports</div>';
    return;
  }
  
  try {
    content.innerHTML = '<div class="loading">Loading sports...</div>';
    const sports = await fetchJSON('get_team_sports', { tour_id: tourId, team_id: teamId });
    
    if (!sports || sports.length === 0) {
      content.innerHTML = `
        <div class="empty-state">
          No sports assigned to this team yet.<br><br>
          <button class="btn btn-primary" onclick="showAddSportModal(${tourId}, ${teamId})">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
            </svg>
            Add Sport
          </button>
        </div>
      `;
      return;
    }
    
    let html = `
      <div style="margin-bottom: 12px;">

      </div>
      <div class="data-grid">
    `;
    
    sports.forEach(sport => {
      html += `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(sport.sports_name)}</div>
          </div>
          <div class="data-card-meta">
            👨‍🏫 Coach: ${escapeHtml(sport.coach_name || 'Not assigned')}<br>
            🎯 Manager: ${escapeHtml(sport.tournament_manager_name || 'Not assigned')}<br>
            👥 ${sport.num_athletes || 0} athlete(s)
          </div>
          <div class="data-card-actions">
            <button class="btn btn-sm btn-primary" onclick="viewSportAthletes(${tourId}, ${teamId}, ${sport.sports_id})">Manage Athletes</button>
            <button class="btn btn-sm btn-secondary" onclick="assignStaff(${tourId}, ${teamId}, ${sport.sports_id})">Assign Staff</button>
          </div>
        </div>
      `;
    });
    html += '</div>';
    content.innerHTML = html;
    
  } catch (err) {
    console.error('Error loading sports:', err);
    content.innerHTML = '<div class="empty-state" style="color:red;">Error loading sports</div>';
  }
}

// Sports filters - Tournament and Team selection
$('#sportsFilterTournament')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const teamSelect = $('#sportsFilterTeam');
  
  teamSelect.disabled = !tourId;
  teamSelect.innerHTML = '<option value="">-- Select Team --</option>';
  $('#sportsContent').innerHTML = '<div class="empty-state">Select a team to view sports</div>';
  
  if (tourId) {
    try {
      const teams = await fetchJSON('get_tournament_teams', { tour_id: tourId });
      if (teams && teams.length > 0) {
        teams.forEach(team => {
          const opt = document.createElement('option');
          opt.value = team.team_id;
          opt.textContent = team.team_name;
          teamSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error loading teams:', err);
    }
  }
});

$('#sportsFilterTeam')?.addEventListener('change', (e) => {
  const tourId = $('#sportsFilterTournament').value;
  const teamId = e.target.value;
  loadSportsForTeam(tourId, teamId);
});

// ==========================================
// ATHLETES VIEW - Cascading Filters
// ==========================================
async function loadAthletesForSport(tourId, teamId, sportsId) {
  const content = $('#athletesContent');
  
  if (!tourId || !teamId || !sportsId) {
    content.innerHTML = '<div class="empty-state">Select tournament, team, and sport to view athletes</div>';
    return;
  }
  
  try {
    content.innerHTML = '<div class="loading">Loading athletes...</div>';
    const athletes = await fetchJSON('get_sport_athletes', { 
      tour_id: tourId, 
      team_id: teamId, 
      sports_id: sportsId 
    });
    
    if (!athletes || athletes.length === 0) {
      content.innerHTML = `
        <div class="empty-state">
          No athletes registered yet.<br><br>
          <button class="btn btn-primary" onclick="showAddAthleteModal(${tourId}, ${teamId}, ${sportsId})">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
            </svg>
            Add Athlete
          </button>
        </div>
      `;
      return;
    }
    
    let html = `
      <div style="margin-bottom: 12px;">
        <button class="btn btn-primary" onclick="showAddAthleteModal(${tourId}, ${teamId}, ${sportsId})">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
          </svg>
          Add Athlete
        </button>
      </div>
      <table class="table">
        <thead>
          <tr>
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
    `;
    
    athletes.forEach(a => {
      html += `
        <tr>
          <td><strong>${escapeHtml(a.full_name)}</strong></td>
          <td><span class="badge" style="background: #dbeafe; color: #1e40af;">${escapeHtml(a.role_type || 'athlete')}</span></td>
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
            <button class="btn btn-sm btn-secondary" onclick="editAthlete(${a.person_id}, ${tourId}, ${teamId}, ${sportsId})">Edit</button>
            <button class="btn btn-sm btn-danger" onclick="removeAthlete(${a.team_ath_id}, ${tourId}, ${teamId}, ${sportsId})">Remove</button>
          </td>
        </tr>
      `;
    });
    html += '</tbody></table>';
    content.innerHTML = html;
    
  } catch (err) {
    console.error('Error loading athletes:', err);
    content.innerHTML = '<div class="empty-state" style="color:red;">Error loading athletes</div>';
  }
}

// Athletes filters - Tournament, Team, and Sport selection
$('#athletesFilterTournament')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const teamSelect = $('#athletesFilterTeam');
  const sportSelect = $('#athletesFilterSport');
  
  teamSelect.disabled = !tourId;
  sportSelect.disabled = true;
  teamSelect.innerHTML = '<option value="">-- Select Team --</option>';
  sportSelect.innerHTML = '<option value="">-- Select Sport --</option>';
  $('#athletesContent').innerHTML = '<div class="empty-state">Select a team to continue</div>';
  
  if (tourId) {
    try {
      const teams = await fetchJSON('get_tournament_teams', { tour_id: tourId });
      if (teams && teams.length > 0) {
        teams.forEach(team => {
          const opt = document.createElement('option');
          opt.value = team.team_id;
          opt.textContent = team.team_name;
          teamSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error loading teams:', err);
    }
  }
});

$('#athletesFilterTeam')?.addEventListener('change', async (e) => {
  const tourId = $('#athletesFilterTournament').value;
  const teamId = e.target.value;
  const sportSelect = $('#athletesFilterSport');
  
  sportSelect.disabled = !teamId;
  sportSelect.innerHTML = '<option value="">-- Select Sport --</option>';
  $('#athletesContent').innerHTML = '<div class="empty-state">Select a sport to view athletes</div>';
  
  if (tourId && teamId) {
    try {
      const sports = await fetchJSON('get_team_sports', { tour_id: tourId, team_id: teamId });
      if (sports && sports.length > 0) {
        sports.forEach(sport => {
          const opt = document.createElement('option');
          opt.value = sport.sports_id;
          opt.textContent = sport.sports_name;
          sportSelect.appendChild(opt);
        });
      }
    } catch (err) {
      console.error('Error loading sports:', err);
    }
  }
});

$('#athletesFilterSport')?.addEventListener('change', (e) => {
  const tourId = $('#athletesFilterTournament').value;
  const teamId = $('#athletesFilterTeam').value;
  const sportsId = e.target.value;
  loadAthletesForSport(tourId, teamId, sportsId);
});

function showTournamentModal() {
  const modalHTML = `
    <div class="modal active" id="tournamentModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Create Tournament</h3>
          <button class="modal-close" onclick="closeModal('tournamentModal')">×</button>
        </div>
        <form id="tournamentForm" onsubmit="saveTournament(event)">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Tournament Name *</label>
              <input type="text" class="form-control" id="tour_name" placeholder="e.g., Inter-College Championship 2025" required>
            </div>
            <div class="form-group">
              <label class="form-label">School Year *</label>
              <input type="text" class="form-control" id="school_year" placeholder="e.g., 2024-2025" required>
            </div>
            <div class="form-group">
              <label class="form-label">Tournament Date *</label>
              <input type="date" class="form-control" id="tour_date" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('tournamentModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Tournament</button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
}

async function saveTournament(e) {
  e.preventDefault();
  
  try {
    const formData = new URLSearchParams();
    formData.set('tour_name', $('#tour_name').value);
    formData.set('school_year', $('#school_year').value);
    formData.set('tour_date', $('#tour_date').value);

    const data = await fetchJSON('create_tournament', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      alert(data.message || 'Tournament created!');
      closeModal('tournamentModal');
      await loadTournaments();
    } else {
      alert(data.message || 'Error creating tournament');
    }
  } catch (err) {
    console.error('❌ Tournament create error:', err);
    alert('Error creating tournament');
  }
}

async function toggleTournament(tourId, currentStatus) {
  if (!confirm(`Are you sure you want to ${currentStatus == 1 ? 'deactivate' : 'activate'} this tournament?`)) return;
  
  try {
    const formData = new URLSearchParams();
    formData.set('tour_id', tourId);
    formData.set('is_active', currentStatus == 1 ? '0' : '1');

    const data = await fetchJSON('toggle_tournament', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      await loadTournaments();
    } else {
      alert(data.message || 'Failed to update tournament');
    }
  } catch (err) {
    console.error('❌ Toggle tournament error:', err);
    alert('Error updating tournament');
  }
}

function closeModal(modalId) {
  const modal = $(`#${modalId}`);
  if (modal) modal.remove();
}

// ==========================================
// SPORTS SELECTION
// ==========================================

$('#sportsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  currentTournamentForSports = tourId;
  
  const selectionArea = $('#sportsSelectionArea');
  const tournamentSportsList = $('#tournamentSportsList');
  
  if (!tourId) {
    if (selectionArea) selectionArea.style.display = 'none';
    if (tournamentSportsList) tournamentSportsList.innerHTML = '<p class="empty-state">Select a tournament to view its sports</p>';
    return;
  }
  
  try {
    const tourSports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
    const tourSportIds = new Set((tourSports || []).map(s => s.sports_id));
    
    if (tournamentSportsList) {
      if (tourSportIds.size > 0) {
        tournamentSportsList.innerHTML = '<div class="sports-grid">' + tourSports.map(s => `
          <div class="sport-card selected">
            <div class="sport-name">${escapeHtml(s.sports_name)}</div>
            <div class="sport-check">✓</div>
          </div>
        `).join('') + '</div>';
      } else {
        tournamentSportsList.innerHTML = '<p style="color:#6b7280;font-size:13px;margin-top:12px;">No sports selected yet. Choose from the options below.</p>';
      }
    }
    
    const allSports = await fetchJSON('all_sports');
    
    if (!Array.isArray(allSports) || allSports.length === 0) {
      const sportsList = $('#sportsList');
      if (sportsList) {
        sportsList.innerHTML = '<p style="color:#ef4444;font-size:13px;">No sports available in the system.</p>';
      }
      if (selectionArea) selectionArea.style.display = 'block';
      return;
    }
    
    selectedSports.clear();
    tourSportIds.forEach(id => selectedSports.add(id));
    
    const sportsList = $('#sportsList');
    if (sportsList) {
      sportsList.innerHTML = allSports.map(s => {
        const isSelected = tourSportIds.has(s.sports_id);
        return `
          <div class="sport-card ${isSelected ? 'selected' : ''}" data-sport-id="${s.sports_id}" onclick="toggleSport(${s.sports_id})">
            <div class="sport-name">${escapeHtml(s.sports_name)}</div>
            <div class="sport-check">✓</div>
          </div>
        `;
      }).join('');
    }
    
    if (selectionArea) selectionArea.style.display = 'block';
    
  } catch (err) {
    console.error('❌ Error loading tournament sports:', err);
  }
});

function toggleSport(sportId) {
  if (selectedSports.has(sportId)) {
    selectedSports.delete(sportId);
  } else {
    selectedSports.add(sportId);
  }
  
  $$('.sport-card').forEach(card => {
    const id = parseInt(card.dataset.sportId);
    card.classList.toggle('selected', selectedSports.has(id));
  });
}

$('#confirmSportsBtn')?.addEventListener('click', async () => {
  if (selectedSports.size === 0) {
    alert('Please select at least one sport for this tournament');
    return;
  }
  
  if (!currentTournamentForSports) {
    alert('No tournament selected');
    return;
  }
  
  const msg = $('#sportsMsg');
  msg.textContent = 'Saving sports selection...';
  msg.style.color = '#6b7280';
  
  try {
    const formData = new URLSearchParams();
    formData.set('tour_id', currentTournamentForSports);
    formData.set('sport_ids', Array.from(selectedSports).join(','));

    const data = await fetchJSON('add_tournament_sports', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      msg.textContent = data.message + ' ✓ Now go to "Select Teams" tab to assign teams for each sport!';
      msg.style.color = 'green';
      
      $('#sportsTourSelect').dispatchEvent(new Event('change'));
    } else {
      msg.textContent = data.message || 'Error saving sports selection';
      msg.style.color = 'red';
    }
  } catch (err) {
    console.error('❌ Add sports error:', err);
    msg.textContent = 'Error saving sports selection. Please try again.';
    msg.style.color = 'red';
  }
});

// Continue with remaining functions in next response...
// This file is getting long, I'll provide the rest in the next artifact



window.setTab = setTab;
window.showTournamentModal = showTournamentModal;
window.toggleTournament = toggleTournament;
window.toggleSport = toggleSport;
window.closeModal = closeModal;

// Initialize on load
(async function init() {
  console.log('🚀 Initializing Tournament Manager dashboard...');
  
  try {
    await loadOverview();
    await loadTournaments();
    console.log('✅ Dashboard initialized');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();

// Tournament Manager Dashboard - Part 2: Remaining Functions
// ADD THIS TO THE END OF tournament.js

// ==========================================
// TEAM SELECTION
// ==========================================

$('#teamsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  currentTournamentForTeams = tourId;
  
  const teamsArea = $('#teamsSelectionArea');
  const container = $('#sportTeamsContainer');
  
  console.log('====================================');
  console.log('🎯 TEAM SELECTION - START');
  console.log('Tournament ID:', tourId);
  console.log('Base URL:', window.BASE_URL);
  console.log('====================================');
  
  if (!tourId) {
    if (teamsArea) teamsArea.style.display = 'none';
    console.log('⚠️ No tournament selected');
    return;
  }
  
  try {
    // STEP 1: Get tournament sports
    console.log('📡 STEP 1: Fetching tournament sports...');
    const sportsUrl = `tournament_sports&tour_id=${tourId}`;
    console.log('URL:', sportsUrl);
    
    const tourSports = await fetchJSON(sportsUrl);
    console.log('✅ Sports received:', tourSports);
    
    if (!tourSports || tourSports.length === 0) {
      console.warn('⚠️ No sports found for tournament');
      if (container) {
        container.innerHTML = `
          <div style="background:#fee2e2;border:1px solid #fca5a5;padding:16px;border-radius:6px;margin-top:16px;">
            <p style="color:#dc2626;font-weight:600;margin-bottom:8px;">⚠️ No sports selected</p>
            <p style="color:#991b1b;font-size:13px;">Please go to "Select Sports" tab first and choose sports for this tournament.</p>
          </div>
        `;
      }
      if (teamsArea) teamsArea.style.display = 'block';
      return;
    }
    
    // STEP 2: Get all available teams - TRY DIFFERENT ENDPOINTS
    console.log('📡 STEP 2: Fetching available teams...');
    
    let allTeams = null;
    let teamsUrl = '';
    
    // Try method 1: available_teams
    try {
      teamsUrl = 'available_teams';
      console.log('🔍 Trying URL:', teamsUrl);
      allTeams = await fetchJSON(teamsUrl);
      console.log('✅ Method 1 SUCCESS - available_teams:', allTeams);
    } catch (err1) {
      console.warn('❌ Method 1 FAILED:', err1.message);
      
      // Try method 2: available_teams_for_sport
      try {
        teamsUrl = 'available_teams_for_sport';
        console.log('🔍 Trying URL:', teamsUrl);
        allTeams = await fetchJSON(teamsUrl);
        console.log('✅ Method 2 SUCCESS - available_teams_for_sport:', allTeams);
      } catch (err2) {
        console.warn('❌ Method 2 FAILED:', err2.message);
        
        // Try method 3: get_all_teams
        try {
          teamsUrl = 'get_all_teams';
          console.log('🔍 Trying URL:', teamsUrl);
          allTeams = await fetchJSON(teamsUrl);
          console.log('✅ Method 3 SUCCESS - get_all_teams:', allTeams);
        } catch (err3) {
          console.error('❌ Method 3 FAILED:', err3.message);
          throw new Error('All team loading methods failed. Check api.php endpoints.');
        }
      }
    }
    
    if (!allTeams || allTeams.length === 0) {
      console.warn('⚠️ No teams available');
      if (container) {
        container.innerHTML = `
          <div style="background:#fef3c7;border:1px solid #fde68a;padding:16px;border-radius:6px;margin-top:16px;">
            <p style="color:#92400e;font-weight:600;margin-bottom:8px;">⚠️ No teams available</p>
            <p style="color:#78350f;font-size:13px;">Please register teams first in the "Register Teams" section.</p>
          </div>
        `;
      }
      if (teamsArea) teamsArea.style.display = 'block';
      return;
    }
    
    console.log('✅ Total teams available:', allTeams.length);
    
    // STEP 3: Build UI for each sport
    let html = `
      <div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:6px;margin-bottom:20px;">
        <strong style="color:#166534;">✅ ${tourSports.length} sport(s) selected for this tournament</strong>
        <p style="color:#15803d;font-size:12px;margin-top:4px;">${allTeams.length} teams available to assign</p>
      </div>
    `;
    
    for (const sport of tourSports) {
      console.log('📡 STEP 3: Processing sport:', sport.sports_name, '(ID:', sport.sports_id + ')');
      
      // Get registered teams for this sport
      let registeredTeams = [];
      try {
        const regTeamsUrl = `tournament_sport_teams&tour_id=${tourId}&sports_id=${sport.sports_id}`;
        console.log('🔍 Fetching registered teams:', regTeamsUrl);
        registeredTeams = await fetchJSON(regTeamsUrl);
        console.log('✅ Registered teams:', registeredTeams);
      } catch (err) {
        console.warn('⚠️ Could not load registered teams:', err.message);
        registeredTeams = [];
      }
      
      const registeredTeamIds = new Set((registeredTeams || []).map(t => t.team_id));
      console.log('📊 Registered team IDs:', Array.from(registeredTeamIds));
      
      // Initialize selection state
      if (!sportTeamSelections[sport.sports_id]) {
        sportTeamSelections[sport.sports_id] = new Set();
      }
      sportTeamSelections[sport.sports_id].clear();
      registeredTeamIds.forEach(id => sportTeamSelections[sport.sports_id].add(id));
      
      html += `
        <div class="card" style="margin-bottom:20px;">
          <h3 class="card-title">${escapeHtml(sport.sports_name)}</h3>
          <p style="font-size:12px;color:#6b7280;margin-bottom:12px;">
            ${sport.team_individual === 'team' ? '👥 Team Sport' : '👤 Individual Sport'} • 
            ${sport.men_women || 'Co-ed'}
          </p>
          
          <div style="margin-bottom:16px;">
            <h4 style="font-size:13px;font-weight:600;margin-bottom:10px;color:#374151;">
              Select Teams:
            </h4>
            <div class="sports-grid" id="teamGrid_${sport.sports_id}">
              ${allTeams.map(team => {
                const isSelected = registeredTeamIds.has(team.team_id);
                return `
                  <div class="sport-card ${isSelected ? 'selected' : ''}" 
                       data-team-id="${team.team_id}"
                       onclick="toggleTeamForSport(${sport.sports_id}, ${team.team_id}, this)">
                    <div class="sport-name">${escapeHtml(team.team_name)}</div>
                    <div class="sport-check">✓</div>
                  </div>
                `;
              }).join('')}
            </div>
          </div>
          
          <div style="background:#f9fafb;padding:12px;border-radius:6px;margin-bottom:12px;">
            <div style="font-size:12px;color:#6b7280;">
              <strong style="color:#374151;">Currently selected:</strong> 
              <span id="selectedCount_${sport.sports_id}">${registeredTeamIds.size}</span> team(s)
            </div>
          </div>
          
          <button class="btn btn-success" style="margin-bottom:16px;" 
                  onclick="saveTeamsForSport(${tourId}, ${sport.sports_id})">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
            </svg>
            Save Teams
          </button>
          
          <div id="teamSaveMsg_${sport.sports_id}" class="msg" style="display:none;"></div>
          
          ${registeredTeams.length > 0 ? `
            <div style="margin-top:20px;border-top:1px solid #e5e7eb;padding-top:16px;">
              <h4 style="font-size:13px;font-weight:600;margin-bottom:12px;">
                📋 Registered Teams (${registeredTeams.length})
              </h4>
              <table class="table">
                <thead>
                  <tr>
                    <th>Team</th>
                    <th>Coach</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  ${registeredTeams.map(t => `
                    <tr>
                      <td><strong>${escapeHtml(t.team_name)}</strong></td>
                      <td>${t.coach_name || '<em style="color:#9ca3af;">Not assigned</em>'}</td>
                      <td>
                        <button class="btn btn-sm" onclick="viewTeamDetails(${tourId}, ${sport.sports_id}, ${t.team_id}, '${escapeHtml(t.team_name).replace(/'/g, "\\'")}')">
                          View
                        </button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
          ` : `
            <div style="padding:20px;text-align:center;color:#9ca3af;font-size:13px;border:1px dashed #e5e7eb;border-radius:6px;">
              No teams registered yet. Select teams above and click "Save Teams".
            </div>
          `}
        </div>
      `;
    }
    
    if (container) container.innerHTML = html;
    if (teamsArea) teamsArea.style.display = 'block';
    
    console.log('====================================');
    console.log('✅ TEAM SELECTION UI - COMPLETE');
    console.log('====================================');
    
  } catch (err) {
    console.error('====================================');
    console.error('❌ CRITICAL ERROR IN TEAM SELECTION');
    console.error('Error:', err);
    console.error('Error message:', err.message);
    console.error('Error stack:', err.stack);
    console.error('====================================');
    
    if (container) {
      container.innerHTML = `
        <div style="background:#fee2e2;border:1px solid #fca5a5;padding:16px;border-radius:6px;margin-top:16px;">
          <p style="color:#dc2626;font-weight:600;margin-bottom:8px;">❌ Error loading teams</p>
          <p style="color:#991b1b;font-size:13px;margin-bottom:8px;">${err.message}</p>
          <details style="font-size:11px;color:#7f1d1d;margin-top:8px;">
            <summary style="cursor:pointer;font-weight:600;">Technical Details (click to expand)</summary>
            <pre style="margin-top:8px;padding:8px;background:#fef2f2;border-radius:4px;overflow:auto;max-height:200px;">${err.stack}</pre>
          </details>
          <p style="color:#991b1b;font-size:12px;margin-top:12px;font-weight:600;">
            💡 Check the browser console (F12) for detailed logs
          </p>
        </div>
      `;
    }
    if (teamsArea) teamsArea.style.display = 'block';
  }
});

function toggleTeamForSport(sportsId, teamId, element) {
  console.log('🔄 Toggle team:', { sportsId, teamId });
  
  element.classList.toggle('selected');
  
  if (!sportTeamSelections[sportsId]) {
    sportTeamSelections[sportsId] = new Set();
  }
  
  if (sportTeamSelections[sportsId].has(teamId)) {
    sportTeamSelections[sportsId].delete(teamId);
    console.log('➖ Removed team', teamId);
  } else {
    sportTeamSelections[sportsId].add(teamId);
    console.log('➕ Added team', teamId);
  }
  
  const countEl = $(`#selectedCount_${sportsId}`);
  if (countEl) {
    countEl.textContent = sportTeamSelections[sportsId].size;
  }
  
  console.log('Current selection:', Array.from(sportTeamSelections[sportsId]));
}

async function saveTeamsForSport(tourId, sportsId) {
  console.log('====================================');
  console.log('💾 SAVING TEAMS FOR SPORT');
  console.log('Tournament:', tourId);
  console.log('Sport:', sportsId);
  console.log('====================================');
  
  const msgEl = $(`#teamSaveMsg_${sportsId}`);
  
  if (!msgEl) {
    console.error('❌ Message element not found');
    alert('Error: Message element not found');
    return;
  }
  
  msgEl.style.display = 'block';
  msgEl.textContent = 'Saving teams...';
  msgEl.style.color = '#6b7280';
  msgEl.style.background = '#f3f4f6';
  msgEl.style.padding = '8px 12px';
  msgEl.style.borderRadius = '4px';
  
  try {
    const selectedTeamIds = Array.from(sportTeamSelections[sportsId] || []);
    console.log('Selected team IDs:', selectedTeamIds);
    
    if (selectedTeamIds.length === 0) {
      console.warn('⚠️ No teams selected');
      msgEl.textContent = '⚠️ Please select at least one team';
      msgEl.style.color = '#d97706';
      msgEl.style.background = '#fffbeb';
      return;
    }
    
    const formData = new URLSearchParams();
    formData.set('tour_id', tourId);
    formData.set('sports_id', sportsId);
    formData.set('team_ids', selectedTeamIds.join(','));
    
    console.log('📡 Sending request to: add_teams_to_tournament_sport');
    console.log('Form data:', Object.fromEntries(formData));
    
    const data = await fetchJSON('add_teams_to_tournament_sport', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    console.log('✅ Response:', data);
    
    if (data.ok) {
      msgEl.textContent = `✅ ${data.message}`;
      msgEl.style.color = '#166534';
      msgEl.style.background = '#f0fdf4';
      msgEl.style.borderLeft = '3px solid #22c55e';
      
      console.log('✅ Teams saved successfully');
      
      setTimeout(() => {
        console.log('🔄 Reloading team selection view...');
        $('#teamsTourSelect').dispatchEvent(new Event('change'));
      }, 1500);
    } else {
      msgEl.textContent = `❌ ${data.message || 'Failed to save teams'}`;
      msgEl.style.color = '#dc2626';
      msgEl.style.background = '#fee2e2';
      msgEl.style.borderLeft = '3px solid #ef4444';
      console.error('❌ Save failed:', data);
    }
  } catch (err) {
    console.error('====================================');
    console.error('❌ ERROR SAVING TEAMS');
    console.error('Error:', err);
    console.error('====================================');
    
    msgEl.textContent = `❌ Error: ${err.message}`;
    msgEl.style.color = '#dc2626';
    msgEl.style.background = '#fee2e2';
    msgEl.style.borderLeft = '3px solid #ef4444';
  }
}



async function viewTeamDetails(tourId, teamId) {
  console.log('📋 View team details:', { tourId, teamId });
  
  try {
    // Fetch team sports and basic info
    const sports = await fetchJSON('get_team_sports', { tour_id: tourId, team_id: teamId });
    
    if (!sports || sports.length === 0) {
      alert('No sports assigned to this team.');
      return;
    }
    
    // Get team name from first sport  
    const teamName = sports[0].team_name || 'Team Details';
    const tournamentName = sports[0].tour_name || '';
    
    // Fetch athletes for each sport
    const sportsWithAthletes = await Promise.all(
      sports.map(async (sport) => {
        try {
          const athletes = await fetchJSON('get_sport_athletes', {
            tour_id: tourId,
            team_id: teamId,
            sports_id: sport.sports_id
          });
          return { ...sport, athletes: athletes || [] };
        } catch (err) {
          console.error('Error fetching athletes for ' + sport.sports_name + ':', err);
          return { ...sport, athletes: [] };
        }
      })
    );
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'teamDetailsModal';
    modal.className = 'modal active';
    modal.style.overflowY = 'auto';
    
    // Build HTML string
    let html = '';
    
    // Modal header
    html += '<div class="modal-content wide" style="max-width: 1000px;">';
    html += '<div class="modal-header" style="padding: 12px 16px;">';
    html += '<h3 style="font-size: 15px; margin: 0;">' + escapeHtml(teamName) + ' - Complete Details</h3>';
    html += '<button class="modal-close" onclick="closeTeamDetailsModal()">×</button>';
    html += '</div>';
    html += '<div class="modal-body" style="padding: 16px;">';
    
    // Team Summary
    const totalAthletes = sportsWithAthletes.reduce((sum, s) => sum + s.athletes.length, 0);
    const totalCaptains = sportsWithAthletes.reduce((sum, s) => sum + s.athletes.filter(a => a.is_captain == 1).length, 0);
    
    html += '<div style="background: #f9fafb; padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; border: 1px solid #e5e7eb;">';
    html += '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; font-size: 12px;">';
    html += '<div><div style="color: #6b7280; margin-bottom: 2px;">Tournament</div>';
    html += '<div style="font-weight: 600;">' + escapeHtml(tournamentName) + '</div></div>';
    html += '<div><div style="color: #6b7280; margin-bottom: 2px;">Total Sports</div>';
    html += '<div style="font-weight: 600;">' + sports.length + '</div></div>';
    html += '<div><div style="color: #6b7280; margin-bottom: 2px;">Total Athletes</div>';
    html += '<div style="font-weight: 600;">' + totalAthletes + '</div></div>';
    html += '<div><div style="color: #6b7280; margin-bottom: 2px;">Captains</div>';
    html += '<div style="font-weight: 600;">' + totalCaptains + '</div></div>';
    html += '</div></div>';
    
    // Sports Details
    sportsWithAthletes.forEach((sport, idx) => {
      const captains = sport.athletes.filter(a => a.is_captain == 1);
      const regularAthletes = sport.athletes.filter(a => a.is_captain != 1);
      const marginBottom = idx < sportsWithAthletes.length - 1 ? '20px' : '0';
      
      html += '<div style="margin-bottom: ' + marginBottom + ';">';
      
      // Sport Header
      html += '<div style="background: #111827; color: white; padding: 8px 12px; border-radius: 6px 6px 0 0; display: flex; justify-content: space-between; align-items: center;">';
      html += '<div style="font-weight: 600; font-size: 13px;">' + escapeHtml(sport.sports_name) + '</div>';
      html += '<div style="font-size: 11px; opacity: 0.9;">' + sport.athletes.length + ' athlete' + (sport.athletes.length !== 1 ? 's' : '') + '</div>';
      html += '</div>';
      
      // Sport Info
      html += '<div style="background: white; border: 1px solid #e5e7eb; border-top: none; padding: 10px 12px;">';
      html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 12px; margin-bottom: 10px;">';
      
      const sportType = sport.team_individual === 'team' ? 'Team Sport' : 'Individual';
      let category = 'Mixed';
      if (sport.men_women === 'men') category = 'Men';
      if (sport.men_women === 'women') category = 'Women';
      const coachName = sport.coach_name || 'Not assigned';
      
      html += '<div><span style="color: #6b7280;">Type:</span> <strong>' + sportType + '</strong></div>';
      html += '<div><span style="color: #6b7280;">Category:</span> <strong>' + category + '</strong></div>';
      html += '<div><span style="color: #6b7280;">Coach:</span> <strong>' + escapeHtml(coachName) + '</strong></div>';
      html += '</div>';
      
      if (sport.athletes.length > 0) {
        // Athletes Table
        html += '<table style="width: 100%; font-size: 12px; border-collapse: collapse; margin-top: 8px;">';
        html += '<thead><tr style="background: #f9fafb; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb;">';
        html += '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; font-size: 11px;">NAME</th>';
        html += '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; font-size: 11px;">ROLE</th>';
        html += '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; font-size: 11px;">COLLEGE</th>';
        html += '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; font-size: 11px;">COURSE</th>';
        html += '<th style="padding: 6px 8px; text-align: center; font-weight: 600; color: #6b7280; font-size: 11px;">HEIGHT</th>';
        html += '<th style="padding: 6px 8px; text-align: center; font-weight: 600; color: #6b7280; font-size: 11px;">WEIGHT</th>';
        html += '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #6b7280; font-size: 11px;">SCHOLARSHIP</th>';
        html += '<th style="padding: 6px 8px; text-align: center; font-weight: 600; color: #6b7280; font-size: 11px;">CAPTAIN</th>';
        html += '</tr></thead><tbody>';
        
        // Captains first
        captains.forEach(athlete => {
          html += '<tr style="border-bottom: 1px solid #e5e7eb; background: #fef3c7;">';
          html += '<td style="padding: 6px 8px; font-weight: 600;">' + escapeHtml(athlete.full_name) + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.role_type || '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.college_code || '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.course || '-') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">' + (athlete.height > 0 ? athlete.height + ' cm' : '-') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">' + (athlete.weight > 0 ? athlete.weight + ' kg' : '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.scholarship_name || 'None') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">';
          html += '<span style="background: #fbbf24; color: #78350f; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600;">⭐ CAPTAIN</span>';
          html += '</td></tr>';
        });
        
        // Regular athletes
        regularAthletes.forEach(athlete => {
          html += '<tr style="border-bottom: 1px solid #e5e7eb;">';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.full_name) + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.role_type || '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.college_code || '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.course || '-') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">' + (athlete.height > 0 ? athlete.height + ' cm' : '-') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">' + (athlete.weight > 0 ? athlete.weight + ' kg' : '-') + '</td>';
          html += '<td style="padding: 6px 8px;">' + escapeHtml(athlete.scholarship_name || 'None') + '</td>';
          html += '<td style="padding: 6px 8px; text-align: center;">-</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table>';
      } else {
        html += '<div style="text-align: center; padding: 16px; color: #6b7280; font-size: 12px;">';
        html += 'No athletes registered for this sport yet.';
        html += '</div>';
      }
      
      html += '</div></div>';
    });
    
    // Modal footer
    html += '</div>';
    html += '<div class="modal-footer" style="padding: 10px 16px;">';
    html += '<button class="btn btn-secondary btn-sm" onclick="closeTeamDetailsModal()">Close</button>';
    html += '</div></div>';
    
    modal.innerHTML = html;
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeTeamDetailsModal();
    });
    
  } catch (err) {
    console.error('❌ View details error:', err);
    alert('Error loading team details: ' + err.message);
  }
}

function closeTeamDetailsModal() {
  const modal = $('#teamDetailsModal');
  if (modal) modal.remove();
}

window.toggleTeamForSport = toggleTeamForSport;
window.saveTeamsForSport = saveTeamsForSport;
window.viewTeamDetails = viewTeamDetails;
window.closeTeamDetailsModal = closeTeamDetailsModal;

console.log('✅ Team selection module loaded');

// ==========================================
// MATCHES
// ==========================================

// ==========================================
// UPDATED LOAD MATCHES FUNCTION
// Replace the loadMatches function in tournament.js
// ==========================================

async function loadMatches(filterTourId = null) {
  try {
    let url = 'matches';
    if (filterTourId) url += `&tour_id=${filterTourId}`;
    
    const data = await fetchJSON(url);
    const tbody = $('#matchesTable tbody');
    
    if (!tbody) return;
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" class="empty-state">No matches found</td></tr>';
      return;
    }
    
    // For individual sports, we need to fetch athlete names for winners
    // Create a map of person_id to athlete names
    const athleteMap = {};
    
    // Get all unique winner_ids from individual sports matches
    const individualMatches = data.filter(m => m.sports_type === 'individual' && m.winner_id);
    const winnerIds = [...new Set(individualMatches.map(m => m.winner_id))];
    
    if (winnerIds.length > 0) {
      // Fetch athlete names (we'll do this per match to avoid complexity)
      for (const winnerId of winnerIds) {
        try {
          const personData = await fetchJSON('get_all_players');
          const athlete = personData.find(p => p.person_id == winnerId);
          if (athlete) {
            athleteMap[winnerId] = `${athlete.f_name} ${athlete.l_name}`;
          }
        } catch (err) {
          console.warn('Could not fetch athlete name for:', winnerId);
        }
      }
    }
    
    tbody.innerHTML = data.map(m => {
      const teamADisplay = m.sports_type === 'individual' 
        ? '<em style="color:#6b7280;">Individual Event</em>' 
        : escapeHtml(m.team_a_name || 'TBA');
      
      const teamBDisplay = m.sports_type === 'individual' 
        ? '<em style="color:#6b7280;">-</em>' 
        : escapeHtml(m.team_b_name || 'TBA');
      
      // Winner display
      let winnerDisplay = '-';
      if (m.winner_id) {
        if (m.sports_type === 'individual') {
          // For individual sports, winner_id is person_id
          winnerDisplay = athleteMap[m.winner_id] || 'Winner declared';
        } else {
          // For team sports, winner_id is team_id
          winnerDisplay = escapeHtml(m.winner_name || 'Winner declared');
        }
      }
      
      return `
        <tr>
          <td>${escapeHtml(m.game_no)}</td>
          <td>${m.sked_date}</td>
          <td>${m.sked_time}</td>
          <td>
            ${escapeHtml(m.sports_name)}
            <br><small style="color:#6b7280;">${m.sports_type === 'individual' ? '👤 Individual' : '👥 Team'}</small>
          </td>
          <td>${escapeHtml(m.match_type)}</td>
          <td>${teamADisplay}</td>
          <td>${teamBDisplay}</td>
          <td>${escapeHtml(m.venue_name || 'TBA')}</td>
          <td>
            ${m.winner_id 
              ? `<span class="badge badge-active">🏆 ${winnerDisplay}</span>` 
              : '<span class="badge" style="background:#fef3c7;color:#92400e;">Pending</span>'}
          </td>
          <td>
            <button class="btn btn-sm" onclick="editMatch(${m.match_id})">Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deleteMatch(${m.match_id})">Delete</button>
          </td>
        </tr>
      `;
    }).join('');
    
    // Populate both scoring and winner dropdowns
    populateMatchDropdowns(data);
    populateWinnerMatchDropdowns(data);
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
  }
}

// ==========================================
// WINNER DECLARATION - SUPPORTS BOTH TEAM AND INDIVIDUAL SPORTS
// Replace the winner declaration section in tournament.js
// ==========================================

// Populate match dropdowns for winner declaration
function populateWinnerMatchDropdowns(matches) {
  const winnerMatchSelect = $('#winnerMatchSelect');
  
  if (!winnerMatchSelect) return;
  
  // Show matches without winners
  const withoutWinner = matches.filter(m => !m.winner_id);
  
  let opts = '<option value="">-- Select Match --</option>';
  withoutWinner.forEach(m => {
    let matchLabel = '';
    
    if (m.sports_type === 'individual') {
      matchLabel = `${escapeHtml(m.sports_name)} - ${escapeHtml(m.match_type)} - Game #${escapeHtml(m.game_no)} (${m.sked_date})`;
    } else {
      matchLabel = `${escapeHtml(m.sports_name)}: ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)} - Game #${escapeHtml(m.game_no)} (${m.sked_date})`;
    }
    
    opts += `<option value="${m.match_id}" 
             data-tour-id="${m.tour_id}" 
             data-sports-id="${m.sports_id}"
             data-sports-type="${m.sports_type}"
             data-match-id="${m.match_id}">
      ${matchLabel}
    </option>`;
  });
  
  winnerMatchSelect.innerHTML = opts;
}

// Export the functions
window.populateWinnerMatchDropdowns = populateWinnerMatchDropdowns;

// Load competitors (teams or athletes) when match is selected
// Load competitors (teams or athletes) when match is selected
$('#winnerMatchSelect')?.addEventListener('change', async (e) => {
  const option = e.target.selectedOptions[0];
  const winnerSelect = $('#winner_team_id');
  
  if (!winnerSelect) return;
  
  if (!option || !option.value) {
    winnerSelect.innerHTML = '<option value="">-- Select Winner --</option>';
    return;
  }
  
  const matchId = option.value;
  const sportsType = option.dataset.sportsType;
  const tourId = option.dataset.tourId;
  const sportsId = option.dataset.sportsId;
  
  try {
    if (sportsType === 'team') {
      // For team sports, get the two teams from the match
      const matches = await fetchJSON('matches');
      const match = matches.find(m => m.match_id == matchId);
      
      if (!match) {
        winnerSelect.innerHTML = '<option value="">-- Error: Match not found --</option>';
        return;
      }
      
      winnerSelect.innerHTML = `
        <option value="">-- Select Winner --</option>
        <option value="team_${match.team_a_id}">${escapeHtml(match.team_a_name)}</option>
        <option value="team_${match.team_b_id}">${escapeHtml(match.team_b_name)}</option>
      `;
    } else {
      // For individual sports, get ALL registered athletes for this sport
      winnerSelect.innerHTML = '<option value="">Loading athletes...</option>';
      
      // Get athletes with scores (to show their scores)
      const scores = await fetchJSON('match_scores', { match_id: matchId });
      
      // Get ALL registered athletes for this sport
      const allAthletes = await fetchJSON('match_athletes', { 
        tour_id: tourId, 
        sports_id: sportsId 
      });
      
      if (!allAthletes || allAthletes.length === 0) {
        winnerSelect.innerHTML = '<option value="">No athletes registered for this sport</option>';
        return;
      }
      
      // Create a map of athletes with their scores (if any)
      const athleteScoreMap = {};
      if (scores && scores.length > 0) {
        scores.forEach(score => {
          if (score.athlete_id) {
            athleteScoreMap[score.athlete_id] = {
              rank: score.rank_no,
              score: score.score
            };
          }
        });
      }
      
      // Sort athletes: those with scores first (by rank), then others
      const sortedAthletes = [...allAthletes].sort((a, b) => {
        const aHasScore = athleteScoreMap[a.athlete_id];
        const bHasScore = athleteScoreMap[b.athlete_id];
        
        if (aHasScore && !bHasScore) return -1;
        if (!aHasScore && bHasScore) return 1;
        
        if (aHasScore && bHasScore) {
          if (aHasScore.rank && bHasScore.rank) {
            return aHasScore.rank - bHasScore.rank;
          }
          return (parseFloat(bHasScore.score) || 0) - (parseFloat(aHasScore.score) || 0);
        }
        
        return a.athlete_name.localeCompare(b.athlete_name);
      });
      
      let options = '<option value="">-- Select Winner --</option>';
      
      sortedAthletes.forEach(athlete => {
        const athleteName = athlete.athlete_name || 'Unknown Athlete';
        const teamInfo = athlete.team_name ? ` (${escapeHtml(athlete.team_name)})` : '';
        
        // Add score info if available
        let scoreInfo = '';
        if (athleteScoreMap[athlete.athlete_id]) {
          const scoreData = athleteScoreMap[athlete.athlete_id];
          scoreInfo = ` - Rank ${scoreData.rank}, Score: ${scoreData.score}`;
        }
        
        options += `<option value="athlete_${athlete.athlete_id}">
          ${escapeHtml(athleteName)}${teamInfo}${scoreInfo}
        </option>`;
      });
      
      winnerSelect.innerHTML = options;
    }
  } catch (err) {
    console.error('Error loading competitors for winner selection:', err);
    winnerSelect.innerHTML = '<option value="">Error loading competitors</option>';
  }
});

// Export the updated function
window.loadMatches = loadMatches;

// Submit winner declaration
$('#winnerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#winnerMsg');
  msg.textContent = "Declaring winner...";
  msg.style.color = '#6b7280';

  try {
    const matchSelect = $('#winnerMatchSelect');
    const winnerSelect = $('#winner_team_id');
    
    const matchId = matchSelect.value;
    const winnerValue = winnerSelect.value;
    
    console.log('=== WINNER FORM DEBUG ===');
    console.log('Match ID:', matchId);
    console.log('Winner Value:', winnerValue);
    
    if (!matchId || !winnerValue) {
      msg.textContent = 'Please select both match and winner';
      msg.style.color = 'red';
      return;
    }
    
    const selectedOption = matchSelect.selectedOptions[0];
    const sportsType = selectedOption.dataset.sportsType;
    
    console.log('Sports Type:', sportsType);
    
    const formData = new URLSearchParams();
    formData.set('match_id', matchId);
    formData.set('sports_type', sportsType);
    
    // Parse winner value to determine if it's a team or athlete
    if (winnerValue.startsWith('team_')) {
      const teamId = winnerValue.replace('team_', '');
      console.log('Parsed Team ID:', teamId);
      
      if (!teamId || teamId === '0' || isNaN(teamId)) {
        msg.textContent = 'Invalid team selection';
        msg.style.color = 'red';
        return;
      }
      
      formData.set('winner_id', teamId);
      formData.set('winner_type', 'team');
    } else if (winnerValue.startsWith('athlete_')) {
      const athleteId = winnerValue.replace('athlete_', '');
      console.log('Parsed Athlete ID:', athleteId);
      
      if (!athleteId || athleteId === '0' || isNaN(athleteId)) {
        msg.textContent = 'Invalid athlete selection';
        msg.style.color = 'red';
        return;
      }
      
      formData.set('winner_id', athleteId);
      formData.set('winner_type', 'athlete');
    } else {
      // Legacy support - if no prefix, assume it's a team_id
      console.log('Legacy team ID (no prefix):', winnerValue);
      
      if (!winnerValue || winnerValue === '0' || isNaN(winnerValue)) {
        msg.textContent = 'Invalid winner selection';
        msg.style.color = 'red';
        return;
      }
      
      formData.set('winner_id', winnerValue);
      formData.set('winner_type', 'team');
    }
    
    console.log('Final Form Data:', Object.fromEntries(formData));

    const data = await fetchJSON('declare_winner', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    console.log('Server Response:', data);

    msg.textContent = data.message || 'Winner declared!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadMatches();
    }
  } catch (err) {
    console.error('❌ Declare winner error:', err);
    msg.textContent = 'Error declaring winner: ' + err.message;
    msg.style.color = 'red';
  }
});

// Export the function
window.populateWinnerMatchDropdowns = populateWinnerMatchDropdowns;

async function showScheduleMatchModal() {
  const existingModal = $('#scheduleMatchModal');
  if (existingModal) {
    existingModal.remove();
  }
  
  isSubmittingMatch = false;
  
  const loadingHTML = `
    <div class="modal active" id="scheduleMatchModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Loading...</h3>
        </div>
        <div class="modal-body">
          <div class="loading">Loading form data...</div>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = loadingHTML;
  
  try {
    const [tournaments, venues, umpires] = await Promise.all([
      fetchJSON('tournaments'),
      fetchJSON('venues'),
      fetchJSON('umpires')
    ]);
    
    const activeTournaments = tournaments.filter(t => t.is_active == 1);
    const activeVenues = venues;
    
    if (!$('#scheduleMatchModal')) {
      console.log('Modal was closed during loading, aborting...');
      return;
    }
    
    const modalHTML = `
      <div class="modal active" id="scheduleMatchModal">
        <div class="modal-content wide">
          <div class="modal-header">
            <h3>Schedule New Match</h3>
            <button class="modal-close" onclick="closeScheduleMatchModal()">×</button>
          </div>
          <form id="scheduleMatchForm">
            <div class="modal-body">
              <div class="form" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                
                <!-- Tournament Selection -->
                <div class="form-group">
                  <label class="form-label">Tournament *</label>
                  <select class="form-control" id="schedule_tour_id" required>
                    <option value="">-- Select Tournament --</option>
                    ${activeTournaments.map(t => 
                      `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`
                    ).join('')}
                  </select>
                </div>

                <!-- Sport Selection -->
                <div class="form-group">
                  <label class="form-label">Sport *</label>
                  <select class="form-control" id="schedule_sports_id" required>
                    <option value="">-- Select Sport --</option>
                  </select>
                </div>

                <!-- Game Number -->
                <div class="form-group">
                  <label class="form-label">Game Number *</label>
                  <input type="text" class="form-control" id="schedule_game_no" placeholder="e.g., G001" required>
                </div>

                <!-- Match Type -->
                <div class="form-group">
                  <label class="form-label">Match Type *</label>
                  <select class="form-control" id="schedule_match_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="EL">Elimination (EL)</option>
                    <option value="QF">Quarter Final (QF)</option>
                    <option value="SF">Semi Final (SF)</option>
                    <option value="F">Final (F)</option>
                  </select>
                </div>

                <!-- Date -->
                <div class="form-group">
                  <label class="form-label">Match Date *</label>
                  <input type="date" class="form-control" id="schedule_sked_date" required>
                </div>

                <!-- Time -->
                <div class="form-group">
                  <label class="form-label">Match Time *</label>
                  <input type="time" class="form-control" id="schedule_sked_time" required>
                </div>

                <!-- Venue -->
                <div class="form-group">
                  <label class="form-label">Venue</label>
                  <select class="form-control" id="schedule_venue_id">
                    <option value="">-- Select Venue --</option>
                    ${activeVenues.map(v => 
                      `<option value="${v.venue_id}">${escapeHtml(v.venue_name)}</option>`
                    ).join('')}
                  </select>
                </div>

                <!-- Umpire -->
                <div class="form-group">
                  <label class="form-label">Umpire</label>
                  <select class="form-control" id="schedule_umpire_id">
                    <option value="">-- Select Umpire --</option>
                    ${umpires.map(u => 
                      `<option value="${u.person_id}">${escapeHtml(u.full_name)}</option>`
                    ).join('')}
                  </select>
                </div>

                <!-- TEAM SPORTS Section -->
                <div id="teamsSportsSection" style="grid-column: span 2; display:none;">
                  <div style="background:#f0f9ff;padding:12px;border-radius:6px;margin-bottom:12px;border:1px solid #bfdbfe;">
                    <strong style="color:#1e40af;">👥 Team Sport Match</strong>
                  </div>
                  <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                      <label class="form-label">Team A *</label>
                      <select class="form-control" id="schedule_team_a_id">
                        <option value="">-- Select Team A --</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Team B *</label>
                      <select class="form-control" id="schedule_team_b_id">
                        <option value="">-- Select Team B --</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- INDIVIDUAL SPORTS Section -->
                <div id="individualSportsSection" style="grid-column: span 2; display:none;">
                  <div style="background:#f0fdf4;padding:12px;border-radius:6px;margin-bottom:12px;border:1px solid #bbf7d0;">
                    <strong style="color:#166534;">🏃 Individual Sport Match</strong>
                    <p style="font-size:12px;color:#15803d;margin-top:4px;">
                      For individual sports, you can schedule the match without pre-selecting athletes. 
                      Athletes will be registered during the actual match or scoring phase.
                    </p>
                  </div>
                  <div style="background:#fefce8;padding:12px;border-radius:6px;border:1px solid #fde047;">
                    <p style="font-size:12px;color:#854d0e;margin:0;">
                      ℹ️ <strong>Note:</strong> Individual matches don't require pre-selection of competitors. 
                      You can add athlete scores later in the Scoring section.
                    </p>
                  </div>
                </div>

                <!-- Sports Type (hidden field) -->
                <input type="hidden" id="schedule_sports_type" value="">

              </div>
              <div id="scheduleMsg" class="msg" style="display:none;"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeScheduleMatchModal()">Cancel</button>
              <button type="submit" class="btn btn-primary">Schedule Match</button>
            </div>
          </form>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = modalHTML;
    
    const form = $('#scheduleMatchForm');
    if (form) {
      form.replaceWith(form.cloneNode(true));
      $('#scheduleMatchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        saveScheduleMatch(e);
      });
    }
    
    const tourSelect = $('#schedule_tour_id');
    if (tourSelect) {
      tourSelect.addEventListener('change', onScheduleTournamentChange);
    }
    
    const sportsSelect = $('#schedule_sports_id');
    if (sportsSelect) {
      sportsSelect.addEventListener('change', onScheduleSportChange);
    }
    
  } catch (err) {
    console.error('❌ Error loading modal data:', err);
    $('#modalContainer').innerHTML = `
      <div class="modal active" id="scheduleMatchModal">
        <div class="modal-content">
          <div class="modal-header">
            <h3>Error</h3>
            <button class="modal-close" onclick="closeScheduleMatchModal()">×</button>
          </div>
          <div class="modal-body">
            <p style="color: red;">Error loading form data. Please try again.</p>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeScheduleMatchModal()">Close</button>
          </div>
        </div>
      </div>
    `;
  }
}

async function loadScheduleMatchData() {
  try {
    // Load tournaments (already filtered by assignment)
    const tournaments = await fetchJSON('tournaments');
    const activeTournaments = tournaments.filter(t => t.is_active == 1);
    const tourSelect = $('#schedule_tour_id');
    tourSelect.innerHTML = '<option value="">-- Select Tournament --</option>' + 
      activeTournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`).join('');

    // Load venues
const venues = await fetchJSON('venues');
const activeVenues = venues; // API already returns only active venues
    const venueSelect = $('#schedule_venue_id');
    venueSelect.innerHTML = '<option value="">-- Select Venue --</option>' + 
      activeVenues.map(v => `<option value="${v.venue_id}">${escapeHtml(v.venue_name)}</option>`).join('');

    // Load umpires
    const umpires = await fetchJSON('umpires');
    const umpireSelect = $('#schedule_umpire_id');
    umpireSelect.innerHTML = '<option value="">-- Select Umpire --</option>' + 
      umpires.map(u => `<option value="${u.person_id}">${escapeHtml(u.full_name)}</option>`).join('');

    // Sports Manager is automatically set to current Tournament Manager
    // Remove the dropdown as it's not editable
    const managerField = $('#schedule_sports_manager_id');
    if (managerField && managerField.parentElement) {
      managerField.parentElement.style.display = 'none';
    }

  } catch (err) {
    console.error('❌ Error loading schedule match data:', err);
  }
}

async function onScheduleTournamentChange() {
  const tourId = $('#schedule_tour_id').value;
  const sportsSelect = $('#schedule_sports_id');
  
  if (!tourId) {
    sportsSelect.innerHTML = '<option value="">-- Select Sport --</option>';
    $('#teamsSportsSection').style.display = 'none';
    $('#individualSportsSection').style.display = 'none';
    return;
  }

  try {
    const assignments = await fetchJSON('my_assignments');
    const tourAssignments = assignments.filter(a => a.tour_id == tourId);
    
    const uniqueSports = [];
    const seenSports = new Set();
    
    tourAssignments.forEach(a => {
      if (!seenSports.has(a.sports_id)) {
        seenSports.add(a.sports_id);
        uniqueSports.push({
          sports_id: a.sports_id,
          sports_name: a.sports_name,
          team_individual: a.team_individual || 'team',
          men_women: a.men_women || 'mixed'
        });
      }
    });
    
    if (uniqueSports.length === 0) {
      sportsSelect.innerHTML = '<option value="">No sports assigned to you in this tournament</option>';
      return;
    }
    
    sportsSelect.innerHTML = '<option value="">-- Select Sport --</option>' + 
      uniqueSports.map(s => {
        const type = s.team_individual || 'team';
        return `<option value="${s.sports_id}" data-type="${type}">${escapeHtml(s.sports_name)} (${type})</option>`;
      }).join('');
      
  } catch (err) {
    console.error('❌ Error loading sports:', err);
    sportsSelect.innerHTML = '<option value="">Error loading sports</option>';
  }
}

async function onScheduleSportChange() {
  const tourId = $('#schedule_tour_id').value;
  const sportsSelect = $('#schedule_sports_id');
  const selectedOption = sportsSelect.selectedOptions[0];
  const teamsSportsSection = $('#teamsSportsSection');
  const individualSportsSection = $('#individualSportsSection');
  
  // Hide both sections initially
  teamsSportsSection.style.display = 'none';
  individualSportsSection.style.display = 'none';
  
  if (!selectedOption || !selectedOption.value) {
    return;
  }

  const sportsId = selectedOption.value;
  const sportsType = selectedOption.dataset.type; // 'team' or 'individual'
  $('#schedule_sports_type').value = sportsType;

  console.log('🎯 Sport selected:', sportsId, 'Type:', sportsType);

  if (sportsType === 'team') {
    // Show team selection section
    teamsSportsSection.style.display = 'block';
    
    try {
      const assignments = await fetchJSON('my_assignments');
      const relevantTeams = assignments.filter(a => 
        a.tour_id == tourId && a.sports_id == sportsId
      );
      
      const uniqueTeams = [];
      const seenTeams = new Set();
      relevantTeams.forEach(a => {
        if (!seenTeams.has(a.team_id)) {
          seenTeams.add(a.team_id);
          uniqueTeams.push({
            team_id: a.team_id,
            team_name: a.team_name
          });
        }
      });
      
      if (uniqueTeams.length === 0) {
        const teamASelect = $('#schedule_team_a_id');
        const teamBSelect = $('#schedule_team_b_id');
        teamASelect.innerHTML = '<option value="">No teams assigned</option>';
        teamBSelect.innerHTML = '<option value="">No teams assigned</option>';
        return;
      }
      
      const teamASelect = $('#schedule_team_a_id');
      const teamBSelect = $('#schedule_team_b_id');
      
      const teamOptions = '<option value="">-- Select Team --</option>' + 
        uniqueTeams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
      
      teamASelect.innerHTML = teamOptions;
      teamBSelect.innerHTML = teamOptions;
      
      teamASelect.required = true;
      teamBSelect.required = true;
    } catch (err) {
      console.error('❌ Error loading teams:', err);
    }
  } else {
    // Show individual sports section
    individualSportsSection.style.display = 'block';
    
    // Make team fields not required
    const teamASelect = $('#schedule_team_a_id');
    const teamBSelect = $('#schedule_team_b_id');
    if (teamASelect) teamASelect.required = false;
    if (teamBSelect) teamBSelect.required = false;
  }
}



async function saveScheduleMatch(event) {
  event.preventDefault();
  event.stopPropagation();
  
  if (isSubmittingMatch) {
    console.log('⚠️ Submission already in progress, ignoring duplicate request');
    return;
  }
  
  isSubmittingMatch = true;
  console.log('🔒 Submission flag set to TRUE');
  
  const msg = $('#scheduleMsg');
  msg.style.display = 'block';
  msg.textContent = 'Scheduling match...';
  msg.style.color = '#6b7280';
  
  const submitBtn = document.querySelector('#scheduleMatchForm button[type="submit"]');
  const originalBtnText = submitBtn ? submitBtn.textContent : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
  }

  try {
    const formData = new URLSearchParams();
    formData.set('tour_id', $('#schedule_tour_id').value);
    formData.set('sports_id', $('#schedule_sports_id').value);
    formData.set('game_no', $('#schedule_game_no').value);
    formData.set('sked_date', $('#schedule_sked_date').value);
    formData.set('sked_time', $('#schedule_sked_time').value);
    formData.set('venue_id', $('#schedule_venue_id').value || '0');
    formData.set('match_umpire_id', $('#schedule_umpire_id').value || '0');
    formData.set('match_type', $('#schedule_match_type').value);
    
    const sportsType = $('#schedule_sports_type').value;
    formData.set('sports_type', sportsType);
    
    // For team sports, include team selections
    // For individual sports, set empty values (athletes will be added during scoring)
    if (sportsType === 'team') {
      formData.set('team_a_id', $('#schedule_team_a_id').value || '');
      formData.set('team_b_id', $('#schedule_team_b_id').value || '');
    } else {
      formData.set('team_a_id', '');
      formData.set('team_b_id', '');
    }

    console.log('📤 Submitting match data:', Object.fromEntries(formData));

    const data = await fetchJSON('create_match', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      msg.textContent = data.message || 'Match scheduled successfully!';
      msg.style.color = 'green';
      
      setTimeout(() => {
        closeScheduleMatchModal();
        isSubmittingMatch = false;
        loadMatches();
      }, 800);
    } else {
      msg.textContent = data.message || 'Error scheduling match';
      msg.style.color = 'red';
      
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalBtnText;
      }
      isSubmittingMatch = false;
    }
  } catch (err) {
    console.error('❌ Schedule match error:', err);
    msg.textContent = 'Error scheduling match: ' + err.message;
    msg.style.color = 'red';
    
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = originalBtnText;
    }
    isSubmittingMatch = false;
  }
}

function closeScheduleMatchModal() {
  const modal = $('#scheduleMatchModal');
  if (modal) {
    modal.remove();
  }
  // Clear the modal container
  $('#modalContainer').innerHTML = '';
  // Reset the submission flag
  isSubmittingMatch = false;
}

async function editMatch(matchId) {
  try {
    // Get match details
    const matches = await fetchJSON('matches');
    const match = matches.find(m => m.match_id === matchId);
    
    if (!match) {
      alert('Match not found');
      return;
    }

    const modalHTML = `
      <div class="modal active" id="editMatchModal">
        <div class="modal-content wide">
          <div class="modal-header">
            <h3>Edit Match</h3>
            <button class="modal-close" onclick="closeEditMatchModal()">×</button>
          </div>
          <form id="editMatchForm" onsubmit="saveEditMatch(event)">
            <input type="hidden" id="edit_match_id" value="${match.match_id}">
            <input type="hidden" id="edit_sports_type" value="${match.sports_type}">
            <div class="modal-body">
              <div class="form" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                
                <!-- Game Number -->
                <div class="form-group">
                  <label class="form-label">Game Number *</label>
                  <input type="text" class="form-control" id="edit_game_no" value="${escapeHtml(match.game_no)}" required>
                </div>

                <!-- Match Type -->
                <div class="form-group">
                  <label class="form-label">Match Type *</label>
                  <select class="form-control" id="edit_match_type" required>
                    <option value="EL" ${match.match_type === 'EL' ? 'selected' : ''}>Elimination (EL)</option>
                    <option value="QF" ${match.match_type === 'QF' ? 'selected' : ''}>Quarter Final (QF)</option>
                    <option value="SF" ${match.match_type === 'SF' ? 'selected' : ''}>Semi Final (SF)</option>
                    <option value="F" ${match.match_type === 'F' ? 'selected' : ''}>Final (F)</option>
                  </select>
                </div>

                <!-- Date -->
                <div class="form-group">
                  <label class="form-label">Match Date *</label>
                  <input type="date" class="form-control" id="edit_sked_date" value="${match.sked_date}" required>
                </div>

                <!-- Time -->
                <div class="form-group">
                  <label class="form-label">Match Time *</label>
                  <input type="time" class="form-control" id="edit_sked_time" value="${match.sked_time}" required>
                </div>

                <!-- Venue -->
                <div class="form-group">
                  <label class="form-label">Venue</label>
                  <select class="form-control" id="edit_venue_id">
                    <option value="">-- Select Venue --</option>
                  </select>
                </div>

                <!-- Umpire -->
                <div class="form-group">
                  <label class="form-label">Umpire</label>
                  <select class="form-control" id="edit_umpire_id">
                    <option value="">-- Select Umpire --</option>
                  </select>
                </div>

                <!-- Sports Manager -->
                <div class="form-group" style="grid-column: span 2;">
                  <label class="form-label">Sports Manager</label>
                  <select class="form-control" id="edit_sports_manager_id">
                    <option value="">-- Select Sports Manager --</option>
                  </select>
                </div>

                <!-- Teams Section (shown for team sports) -->
                ${match.sports_type === 'team' ? `
                  <div style="grid-column: span 2;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                      <div class="form-group">
                        <label class="form-label">Team A *</label>
                        <select class="form-control" id="edit_team_a_id" required>
                          <option value="">-- Select Team A --</option>
                        </select>
                      </div>

                      <div class="form-group">
                        <label class="form-label">Team B *</label>
                        <select class="form-control" id="edit_team_b_id" required>
                          <option value="">-- Select Team B --</option>
                        </select>
                      </div>
                    </div>
                  </div>
                ` : ''}

              </div>
              <div id="editMatchMsg" class="msg" style="display:none;"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeEditMatchModal()">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Match</button>
            </div>
          </form>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = modalHTML;
    
    // Load dropdowns
    await loadEditMatchData(match);
    
  } catch (err) {
    console.error('❌ Error editing match:', err);
    alert('Error loading match details');
  }
}

async function loadEditMatchData(match) {
  try {
    // Load venues
    const venues = await fetchJSON('venues');
    const activeVenues = venues.filter(v => v.is_active == 1);
    const venueSelect = $('#edit_venue_id');
    venueSelect.innerHTML = '<option value="">-- Select Venue --</option>' + 
      activeVenues.map(v => `<option value="${v.venue_id}" ${v.venue_id == match.venue_id ? 'selected' : ''}>${escapeHtml(v.venue_name)}</option>`).join('');

    // Load umpires
    const umpires = await fetchJSON('umpires');
    const umpireSelect = $('#edit_umpire_id');
    umpireSelect.innerHTML = '<option value="">-- Select Umpire --</option>' + 
      umpires.map(u => `<option value="${u.person_id}" ${u.person_id == match.match_umpire_id ? 'selected' : ''}>${escapeHtml(u.full_name)}</option>`).join('');

    // Load sports managers
    const managers = await fetchJSON('sports_managers');
    const managerSelect = $('#edit_sports_manager_id');
    managerSelect.innerHTML = '<option value="">-- Select Sports Manager --</option>' + 
      managers.map(m => `<option value="${m.person_id}" ${m.person_id == match.match_sports_manager_id ? 'selected' : ''}>${escapeHtml(m.full_name)}</option>`).join('');

    // Load teams if team sport
    if (match.sports_type === 'team') {
      const teams = await fetchJSON(`tournament_teams_by_sport&tour_id=${match.tour_id}&sports_id=${match.sports_id}`);
      const teamOptions = '<option value="">-- Select Team --</option>' + 
        teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
      
      const teamASelect = $('#edit_team_a_id');
      const teamBSelect = $('#edit_team_b_id');
      
      teamASelect.innerHTML = teamOptions;
      teamBSelect.innerHTML = teamOptions;
      
      // Set selected teams
      teamASelect.value = match.team_a_id || '';
      teamBSelect.value = match.team_b_id || '';
    }

  } catch (err) {
    console.error('❌ Error loading edit match data:', err);
  }
}

async function saveEditMatch(event) {
  event.preventDefault();
  const msg = $('#editMatchMsg');
  msg.style.display = 'block';
  msg.textContent = 'Updating match...';
  msg.style.color = '#6b7280';

  try {
    const formData = new URLSearchParams();
    formData.set('match_id', $('#edit_match_id').value);
    formData.set('game_no', $('#edit_game_no').value);
    formData.set('sked_date', $('#edit_sked_date').value);
    formData.set('sked_time', $('#edit_sked_time').value);
    formData.set('venue_id', $('#edit_venue_id').value || '0');
    formData.set('match_umpire_id', $('#edit_umpire_id').value || '0');
    formData.set('match_sports_manager_id', $('#edit_sports_manager_id').value || '0');
    formData.set('match_type', $('#edit_match_type').value);
    
    const sportsType = $('#edit_sports_type').value;
    if (sportsType === 'team') {
      formData.set('team_a_id', $('#edit_team_a_id').value || '');
      formData.set('team_b_id', $('#edit_team_b_id').value || '');
    } else {
      formData.set('team_a_id', '');
      formData.set('team_b_id', '');
    }

    const data = await fetchJSON('update_match', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Match updated!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      setTimeout(() => {
        closeEditMatchModal();
        loadMatches();
      }, 1000);
    }
  } catch (err) {
    console.error('❌ Update match error:', err);
    msg.textContent = 'Error updating match';
    msg.style.color = 'red';
  }
}

function closeEditMatchModal() {
  const modal = $('#editMatchModal');
  if (modal) modal.remove();
}

async function deleteMatch(matchId) {
  if (!confirm('Are you sure you want to delete this match? This will also delete associated scores.')) {
    return;
  }

  try {
    const formData = new URLSearchParams();
    formData.set('match_id', matchId);

    const data = await fetchJSON('delete_match', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      alert('Match deleted successfully');
      await loadMatches();
    } else {
      alert(data.message || 'Failed to delete match');
    }
  } catch (err) {
    console.error('❌ Delete match error:', err);
    alert('Error deleting match');
  }
}

$('#matchesFilterTour')?.addEventListener('change', (e) => {
  const tourId = e.target.value || null;
  loadMatches(tourId);
});

function populateMatchDropdowns(matches) {
  const withoutWinner = matches.filter(m => !m.winner_id);
  
  const scoreMatchSelect = $('#scoreMatchSelect');
  const winnerMatchSelect = $('#winnerMatchSelect');
  
  if (scoreMatchSelect) {
    let opts = '<option value="">-- Select Match --</option>';
    withoutWinner.forEach(m => {
      const matchLabel = m.sports_type === 'individual' 
        ? `${escapeHtml(m.sports_name)} - ${escapeHtml(m.match_type)} (${m.sked_date})`
        : `${escapeHtml(m.sports_name)}: ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)} (${m.sked_date})`;
      
      opts += `<option value="${m.match_id}" 
               data-tour-id="${m.tour_id}" 
               data-sports-id="${m.sports_id}"
               data-sports-type="${m.sports_type}"
               data-team-a="${m.team_a_id || ''}" 
               data-team-b="${m.team_b_id || ''}" 
               data-team-a-name="${escapeHtml(m.team_a_name || '')}" 
               data-team-b-name="${escapeHtml(m.team_b_name || '')}">
        ${matchLabel}
      </option>`;
    });
    scoreMatchSelect.innerHTML = opts;
  }
  
  if (winnerMatchSelect) {
    // Only show team matches for winner declaration
    const teamMatches = withoutWinner.filter(m => m.sports_type === 'team');
    let opts = '<option value="">-- Select Match --</option>';
    teamMatches.forEach(m => {
      opts += `<option value="${m.match_id}" 
               data-tour-id="${m.tour_id}" 
               data-team-a="${m.team_a_id}" 
               data-team-b="${m.team_b_id}" 
               data-team-a-name="${escapeHtml(m.team_a_name)}" 
               data-team-b-name="${escapeHtml(m.team_b_name)}">
        ${escapeHtml(m.sports_name)}: ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)} (${m.sked_date})
      </option>`;
    });
    winnerMatchSelect.innerHTML = opts;
  }
}

// ==========================================
// SCORING
// ==========================================

$('#scoreMatchSelect')?.addEventListener('change', async (e) => {
  const option = e.target.selectedOptions[0];
  const scoreCompetitorSelect = $('#scoreCompetitorSelect');
  
  if (!scoreCompetitorSelect) return;
  
  if (!option || !option.value) {
    scoreCompetitorSelect.innerHTML = '<option value="">-- Select Competitor --</option>';
    return;
  }
  
  const matchId = option.value;
  const tourId = option.dataset.tourId;
  const sportsId = option.dataset.sportsId;
  const sportsType = option.dataset.sportsType;
  
  if (sportsType === 'team') {
    // For team sports, show the two competing teams
    const teamAId = option.dataset.teamA;
    const teamBId = option.dataset.teamB;
    const teamAName = option.dataset.teamAName;
    const teamBName = option.dataset.teamBName;
    
    scoreCompetitorSelect.innerHTML = `
      <option value="">-- Select Competitor --</option>
      <option value="team_${teamAId}">${teamAName}</option>
      <option value="team_${teamBId}">${teamBName}</option>
    `;
  } else {
    // For individual sports, load all registered athletes for this sport
    try {
      scoreCompetitorSelect.innerHTML = '<option value="">Loading athletes...</option>';
      
      const athletes = await fetchJSON('match_athletes', { 
        tour_id: tourId, 
        sports_id: sportsId 
      });
      
      if (!athletes || athletes.length === 0) {
        scoreCompetitorSelect.innerHTML = `
          <option value="">No athletes registered for this sport</option>
        `;
        return;
      }
      
      let options = '<option value="">-- Select Athlete --</option>';
      athletes.forEach(athlete => {
        const teamInfo = athlete.team_name ? ` (${athlete.team_name})` : '';
        options += `<option value="athlete_${athlete.athlete_id}">
          ${escapeHtml(athlete.athlete_name)}${teamInfo}
        </option>`;
      });
      
      scoreCompetitorSelect.innerHTML = options;
    } catch (err) {
      console.error('Error loading athletes:', err);
      scoreCompetitorSelect.innerHTML = '<option value="">Error loading athletes</option>';
    }
  }
});

$('#scoreForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#scoreMsg');
  msg.textContent = "Saving score...";
  msg.style.color = '#6b7280';

  try {
    const matchSelect = $('#scoreMatchSelect');
    const matchId = matchSelect.value;
    
    if (!matchId) {
      msg.textContent = 'Please select a match';
      msg.style.color = 'red';
      return;
    }
    
    const selectedOption = matchSelect.selectedOptions[0];
    const tourId = selectedOption.dataset.tourId;
    const sportsType = selectedOption.dataset.sportsType;
    
    const competitorValue = $('#scoreCompetitorSelect').value;
    
    if (!competitorValue) {
      msg.textContent = 'Please select a competitor';
      msg.style.color = 'red';
      return;
    }
    
    const formData = new URLSearchParams();
    formData.set('tour_id', tourId);
    formData.set('match_id', matchId);
    
    // Parse competitor value to determine if it's a team or athlete
    if (competitorValue.startsWith('team_')) {
      // Team sport - extract team_id
      const teamId = competitorValue.replace('team_', '');
      formData.set('team_id', teamId);
      formData.set('athlete_id', '');
    } else if (competitorValue.startsWith('athlete_')) {
      // Individual sport - extract athlete_id
      const athleteId = competitorValue.replace('athlete_', '');
      formData.set('team_id', '');
      formData.set('athlete_id', athleteId);
    } else {
      msg.textContent = 'Invalid competitor selection';
      msg.style.color = 'red';
      return;
    }
    
    formData.set('score', $('#score_value').value);
    formData.set('rank_no', $('#rank_no').value);
    formData.set('medal_type', $('#medal_type').value);

    const data = await fetchJSON('save_score', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Score saved!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadScores();
    }
  } catch (err) {
    console.error('❌ Score save error:', err);
    msg.textContent = 'Error saving score';
    msg.style.color = 'red';
  }
});

// Export functions
window.populateMatchDropdowns = populateMatchDropdowns;

async function loadScores() {
  try {
    const data = await fetchJSON('scores');
    const tbody = $('#scoresTable tbody');
    
    if (!tbody) return;
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No scores yet</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(s => `
      <tr>
        <td style="font-size:11px;">${escapeHtml(s.match_info)}</td>
        <td>${escapeHtml(s.team_name)}</td>
        <td><strong>${escapeHtml(s.score)}</strong></td>
        <td>${s.rank_no}</td>
        <td>${s.medal_type && s.medal_type !== 'None' ? `<span class="badge ${s.medal_type}">${s.medal_type}</span>` : '-'}</td>
        <td>
          <button class="btn btn-sm btn-danger" onclick="deleteScore(${s.competetors_score_id})">Delete</button>
        </td>
      </tr>
    `).join('');
    
    console.log('✅ Scores loaded:', data.length);
  } catch (err) {
    console.error('❌ loadScores error:', err);
  }
}

async function deleteScore(scoreId) {
  if (!confirm('Delete this score?')) return;
  
  try {
    const formData = new URLSearchParams();
    formData.set('score_id', scoreId);

    const data = await fetchJSON('delete_score', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      await loadScores();
    } else {
      alert(data.message || 'Failed to delete');
    }
  } catch (err) {
    console.error('❌ Delete score error:', err);
    alert('Error deleting score');
  }
}

// ==========================================
// WINNER DECLARATION
// ==========================================
// Note: Winner declaration functionality is already implemented above
// (see lines ~1512-1720 for the complete implementation that handles
// both team and individual sports with proper prefix parsing)

// ==========================================
// STANDINGS & MEDALS
// ==========================================

// STANDINGS - Fixed version
$('#standingsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const tbody = $('#standingsTable tbody');
  
  if (!tbody) return;
  
  if (!tourId) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty-state">Select a tournament</td></tr>';
    return;
  }
  
  try {
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Loading standings...</td></tr>';
    
    console.log('📡 Fetching standings for tournament:', tourId);
const data = await fetchJSON('standings', { tour_id: tourId });
    console.log('✅ Standings data received:', data);
    
    if (!Array.isArray(data)) {
      console.error('❌ Invalid data format:', data);
      tbody.innerHTML = '<tr><td colspan="9" class="empty-state" style="color:red;">Invalid data received from server</td></tr>';
      return;
    }
    
    if (data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No standings data available yet. Standings will appear after matches are completed and scored.</td></tr>';
      return;
    }
    
    // Generate table rows
    let html = '';
    data.forEach(s => {
      // For individual sports, show athlete name; for team sports, show team name
      const displayName = s.team_individual === 'individual' && s.athlete_name 
        ? escapeHtml(s.athlete_name) + (s.team_name ? ` (${escapeHtml(s.team_name)})` : '')
        : escapeHtml(s.team_name || 'Unknown');
      
      html += `
        <tr>
          <td><strong>${displayName}</strong></td>
          <td>${escapeHtml(s.sports_name || 'N/A')}</td>
          <td style="text-align:center;">${s.no_games_played || 0}</td>
          <td style="text-align:center;">${s.no_win || 0}</td>
          <td style="text-align:center;">${s.no_loss || 0}</td>
          <td style="text-align:center;">${s.no_draw || 0}</td>
          <td style="text-align:center;font-weight:600;color:#f59e0b;">${s.no_gold || 0}</td>
          <td style="text-align:center;font-weight:600;color:#9ca3af;">${s.no_silver || 0}</td>
          <td style="text-align:center;font-weight:600;color:#d97706;">${s.no_bronze || 0}</td>
        </tr>
      `;
    });
    
    tbody.innerHTML = html;
    console.log('✅ Standings table updated with', data.length, 'rows');
    
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    tbody.innerHTML = '<tr><td colspan="9" style="color:red;padding:20px;text-align:center;">Error loading standings: ' + err.message + '</td></tr>';
  }
});

// MEDALS - Fixed version
$('#medalsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const tbody = $('#medalsTable tbody');
  
  if (!tbody) return;
  
  if (!tourId) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Select a tournament</td></tr>';
    return;
  }
  
  try {
    tbody.innerHTML = '<tr><td colspan="6" class="loading">Loading medal tally...</td></tr>';
    
    console.log('📡 Fetching medals for tournament:', tourId);
const data = await fetchJSON('medal_tally', { tour_id: tourId });
    console.log('✅ Medal data received:', data);
    
    if (!Array.isArray(data)) {
      console.error('❌ Invalid data format:', data);
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state" style="color:red;">Invalid data received from server</td></tr>';
      return;
    }
    
    if (data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No medals awarded yet. Medal tally will appear after matches are completed and medals are awarded.</td></tr>';
      return;
    }
    
    // Generate table rows with ranking
    let html = '';
    data.forEach((m, idx) => {
      const rank = idx + 1;
      const totalMedals = (parseInt(m.total_gold) || 0) + 
                         (parseInt(m.total_silver) || 0) + 
                         (parseInt(m.total_bronze) || 0);
      
      html += `
        <tr>
          <td style="text-align:center;font-weight:600;font-size:16px;">${rank}</td>
          <td><strong>${escapeHtml(m.team_name || 'Unknown')}</strong></td>
          <td style="text-align:center;font-weight:600;color:#f59e0b;font-size:16px;">🥇 ${m.total_gold || 0}</td>
          <td style="text-align:center;font-weight:600;color:#9ca3af;font-size:16px;">🥈 ${m.total_silver || 0}</td>
          <td style="text-align:center;font-weight:600;color:#d97706;font-size:16px;">🥉 ${m.total_bronze || 0}</td>
          <td style="text-align:center;font-weight:700;font-size:16px;background:#f9fafb;">${totalMedals}</td>
        </tr>
      `;
    });
    
    tbody.innerHTML = html;
    console.log('✅ Medal table updated with', data.length, 'teams');
    
  } catch (err) {
    console.error('❌ loadMedals error:', err);
    tbody.innerHTML = '<tr><td colspan="6" style="color:red;padding:20px;text-align:center;">Error loading medals: ' + err.message + '</td></tr>';
  }
});

console.log('✅ Standings and Medals event listeners registered');

// ==========================================
// VENUES
// ==========================================

async function loadVenuesTable() {
  try {
    const data = await fetchJSON('venues');
    const content = $('#venuesContent');
    
    if (!Array.isArray(data) || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No venues found</div>';
      return;
    }
    
    let html = '<div class="data-grid">';
    html += data.map(v => `
      <div class="data-card">
        <div class="data-card-header">
          <div class="data-card-title">${escapeHtml(v.venue_name)}</div>
          <span class="badge badge-${v.is_active == 1 ? 'active' : 'inactive'}">
            ${v.is_active == 1 ? 'Active' : 'Inactive'}
          </span>
        </div>
        <div class="data-card-meta">
          ${v.venue_building ? '🏢 ' + escapeHtml(v.venue_building) : ''} 
          ${v.venue_room ? '• 🚪 ' + escapeHtml(v.venue_room) : ''}
        </div>
        <div class="data-card-actions">
          <button class="btn btn-sm" onclick="editVenue(${v.venue_id})">Edit</button>
          <button class="btn btn-sm btn-${v.is_active == 1 ? 'warning' : 'success'}" 
            onclick="toggleVenue(${v.venue_id}, ${v.is_active})">
            ${v.is_active == 1 ? 'Deactivate' : 'Activate'}
          </button>
        </div>
      </div>
    `).join('');
    html += '</div>';
    
    content.innerHTML = html;
    
    console.log('✅ Venues loaded:', data.length);
  } catch (err) {
    console.error('❌ loadVenues error:', err);
  }
}

function showVenueModal(venueId = null) {
  // Venue modal implementation
  alert('Venue modal - implement similarly to tournament modal');
}

async function editVenue(venueId) {
  // Edit venue implementation
  alert('Edit venue - implement modal with pre-filled data');
}

async function toggleVenue(venueId, currentStatus) {
  // Toggle venue implementation
  if (!confirm(`${currentStatus == 1 ? 'Deactivate' : 'Activate'} this venue?`)) return;
  
  try {
    const formData = new URLSearchParams();
    formData.set('venue_id', venueId);
    formData.set('is_active', currentStatus == 1 ? '0' : '1');

    const data = await fetchJSON('toggle_venue', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    if (data.ok) {
      await loadVenuesTable();
    }
  } catch (err) {
    console.error('❌ Toggle venue error:', err);
  }
}

// ==========================================
// PRINT FUNCTIONALITY
// ==========================================

// ==========================================
// COMPLETE PRINT FUNCTIONALITY
// Replace the print section in tournament.js (lines ~1615-1653)
// ==========================================

// ==========================================
// FIXED PRINT FUNCTIONALITY WITH DEBUGGING
// Replace the print section in tournament.js
// ==========================================

// ==========================================
// PLAIN TEXT FORMAL PRINT FUNCTIONALITY
// Replace the print section in tournament.js
// ==========================================

function openPrintModal() {
  const modal = $('#printModal');
  modal.classList.add('active');
}

function closePrintModal() {
  const modal = $('#printModal');
  modal.classList.remove('active');
  $('#printTourSelect').value = '';
  $('#printOptions').style.display = 'none';
  $('#generatePrintBtn').disabled = true;
}

$('#printTourSelect')?.addEventListener('change', function() {
  const printOptions = $('#printOptions');
  const generateBtn = $('#generatePrintBtn');
  
  if (this.value) {
    printOptions.style.display = 'block';
    generateBtn.disabled = false;
  } else {
    printOptions.style.display = 'none';
    generateBtn.disabled = true;
  }
});

async function generatePrintReport() {
  const tourId = $('#printTourSelect').value;
  
  if (!tourId) {
    alert('Please select a tournament');
    return;
  }

  const generateBtn = $('#generatePrintBtn');
  generateBtn.disabled = true;
  generateBtn.textContent = 'Generating...';

  try {
    console.log('🔍 Starting report generation for tournament:', tourId);

    // Get selected options
    const options = {
      overview: $('#print_overview')?.checked || false,
      sports: $('#print_sports')?.checked || false,
      teams: $('#print_teams')?.checked || false,
      players: $('#print_players')?.checked || false,
      coaches: $('#print_coaches')?.checked || false,
      matches: $('#print_matches')?.checked || false,
      results: $('#print_results')?.checked || false,
      standings: $('#print_standings')?.checked || false,
      medals: $('#print_medals')?.checked || false,
      officials: $('#print_officials')?.checked || false
    };

    // Get tournament data from dropdown
    const selectedOption = $('#printTourSelect').selectedOptions[0];
    const tourName = selectedOption.dataset.tourName || selectedOption.text;
    const schoolYear = selectedOption.dataset.schoolYear || '';
    const tourDate = selectedOption.dataset.tourDate || '';

    // Fetch data with error handling
    let sports = [];
    let teams = [];
    let matches = [];
    let scores = [];
    let standings = [];
    let medals = [];
    let venues = [];
    let umpires = [];
    let managers = [];

    // Fetch sports if needed
    if (options.sports || options.teams || options.players || options.coaches) {
      try {
        sports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
        console.log('✅ Sports fetched:', sports.length);
      } catch (err) {
        console.warn('⚠️ Error fetching sports:', err);
        sports = [];
      }
    }

    // Fetch teams if needed
    if (options.teams || options.players || options.coaches) {
      try {
        teams = await fetchJSON(`tournament_teams&tour_id=${tourId}`);
        console.log('✅ Teams fetched:', teams.length);
      } catch (err) {
        console.warn('⚠️ Error fetching teams:', err);
        teams = [];
      }
    }

    // Fetch matches if needed
    if (options.matches || options.results) {
      try {
        matches = await fetchJSON(`matches&tour_id=${tourId}`);
        console.log('✅ Matches fetched:', matches.length);
      } catch (err) {
        console.warn('⚠️ Error fetching matches:', err);
        matches = [];
      }
    }

    // Fetch scores if needed
    if (options.results) {
      try {
        const allScores = await fetchJSON('scores');
        const matchIds = matches.map(m => m.match_id);
        scores = allScores.filter(s => matchIds.includes(s.match_id));
        console.log('✅ Scores fetched:', scores.length);
      } catch (err) {
        console.warn('⚠️ Error fetching scores:', err);
        scores = [];
      }
    }

    // Fetch standings if needed
    if (options.standings) {
      try {
        standings = await fetchJSON(`standings&tour_id=${tourId}`);
        console.log('✅ Standings fetched:', standings.length);
      } catch (err) {
        console.warn('⚠️ Error fetching standings:', err);
        standings = [];
      }
    }

    // Fetch medals if needed
    if (options.medals) {
      try {
        medals = await fetchJSON(`medal_tally&tour_id=${tourId}`);
        console.log('✅ Medals fetched:', medals.length);
      } catch (err) {
        console.warn('⚠️ Error fetching medals:', err);
        medals = [];
      }
    }

    // Fetch venues
    try {
      venues = await fetchJSON('venues');
      console.log('✅ Venues fetched:', venues.length);
    } catch (err) {
      console.warn('⚠️ Error fetching venues:', err);
      venues = [];
    }

    // Fetch umpires if needed
    if (options.officials) {
      try {
        umpires = await fetchJSON('umpires');
        console.log('✅ Umpires fetched:', umpires.length);
      } catch (err) {
        console.warn('⚠️ Error fetching umpires:', err);
        umpires = [];
      }
    }

    // Fetch sports managers if needed
    if (options.officials) {
      try {
        managers = await fetchJSON('sports_managers');
        console.log('✅ Managers fetched:', managers.length);
      } catch (err) {
        console.warn('⚠️ Error fetching managers:', err);
        managers = [];
      }
    }

    console.log('📊 Data fetching complete. Generating HTML...');

    // Generate the report HTML
    const reportHTML = generatePlainTextReport(tourName, schoolYear, tourDate, options, {
      sports, teams, matches, scores, standings, medals, venues, umpires, managers, tourId
    });

    console.log('✅ HTML generated successfully');

    // Display preview
    const preview = $('#printPreview');
    preview.innerHTML = reportHTML;
    preview.style.display = 'block';
    
    // Close modal
    closePrintModal();
    
    // Scroll to preview
    preview.scrollIntoView({ behavior: 'smooth' });
    
    console.log('✅ Report generated successfully!');
    
    // Auto-print prompt
    setTimeout(() => {
      if (confirm('Report generated! Would you like to print now?')) {
        printReport();
      }
    }, 500);

  } catch (err) {
    console.error('❌ PRINT GENERATION ERROR:', err);
    alert('Error generating report: ' + err.message + '\n\nCheck the browser console (F12) for details.');
  } finally {
    generateBtn.disabled = false;
    generateBtn.textContent = 'Generate Report';
  }
}

function generatePlainTextReport(tourName, schoolYear, tourDate, options, data) {
  const { sports, teams, matches, scores, standings, medals, venues, umpires, managers, tourId } = data;
  
  const currentDate = new Date().toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
  
  let html = `
    <div id="printableReport" style="max-width:850px;margin:40px auto;background:white;padding:60px;font-family:'Courier New',monospace;font-size:12px;line-height:1.8;color:#000;">
      
      <!-- Header -->
      <div style="text-align:center;margin-bottom:50px;border-bottom:2px solid #000;padding-bottom:30px;">
        <div style="font-size:24px;font-weight:bold;letter-spacing:2px;margin-bottom:15px;">TOURNAMENT REPORT</div>
        <div style="font-size:18px;font-weight:bold;margin-bottom:10px;">${escapeHtml(tourName).toUpperCase()}</div>
        <div style="margin-top:10px;">School Year: ${escapeHtml(schoolYear)}</div>
        <div>Tournament Date: ${formatDatePlain(tourDate)}</div>
      </div>

      ${options.overview ? generatePlainOverview(tourName, schoolYear, tourDate, sports, teams, matches) : ''}
      ${options.sports ? generatePlainSports(sports) : ''}
      ${options.teams ? generatePlainTeams(teams, sports) : ''}
      ${options.coaches ? generatePlainCoaches(teams) : ''}
      ${options.matches ? generatePlainMatches(matches, venues) : ''}
      ${options.results ? generatePlainResults(scores, matches) : ''}
      ${options.standings ? generatePlainStandings(standings) : ''}
      ${options.medals ? generatePlainMedals(medals) : ''}
      ${options.officials ? generatePlainOfficials(umpires, managers) : ''}

      <!-- Footer -->
      <div style="margin-top:80px;padding-top:20px;border-top:2px solid #000;text-align:center;font-size:10px;">
        <div>This report was generated on ${currentDate}</div>
        <div style="margin-top:5px;">Tournament Management System</div>
      </div>
    </div>

    <!-- Print Controls -->
    <div style="position:fixed;top:20px;right:20px;z-index:10000;display:flex;gap:10px;background:white;padding:10px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);" class="no-print">
      <button onclick="printReport()" style="padding:12px 24px;background:#111827;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:14px;font-family:sans-serif;">
        Print Report
      </button>
      <button onclick="closePreview()" style="padding:12px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:14px;font-family:sans-serif;">
        Close
      </button>
    </div>
  `;
  
  return html;
}

function generatePlainOverview(tourName, schoolYear, tourDate, sports, teams, matches) {
  const uniqueTeams = teams.length > 0 ? [...new Set(teams.map(t => t.team_id))].length : 0;
  const totalMatches = matches.length;
  
  return `
    <div style="margin-bottom:50px;page-break-after:avoid;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">I. TOURNAMENT OVERVIEW</div>
      
      <div style="margin-left:20px;">
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">Tournament Name:</span>
          <span style="font-weight:bold;">${escapeHtml(tourName)}</span>
        </div>
        
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">School Year:</span>
          <span style="font-weight:bold;">${escapeHtml(schoolYear)}</span>
        </div>
        
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">Tournament Date:</span>
          <span style="font-weight:bold;">${formatDatePlain(tourDate)}</span>
        </div>
        
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">Number of Sports:</span>
          <span style="font-weight:bold;">${sports.length}</span>
        </div>
        
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">Participating Teams:</span>
          <span style="font-weight:bold;">${uniqueTeams}</span>
        </div>
        
        <div style="margin-bottom:10px;">
          <span style="display:inline-block;width:200px;">Total Matches:</span>
          <span style="font-weight:bold;">${totalMatches}</span>
        </div>
      </div>
    </div>
  `;
}

function generatePlainSports(sports) {
  if (!sports || sports.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">II. SPORTS AND CATEGORIES</div>
        <div style="margin-left:20px;font-style:italic;">No sports registered for this tournament.</div>
      </div>
    `;
  }

  let html = `
    <div style="margin-bottom:50px;page-break-inside:avoid;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">II. SPORTS AND CATEGORIES</div>
      <div style="margin-left:20px;">
  `;

  sports.forEach((sport, index) => {
    html += `
      <div style="margin-bottom:15px;">
        <div style="font-weight:bold;">${index + 1}. ${escapeHtml(sport.sports_name)}</div>
        <div style="margin-left:20px;margin-top:5px;">
          Type: ${sport.team_individual === 'team' ? 'Team Sport' : 'Individual Sport'}
          ${sport.men_women ? ` | Category: ${escapeHtml(sport.men_women)}` : ''}
          ${sport.weight_class ? ` | Weight Class: ${escapeHtml(sport.weight_class)}` : ''}
        </div>
      </div>
    `;
  });

  html += `
      </div>
    </div>
  `;

  return html;
}

function generatePlainTeams(teams, sports) {
  if (!teams || teams.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">III. PARTICIPATING TEAMS</div>
        <div style="margin-left:20px;font-style:italic;">No teams registered for this tournament.</div>
      </div>
    `;
  }

  // Group teams by sport
  const teamsBySport = {};
  teams.forEach(team => {
    const sportName = team.sports_name || 'Other';
    if (!teamsBySport[sportName]) {
      teamsBySport[sportName] = [];
    }
    if (!teamsBySport[sportName].find(t => t.team_id === team.team_id)) {
      teamsBySport[sportName].push(team);
    }
  });

  let html = `
    <div style="margin-bottom:50px;page-break-inside:avoid;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">III. PARTICIPATING TEAMS</div>
  `;

  Object.keys(teamsBySport).sort().forEach(sportName => {
    const sportTeams = teamsBySport[sportName];
    
    html += `
      <div style="margin-bottom:25px;margin-left:20px;">
        <div style="font-weight:bold;margin-bottom:10px;">${escapeHtml(sportName)} (${sportTeams.length} team${sportTeams.length !== 1 ? 's' : ''})</div>
        <div style="margin-left:20px;">
    `;

    sportTeams.forEach((team, idx) => {
      html += `
        <div style="margin-bottom:5px;">
          ${idx + 1}. ${escapeHtml(team.team_name)}${team.coach_name ? ' - Coach: ' + escapeHtml(team.coach_name) : ''}
        </div>
      `;
    });

    html += `
        </div>
      </div>
    `;
  });

  html += `</div>`;
  return html;
}

function generatePlainCoaches(teams) {
  if (!teams || teams.length === 0) return '';

  const coaches = teams.filter(t => t.coach_name).map(t => ({
    team: t.team_name,
    coach: t.coach_name,
    sport: t.sports_name
  }));

  if (coaches.length === 0) return '';

  let html = `
    <div style="margin-bottom:50px;page-break-inside:avoid;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">IV. COACHES AND TEAM OFFICIALS</div>
      <div style="margin-left:20px;">
  `;

  coaches.forEach((c, idx) => {
    html += `
      <div style="margin-bottom:8px;">
        ${idx + 1}. ${escapeHtml(c.team)} (${escapeHtml(c.sport)}) - ${escapeHtml(c.coach)}
      </div>
    `;
  });

  html += `
      </div>
    </div>
  `;

  return html;
}

function generatePlainMatches(matches, venues) {
  if (!matches || matches.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">V. MATCH SCHEDULE</div>
        <div style="margin-left:20px;font-style:italic;">No matches scheduled yet.</div>
      </div>
    `;
  }

  // Group by date
  const byDate = {};
  matches.forEach(m => {
    const date = m.sked_date || 'No Date';
    if (!byDate[date]) byDate[date] = [];
    byDate[date].push(m);
  });

  let html = `
    <div style="margin-bottom:50px;page-break-before:always;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">V. MATCH SCHEDULE</div>
  `;

  Object.keys(byDate).sort().forEach(date => {
    const dayMatches = byDate[date].sort((a, b) => (a.sked_time || '').localeCompare(b.sked_time || ''));
    
    html += `
      <div style="margin-bottom:30px;margin-left:20px;">
        <div style="font-weight:bold;margin-bottom:15px;text-decoration:underline;">${formatDatePlain(date)}</div>
    `;

    dayMatches.forEach((m, idx) => {
      html += `
        <div style="margin-bottom:10px;margin-left:20px;">
          <div>${idx + 1}. Game #${escapeHtml(m.game_no)} - ${formatTimePlain(m.sked_time)}</div>
          <div style="margin-left:20px;">
            Sport: ${escapeHtml(m.sports_name)} (${escapeHtml(m.match_type)})<br>
            Match: ${escapeHtml(m.team_a_name || 'TBA')} vs ${escapeHtml(m.team_b_name || 'TBA')}<br>
            Venue: ${escapeHtml(m.venue_name || 'TBA')}
          </div>
        </div>
      `;
    });

    html += `</div>`;
  });

  html += `</div>`;
  return html;
}

function generatePlainResults(scores, matches) {
  if (!scores || scores.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VI. MATCH RESULTS AND SCORES</div>
        <div style="margin-left:20px;font-style:italic;">No results recorded yet.</div>
      </div>
    `;
  }

  let html = `
    <div style="margin-bottom:50px;page-break-before:always;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VI. MATCH RESULTS AND SCORES</div>
      <div style="margin-left:20px;">
  `;

  scores.slice(0, 50).forEach((s, idx) => {
    const medalText = s.medal_type && s.medal_type !== 'None' ? ` - ${s.medal_type.toUpperCase()} MEDAL` : '';
    
    html += `
      <div style="margin-bottom:12px;">
        <div>${idx + 1}. ${escapeHtml(s.match_info || 'N/A')}</div>
        <div style="margin-left:20px;">
          Athlete: ${escapeHtml(s.athlete_name || 'N/A')}${s.team_name ? ' (' + escapeHtml(s.team_name) + ')' : ''}<br>
          Score: ${escapeHtml(s.score)} | Rank: ${s.rank_no}${medalText}
        </div>
      </div>
    `;
  });

  html += `
      </div>
    </div>
  `;

  return html;
}

function generatePlainStandings(standings) {
  if (!standings || standings.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VII. TEAM STANDINGS</div>
        <div style="margin-left:20px;font-style:italic;">No standings data available.</div>
      </div>
    `;
  }

  let html = `
    <div style="margin-bottom:50px;page-break-before:always;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VII. TEAM STANDINGS</div>
      <div style="margin-left:20px;">
  `;

  standings.forEach((s, idx) => {
    html += `
      <div style="margin-bottom:12px;">
        <div style="font-weight:bold;">${idx + 1}. ${escapeHtml(s.team_name)} - ${escapeHtml(s.sports_name)}</div>
        <div style="margin-left:20px;">
          Played: ${s.no_games_played || 0} | Won: ${s.no_win || 0} | Lost: ${s.no_loss || 0} | Draw: ${s.no_draw || 0}<br>
          Medals - Gold: ${s.no_gold || 0}, Silver: ${s.no_silver || 0}, Bronze: ${s.no_bronze || 0}
        </div>
      </div>
    `;
  });

  html += `
      </div>
    </div>
  `;

  return html;
}

function generatePlainMedals(medals) {
  if (!medals || medals.length === 0) {
    return `
      <div style="margin-bottom:50px;">
        <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VIII. MEDAL TALLY</div>
        <div style="margin-left:20px;font-style:italic;">No medals awarded yet.</div>
      </div>
    `;
  }

  let html = `
    <div style="margin-bottom:50px;page-break-before:always;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">VIII. MEDAL TALLY</div>
      <div style="margin-left:20px;">
        <div style="margin-bottom:20px;font-weight:bold;">
          <span style="display:inline-block;width:40px;">RANK</span>
          <span style="display:inline-block;width:250px;">TEAM</span>
          <span style="display:inline-block;width:60px;text-align:center;">GOLD</span>
          <span style="display:inline-block;width:70px;text-align:center;">SILVER</span>
          <span style="display:inline-block;width:70px;text-align:center;">BRONZE</span>
          <span style="display:inline-block;width:60px;text-align:center;">TOTAL</span>
        </div>
        <div style="border-top:1px solid #000;margin-bottom:10px;"></div>
  `;

  medals.forEach((m, idx) => {
    const total = (m.gold || 0) + (m.silver || 0) + (m.bronze || 0);
    const rank = (idx + 1).toString().padStart(2, ' ');
    
    html += `
      <div style="margin-bottom:8px;">
        <span style="display:inline-block;width:40px;">${rank}</span>
        <span style="display:inline-block;width:250px;">${escapeHtml(m.team_name)}</span>
        <span style="display:inline-block;width:60px;text-align:center;">${m.gold || 0}</span>
        <span style="display:inline-block;width:70px;text-align:center;">${m.silver || 0}</span>
        <span style="display:inline-block;width:70px;text-align:center;">${m.bronze || 0}</span>
        <span style="display:inline-block;width:60px;text-align:center;font-weight:bold;">${total}</span>
      </div>
    `;
  });

  html += `
      </div>
    </div>
  `;

  return html;
}

function generatePlainOfficials(umpires, managers) {
  let html = `
    <div style="margin-bottom:50px;page-break-before:always;">
      <div style="font-size:16px;font-weight:bold;margin-bottom:20px;text-decoration:underline;">IX. TOURNAMENT OFFICIALS</div>
  `;

  if (managers && managers.length > 0) {
    html += `
      <div style="margin-bottom:30px;margin-left:20px;">
        <div style="font-weight:bold;margin-bottom:10px;">Sports Directors/Managers:</div>
        <div style="margin-left:20px;">
    `;

    managers.forEach((m, idx) => {
      html += `<div style="margin-bottom:5px;">${idx + 1}. ${escapeHtml(m.full_name)}</div>`;
    });

    html += `</div></div>`;
  }

  if (umpires && umpires.length > 0) {
    html += `
      <div style="margin-bottom:30px;margin-left:20px;">
        <div style="font-weight:bold;margin-bottom:10px;">Umpires:</div>
        <div style="margin-left:20px;">
    `;

    umpires.forEach((u, idx) => {
      html += `<div style="margin-bottom:5px;">${idx + 1}. ${escapeHtml(u.full_name)}</div>`;
    });

    html += `</div></div>`;
  }

  // Signature section
  html += `
    <div style="margin-top:80px;margin-left:20px;">
      <div style="font-weight:bold;margin-bottom:40px;">CERTIFICATION:</div>
      
      <div style="margin-bottom:60px;">
        <div style="margin-bottom:40px;">
          Prepared by:
        </div>
        <div style="border-bottom:1px solid #000;width:300px;margin-bottom:10px;"></div>
        <div>Tournament Manager</div>
        <div style="margin-top:15px;">Date: _____________________</div>
      </div>
      
      <div style="margin-bottom:60px;">
        <div style="margin-bottom:40px;">
          Reviewed and approved by:
        </div>
        <div style="border-bottom:1px solid #000;width:300px;margin-bottom:10px;"></div>
        <div>Sports Director</div>
        <div style="margin-top:15px;">Date: _____________________</div>
      </div>
    </div>
  </div>`;

  return html;
}

// Helper functions
function formatDatePlain(dateStr) {
  if (!dateStr || dateStr === 'No Date') return 'No Date';
  try {
    const date = new Date(dateStr);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
  } catch (e) {
    return dateStr;
  }
}

function formatTimePlain(timeStr) {
  if (!timeStr) return 'TBA';
  try {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
  } catch (e) {
    return timeStr;
  }
}

function printReport() {
  const style = document.createElement('style');
  style.textContent = `
    @media print {
      @page {
        margin: 0.75in;
        size: letter portrait;
      }
      body {
        margin: 0;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      .sidebar, .top-bar {
        display: none !important;
      }
      .main-content {
        margin-left: 0 !important;
      }
      #printPreview {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      #printableReport {
        box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
      }
    }
  `;
  document.head.appendChild(style);
  
  window.print();
  
  setTimeout(() => {
    document.head.removeChild(style);
  }, 1000);
}

function closePreview() {
  const preview = $('#printPreview');
  if (preview) {
    preview.style.display = 'none';
    preview.innerHTML = '';
  }
}

// ==========================================
// COMPLETE REGISTER TEAMS & PLAYERS JAVASCRIPT
// Copy this entire section and paste at the END of your tournament.js file
// ==========================================

// ==========================================
// REGISTER TEAMS FUNCTIONALITY
// ==========================================

async function loadAllTeams() {
  try {
    console.log('🔄 Loading teams...');
    const teams = await fetchJSON('get_all_teams');
    console.log('✅ Teams loaded:', teams);
    
    let html = '';
    
    if (!teams || teams.length === 0) {
      html = '<div class="empty-state">No teams registered yet. Click "Register New Team" to add one.</div>';
    } else {
      html = '<div class="data-grid">';
      teams.forEach(team => {
        const schoolName = team.school_name || 'Unknown School';
        const statusBadge = team.is_active == 1 
          ? '<span class="badge badge-active">Active</span>' 
          : '<span class="badge badge-inactive">Inactive</span>';
        
        html += `
          <div class="data-card">
            <div class="data-card-header">
              <div class="data-card-title">${escapeHtml(team.team_name)}</div>
              ${statusBadge}
            </div>
            <div class="data-card-meta">
              <div><strong>School:</strong> ${escapeHtml(schoolName)}</div>
              <div><strong>Team ID:</strong> ${team.team_id}</div>
            </div>
            <div class="data-card-actions">
              <button class="btn btn-sm btn-primary" onclick="editTeam(${team.school_id}, ${team.team_id})">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                </svg>
                Edit
              </button>
              <button class="btn btn-sm ${team.is_active == 1 ? 'btn-danger' : 'btn-success'}" 
                      onclick="toggleTeamStatus(${team.school_id}, ${team.team_id}, ${team.is_active})">
                ${team.is_active == 1 ? 'Deactivate' : 'Activate'}
              </button>
            </div>
          </div>
        `;
      });
      html += '</div>';
    }
    
    $('#teamsListContent').innerHTML = html;
  } catch(err) {
    console.error('❌ Error loading teams:', err);
    $('#teamsListContent').innerHTML = '<div class="empty-state" style="color: #dc2626;">Error loading teams. Check console for details.</div>';
  }
}

async function showRegisterTeamModal() {
  try {
    console.log('🔄 Loading schools for team registration...');
    const schools = await fetchJSON('get_schools');
    console.log('✅ Schools loaded:', schools);
    
    if (!schools || schools.length === 0) {
      alert('No schools found in the database. Please add schools first.');
      return;
    }
    
    let schoolOptions = '<option value="">-- Select School --</option>';
    schools.forEach(school => {
      schoolOptions += `<option value="${school.school_id}">${escapeHtml(school.school_name)}</option>`;
    });
    
    const html = `
      <div class="modal active" id="registerTeamModal">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Register New Team</h2>
            <span class="close-modal" onclick="closeModal('registerTeamModal')">&times;</span>
          </div>
          <div class="modal-body">
            <form id="registerTeamForm" class="form" onsubmit="submitRegisterTeam(event)">
              <div class="form-group">
                <label class="form-label">School *</label>
                <select name="school_id" class="form-control" required>
                  ${schoolOptions}
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" class="form-control" required 
                       placeholder="e.g., BSIT Warriors, Engineering Tigers">
              </div>
              
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                  <option value="1" selected>Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              
              <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('registerTeamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Team</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = html;
  } catch(err) {
    console.error('❌ Error showing register team modal:', err);
    alert('Error loading schools. Please check console for details.');
  }
}

async function submitRegisterTeam(e) {
  e.preventDefault();
  
  const form = e.target;
  const formData = new FormData(form);
  
  try {
    console.log('📤 Submitting team registration...');
    const result = await fetchJSON('register_team', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Registration result:', result);
    
    if (result.ok) {
      alert('Team registered successfully!');
      closeModal('registerTeamModal');
      loadAllTeams();
    } else {
      alert('Error: ' + (result.message || 'Failed to register team'));
    }
  } catch(err) {
    console.error('❌ Error registering team:', err);
    alert('Error registering team. Please check console for details.');
  }
}

async function editTeam(schoolId, teamId) {
  try {
    console.log('🔄 Loading team for edit:', schoolId, teamId);
    const teams = await fetchJSON('get_all_teams');
    const schools = await fetchJSON('get_schools');
    const team = teams.find(t => t.school_id == schoolId && t.team_id == teamId);
    
    if (!team) {
      alert('Team not found');
      return;
    }
    
    let schoolOptions = '';
    schools.forEach(school => {
      const selected = school.school_id == team.school_id ? 'selected' : '';
      schoolOptions += `<option value="${school.school_id}" ${selected}>${escapeHtml(school.school_name)}</option>`;
    });
    
    const html = `
      <div class="modal active" id="editTeamModal">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Edit Team</h2>
            <span class="close-modal" onclick="closeModal('editTeamModal')">&times;</span>
          </div>
          <div class="modal-body">
            <form id="editTeamForm" class="form" onsubmit="submitEditTeam(event, ${schoolId}, ${teamId})">
              <div class="form-group">
                <label class="form-label">School *</label>
                <select name="school_id" class="form-control" required disabled>
                  ${schoolOptions}
                </select>
                <small style="color: #6b7280; font-size: 11px;">School cannot be changed after team creation</small>
              </div>
              
              <div class="form-group">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" class="form-control" required 
                       value="${escapeHtml(team.team_name)}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                  <option value="1" ${team.is_active == 1 ? 'selected' : ''}>Active</option>
                  <option value="0" ${team.is_active == 0 ? 'selected' : ''}>Inactive</option>
                </select>
              </div>
              
              <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTeamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Team</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = html;
  } catch(err) {
    console.error('❌ Error loading team for edit:', err);
    alert('Error loading team details.');
  }
}

async function submitEditTeam(e, schoolId, teamId) {
  e.preventDefault();
  
  const form = e.target;
  const formData = new FormData(form);
  formData.append('old_school_id', schoolId);
  formData.append('old_team_id', teamId);
  
  try {
    console.log('📤 Updating team...');
    const result = await fetchJSON('update_team', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Update result:', result);
    
    if (result.ok) {
      alert('Team updated successfully!');
      closeModal('editTeamModal');
      loadAllTeams();
    } else {
      alert('Error: ' + (result.message || 'Failed to update team'));
    }
  } catch(err) {
    console.error('❌ Error updating team:', err);
    alert('Error updating team. Please check console for details.');
  }
}

async function toggleTeamStatus(schoolId, teamId, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this team?`)) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('school_id', schoolId);
    formData.append('team_id', teamId);
    formData.append('is_active', newStatus);
    
    console.log('📤 Toggling team status...');
    const result = await fetchJSON('toggle_team_status', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Toggle result:', result);
    
    if (result.ok) {
      alert(`Team ${action}d successfully!`);
      loadAllTeams();
    } else {
      alert('Error: ' + (result.message || `Failed to ${action} team`));
    }
  } catch(err) {
    console.error('❌ Error toggling team status:', err);
    alert('Error updating team status. Please check console for details.');
  }
}

// ==========================================
// REGISTER PLAYERS FUNCTIONALITY
// ==========================================

async function loadAllPlayers(teamFilter = '') {
  try {
    console.log('🔄 Loading athletes with filter:', teamFilter);
    let url = 'get_all_players';
    if (teamFilter) {
      url += `&team_id=${teamFilter}`;
    }
    
    const players = await fetchJSON(url);
    console.log('✅ Athletes loaded:', players);
    
    let html = '';
    
    if (!players || players.length === 0) {
      html = '<div class="empty-state">No athletes registered yet. Click "Register New Athlete" to add one.</div>';
    } else {
      html = '<div class="data-grid">';
      players.forEach(player => {
        const fullName = `${player.f_name} ${player.m_name ? player.m_name.charAt(0) + '.' : ''} ${player.l_name}`;
        const statusBadge = player.is_active == 1 
          ? '<span class="badge badge-active">Active</span>' 
          : '<span class="badge badge-inactive">Inactive</span>';
        
        html += `
          <div class="data-card">
            <div class="data-card-header">
              <div class="data-card-title">${escapeHtml(fullName)}</div>
              ${statusBadge}
            </div>
            <div class="data-card-meta">
              ${player.college_name ? `<div><strong>College:</strong> ${escapeHtml(player.college_name)}</div>` : ''}
              ${player.course ? `<div><strong>Course:</strong> ${escapeHtml(player.course)}</div>` : ''}
              ${player.date_birth ? `<div><strong>Birth Date:</strong> ${player.date_birth}</div>` : ''}
              ${player.blood_type ? `<div><strong>Blood Type:</strong> ${player.blood_type}</div>` : ''}
            </div>
            <div class="data-card-actions">
              <button class="btn btn-sm btn-primary" onclick="editPlayer(${player.person_id})">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                </svg>
                Edit
              </button>
              <button class="btn btn-sm ${player.is_active == 1 ? 'btn-danger' : 'btn-success'}" 
                      onclick="togglePlayerStatus(${player.person_id}, ${player.is_active})">
                ${player.is_active == 1 ? 'Deactivate' : 'Activate'}
              </button>
            </div>
          </div>
        `;
      });
      html += '</div>';
    }
    
    $('#playersListContent').innerHTML = html;
  } catch(err) {
    console.error('❌ Error loading athletes:', err);
    $('#playersListContent').innerHTML = '<div class="empty-state" style="color: #dc2626;">Error loading athletes. Check console for details.</div>';
  }
}

async function loadPlayersFilter() {
  const teamFilter = $('#playerFilterTeam').value;
  await loadAllPlayers(teamFilter);
}

async function loadPlayerFilterDropdown() {
  try {
    console.log('🔄 Loading teams for filter dropdown...');
    const teams = await fetchJSON('get_all_teams');
    
    let options = '<option value="">-- All Teams --</option>';
    if (teams && teams.length > 0) {
      teams.forEach(team => {
        const schoolName = team.school_name || 'Unknown';
        options += `<option value="${team.team_id}">${escapeHtml(team.team_name)} (${escapeHtml(schoolName)})</option>`;
      });
    }
    
    $('#playerFilterTeam').innerHTML = options;
  } catch(err) {
    console.error('❌ Error loading team filter:', err);
  }
}

async function showRegisterPlayerModal() {
  try {
    console.log('🔄 Loading colleges for player registration...');
    const colleges = await fetchJSON('get_colleges');
    console.log('✅ Colleges loaded:', colleges);
    
    let collegeOptions = '<option value="">-- Select College --</option>';
    if (colleges && colleges.length > 0) {
      colleges.forEach(college => {
        collegeOptions += `<option value="${escapeHtml(college.college_code)}">${escapeHtml(college.college_name)}</option>`;
      });
    }
    
    const html = `
      <div class="modal active" id="registerPlayerModal">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Register New Athlete</h2>
            <span class="close-modal" onclick="closeModal('registerPlayerModal')">&times;</span>
          </div>
          <div class="modal-body">
            <form id="registerPlayerForm" class="form" onsubmit="submitRegisterPlayer(event)">
              <!-- HIDDEN FIELD: Role type is always athlete/player -->
              <input type="hidden" name="role_type" value="athlete/player">
              
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="l_name" class="form-control" required>
              </div>
              
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="f_name" class="form-control" required>
              </div>
              
              <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" name="m_name" class="form-control">
              </div>
              
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g., Mr., Ms.">
              </div>
              
              <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_birth" class="form-control">
              </div>
              
              <div class="form-group">
                <label class="form-label">College</label>
                <select name="college_code" class="form-control">
                  ${collegeOptions}
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Course</label>
                <input type="text" name="course" class="form-control" placeholder="e.g., BSIT, BSCS">
              </div>
              
              <div class="form-group">
                <label class="form-label">Blood Type</label>
                <select name="blood_type" class="form-control">
                  <option value="">-- Select Blood Type --</option>
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
              
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                  <option value="1" selected>Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              
              <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('registerPlayerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Athlete</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = html;
  } catch(err) {
    console.error('❌ Error showing register player modal:', err);
    alert('Error loading colleges. Please check console for details.');
  }
}

async function submitRegisterPlayer(e) {
  e.preventDefault();
  
  const form = e.target;
  const formData = new FormData(form);
  
  try {
    console.log('📤 Submitting player registration...');
    const result = await fetchJSON('register_player', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Registration result:', result);
    
    if (result.ok) {
      alert('Player registered successfully!');
      closeModal('registerPlayerModal');
      loadAllPlayers();
    } else {
      alert('Error: ' + (result.message || 'Failed to register player'));
    }
  } catch(err) {
    console.error('❌ Error registering player:', err);
    alert('Error registering player. Please check console for details.');
  }
}

async function editPlayer(personId) {
  try {
    console.log('🔄 Loading player for edit:', personId);
    const players = await fetchJSON('get_all_players');
    const colleges = await fetchJSON('get_colleges');
    const player = players.find(p => p.person_id == personId);
    
    if (!player) {
      alert('Player not found');
      return;
    }
    
    let collegeOptions = '<option value="">-- Select College --</option>';
    if (colleges && colleges.length > 0) {
      colleges.forEach(college => {
        const selected = college.college_code == player.college_code ? 'selected' : '';
        collegeOptions += `<option value="${escapeHtml(college.college_code)}" ${selected}>${escapeHtml(college.college_name)}</option>`;
      });
    }
    
    const html = `
      <div class="modal active" id="editPlayerModal">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Edit Athlete</h2>
            <span class="close-modal" onclick="closeModal('editPlayerModal')">&times;</span>
          </div>
          <div class="modal-body">
            <form id="editPlayerForm" class="form" onsubmit="submitEditPlayer(event, ${personId})">
              <!-- HIDDEN FIELD: Role type is always athlete/player -->
              <input type="hidden" name="role_type" value="athlete/player">
              
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" name="l_name" class="form-control" required value="${escapeHtml(player.l_name || '')}">
              </div>
              
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" name="f_name" class="form-control" required value="${escapeHtml(player.f_name || '')}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" name="m_name" class="form-control" value="${escapeHtml(player.m_name || '')}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" value="${escapeHtml(player.title || '')}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_birth" class="form-control" value="${player.date_birth || ''}">
              </div>
              
              <div class="form-group">
                <label class="form-label">College</label>
                <select name="college_code" class="form-control">
                  ${collegeOptions}
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Course</label>
                <input type="text" name="course" class="form-control" value="${escapeHtml(player.course || '')}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Blood Type</label>
                <select name="blood_type" class="form-control">
                  <option value="">-- Select Blood Type --</option>
                  <option value="A+" ${player.blood_type == 'A+' ? 'selected' : ''}>A+</option>
                  <option value="A-" ${player.blood_type == 'A-' ? 'selected' : ''}>A-</option>
                  <option value="B+" ${player.blood_type == 'B+' ? 'selected' : ''}>B+</option>
                  <option value="B-" ${player.blood_type == 'B-' ? 'selected' : ''}>B-</option>
                  <option value="AB+" ${player.blood_type == 'AB+' ? 'selected' : ''}>AB+</option>
                  <option value="AB-" ${player.blood_type == 'AB-' ? 'selected' : ''}>AB-</option>
                  <option value="O+" ${player.blood_type == 'O+' ? 'selected' : ''}>O+</option>
                  <option value="O-" ${player.blood_type == 'O-' ? 'selected' : ''}>O-</option>
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-control">
                  <option value="1" ${player.is_active == 1 ? 'selected' : ''}>Active</option>
                  <option value="0" ${player.is_active == 0 ? 'selected' : ''}>Inactive</option>
                </select>
              </div>
              
              <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPlayerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Athlete</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = html;
  } catch(err) {
    console.error('❌ Error loading player for edit:', err);
    alert('Error loading player details.');
  }
}

async function submitEditPlayer(e, personId) {
  e.preventDefault();
  
  const form = e.target;
  const formData = new FormData(form);
  formData.append('person_id', personId);
  
  try {
    console.log('📤 Updating player...');
    const result = await fetchJSON('update_player', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Update result:', result);
    
    if (result.ok) {
      alert('Player updated successfully!');
      closeModal('editPlayerModal');
      loadAllPlayers($('#playerFilterTeam').value);
    } else {
      alert('Error: ' + (result.message || 'Failed to update player'));
    }
  } catch(err) {
    console.error('❌ Error updating player:', err);
    alert('Error updating player. Please check console for details.');
  }
}

async function togglePlayerStatus(personId, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this player?`)) {
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('person_id', personId);
    formData.append('is_active', newStatus);
    
    console.log('📤 Toggling player status...');
    const result = await fetchJSON('toggle_player_status', {
      method: 'POST',
      body: formData
    });
    
    console.log('📥 Toggle result:', result);
    
    if (result.ok) {
      alert(`Player ${action}d successfully!`);
      loadAllPlayers($('#playerFilterTeam').value);
    } else {
      alert('Error: ' + (result.message || `Failed to ${action} player`));
    }
  } catch(err) {
    console.error('❌ Error toggling player status:', err);
    alert('Error updating player status. Please check console for details.');
  }
}

// ==========================================
// ATHLETE APPROVAL & DISQUALIFICATION MODULE
// Add this entire section to your tournament.js file
// ==========================================

let selectedPendingAthletes = new Set();
let currentApprovalTab = 'pending';

function switchApprovalTab(tab) {
  currentApprovalTab = tab;
  
  // Update tab buttons
  document.querySelectorAll('.approval-tab').forEach(btn => {
    if (btn.dataset.tab === tab) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
  
  // Update content
  document.querySelectorAll('.approval-content').forEach(content => {
    content.style.display = 'none';
  });
  
  if (tab === 'pending') {
    const pendingContent = document.getElementById('pendingAthletesContent');
    if (pendingContent) pendingContent.style.display = 'block';
    loadPendingAthletes();
  } else {
    const approvedContent = document.getElementById('approvedAthletesContent');
    if (approvedContent) approvedContent.style.display = 'block';
    loadApprovedAthletes();
  }
}

async function loadPendingAthletes() {
  const tourId = document.getElementById('approvalFilterTournament')?.value || '';
  const list = document.getElementById('pendingAthletesList');
  
  if (!list) return;
  
  try {
    list.innerHTML = '<div class="loading">Loading pending athletes...</div>';
    
    let url = 'pending_athletes';
    if (tourId) url += `&tour_id=${tourId}`;
    
    const athletes = await fetchJSON(url);
    console.log('Pending athletes:', athletes);
    
    if (!athletes || athletes.length === 0) {
      list.innerHTML = '<div class="empty-state">No pending athletes to review. All athletes have been approved!</div>';
      document.getElementById('pendingCount').textContent = '0';
      return;
    }
    
    document.getElementById('pendingCount').textContent = athletes.length;
    
    // Group by tournament and sport
    const grouped = {};
    athletes.forEach(a => {
      const key = `${a.tour_name} - ${a.sports_name}`;
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(a);
    });
    
    let html = '';
    
    Object.keys(grouped).forEach(key => {
      const group = grouped[key];
      html += `
        <div class="group-header" style="margin-bottom: 12px;">
          <h4 class="group-title">${escapeHtml(key)}</h4>
          <span class="group-badge">${group.length} athlete${group.length !== 1 ? 's' : ''}</span>
        </div>
      `;
      
      group.forEach(athlete => {
        const fullName = `${athlete.f_name} ${athlete.m_name ? athlete.m_name + ' ' : ''}${athlete.l_name}`;
        const age = athlete.date_birth ? calculateAge(athlete.date_birth) : 'N/A';
        
        html += `
          <div class="athlete-card">
            <div class="athlete-card-checkbox">
              <input type="checkbox" class="pending-athlete-checkbox" value="${athlete.team_ath_id}" 
                     onchange="updateBulkApproveButton()">
            </div>
            <div class="athlete-card-content">
              <div class="athlete-card-header">
                <div>
                  <div class="athlete-card-name">
                    ${escapeHtml(fullName)}
                    ${athlete.is_captain == 1 ? '<span class="badge" style="background: #fef3c7; color: #92400e; margin-left: 6px;">⭐ Captain</span>' : ''}
                  </div>
                </div>
                <span class="badge" style="background: #fffbeb; color: #92400e; border-color: #fde68a;">Pending</span>
              </div>
              <div class="athlete-card-meta">
                <div><strong>Team:</strong> ${escapeHtml(athlete.team_name)}</div>
                <div><strong>Coach:</strong> ${escapeHtml(athlete.coach_name || 'Not assigned')}</div>
                ${athlete.college_code ? `<div><strong>College:</strong> ${escapeHtml(athlete.college_code)}</div>` : ''}
                ${athlete.course ? `<div><strong>Course:</strong> ${escapeHtml(athlete.course)}</div>` : ''}
                <div><strong>Age:</strong> ${age} ${athlete.date_birth ? `(Born: ${athlete.date_birth})` : ''}</div>
              </div>
              <div class="athlete-card-actions">
                <button class="btn btn-sm btn-success" onclick="approveAthlete(${athlete.team_ath_id})">
                  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
                  </svg>
                  Approve
                </button>
                <button class="btn btn-sm btn-danger" onclick="showDisqualifyModal(${athlete.team_ath_id}, '${escapeHtml(fullName).replace(/'/g, "\\'")}')">
                  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                  </svg>
                  Disqualify
                </button>
                <button class="btn btn-sm btn-secondary" onclick="viewAthleteDetails(${athlete.person_id})">
                  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                  </svg>
                  View Details
                </button>
              </div>
            </div>
          </div>
        `;
      });
    });
    
    list.innerHTML = html;
    updateBulkApproveButton();
    
  } catch (err) {
    console.error('Error loading pending athletes:', err);
    list.innerHTML = '<div class="empty-state" style="color: #dc2626;">Error loading pending athletes</div>';
  }
}

async function loadApprovedAthletes() {
  const tourId = document.getElementById('approvalFilterTournament')?.value || '';
  const list = document.getElementById('approvedAthletesList');
  
  if (!list) return;
  
  try {
    list.innerHTML = '<div class="loading">Loading approved athletes...</div>';
    
    let url = 'approved_athletes';
    if (tourId) url += `&tour_id=${tourId}`;
    
    const athletes = await fetchJSON(url);
    console.log('Approved athletes:', athletes);
    
    if (!athletes || athletes.length === 0) {
      list.innerHTML = '<div class="empty-state">No approved athletes yet.</div>';
      document.getElementById('approvedCount').textContent = '0';
      return;
    }
    
    document.getElementById('approvedCount').textContent = athletes.length;
    
    // Group by tournament and sport
    const grouped = {};
    athletes.forEach(a => {
      const key = `${a.tour_name} - ${a.sports_name}`;
      if (!grouped[key]) grouped[key] = [];
      grouped[key].push(a);
    });
    
    let html = '';
    
    Object.keys(grouped).forEach(key => {
      const group = grouped[key];
      html += `
        <div class="group-header" style="margin-bottom: 12px;">
          <h4 class="group-title">${escapeHtml(key)}</h4>
          <span class="group-badge">${group.length} athlete${group.length !== 1 ? 's' : ''}</span>
        </div>
      `;
      
      group.forEach(athlete => {
        const fullName = `${athlete.f_name} ${athlete.m_name ? athlete.m_name + ' ' : ''}${athlete.l_name}`;
        const age = athlete.date_birth ? calculateAge(athlete.date_birth) : 'N/A';
        
        html += `
          <div class="athlete-card">
            <div class="athlete-card-content">
              <div class="athlete-card-header">
                <div>
                  <div class="athlete-card-name">
                    ${escapeHtml(fullName)}
                    ${athlete.is_captain == 1 ? '<span class="badge" style="background: #fef3c7; color: #92400e; margin-left: 6px;">⭐ Captain</span>' : ''}
                  </div>
                </div>
                <span class="badge badge-active">✅ Approved</span>
              </div>
              <div class="athlete-card-meta">
                <div><strong>Team:</strong> ${escapeHtml(athlete.team_name)}</div>
                <div><strong>Coach:</strong> ${escapeHtml(athlete.coach_name || 'Not assigned')}</div>
                ${athlete.college_code ? `<div><strong>College:</strong> ${escapeHtml(athlete.college_code)}</div>` : ''}
                ${athlete.course ? `<div><strong>Course:</strong> ${escapeHtml(athlete.course)}</div>` : ''}
                <div><strong>Age:</strong> ${age} ${athlete.date_birth ? `(Born: ${athlete.date_birth})` : ''}</div>
              </div>
              <div class="athlete-card-actions">
                <button class="btn btn-sm btn-danger" onclick="showDisqualifyModal(${athlete.team_ath_id}, '${escapeHtml(fullName).replace(/'/g, "\\'")}')">
                  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                  </svg>
                  Disqualify
                </button>
                <button class="btn btn-sm btn-secondary" onclick="viewAthleteDetails(${athlete.person_id})">
                  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                  </svg>
                  View Details
                </button>
              </div>
            </div>
          </div>
        `;
      });
    });
    
    list.innerHTML = html;
    
  } catch (err) {
    console.error('Error loading approved athletes:', err);
    list.innerHTML = '<div class="empty-state" style="color: #dc2626;">Error loading approved athletes</div>';
  }
}

function toggleSelectAll(type) {
  const checkbox = document.getElementById('selectAllPending');
  const checkboxes = document.querySelectorAll('.pending-athlete-checkbox');
  
  checkboxes.forEach(cb => {
    cb.checked = checkbox.checked;
  });
  
  updateBulkApproveButton();
}

function updateBulkApproveButton() {
  const checkboxes = document.querySelectorAll('.pending-athlete-checkbox:checked');
  const bulkBtn = document.getElementById('bulkApproveBtn');
  
  if (bulkBtn) {
    bulkBtn.disabled = checkboxes.length === 0;
    if (checkboxes.length > 0) {
      bulkBtn.innerHTML = `
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
        </svg>
        Approve Selected (${checkboxes.length})
      `;
    } else {
      bulkBtn.innerHTML = `
        <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
          <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425a.267.267 0 0 1 .02-.022z"/>
        </svg>
        Approve Selected
      `;
    }
  }
}

async function approveAthlete(teamAthId) {
  if (!confirm('Are you sure you want to approve this athlete?')) {
    return;
  }
  
  try {
    const formData = new URLSearchParams();
    formData.set('team_ath_id', teamAthId);
    
    const result = await fetchJSON('approve_athlete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    if (result.ok) {
      alert('Athlete approved successfully!');
      loadPendingAthletes();
      loadApprovedAthletes();
    } else {
      alert('Error: ' + (result.message || 'Failed to approve athlete'));
    }
  } catch (err) {
    console.error('Error approving athlete:', err);
    alert('Error approving athlete');
  }
}

async function bulkApproveAthletes() {
  const checkboxes = document.querySelectorAll('.pending-athlete-checkbox:checked');
  
  if (checkboxes.length === 0) {
    alert('Please select at least one athlete to approve');
    return;
  }
  
  if (!confirm(`Are you sure you want to approve ${checkboxes.length} athlete(s)?`)) {
    return;
  }
  
  try {
    const ids = Array.from(checkboxes).map(cb => cb.value);
    const formData = new URLSearchParams();
    formData.set('team_ath_ids', ids.join(','));
    
    const result = await fetchJSON('bulk_approve_athletes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    if (result.ok) {
      alert(result.message || 'Athletes approved successfully!');
      document.getElementById('selectAllPending').checked = false;
      loadPendingAthletes();
      loadApprovedAthletes();
    } else {
      alert('Error: ' + (result.message || 'Failed to approve athletes'));
    }
  } catch (err) {
    console.error('Error bulk approving athletes:', err);
    alert('Error approving athletes');
  }
}

function showDisqualifyModal(teamAthId, athleteName) {
  const html = `
    <div class="modal active" id="disqualifyModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Disqualify Athlete</h2>
          <span class="close-modal" onclick="closeModal('disqualifyModal')">&times;</span>
        </div>
        <div class="modal-body">
          <p style="margin-bottom: 16px;">Are you sure you want to disqualify <strong>${athleteName}</strong>?</p>
          <form id="disqualifyForm" class="form" onsubmit="submitDisqualify(event, ${teamAthId})">
            <div class="form-group">
              <label class="form-label">Reason for Disqualification *</label>
              <textarea name="reason" class="form-control" rows="4" required 
                        placeholder="e.g., Incomplete documents, Age requirement not met, etc."></textarea>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('disqualifyModal')">Cancel</button>
              <button type="submit" class="btn btn-danger">Disqualify</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  `;
  
  document.getElementById('modalContainer').innerHTML = html;
}

async function submitDisqualify(e, teamAthId) {
  e.preventDefault();
  
  const form = e.target;
  const reason = form.reason.value;
  
  try {
    const formData = new URLSearchParams();
    formData.set('team_ath_id', teamAthId);
    formData.set('reason', reason);
    
    const result = await fetchJSON('disqualify_athlete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    if (result.ok) {
      alert('Athlete disqualified successfully');
      closeModal('disqualifyModal');
      
      if (currentApprovalTab === 'pending') {
        loadPendingAthletes();
      } else {
        loadApprovedAthletes();
      }
    } else {
      alert('Error: ' + (result.message || 'Failed to disqualify athlete'));
    }
  } catch (err) {
    console.error('Error disqualifying athlete:', err);
    alert('Error disqualifying athlete');
  }
}

async function viewAthleteDetails(personId) {
  try {
    const players = await fetchJSON('get_all_players');
    const athlete = players.find(p => p.person_id == personId);
    
    if (!athlete) {
      alert('Athlete not found');
      return;
    }
    
    const fullName = `${athlete.f_name} ${athlete.m_name ? athlete.m_name + ' ' : ''}${athlete.l_name}`;
    const age = athlete.date_birth ? calculateAge(athlete.date_birth) : 'N/A';
    
    const html = `
      <div class="modal active" id="athleteDetailsModal">
        <div class="modal-content">
          <div class="modal-header">
            <h2>Athlete Details</h2>
            <span class="close-modal" onclick="closeModal('athleteDetailsModal')">&times;</span>
          </div>
          <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 13px;">
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Full Name:</div>
                <div>${escapeHtml(fullName)}</div>
              </div>
              
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Age:</div>
                <div>${age}</div>
              </div>
              
              ${athlete.date_birth ? `
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Date of Birth:</div>
                <div>${athlete.date_birth}</div>
              </div>
              ` : ''}
              
              ${athlete.college_name ? `
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">College:</div>
                <div>${escapeHtml(athlete.college_name)}</div>
              </div>
              ` : ''}
              
              ${athlete.course ? `
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Course:</div>
                <div>${escapeHtml(athlete.course)}</div>
              </div>
              ` : ''}
              
              ${athlete.blood_type ? `
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Blood Type:</div>
                <div>${athlete.blood_type}</div>
              </div>
              ` : ''}
              
              ${athlete.title ? `
              <div>
                <div style="font-weight: 600; margin-bottom: 4px;">Title:</div>
                <div>${escapeHtml(athlete.title)}</div>
              </div>
              ` : ''}
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('athleteDetailsModal')">Close</button>
          </div>
        </div>
      </div>
    `;
    
    document.getElementById('modalContainer').innerHTML = html;
  } catch (err) {
    console.error('Error loading athlete details:', err);
    alert('Error loading athlete details');
  }
}

function calculateAge(dateOfBirth) {
  const today = new Date();
  const birthDate = new Date(dateOfBirth);
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  
  return age;
}

// Event listener for approval tournament filter
const approvalFilter = document.getElementById('approvalFilterTournament');
if (approvalFilter) {
  approvalFilter.addEventListener('change', () => {
    if (currentApprovalTab === 'pending') {
      loadPendingAthletes();
    } else {
      loadApprovedAthletes();
    }
  });
}



// ==========================================
// UTILITY FUNCTIONS
// ==========================================

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.remove();
  }
  $('#modalContainer').innerHTML = '';
}

// ==========================================
// GLOBAL EXPORTS
// ==========================================

window.toggleTeamForSport = toggleTeamForSport;
window.saveTeamsForSport = saveTeamsForSport;
window.viewTeamDetails = viewTeamDetails;
window.closeTeamDetailsModal = closeTeamDetailsModal;
window.deleteScore = deleteScore;
window.editVenue = editVenue;
window.toggleVenue = toggleVenue;
window.showVenueModal = showVenueModal;
window.openPrintModal = openPrintModal;
window.closePrintModal = closePrintModal;
window.generatePrintReport = generatePrintReport;
window.closePreview = closePreview;
window.showScheduleMatchModal = showScheduleMatchModal;
window.closeScheduleMatchModal = closeScheduleMatchModal;
window.saveScheduleMatch = saveScheduleMatch;
window.onScheduleTournamentChange = onScheduleTournamentChange;
window.onScheduleSportChange = onScheduleSportChange;
window.editMatch = editMatch;
window.closeEditMatchModal = closeEditMatchModal;
window.saveEditMatch = saveEditMatch;
window.deleteMatch = deleteMatch;
window.showScheduleMatchModal = showScheduleMatchModal;
window.onScheduleTournamentChange = onScheduleTournamentChange;
window.onScheduleSportChange = onScheduleSportChange;
window.saveScheduleMatch = saveScheduleMatch;
// Export functions to window
window.switchApprovalTab = switchApprovalTab;
window.loadPendingAthletes = loadPendingAthletes;
window.loadApprovedAthletes = loadApprovedAthletes;
window.toggleSelectAll = toggleSelectAll;
window.updateBulkApproveButton = updateBulkApproveButton;
window.approveAthlete = approveAthlete;
window.bulkApproveAthletes = bulkApproveAthletes;
window.showDisqualifyModal = showDisqualifyModal;
window.submitDisqualify = submitDisqualify;
window.viewAthleteDetails = viewAthleteDetails;
window.calculateAge = calculateAge;