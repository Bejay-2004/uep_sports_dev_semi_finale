const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

// Mobile Menu Toggle
const menuToggle = $('#menuToggle');
const sidebar = $('.sidebar');
const sidebarOverlay = $('#sidebarOverlay');

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');
  });
}

if (sidebarOverlay) {
  sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');
  });
}

// Page titles for navigation
const pageTitles = {
  'players': 'Players',
  'teams': 'Teams',
  'standings': 'Statistics & Rankings',
  'tournaments': 'Tournament Schedules',
  'training': 'Training Schedules'
};

// Tab Navigation
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    // Update active states
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    
    // Update page title
    const pageTitle = $('#pageTitle');
    if (pageTitle) {
      pageTitle.textContent = pageTitles[view] || 'Dashboard';
    }
    
    // Switch views
    $$('.content-view').forEach(p => p.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
    // Close mobile menu
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('active');
      sidebarOverlay.classList.remove('active');
    }
    
    // Load view data
    loadViewData(view);
  });
});

function loadViewData(view) {
  switch(view) {
    case 'players': loadPlayers(); break;
    case 'teams': loadTeams(); break;
    case 'standings': loadStandings(); break;
    case 'tournaments': loadMatches(); break;
    case 'training': 
      loadTrainingList(); 
      loadTrainingTeams();
      loadVenues();
      break;
  }
}

async function fetchJSON(action, opts = {}) {
  try {
    // Build URL with query parameters
    let url = `api.php?action=${encodeURIComponent(action)}`;
    
    // If opts has non-method properties, add them as query params for GET requests
    if (!opts.method || opts.method === 'GET') {
      const params = Object.entries(opts)
        .filter(([key]) => key !== 'method' && key !== 'headers' && key !== 'body')
        .map(([key, val]) => `${encodeURIComponent(key)}=${encodeURIComponent(val)}`)
        .join('&');
      
      if (params) {
        url += '&' + params;
      }
      
      // Remove the params from opts to avoid conflicts
      opts = { method: 'GET' };
    }
    
    console.log('📡 Fetching:', url);
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
  tbody.innerHTML = rowsHtml || `<tr><td colspan="20" style="text-align:center;padding:20px;color:var(--muted);">No data found.</td></tr>`;
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
        <td><strong>${escapeHtml(r.player_name)}</strong></td>
        <td>${escapeHtml(r.team_name)}</td>
        <td>${r.is_captain == 1 ? '<span style="color:#10b981;font-weight:700;">✓ Yes</span>' : 'No'}</td>
        <td>
          <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="openUpdateModal(${r.person_id})">
            Edit
          </button>
        </td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    setupSearch('#playersSearch', '#playersTable');
    console.log('✅ Players loaded:', data.length);
  } catch (err) {
    console.error('❌ loadPlayers error:', err);
    $('#playersTable tbody').innerHTML = '<tr><td colspan="4" style="color:red;text-align:center;padding:20px;">Error loading players. Check console.</td></tr>';
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
        <td><strong>${escapeHtml(r.team_name)}</strong></td>
        <td>${escapeHtml(String(r.tour_id ?? 'N/A'))}</td>
        <td>${escapeHtml(r.coach_name ?? 'N/A')}</td>
        <td>${escapeHtml(r.asst_coach_name ?? 'N/A')}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    setupSearch('#teamsSearch', '#teamsTable');
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeams error:', err);
    $('#teamsTable tbody').innerHTML = '<tr><td colspan="4" style="color:red;text-align:center;padding:20px;">Error loading teams. Check console.</td></tr>';
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
        <td><strong>${escapeHtml(r.team_name)}</strong></td>
        <td>${num(r.no_games_played)}</td>
        <td><strong style="color:#10b981;">${num(r.no_win)}</strong></td>
        <td style="color:#ef4444;">${num(r.no_loss)}</td>
        <td>${num(r.no_draw)}</td>
        <td><span style="color:#f59e0b;">🥇 ${num(r.no_gold)}</span></td>
        <td><span style="color:#94a3b8;">🥈 ${num(r.no_silver)}</span></td>
        <td><span style="color:#cd7f32;">🥉 ${num(r.no_bronze)}</span></td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Standings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadStandings error:', err);
    $('#standingsTable tbody').innerHTML = '<tr><td colspan="9" style="color:red;text-align:center;padding:20px;">Error loading standings. Check console.</td></tr>';
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
        <td>${escapeHtml(r.sked_date ?? 'TBA')}</td>
        <td>${escapeHtml(r.sked_time ?? 'TBA')}</td>
        <td><strong>${escapeHtml(String(r.game_no ?? '-'))}</strong></td>
        <td><span style="padding:4px 8px;background:#dbeafe;color:#1e40af;border-radius:4px;font-size:11px;font-weight:700;">${escapeHtml(r.match_type ?? 'TBA')}</span></td>
        <td>${escapeHtml(r.venue_name ?? 'TBA')}</td>
        <td><strong>${escapeHtml(r.team_a ?? 'TBA')}</strong></td>
        <td><strong>${escapeHtml(r.team_b ?? 'TBA')}</strong></td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
    $('#matchesTable tbody').innerHTML = '<tr><td colspan="7" style="color:red;text-align:center;padding:20px;">Error loading matches. Check console.</td></tr>';
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
        <td><strong>${escapeHtml(r.team_name)}</strong></td>
        <td>${escapeHtml(r.sked_date)}</td>
        <td>${escapeHtml(r.sked_time)}</td>
        <td>${escapeHtml(r.venue_name ?? 'TBA')}</td>
        <td>${r.is_active == 1 ? '<span style="color:#10b981;font-weight:700;">● Active</span>' : '<span style="color:#6b7280;">● Inactive</span>'}</td>
      </tr>
    `).join('');
    renderRows(tbody, html);
    console.log('✅ Training schedules loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTrainingList error:', err);
    $('#trainingTable tbody').innerHTML = '<tr><td colspan="5" style="color:red;text-align:center;padding:20px;">Error loading training. Check console.</td></tr>';
  }
}

$('#trainingForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#trainingMsg');
  msg.textContent = "Saving...";
  msg.className = "msg";

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

    msg.textContent = data.message || (data.ok ? "Training schedule saved successfully!" : "Failed to save.");
    msg.className = data.ok ? 'msg success' : 'msg error';
    
    if (data.ok) {
      e.target.reset();
      await loadTrainingList();
    }
  } catch (err) {
    console.error('❌ Form submit error:', err);
    msg.textContent = 'Error saving schedule';
    msg.className = 'msg error';
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
    // Load initial data for the active view
    await loadPlayers();
    console.log('✅ Initial data loaded successfully');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();

/* UPDATE PLAYER FUNCTIONALITY */
window.openUpdateModal = async function(personId) {
  try {
    console.log('🔍 Opening modal for person_id:', personId);
    const data = await fetchJSON('get_player', { person_id: personId });
    
    console.log('📥 Received data:', data);
    
    if (!data || data.ok === false) {
      const errorMsg = data?.message || 'Error loading player information';
      const debugInfo = data?.debug ? '\n\nDebug: ' + JSON.stringify(data.debug) : '';
      alert(errorMsg + debugInfo);
      console.error('❌ Error response:', data);
      return;
    }
    
    // Populate form
    $('#update_person_id').value = data.person_id;
    $('#update_f_name').value = data.f_name || '';
    $('#update_l_name').value = data.l_name || '';
    $('#update_m_name').value = data.m_name || '';
    $('#update_date_birth').value = data.date_birth || '';
    $('#update_blood_type').value = data.blood_type || '';
    $('#update_course').value = data.course || '';
    $('#update_height').value = data.height || '';
    $('#update_weight').value = data.weight || '';
    $('#update_b_pressure').value = data.b_pressure || '';
    $('#update_b_sugar').value = data.b_sugar || '';
    $('#update_b_choles').value = data.b_choles || '';
    
    console.log('✅ Form populated successfully');
    
    // Show modal
    $('#updatePlayerModal').classList.add('active');
    
  } catch (err) {
    console.error('❌ openUpdateModal error:', err);
    alert('Error loading player information: ' + err.message);
  }
};

window.closeUpdateModal = function() {
  $('#updatePlayerModal').classList.remove('active');
  $('#updatePlayerForm').reset();
  $('#updateMsg').style.display = 'none';
};

// Handle update form submission
$('#updatePlayerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = $('#updateMsg');
  msg.style.display = 'block';
  msg.textContent = "Updating...";
  msg.className = "msg";

  try {
    const formData = new URLSearchParams();
    formData.set('person_id', $('#update_person_id').value);
    formData.set('f_name', $('#update_f_name').value);
    formData.set('l_name', $('#update_l_name').value);
    formData.set('m_name', $('#update_m_name').value);
    formData.set('date_birth', $('#update_date_birth').value);
    formData.set('blood_type', $('#update_blood_type').value);
    formData.set('course', $('#update_course').value);
    formData.set('height', $('#update_height').value);
    formData.set('weight', $('#update_weight').value);
    formData.set('b_pressure', $('#update_b_pressure').value);
    formData.set('b_sugar', $('#update_b_sugar').value);
    formData.set('b_choles', $('#update_b_choles').value);

    const data = await fetchJSON('update_player', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    msg.textContent = data.message || (data.ok ? "Player updated successfully!" : "Failed to update.");
    msg.className = data.ok ? 'msg success' : 'msg error';
    
    if (data.ok) {
      setTimeout(() => {
        closeUpdateModal();
        loadPlayers(); // Reload the list
      }, 1500);
    }
  } catch (err) {
    console.error('❌ Update form error:', err);
    msg.textContent = 'Error updating player';
    msg.className = 'msg error';
    msg.style.display = 'block';
  }
});

// Close modal on overlay click
$('#updatePlayerModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'updatePlayerModal') {
    closeUpdateModal();
  }
});