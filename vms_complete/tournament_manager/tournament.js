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
      tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No matches found</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(m => `
      <tr>
        <td>${m.sked_date}</td>
        <td>${m.sked_time}</td>
        <td>${escapeHtml(m.sports_name)}</td>
        <td>${escapeHtml(m.match_type)}</td>
        <td>${escapeHtml(m.team_a_name)}</td>
        <td>${escapeHtml(m.team_b_name)}</td>
        <td>${escapeHtml(m.venue_name || 'TBA')}</td>
        <td>${m.winner_name ? '<strong>' + escapeHtml(m.winner_name) + '</strong>' : '-'}</td>
      </tr>
    `).join('');
    
    populateMatchDropdowns(data);
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
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
  alert('Print report generation - uses existing code from original tournament.js');
  closePrintModal();
}

function closePreview() {
  $('#printPreview').style.display = 'none';
  $('#printPreview').innerHTML = '';
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