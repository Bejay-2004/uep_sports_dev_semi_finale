const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

let selectedSports = new Set();
let currentTournamentForSports = null;

// Tab switching
function setTab(tabId){
  $$('.tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
  $$('.panel').forEach(p => p.classList.toggle('active', p.id === tabId));
}

$$('.tab').forEach(btn => btn.addEventListener('click', () => {
  setTab(btn.dataset.tab);
}));

// API Helper
async function fetchJSON(action, opts = {}) {
  try {
    const url = `api.php?action=${encodeURIComponent(action)}`;
    console.log('🔍 Fetching:', url);
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

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

// ==========================================
// MODULE 1: TOURNAMENT CREATION
// ==========================================

async function loadTournaments(){
  try {
    const data = await fetchJSON('tournaments');
    const tbody = $('#tournamentsTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5">No tournaments found</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(t => `
      <tr>
        <td>${escapeHtml(t.tour_name)}</td>
        <td>${escapeHtml(t.school_year)}</td>
        <td>${t.tour_date}</td>
        <td><span class="badge ${t.is_active == 1 ? 'active' : 'inactive'}">
          ${t.is_active == 1 ? 'Active' : 'Inactive'}
        </span></td>
        <td>
          <button class="btn small ${t.is_active == 1 ? 'warning' : 'success'}" 
            onclick="toggleTournament(${t.tour_id}, ${t.is_active})">
            ${t.is_active == 1 ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
    
    // Populate tournament dropdowns
    populateTournamentDropdowns(data);
    
    console.log('✅ Tournaments loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTournaments error:', err);
    $('#tournamentsTable tbody').innerHTML = '<tr><td colspan="5" style="color:red">Error loading</td></tr>';
  }
}

function populateTournamentDropdowns(tournaments){
  const active = tournaments.filter(t => t.is_active == 1);
  const opts = active.map(t => 
    `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${t.school_year})</option>`
  ).join('');
  
  $('#sportsTourSelect').innerHTML = '<option value="">-- Select Tournament --</option>' + opts;
  $('#matchTourSelect').innerHTML = '<option value="">-- Select Tournament --</option>' + opts;
  $('#matchesFilterTour').innerHTML = '<option value="">All Tournaments</option>' + opts;
  $('#standingsTourSelect').innerHTML = '<option value="">-- Select Tournament --</option>' + opts;
  $('#medalsTourSelect').innerHTML = '<option value="">-- Select Tournament --</option>' + opts;
}

$('#tournamentForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#tournamentMsg');
  msg.textContent = "Creating tournament...";
  msg.style.color = '#6b7280';

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

    msg.textContent = data.message || 'Tournament created!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadTournaments();
    }
  } catch (err) {
    console.error('❌ Tournament create error:', err);
    msg.textContent = 'Error creating tournament';
    msg.style.color = 'red';
  }
});

async function toggleTournament(tourId, currentStatus){
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

// ==========================================
// MODULE 1: SPORTS SELECTION
// ==========================================

async function loadAllSports(){
  try {
    const data = await fetchJSON('all_sports');
    return Array.isArray(data) ? data : [];
  } catch (err) {
    console.error('❌ loadAllSports error:', err);
    return [];
  }
}

$('#sportsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  currentTournamentForSports = tourId;
  
  if (!tourId) {
    $('#sportsSelectionArea').style.display = 'none';
    $('#tournamentSportsList').innerHTML = '';
    return;
  }
  
  // Load sports already in this tournament
  await loadTournamentSports(tourId);
  
  // Load all available sports
  const allSports = await loadAllSports();
  const tourSports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
  const tourSportIds = new Set(tourSports.map(s => s.sports_id));
  
  if (tourSportIds.size > 0) {
    // Tournament already has sports, show them only
    $('#sportsSelectionArea').style.display = 'none';
  } else {
    // Allow sport selection
    $('#sportsSelectionArea').style.display = 'block';
    selectedSports.clear();
    
    $('#sportsList').innerHTML = allSports.map(s => `
      <div class="sport-card" data-sport-id="${s.sports_id}" onclick="toggleSport(${s.sports_id})">
        <div class="sport-name">${escapeHtml(s.sports_name)}</div>
        <div class="sport-check">✓</div>
      </div>
    `).join('');
  }
});

function toggleSport(sportId){
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
    alert('Please select at least one sport');
    return;
  }
  
  if (!currentTournamentForSports) {
    alert('No tournament selected');
    return;
  }
  
  const msg = $('#sportsMsg');
  msg.textContent = 'Adding sports to tournament...';
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

    msg.textContent = data.message || 'Sports added!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      $('#sportsSelectionArea').style.display = 'none';
      await loadTournamentSports(currentTournamentForSports);
    }
  } catch (err) {
    console.error('❌ Add sports error:', err);
    msg.textContent = 'Error adding sports';
    msg.style.color = 'red';
  }
});

async function loadTournamentSports(tourId){
  try {
    const data = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
    const list = $('#tournamentSportsList');
    
    if (!Array.isArray(data) || data.length === 0) {
      list.innerHTML = '<p style="color:#6b7280;">No sports selected yet for this tournament</p>';
      return;
    }
    
    list.innerHTML = '<div class="sports-grid">' + data.map(s => `
      <div class="sport-card selected">
        <div class="sport-name">${escapeHtml(s.sports_name)}</div>
        <div class="sport-check">✓</div>
      </div>
    `).join('') + '</div>';
    
  } catch (err) {
    console.error('❌ loadTournamentSports error:', err);
    $('#tournamentSportsList').innerHTML = '<p style="color:red;">Error loading sports</p>';
  }
}

window.toggleSport = toggleSport;
window.toggleTournament = toggleTournament;
// ==========================================
// MODULE 2: MATCHES & SCHEDULE
// ==========================================

$('#matchTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  if (!tourId) {
    $('#matchSportSelect').innerHTML = '<option value="">-- Select Sport --</option>';
    return;
  }
  
  // Load sports for this tournament
  try {
    const sports = await fetchJSON(`tournament_sports&tour_id=${tourId}`);
    $('#matchSportSelect').innerHTML = '<option value="">-- Select Sport --</option>' +
      sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading tournament sports:', err);
  }
});

$('#matchSportSelect')?.addEventListener('change', async (e) => {
  const sportId = e.target.value;
  if (!sportId) {
    $('#team_a_id').innerHTML = '<option value="">-- Select Team A --</option>';
    $('#team_b_id').innerHTML = '<option value="">-- Select Team B --</option>';
    return;
  }
  
  // Load teams for this sport
  try {
    const teams = await fetchJSON(`sport_teams&sports_id=${sportId}`);
    const opts = teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
    $('#team_a_id').innerHTML = '<option value="">-- Select Team A --</option>' + opts;
    $('#team_b_id').innerHTML = '<option value="">-- Select Team B --</option>' + opts;
  } catch (err) {
    console.error('Error loading teams:', err);
  }
});

async function loadVenues(){
  try {
    const data = await fetchJSON('venues');
    const opts = data.map(v => 
      `<option value="${v.venue_id}">${escapeHtml(v.venue_name)}${v.venue_building ? ' - ' + v.venue_building : ''}</option>`
    ).join('');
    $('#match_venue').innerHTML = '<option value="">-- Select Venue --</option>' + opts;
  } catch (err) {
    console.error('Error loading venues:', err);
  }
}

$('#matchForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#matchMsg');
  msg.textContent = "Creating match...";

  try {
    const formData = new URLSearchParams();
    formData.set('tour_id', $('#matchTourSelect').value);
    formData.set('sports_id', $('#matchSportSelect').value);
    formData.set('sports_type', $('#sports_type').value);
    formData.set('match_type', $('#match_type').value);
    formData.set('team_a_id', $('#team_a_id').value);
    formData.set('team_b_id', $('#team_b_id').value);
    formData.set('sked_date', $('#match_date').value);
    formData.set('sked_time', $('#match_time').value);
    formData.set('venue_id', $('#match_venue').value);

    const data = await fetchJSON('create_match', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Match created!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadMatches();
    }
  } catch (err) {
    console.error('❌ Match create error:', err);
    msg.textContent = 'Error creating match';
    msg.style.color = 'red';
  }
});

async function loadMatches(filterTourId = null){
  try {
    let url = 'matches';
    if (filterTourId) url += `&tour_id=${filterTourId}`;
    
    const data = await fetchJSON(url);
    const tbody = $('#matchesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8">No matches found</td></tr>';
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
    
    // Populate match dropdowns for scoring
    populateMatchDropdowns(data);
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
    $('#matchesTable tbody').innerHTML = '<tr><td colspan="8" style="color:red">Error loading</td></tr>';
  }
}

$('#matchesFilterTour')?.addEventListener('change', (e) => {
  const tourId = e.target.value || null;
  loadMatches(tourId);
});

function populateMatchDropdowns(matches){
  const withoutWinner = matches.filter(m => !m.winner_id);
  const opts = withoutWinner.map(m => 
    `<option value="${m.match_id}" data-team-a="${m.team_a_id}" data-team-b="${m.team_b_id}" data-team-a-name="${escapeHtml(m.team_a_name)}" data-team-b-name="${escapeHtml(m.team_b_name)}">
      ${escapeHtml(m.sports_name)}: ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)} (${m.sked_date})
    </option>`
  ).join('');
  
  $('#scoreMatchSelect').innerHTML = '<option value="">-- Select Match --</option>' + opts;
  $('#winnerMatchSelect').innerHTML = '<option value="">-- Select Match --</option>' + opts;
}

// ==========================================
// MODULE 3: SCORING
// ==========================================

$('#scoreMatchSelect')?.addEventListener('change', (e) => {
  const option = e.target.selectedOptions[0];
  if (!option || !option.value) {
    $('#scoreCompetitorSelect').innerHTML = '<option value="">-- Select Competitor --</option>';
    return;
  }
  
  const teamAId = option.dataset.teamA;
  const teamBId = option.dataset.teamB;
  const teamAName = option.dataset.teamAName;
  const teamBName = option.dataset.teamBName;
  
  $('#scoreCompetitorSelect').innerHTML = `
    <option value="">-- Select Competitor --</option>
    <option value="${teamAId}">${teamAName}</option>
    <option value="${teamBId}">${teamBName}</option>
  `;
});

$('#scoreForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#scoreMsg');
  msg.textContent = "Saving score...";

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

async function loadScores(){
  try {
    const data = await fetchJSON('scores');
    const tbody = $('#scoresTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">No scores yet</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(s => `
      <tr>
        <td>${escapeHtml(s.match_info)}</td>
        <td>${escapeHtml(s.team_name)}</td>
        <td><strong>${escapeHtml(s.score_value)}</strong></td>
        <td>${s.rank_no}</td>
        <td>${s.medal_type ? `<span class="badge ${s.medal_type}">${s.medal_type}</span>` : '-'}</td>
        <td>
          <button class="btn small danger" onclick="deleteScore(${s.score_id})">Delete</button>
        </td>
      </tr>
    `).join('');
    
    console.log('✅ Scores loaded:', data.length);
  } catch (err) {
    console.error('❌ loadScores error:', err);
    $('#scoresTable tbody').innerHTML = '<tr><td colspan="6" style="color:red">Error loading</td></tr>';
  }
}

async function deleteScore(scoreId){
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

// Declare Winner
$('#winnerMatchSelect')?.addEventListener('change', (e) => {
  const option = e.target.selectedOptions[0];
  if (!option || !option.value) {
    $('#winner_team_id').innerHTML = '<option value="">-- Select Winner --</option>';
    return;
  }
  
  const teamAId = option.dataset.teamA;
  const teamBId = option.dataset.teamB;
  const teamAName = option.dataset.teamAName;
  const teamBName = option.dataset.teamBName;
  
  $('#winner_team_id').innerHTML = `
    <option value="">-- Select Winner --</option>
    <option value="${teamAId}">${teamAName}</option>
    <option value="${teamBId}">${teamBName}</option>
  `;
});

$('#winnerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#winnerMsg');
  msg.textContent = "Declaring winner...";

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
// MODULE 3: STANDINGS & MEDALS
// ==========================================

$('#standingsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  if (!tourId) {
    $('#standingsTable tbody').innerHTML = '<tr><td colspan="9">Select a tournament</td></tr>';
    return;
  }
  
  try {
    const data = await fetchJSON(`standings&tour_id=${tourId}`);
    const tbody = $('#standingsTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9">No standings data</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(s => `
      <tr>
        <td>${escapeHtml(s.team_name)}</td>
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
    $('#standingsTable tbody').innerHTML = '<tr><td colspan="9" style="color:red">Error loading</td></tr>';
  }
});

$('#medalsTourSelect')?.addEventListener('change', async (e) => {
  const tourId = e.target.value;
  if (!tourId) {
    $('#medalsTable tbody').innerHTML = '<tr><td colspan="6">Select a tournament</td></tr>';
    return;
  }
  
  try {
    const data = await fetchJSON(`medal_tally&tour_id=${tourId}`);
    const tbody = $('#medalsTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">No medal data</td></tr>';
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
    $('#medalsTable tbody').innerHTML = '<tr><td colspan="6" style="color:red">Error loading</td></tr>';
  }
});

// ==========================================
// MODULE 2: VENUES
// ==========================================

async function loadVenuesTable(){
  try {
    const data = await fetchJSON('venues');
    const tbody = $('#venuesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5">No venues found</td></tr>';
      return;
    }
    
    tbody.innerHTML = data.map(v => `
      <tr>
        <td>${escapeHtml(v.venue_name)}</td>
        <td>${escapeHtml(v.venue_building || '-')}</td>
        <td>${escapeHtml(v.venue_room || '-')}</td>
        <td><span class="badge ${v.is_active == 1 ? 'active' : 'inactive'}">
          ${v.is_active == 1 ? 'Active' : 'Inactive'}
        </span></td>
        <td>
          <button class="btn small" onclick="editVenue(${v.venue_id})">Edit</button>
          <button class="btn small ${v.is_active == 1 ? 'warning' : 'success'}" 
            onclick="toggleVenue(${v.venue_id}, ${v.is_active})">
            ${v.is_active == 1 ? 'Deactivate' : 'Activate'}
          </button>
        </td>
      </tr>
    `).join('');
    
    console.log('✅ Venues loaded:', data.length);
  } catch (err) {
    console.error('❌ loadVenues error:', err);
    $('#venuesTable tbody').innerHTML = '<tr><td colspan="5" style="color:red">Error loading</td></tr>';
  }
}

$('#venueForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#venueMsg');
  const venueId = $('#venue_id').value;
  msg.textContent = venueId ? "Updating venue..." : "Creating venue...";

  try {
    const formData = new URLSearchParams();
    if (venueId) formData.set('venue_id', venueId);
    formData.set('venue_name', $('#venue_name').value);
    formData.set('venue_building', $('#venue_building').value);
    formData.set('venue_room', $('#venue_room').value);

    const data = await fetchJSON(venueId ? 'update_venue' : 'create_venue', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || 'Venue saved!';
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      $('#venue_id').value = '';
      await loadVenuesTable();
      await loadVenues(); // Refresh dropdown
    }
  } catch (err) {
    console.error('❌ Venue save error:', err);
    msg.textContent = 'Error saving venue';
    msg.style.color = 'red';
  }
});

async function editVenue(venueId){
  try {
    const venues = await fetchJSON('venues');
    const venue = venues.find(v => v.venue_id == venueId);
    if (!venue) return;
    
    $('#venue_id').value = venue.venue_id;
    $('#venue_name').value = venue.venue_name;
    $('#venue_building').value = venue.venue_building || '';
    $('#venue_room').value = venue.venue_room || '';
    
    // Scroll to form
    $('#venueForm').scrollIntoView({behavior: 'smooth'});
  } catch (err) {
    console.error('Error loading venue:', err);
  }
}

async function toggleVenue(venueId, currentStatus){
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
      await loadVenues();
    }
  } catch (err) {
    console.error('❌ Toggle venue error:', err);
  }
}

$('#clearVenueBtn')?.addEventListener('click', () => {
  $('#venueForm').reset();
  $('#venue_id').value = '';
  $('#venueMsg').textContent = '';
});

// Global functions
window.deleteScore = deleteScore;
window.editVenue = editVenue;
window.toggleVenue = toggleVenue;

// ==========================================
// INITIAL LOAD
// ==========================================

(async function init(){
  console.log('🚀 Initializing Tournament Manager dashboard...');
  
  try {
    await Promise.all([
      loadTournaments(),
      loadMatches(),
      loadScores(),
      loadVenuesTable(),
      loadVenues()
    ]);
    console.log('✅ All data loaded successfully');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();