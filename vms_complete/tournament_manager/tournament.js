// Tournament Manager Dashboard - Updated for Sidebar Design

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

let selectedSports = new Set();
let currentTournamentForSports = null;
let currentTournamentForTeams = null;
let sportTeamSelections = {};

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
    'sports-select': 'Select Sports',
    'team-select': 'Select Teams',
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
    case 'overview': loadOverview(); break;
    case 'tournaments': loadTournaments(); break;
    case 'matches': loadMatches(); break;
    case 'scoring': loadScores(); break;
    case 'venues': loadVenuesTable(); break;
  }
}

// ==========================================
// API HELPER
// ==========================================

async function fetchJSON(action, opts = {}) {
  try {
    let url;
    if (action.includes('&') || action.includes('?')) {
      const parts = action.split('&');
      const mainAction = parts[0];
      const params = parts.slice(1).join('&');
      url = params ? `api.php?action=${mainAction}&${params}` : `api.php?action=${mainAction}`;
    } else {
      url = `api.php?action=${encodeURIComponent(action)}`;
    }
    
    console.log('📡 Fetching:', url);
    const res = await fetch(url, opts);
    
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
      content.innerHTML = '<div class="empty-state">No tournaments found. Click "Create Tournament" to add one.</div>';
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
    '#sportsTourSelect',
    '#teamsTourSelect',
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
  
  if (!tourId) {
    if (teamsArea) teamsArea.style.display = 'none';
    return;
  }
  
  try {
    const tourSports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
    
    if (!tourSports || tourSports.length === 0) {
      if (container) {
        container.innerHTML = '<p style="color:#dc2626;padding:16px;background:#fee2e2;border-radius:6px;margin-top:16px;">⚠️ No sports selected for this tournament yet. Please go to "Select Sports" tab first.</p>';
      }
      if (teamsArea) teamsArea.style.display = 'block';
      return;
    }
    
    const allTeams = await loadAllTeams();
    
    let html = '<div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:6px;margin-bottom:20px;">';
    html += `<strong style="color:#166534;">✓ ${tourSports.length} sport(s) selected for this tournament</strong>`;
    html += '</div>';
    
    for (const sport of tourSports) {
      const registeredTeams = await fetchJSON(`tournament_sport_teams&tour_id=${tourId}&sports_id=${sport.sports_id}`);
      const registeredTeamIds = new Set((registeredTeams || []).map(t => t.team_id));
      
      sportTeamSelections[sport.sports_id] = registeredTeamIds;
      
      html += `
        <div class="card" style="margin-bottom:20px;">
          <h3 class="card-title">${escapeHtml(sport.sports_name)}</h3>
          <p style="font-size:12px;color:#6b7280;margin-bottom:12px;">
            ${sport.team_individual === 'team' ? '👥 Team Sport' : '👤 Individual Sport'} • 
            ${sport.men_women || 'Co-ed'}
          </p>
          
          <div class="sports-grid" id="teamGrid_${sport.sports_id}">
            ${allTeams.map(team => {
              const isSelected = registeredTeamIds.has(team.team_id);
              return `
                <div class="sport-card ${isSelected ? 'selected' : ''}" 
                     onclick="toggleTeamForSport(${sport.sports_id}, ${team.team_id}, this)">
                  <div class="sport-name">${escapeHtml(team.team_name)}</div>
                  <div class="sport-check">✓</div>
                </div>
              `;
            }).join('')}
          </div>
          
          <button class="btn btn-success" style="margin-top:12px;" 
                  onclick="saveTeamsForSport(${tourId}, ${sport.sports_id})">
            Save Teams for ${escapeHtml(sport.sports_name)}
          </button>
          
          <div id="teamRegisteredList_${sport.sports_id}" style="margin-top:16px;">
            ${registeredTeams.length > 0 ? `
              <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;">Registered Teams (${registeredTeams.length})</h4>
              <table class="table">
                <thead>
                  <tr>
                    <th>Team</th>
                    <th>Coach</th>
                    <th>Asst Coach</th>
                    <th>Players</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  ${registeredTeams.map(t => `
                    <tr>
                      <td><strong>${escapeHtml(t.team_name)}</strong></td>
                      <td>${t.coach_name || '<em style="color:#9ca3af;">Not assigned</em>'}</td>
                      <td>${t.asst_coach_name || '<em style="color:#9ca3af;">Not assigned</em>'}</td>
                      <td><span class="badge">${t.num_players || 0} players</span></td>
                      <td>
                        <button class="btn btn-sm" onclick="viewTeamDetails(${tourId}, ${sport.sports_id}, ${t.team_id}, '${escapeHtml(t.team_name)}')">
                          View Details
                        </button>
                      </td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            ` : '<p style="color:#9ca3af;font-size:13px;">No teams registered yet. Select teams above.</p>'}
          </div>
        </div>
      `;
    }
    
    if (container) container.innerHTML = html;
    if (teamsArea) teamsArea.style.display = 'block';
    
  } catch (err) {
    console.error('❌ Load teams error:', err);
  }
});

async function loadAllTeams() {
  try {
    const data = await fetchJSON('available_teams_for_sport&sports_id=1');
    return Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('❌ loadAllTeams error:', err);
    return [];
  }
}

function toggleTeamForSport(sportsId, teamId, element) {
  element.classList.toggle('selected');
  
  if (!sportTeamSelections[sportsId]) {
    sportTeamSelections[sportsId] = new Set();
  }
  
  if (sportTeamSelections[sportsId].has(teamId)) {
    sportTeamSelections[sportsId].delete(teamId);
  } else {
    sportTeamSelections[sportsId].add(teamId);
  }
}

async function saveTeamsForSport(tourId, sportsId) {
  try {
    const selectedTeamIds = Array.from(sportTeamSelections[sportsId] || []);
    
    const formData = new URLSearchParams();
    formData.set('tour_id', tourId);
    formData.set('sports_id', sportsId);
    formData.set('team_ids', selectedTeamIds.join(','));
    
    const data = await fetchJSON('add_teams_to_tournament_sport', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    if (data.ok) {
      alert(data.message || 'Teams saved successfully');
      $('#teamsTourSelect').dispatchEvent(new Event('change'));
    } else {
      alert(data.message || 'Failed to save teams');
    }
  } catch (err) {
    console.error('❌ Save teams error:', err);
    alert('Error saving teams');
  }
}

async function viewTeamDetails(tourId, sportsId, teamId, teamName) {
  try {
    const players = await fetchJSON(`team_players&tour_id=${tourId}&sports_id=${sportsId}&team_id=${teamId}`);
    const coaches = await fetchJSON(`team_coaches&tour_id=${tourId}&sports_id=${sportsId}&team_id=${teamId}`);
    
    const modalHTML = `
      <div class="modal active" id="teamDetailsModal" onclick="if(event.target === this) closeTeamDetailsModal()">
        <div class="modal-content wide">
          <div class="modal-header">
            <h2>Team Details: ${escapeHtml(teamName)}</h2>
            <span class="close-modal" onclick="closeTeamDetailsModal()">×</span>
          </div>
          
          <div class="modal-body">
            <div style="margin-bottom:24px;">
              <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:#111827;">👨‍🏫 Coaching Staff</h3>
              <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;font-size:13px;">
                <div>
                  <strong style="color:#6b7280;">Head Coach:</strong><br>
                  <span>${coaches.coach_name || '<em style="color:#9ca3af;">Not assigned</em>'}</span>
                </div>
                <div>
                  <strong style="color:#6b7280;">Assistant Coach:</strong><br>
                  <span>${coaches.asst_coach_name || '<em style="color:#9ca3af;">Not assigned</em>'}</span>
                </div>
              </div>
            </div>
            
            <div>
              <h3 style="font-size:15px;font-weight:600;margin-bottom:12px;color:#111827;">
                👥 Players (${players.length})
              </h3>
              ${players.length > 0 ? `
                <table class="table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>College</th>
                      <th>Course</th>
                      <th>Role</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${players.map(p => `
                      <tr>
                        <td><strong>${escapeHtml(p.player_name)}</strong></td>
                        <td>${p.college_code || '-'}</td>
                        <td>${escapeHtml(p.course || '-')}</td>
                        <td>
                          ${p.is_captain == 1 
                            ? '<span class="badge" style="background:#fef3c7;color:#92400e;">Captain</span>' 
                            : '<span class="badge">Player</span>'}
                        </td>
                      </tr>
                    `).join('')}
                  </tbody>
                </table>
              ` : '<p style="color:#9ca3af;font-size:13px;text-align:center;padding:20px;">No players registered yet</p>'}
            </div>
          </div>
          
          <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeTeamDetailsModal()">Close</button>
          </div>
        </div>
      </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
  } catch (err) {
    console.error('❌ View team details error:', err);
    alert('Error loading team details');
  }
}

function closeTeamDetailsModal() {
  const modal = $('#teamDetailsModal');
  if (modal) modal.remove();
}

// ==========================================
// MATCHES
// ==========================================

async function loadMatches(filterTourId = null) {
  try {
    let url = 'matches';
    if (filterTourId) url += `&tour_id=${filterTourId}`;
    
    const data = await fetchJSON(url);
    const tbody = $('#matchesTable tbody');
    
    if (!tbody) return;
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No matches found</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(m => `
      <tr>
        <td>${escapeHtml(m.game_no)}</td>
        <td>${m.sked_date}</td>
        <td>${m.sked_time}</td>
        <td>${escapeHtml(m.sports_name)}</td>
        <td>${escapeHtml(m.match_type)}</td>
        <td>${escapeHtml(m.team_a_name || 'TBA')}</td>
        <td>${escapeHtml(m.team_b_name || 'TBA')}</td>
        <td>${escapeHtml(m.venue_name || 'TBA')}</td>
        <td>
          <button class="btn btn-sm" onclick="editMatch(${m.match_id})">Edit</button>
          <button class="btn btn-sm btn-danger" onclick="deleteMatch(${m.match_id})">Delete</button>
        </td>
      </tr>
    `).join('');
    
    populateMatchDropdowns(data);
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
  }
}

function showScheduleMatchModal() {
  const modalHTML = `
    <div class="modal active" id="scheduleMatchModal">
      <div class="modal-content wide">
        <div class="modal-header">
          <h3>Schedule New Match</h3>
          <button class="modal-close" onclick="closeScheduleMatchModal()">×</button>
        </div>
        <form id="scheduleMatchForm" onsubmit="saveScheduleMatch(event)">
          <div class="modal-body">
            <div class="form" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
              
              <!-- Tournament Selection -->
              <div class="form-group">
                <label class="form-label">Tournament *</label>
                <select class="form-control" id="schedule_tour_id" required onchange="onScheduleTournamentChange()">
                  <option value="">-- Select Tournament --</option>
                </select>
              </div>

              <!-- Sport Selection -->
              <div class="form-group">
                <label class="form-label">Sport *</label>
                <select class="form-control" id="schedule_sports_id" required onchange="onScheduleSportChange()">
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
                </select>
              </div>

              <!-- Umpire -->
              <div class="form-group">
                <label class="form-label">Umpire</label>
                <select class="form-control" id="schedule_umpire_id">
                  <option value="">-- Select Umpire --</option>
                </select>
              </div>

              <!-- Sports Manager -->
              <div class="form-group" style="grid-column: span 2;">
                <label class="form-label">Sports Manager</label>
                <select class="form-control" id="schedule_sports_manager_id">
                  <option value="">-- Select Sports Manager --</option>
                </select>
              </div>

              <!-- Teams Section (shown for team sports) -->
              <div id="teamsSection" style="grid-column: span 2; display:none;">
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
  
  // Load initial data
  loadScheduleMatchData();
}

async function loadScheduleMatchData() {
  try {
    // Load tournaments
    const tournaments = await fetchJSON('tournaments');
    const activeTournaments = tournaments.filter(t => t.is_active == 1);
    const tourSelect = $('#schedule_tour_id');
    tourSelect.innerHTML = '<option value="">-- Select Tournament --</option>' + 
      activeTournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`).join('');

    // Load venues
    const venues = await fetchJSON('venues');
    const activeVenues = venues.filter(v => v.is_active == 1);
    const venueSelect = $('#schedule_venue_id');
    venueSelect.innerHTML = '<option value="">-- Select Venue --</option>' + 
      activeVenues.map(v => `<option value="${v.venue_id}">${escapeHtml(v.venue_name)}</option>`).join('');

    // Load umpires
    const umpires = await fetchJSON('umpires');
    const umpireSelect = $('#schedule_umpire_id');
    umpireSelect.innerHTML = '<option value="">-- Select Umpire --</option>' + 
      umpires.map(u => `<option value="${u.person_id}">${escapeHtml(u.full_name)}</option>`).join('');

    // Load sports managers
    const managers = await fetchJSON('sports_managers');
    const managerSelect = $('#schedule_sports_manager_id');
    managerSelect.innerHTML = '<option value="">-- Select Sports Manager --</option>' + 
      managers.map(m => `<option value="${m.person_id}">${escapeHtml(m.full_name)}</option>`).join('');

  } catch (err) {
    console.error('❌ Error loading schedule match data:', err);
  }
}

async function onScheduleTournamentChange() {
  const tourId = $('#schedule_tour_id').value;
  const sportsSelect = $('#schedule_sports_id');
  
  if (!tourId) {
    sportsSelect.innerHTML = '<option value="">-- Select Sport --</option>';
    $('#teamsSection').style.display = 'none';
    return;
  }

  try {
    // Load sports for selected tournament
    const sports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
    sportsSelect.innerHTML = '<option value="">-- Select Sport --</option>' + 
      sports.map(s => `<option value="${s.sports_id}" data-type="${s.team_individual}">${escapeHtml(s.sports_name)}</option>`).join('');
  } catch (err) {
    console.error('❌ Error loading sports:', err);
  }
}

async function onScheduleSportChange() {
  const tourId = $('#schedule_tour_id').value;
  const sportsSelect = $('#schedule_sports_id');
  const selectedOption = sportsSelect.selectedOptions[0];
  const teamsSection = $('#teamsSection');
  
  if (!selectedOption || !selectedOption.value) {
    teamsSection.style.display = 'none';
    return;
  }

  const sportsType = selectedOption.dataset.type; // 'team' or 'individual'
  $('#schedule_sports_type').value = sportsType;

  if (sportsType === 'team') {
    teamsSection.style.display = 'block';
    
    // Load teams for this sport
    try {
      const teams = await fetchJSON(`tournament_teams_by_sport&tour_id=${tourId}&sports_id=${selectedOption.value}`);
      const teamASelect = $('#schedule_team_a_id');
      const teamBSelect = $('#schedule_team_b_id');
      
      const teamOptions = '<option value="">-- Select Team --</option>' + 
        teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
      
      teamASelect.innerHTML = teamOptions;
      teamBSelect.innerHTML = teamOptions;
      
      // Make teams required for team sports
      teamASelect.required = true;
      teamBSelect.required = true;
    } catch (err) {
      console.error('❌ Error loading teams:', err);
    }
  } else {
    teamsSection.style.display = 'none';
    $('#schedule_team_a_id').required = false;
    $('#schedule_team_b_id').required = false;
  }
}

async function saveScheduleMatch(event) {
  event.preventDefault();
  const msg = $('#scheduleMsg');
  msg.style.display = 'block';
  msg.textContent = 'Scheduling match...';
  msg.style.color = '#6b7280';

  try {
    const formData = new URLSearchParams();
    formData.set('tour_id', $('#schedule_tour_id').value);
    formData.set('sports_id', $('#schedule_sports_id').value);
    formData.set('game_no', $('#schedule_game_no').value);
    formData.set('sked_date', $('#schedule_sked_date').value);
    formData.set('sked_time', $('#schedule_sked_time').value);
    formData.set('venue_id', $('#schedule_venue_id').value || '0');
    formData.set('match_umpire_id', $('#schedule_umpire_id').value || '0');
    formData.set('match_sports_manager_id', $('#schedule_sports_manager_id').value || '0');
    formData.set('match_type', $('#schedule_match_type').value);
    formData.set('sports_type', $('#schedule_sports_type').value);
    formData.set('team_a_id', $('#schedule_team_a_id').value || '');
    formData.set('team_b_id', $('#schedule_team_b_id').value || '');

    const data = await fetchJSON('create_match', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Match scheduled!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      setTimeout(() => {
        closeScheduleMatchModal();
        loadMatches();
      }, 1000);
    }
  } catch (err) {
    console.error('❌ Schedule match error:', err);
    msg.textContent = 'Error scheduling match';
    msg.style.color = 'red';
  }
}

function closeScheduleMatchModal() {
  const modal = $('#scheduleMatchModal');
  if (modal) modal.remove();
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
  const opts = withoutWinner.map(m => 
    `<option value="${m.match_id}" data-team-a="${m.team_a_id}" data-team-b="${m.team_b_id}" data-team-a-name="${escapeHtml(m.team_a_name)}" data-team-b-name="${escapeHtml(m.team_b_name)}">
      ${escapeHtml(m.sports_name)}: ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)} (${m.sked_date})
    </option>`
  ).join('');
  
  const scoreMatchSelect = $('#scoreMatchSelect');
  const winnerMatchSelect = $('#winnerMatchSelect');
  
  if (scoreMatchSelect) {
    scoreMatchSelect.innerHTML = '<option value="">-- Select Match --</option>' + opts;
  }
  
  if (winnerMatchSelect) {
    winnerMatchSelect.innerHTML = '<option value="">-- Select Match --</option>' + opts;
  }
}

// ==========================================
// SCORING
// ==========================================

$('#scoreMatchSelect')?.addEventListener('change', (e) => {
  const option = e.target.selectedOptions[0];
  const scoreCompetitorSelect = $('#scoreCompetitorSelect');
  
  if (!scoreCompetitorSelect) return;
  
  if (!option || !option.value) {
    scoreCompetitorSelect.innerHTML = '<option value="">-- Select Competitor --</option>';
    return;
  }
  
  const teamAId = option.dataset.teamA;
  const teamBId = option.dataset.teamB;
  const teamAName = option.dataset.teamAName;
  const teamBName = option.dataset.teamBName;
  
  scoreCompetitorSelect.innerHTML = `
    <option value="">-- Select Competitor --</option>
    <option value="${teamAId}">${teamAName}</option>
    <option value="${teamBId}">${teamBName}</option>
  `;
});

$('#scoreForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#scoreMsg');
  msg.textContent = "Saving score...";
  msg.style.color = '#6b7280';

  try {
    const formData = new URLSearchParams();
    formData.set('match_id', $('#scoreMatchSelect').value);
    formData.set('team_id', $('#scoreCompetitorSelect').value);
    formData.set('score_value', $('#score_value').value);
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

$('#winnerMatchSelect')?.addEventListener('change', (e) => {
  const option = e.target.selectedOptions[0];
  const winnerTeamSelect = $('#winner_team_id');
  
  if (!winnerTeamSelect) return;
  
  if (!option || !option.value) {
    winnerTeamSelect.innerHTML = '<option value="">-- Select Winner --</option>';
    return;
  }
  
  const teamAId = option.dataset.teamA;
  const teamBId = option.dataset.teamB;
  const teamAName = option.dataset.teamAName;
  const teamBName = option.dataset.teamBName;
  
  winnerTeamSelect.innerHTML = `
    <option value="">-- Select Winner --</option>
    <option value="${teamAId}">${teamAName}</option>
    <option value="${teamBId}">${teamBName}</option>
  `;
});

$('#winnerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#winnerMsg');
  msg.textContent = "Declaring winner...";
  msg.style.color = '#6b7280';

  try {
    const formData = new URLSearchParams();
    formData.set('match_id', $('#winnerMatchSelect').value);
    formData.set('winner_id', $('#winner_team_id').value);

    const data = await fetchJSON('declare_winner', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Winner declared!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadMatches();
    }
  } catch (err) {
    console.error('❌ Declare winner error:', err);
    msg.textContent = 'Error declaring winner';
    msg.style.color = 'red';
  }
});

// ==========================================
// STANDINGS & MEDALS
// ==========================================

$('#standingsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const tbody = $('#standingsTable tbody');
  
  if (!tbody) return;
  
  if (!tourId) {
    tbody.innerHTML = '<tr><td colspan="9" class="empty-state">Select a tournament</td></tr>';
    return;
  }
  
  try {
    const data = await fetchJSON(`standings&tour_id=${tourId}`);
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No standings data</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(s => `
      <tr>
        <td><strong>${escapeHtml(s.team_name)}</strong></td>
        <td>${escapeHtml(s.sports_name)}</td>
        <td>${s.no_games_played || 0}</td>
        <td>${s.no_win || 0}</td>
        <td>${s.no_loss || 0}</td>
        <td>${s.no_draw || 0}</td>
        <td>${s.no_gold || 0}</td>
        <td>${s.no_silver || 0}</td>
        <td>${s.no_bronze || 0}</td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    tbody.innerHTML = '<tr><td colspan="9" style="color:red" class="empty-state">Error loading standings</td></tr>';
  }
});

$('#medalsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  const tbody = $('#medalsTable tbody');
  
  if (!tbody) return;
  
  if (!tourId) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Select a tournament</td></tr>';
    return;
  }
  
  try {
    const data = await fetchJSON(`medal_tally&tour_id=${tourId}`);
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No medal data</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map((m, idx) => `
      <tr>
        <td><strong>${idx + 1}</strong></td>
        <td><strong>${escapeHtml(m.team_name)}</strong></td>
        <td>${m.gold || 0}</td>
        <td>${m.silver || 0}</td>
        <td>${m.bronze || 0}</td>
        <td><strong>${(m.gold || 0) + (m.silver || 0) + (m.bronze || 0)}</strong></td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('❌ loadMedals error:', err);
    tbody.innerHTML = '<tr><td colspan="6" style="color:red" class="empty-state">Error loading medals</td></tr>';
  }
});

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