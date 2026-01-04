const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function setTab(tabId){
  $$('.tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
  $$('.panel').forEach(p => p.classList.toggle('active', p.id === tabId));
}

$$('.tab').forEach(btn => btn.addEventListener('click', () => {
  setTab(btn.dataset.tab);
}));

async function fetchJSON(action, opts = {}) {
  try {
    const url = `api.php?action=${encodeURIComponent(action)}`;
    console.log('🔍 Fetching:', url);
    const res = await fetch(url, opts);
    
    if (!res.ok) {
      console.error('❌ HTTP error:', res.status, res.statusText);
      const text = await res.text();
      console.error('Response:', text);
      throw new Error(`HTTP ${res.status}: ${res.statusText}`);
    }
    
    const data = await res.json();
    console.log('✅ Response for', action, ':', data);
    return data;
  } catch (err) {
    console.error('❌ fetchJSON error:', err);
    throw err;
  }
}

function renderRows(tbody, rowsHtml){
  tbody.innerHTML = rowsHtml || `<tr><td colspan="20">No data found.</td></tr>`;
}

/* PLAYERS */
async function loadPlayers(){
  try {
    const data = await fetchJSON('players');
    const tbody = $('#playersTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No players data');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td>${escapeHtml(r.player_name)}</td>
        <td>${escapeHtml(r.team_name)}</td>
        <td>${r.is_captain == 1 ? 'Yes' : 'No'}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    setupSearch('#playersSearch', '#playersTable');
    console.log('✅ Players loaded:', data.length);
  } catch (err) {
    console.error('❌ loadPlayers error:', err);
    $('#playersTable tbody').innerHTML = '<tr><td colspan="3" style="color:red">Error loading players. Check console.</td></tr>';
  }
}

/* TEAMS */
async function loadTeams(){
  try {
    const data = await fetchJSON('teams');
    const tbody = $('#teamsTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No teams data');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td>${escapeHtml(r.team_name)}</td>
        <td>${escapeHtml(String(r.tour_id ?? ''))}</td>
        <td>${escapeHtml(r.coach_name ?? '')}</td>
        <td>${escapeHtml(r.asst_coach_name ?? '')}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    setupSearch('#teamsSearch', '#teamsTable');
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeams error:', err);
    $('#teamsTable tbody').innerHTML = '<tr><td colspan="4" style="color:red">Error loading teams. Check console.</td></tr>';
  }
}

/* STANDINGS */
async function loadStandings(){
  try {
    const data = await fetchJSON('standings');
    const tbody = $('#standingsTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No standings data');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td>${escapeHtml(r.tour_name)}</td>
        <td>${escapeHtml(r.team_name)}</td>
        <td>${num(r.no_games_played)}</td>
        <td>${num(r.no_win)}</td>
        <td>${num(r.no_loss)}</td>
        <td>${num(r.no_draw)}</td>
        <td>${num(r.no_gold)}</td>
        <td>${num(r.no_silver)}</td>
        <td>${num(r.no_bronze)}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    $('#standingsTable tbody').innerHTML = '<tr><td colspan="9" style="color:red">Error loading standings. Check console.</td></tr>';
  }
}

/* MATCHES */
async function loadMatches(){
  try {
    const data = await fetchJSON('matches');
    const tbody = $('#matchesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No matches data');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td>${escapeHtml(r.sked_date ?? '')}</td>
        <td>${escapeHtml(r.sked_time ?? '')}</td>
        <td>${escapeHtml(String(r.game_no ?? ''))}</td>
        <td>${escapeHtml(r.match_type ?? '')}</td>
        <td>${escapeHtml(r.venue_name ?? '')}</td>
        <td>${escapeHtml(r.team_a ?? '')}</td>
        <td>${escapeHtml(r.team_b ?? '')}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
    $('#matchesTable tbody').innerHTML = '<tr><td colspan="7" style="color:red">Error loading matches. Check console.</td></tr>';
  }
}

/* VENUES */
async function loadVenues() {
  try {
    const venues = await fetchJSON('venues');
    const select = $('#venueSelect');
    
    if (!Array.isArray(venues) || venues.length === 0) {
      console.warn('⚠️ No venues available');
      select.innerHTML = '<option value="">No venues available</option>';
      return;
    }
    
    select.innerHTML = `
      <option value="">-- Select Venue --</option>
      ${venues.map(v => `
        <option value="${v.venue_id}">
          ${escapeHtml(v.venue_name)}
          ${v.venue_building ? ' - ' + escapeHtml(v.venue_building) : ''}
          ${v.venue_room ? ' (' + escapeHtml(v.venue_room) + ')' : ''}
        </option>
      `).join('')}
    `;
    console.log('✅ Venues loaded:', venues.length);
  } catch (err) {
    console.error('❌ loadVenues error:', err);
    $('#venueSelect').innerHTML = '<option value="">Error loading venues</option>';
  }
}

/* TRAINING */
async function loadTrainingTeams(){
  try {
    const teams = await fetchJSON('training_teams');
    const sel = $('#teamSelect');
    
    if (!Array.isArray(teams) || teams.length === 0) {
      console.warn('⚠️ No training teams available');
      sel.innerHTML = '<option value="">No teams available</option>';
      return;
    }
    
    sel.innerHTML = `
      <option value="">-- Select Team --</option>
      ${teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('')}
    `;
    console.log('✅ Training teams loaded:', teams.length);
  } catch (err) {
    console.error('❌ loadTrainingTeams error:', err);
    $('#teamSelect').innerHTML = '<option value="">Error loading teams</option>';
  }
}

async function loadTrainingList(){
  try {
    const data = await fetchJSON('training_list');
    const tbody = $('#trainingTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No training schedules');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td>${escapeHtml(r.team_name)}</td>
        <td>${escapeHtml(r.sked_date)}</td>
        <td>${escapeHtml(r.sked_time)}</td>
        <td>${escapeHtml(r.venue_name ?? '')}</td>
        <td>${r.is_active == 1 ? 'Active' : 'Inactive'}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Training schedules loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTrainingList error:', err);
    $('#trainingTable tbody').innerHTML = '<tr><td colspan="5" style="color:red">Error loading training. Check console.</td></tr>';
  }
}

$('#trainingForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#trainingMsg');
  msg.textContent = "Saving...";

  try {
    const formData = new URLSearchParams();
    formData.set('team_id', $('#teamSelect').value);
    formData.set('sked_date', $('#sked_date').value);
    formData.set('sked_time', $('#sked_time').value);
    formData.set('venue_id', $('#venueSelect').value);

    const data = await fetchJSON('training_create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || (data.ok ? "Saved." : "Failed.");
    msg.style.color = data.ok ? 'green' : 'red';
    
    if (data.ok) {
      e.target.reset();
      await loadTrainingList();
    }
  } catch (err) {
    console.error('❌ Form submit error:', err);
    msg.textContent = 'Error saving schedule';
    msg.style.color = 'red';
  }
});

/* UTIL */
function setupSearch(inputSel, tableSel){
  const input = $(inputSel);
  const table = $(tableSel);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(tr => {
      const txt = tr.textContent.toLowerCase();
      tr.style.display = txt.includes(q) ? '' : 'none';
    });
  });
}

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

function num(v){ return (v === null || v === undefined) ? '0' : String(v); }

/* INITIAL LOAD */
(async function init(){
  console.log('🚀 Initializing coach dashboard...');
  console.log('📊 Session context:', window.COACH_CONTEXT);
  
  try {
    await Promise.all([
      loadPlayers(),
      loadTeams(),
      loadStandings(),
      loadMatches(),
      loadVenues(),
      loadTrainingTeams(),
      loadTrainingList()
    ]);
    console.log('✅ All data loaded successfully');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();