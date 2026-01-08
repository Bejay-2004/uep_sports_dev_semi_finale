const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const { person_id, sports_id } = window.COACH_CONTEXT;

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
  'overview': 'Dashboard Overview',
  'players': 'Players',
  'teams': 'Teams',
  'training': 'Training Sessions',
  'attendance': 'Session Attendance',
  'performance': 'Performance Ratings',
  'standings': 'Statistics & Rankings',
  'tournaments': 'Tournament Schedules'
};

// Tab Navigation
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    
    const pageTitle = $('#pageTitle');
    if (pageTitle) {
      pageTitle.textContent = pageTitles[view] || 'Dashboard';
    }
    
    $$('.content-view').forEach(p => p.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
    if (window.innerWidth <= 768) {
      sidebar.classList.remove('active');
      sidebarOverlay.classList.remove('active');
    }
    
    loadViewData(view);
  });
});

// API Helper
async function fetchJSON(action, opts = {}) {
  try {
    let url = `api.php?action=${encodeURIComponent(action)}`;
    
    if (!opts.method || opts.method === 'GET') {
      const params = Object.entries(opts)
        .filter(([key]) => key !== 'method' && key !== 'headers' && key !== 'body')
        .map(([key, val]) => `${encodeURIComponent(key)}=${encodeURIComponent(val)}`)
        .join('&');
      
      if (params) {
        url += '&' + params;
      }
      
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

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

function num(v){ return (v === null || v === undefined) ? '0' : String(v); }

function showMsg(selector, message, type = 'success') {
  const el = $(selector);
  if (!el) return;
  
  el.textContent = message;
  el.className = `msg ${type}`;
  el.style.display = 'block';
  
  setTimeout(() => {
    el.style.display = 'none';
  }, 5000);
}

function loadViewData(view) {
  switch(view) {
    case 'overview': loadOverview(); break;
    case 'players': loadPlayers(); break;
    case 'teams': loadTeams(); break;
    case 'training': loadSessions(); break;
    case 'attendance': loadAttendanceSessions(); break;
    case 'performance': loadPerformanceRatings(); break;
    case 'standings': loadStandings(); break;
    case 'tournaments': loadMatches(); break;
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    // Load stats
    const [playersData, teamsData, sessionsData] = await Promise.all([
      fetchJSON('players'),
      fetchJSON('teams'),
      fetchJSON('training_list')
    ]);
    
    // Update stat cards
    $('#statPlayers').textContent = Array.isArray(playersData) ? playersData.length : 0;
    $('#statTeams').textContent = Array.isArray(teamsData) ? teamsData.length : 0;
    
    // Sessions this month
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    
    let sessionsThisMonth = 0;
    if (Array.isArray(sessionsData)) {
      sessionsThisMonth = sessionsData.filter(s => {
        const sDate = new Date(s.sked_date);
        return sDate.getMonth() === currentMonth && sDate.getFullYear() === currentYear;
      }).length;
    }
    
    $('#statSessions').textContent = sessionsThisMonth;
    $('#statAttendance').textContent = '85%'; // Placeholder
    
    // Load upcoming sessions
    const upcoming = await fetchJSON('training_list');
    const upcomingEl = $('#upcomingSessions');
    
    if (!upcoming || upcoming.length === 0) {
      upcomingEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No upcoming sessions scheduled</p>
        </div>
      `;
    } else {
      const today = new Date();
      const upcomingOnly = upcoming.filter(s => new Date(s.sked_date) >= today).slice(0, 3);
      
      if (upcomingOnly.length === 0) {
        upcomingEl.innerHTML = `
          <div class="data-card">
            <p style="color:var(--muted);text-align:center;padding:20px;">No upcoming sessions scheduled</p>
          </div>
        `;
      } else {
        upcomingEl.innerHTML = upcomingOnly.map(s => `
          <div class="data-card">
            <div class="data-card-header">
              <div class="data-card-title">Training Session</div>
              <span class="badge active">Scheduled</span>
            </div>
            <div class="data-card-meta">
              📅 ${escapeHtml(s.sked_date)}<br>
              ⏰ ${escapeHtml(s.sked_time)}<br>
              📍 ${escapeHtml(s.venue_name || 'TBA')}<br>
              👥 Team: ${escapeHtml(s.team_name || 'TBA')}
            </div>
          </div>
        `).join('');
      }
    }
    
    // Load recent activity
    const activityEl = $('#recentActivity');
    
    if (!upcoming || upcoming.length === 0) {
      activityEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No recent activity</p>
        </div>
      `;
    } else {
      activityEl.innerHTML = `
        <div class="data-card">
          ${upcoming.slice(0, 5).map(s => `
            <div style="padding:12px 0;border-bottom:1px solid var(--line);">
              <div style="font-weight:600;margin-bottom:4px;">Training Session for ${escapeHtml(s.team_name)}</div>
              <div style="font-size:12px;color:var(--muted);">${escapeHtml(s.sked_date)} at ${escapeHtml(s.sked_time)}</div>
            </div>
          `).join('')}
        </div>
      `;
    }
  } catch (err) {
    console.error('loadOverview error:', err);
  }
}

// ==========================================
// PLAYERS
// ==========================================

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

// ==========================================
// TEAMS
// ==========================================

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

// ==========================================
// STANDINGS
// ==========================================

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

// ==========================================
// MATCHES
// ==========================================

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

// ==========================================
// TRAINING SESSIONS
// ==========================================

$('#createSessionBtn')?.addEventListener('click', () => {
  resetSessionForm();
  $('#sessionFormCard').style.display = 'block';
  loadTeamsForSession();
  loadVenuesForSession();
});

$('#cancelSessionBtn')?.addEventListener('click', () => {
  $('#sessionFormCard').style.display = 'none';
  $('#sessionForm').reset();
  $('#session_sked_id').value = '';
});

function resetSessionForm() {
  $('#sessionForm').reset();
  $('#session_sked_id').value = '';
  $('#sessionMsg').style.display = 'none';
}

async function loadTeamsForSession() {
  try {
    const teams = await fetchJSON('training_teams');
    const select = $('#session_team_id');
    
    if (!teams || teams.length === 0) {
      select.innerHTML = '<option value="">No teams available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Team --</option>' +
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading teams:', err);
  }
}

async function loadVenuesForSession() {
  try {
    const venues = await fetchJSON('venues');
    const select = $('#session_venue_id');
    
    if (!venues || venues.length === 0) {
      select.innerHTML = '<option value="">No venues available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Venue --</option>' +
      venues.map(v => `
        <option value="${v.venue_id}">
          ${escapeHtml(v.venue_name)}
          ${v.venue_building ? ' - ' + escapeHtml(v.venue_building) : ''}
          ${v.venue_room ? ' (' + escapeHtml(v.venue_room) + ')' : ''}
        </option>
      `).join('');
  } catch (err) {
    console.error('Error loading venues:', err);
  }
}

$('#sessionForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new URLSearchParams();
  formData.set('team_id', $('#session_team_id').value);
  formData.set('sked_date', $('#training_date').value);
  formData.set('sked_time', $('#start_time').value);
  formData.set('venue_id', $('#session_venue_id').value);

  try {
    const data = await fetchJSON('training_create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    showMsg('#sessionMsg', data.message || (data.ok ? "Training schedule saved successfully!" : "Failed to save."), data.ok ? 'success' : 'error');
    
    if (data.ok) {
      $('#sessionForm').reset();
      setTimeout(() => {
        $('#sessionFormCard').style.display = 'none';
        loadSessions();
        loadOverview();
      }, 1500);
    }
  } catch (err) {
    console.error('❌ Form submit error:', err);
    showMsg('#sessionMsg', 'Error saving schedule', 'error');
  }
});

async function loadSessions() {
  try {
    const data = await fetchJSON('training_list');
    const content = $('#sessionsListContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training sessions. Click "Create New Session" to start.</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = data.map(s => {
      const isActive = s.is_active == 1;
      return `
        <div class="data-card" style="margin-bottom:16px;">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(s.team_name)}</div>
            <span class="badge ${isActive ? 'active' : 'inactive'}">
              ${isActive ? '● Active' : '● Inactive'}
            </span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(s.sked_date)}<br>
            ⏰ ${escapeHtml(s.sked_time)}<br>
            📍 ${escapeHtml(s.venue_name || 'TBA')}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('❌ loadSessions error:', err);
  }
}

// ==========================================
// ATTENDANCE
// ==========================================

async function loadAttendanceSessions() {
  try {
    const sessions = await fetchJSON('training_list');
    const select = $('#attendanceSessionSelect');
    
    if (!sessions || sessions.length === 0) {
      select.innerHTML = '<option value="">No sessions available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Session --</option>' +
      sessions.map(s => 
        `<option value="${s.sked_id}">Training - ${escapeHtml(s.sked_date)} (${escapeHtml(s.team_name)})</option>`
      ).join('');
  } catch (err) {
    console.error('Error loading sessions:', err);
  }
}

$('#attendanceSessionSelect')?.addEventListener('change', async function() {
  const skedId = this.value;
  const content = $('#attendanceContent');
  
  if (!skedId) {
    content.innerHTML = '<div class="empty-state">Select a session to view/mark attendance</div>';
    return;
  }
  
  content.innerHTML = '<div class="loading">Loading attendance...</div>';
  
  try {
    console.log('🔍 Loading attendance for session:', skedId);
    const attendance = await fetchJSON('session_attendance', { sked_id: skedId });
    
    console.log('📥 Attendance response:', attendance);
    
    // Check if response indicates an error (check for ok: false OR if it's not an array)
    if (!attendance || !Array.isArray(attendance)) {
      const errorMsg = (attendance && attendance.message) ? attendance.message : 'Could not load attendance';
      const errorDetail = (attendance && attendance.error) ? attendance.error : '';
      
      console.error('❌ Attendance error:', errorMsg);
      content.innerHTML = `
        <div class="card">
          <p style="color:var(--danger);text-align:center;padding:20px;">
            <strong>Error:</strong> ${escapeHtml(errorMsg)}
          </p>
          ${errorDetail ? `<p style="color:var(--muted);text-align:center;font-size:12px;">Details: ${escapeHtml(errorDetail)}</p>` : ''}
          <p style="color:var(--muted);text-align:center;font-size:12px;margin-top:10px;">
            <strong>Troubleshooting:</strong><br>
            • Make sure players are added to this team<br>
            • Check that players are marked as active<br>
            • Verify the team has players assigned in Team Athletes
          </p>
        </div>
      `;
      return;
    }
    
    if (attendance.length === 0) {
      console.warn('⚠️ No players found');
      content.innerHTML = `
        <div class="card">
          <p style="color:var(--muted);text-align:center;padding:20px;">
            No players found for this session's team.<br>
            <small>Make sure players are assigned to this team in the system.</small>
          </p>
        </div>
      `;
      return;
    }
    
    console.log('✅ Loaded', attendance.length, 'players');
    
    content.innerHTML = `
      <div class="card">
        <div style="margin-bottom:16px;">
          <button class="btn btn-success" id="saveAllAttendanceBtn" style="margin-right:8px;">
            Save All Attendance
          </button>
          <span style="color:var(--muted);font-size:13px;">Check players who attended, then click Save</span>
        </div>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Player Name</th>
                <th style="text-align:center;width:150px;">Present</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${attendance.map(a => {
                const isPresent = parseInt(a.is_present) === 1;
                return `
                  <tr>
                    <td><strong>${escapeHtml(a.player_name)}</strong></td>
                    <td style="text-align:center;">
                      <input type="checkbox" 
                             class="attendance-checkbox" 
                             data-person-id="${a.person_id}"
                             ${isPresent ? 'checked' : ''}>
                    </td>
                    <td>
                      <span class="badge ${isPresent ? 'present' : 'absent'}">
                        ${isPresent ? '✓ Present' : '✗ Absent'}
                      </span>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;
    
    // Add event listener for save button (after DOM is updated)
    const saveBtn = $('#saveAllAttendanceBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        await saveAllAttendance(skedId, attendance);
      });
    }
    
    // Update badge when checkbox changes - USE $ or document.querySelectorAll
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const row = this.closest('tr');
        const badge = row.querySelector('.badge');
        if (this.checked) {
          badge.className = 'badge present';
          badge.textContent = '✓ Present';
        } else {
          badge.className = 'badge absent';
          badge.textContent = '✗ Absent';
        }
      });
    });
    
  } catch (err) {
    console.error('❌ Error loading attendance:', err);
    content.innerHTML = `
      <div class="card">
        <p style="color:var(--danger);text-align:center;padding:20px;">
          <strong>Error loading attendance</strong><br>
          <small style="color:var(--muted);">${escapeHtml(err.message)}</small>
        </p>
      </div>
    `;
  }
});

async function saveAllAttendance(skedId, attendanceData) {
  // USE document.querySelectorAll instead of $
  const checkboxes = document.querySelectorAll('.attendance-checkbox');
  const saveBtn = $('#saveAllAttendanceBtn');
  
  if (!saveBtn) return;
  
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';
  
  try {
    const promises = [];
    
    checkboxes.forEach(checkbox => {
      const personId = checkbox.dataset.personId;
      const isPresent = checkbox.checked ? 1 : 0;
      
      promises.push(
        fetchJSON('mark_attendance', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            sked_id: skedId,
            person_id: personId,
            is_present: isPresent
          }).toString()
        })
      );
    });
    
    await Promise.all(promises);
    
    saveBtn.textContent = '✓ Saved Successfully!';
    saveBtn.className = 'btn btn-success';
    
    setTimeout(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save All Attendance';
    }, 2000);
    
  } catch (err) {
    console.error('Error saving attendance:', err);
    saveBtn.textContent = 'Error - Try Again';
    saveBtn.className = 'btn btn-danger';
    setTimeout(() => {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save All Attendance';
      saveBtn.className = 'btn btn-success';
    }, 2000);
  }
}

// ==========================================
// PERFORMANCE RATINGS
// ==========================================

$('#addPerformanceBtn')?.addEventListener('click', async () => {
  resetPerformanceForm();
  $('#performanceFormCard').style.display = 'block';
  await loadPlayersForPerformance();
  await loadActivitiesForPerformance();
  await loadTeamsForPerformance();
  
  // Set default date to today
  $('#perf_date_eval').value = new Date().toISOString().split('T')[0];
});

$('#cancelPerfBtn')?.addEventListener('click', () => {
  $('#performanceFormCard').style.display = 'none';
  resetPerformanceForm();
});

function resetPerformanceForm() {
  $('#performanceForm').reset();
  $('#perf_id').value = '';
  $('#perfMsg').style.display = 'none';
}

async function loadPlayersForPerformance() {
  try {
    const players = await fetchJSON('players');
    const select = $('#perf_person_id');
    
    if (!players || players.length === 0) {
      select.innerHTML = '<option value="">No players available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Player --</option>' +
      players.map(p => `<option value="${p.person_id}">${escapeHtml(p.player_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading players:', err);
  }
}

async function loadActivitiesForPerformance() {
  try {
    const activities = await fetchJSON('training_activities');
    const select = $('#perf_activity_id');
    
    if (!activities || activities.length === 0) {
      select.innerHTML = '<option value="">No activities available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Activity --</option>' +
      activities.map(a => `<option value="${a.activity_id}">${escapeHtml(a.activity_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading activities:', err);
  }
}

async function loadTeamsForPerformance() {
  try {
    const teams = await fetchJSON('training_teams');
    const select = $('#perf_team_id');
    
    if (!teams || teams.length === 0) {
      select.innerHTML = '<option value="">No teams available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Team --</option>' +
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading teams:', err);
  }
}

$('#performanceForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new URLSearchParams();
  formData.set('perf_id', $('#perf_id').value || '');
  formData.set('person_id', $('#perf_person_id').value);
  formData.set('activity_id', $('#perf_activity_id').value);
  formData.set('team_id', $('#perf_team_id').value);
  formData.set('rating', $('#perf_rating').value);
  formData.set('date_eval', $('#perf_date_eval').value);

  try {
    const data = await fetchJSON('save_performance', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    showMsg('#perfMsg', data.message || (data.ok ? "Performance rating saved successfully!" : "Failed to save."), data.ok ? 'success' : 'error');
    
    if (data.ok) {
      setTimeout(() => {
        $('#performanceFormCard').style.display = 'none';
        resetPerformanceForm();
        loadPerformanceRatings();
      }, 1500);
    }
  } catch (err) {
    console.error('❌ Form submit error:', err);
    showMsg('#perfMsg', 'Error saving performance rating', 'error');
  }
});

async function loadPerformanceRatings() {
  try {
    const data = await fetchJSON('performance_list');
    const tbody = $('#performanceTable tbody');
    
    if (!data || data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;padding:40px;">
            <p style="color:var(--muted);">No performance ratings yet. Click "Add Rating" to start.</p>
          </td>
        </tr>
      `;
      return;
    }
    
    tbody.innerHTML = data.map(r => {
      const rating = parseFloat(r.rating);
      let ratingClass = 'rating-average';
      let ratingLabel = 'Average';
      
      if (rating >= 8) {
        ratingClass = 'rating-excellent';
        ratingLabel = 'Excellent';
      } else if (rating >= 6) {
        ratingClass = 'rating-good';
        ratingLabel = 'Good';
      } else if (rating < 4) {
        ratingClass = 'rating-poor';
        ratingLabel = 'Poor';
      }
      
      return `
        <tr>
          <td><strong>${escapeHtml(r.player_name)}</strong></td>
          <td>${escapeHtml(r.activity_name)}</td>
          <td>${escapeHtml(r.team_name)}</td>
          <td>
            <span class="rating-display ${ratingClass}">
              ${rating.toFixed(1)}/10
              <span style="font-size:11px;opacity:0.8;">(${ratingLabel})</span>
            </span>
          </td>
          <td>${escapeHtml(r.date_eval)}</td>
          <td>
            <button class="btn btn-secondary btn-small" onclick="editPerformance(${r.perf_id})" title="Edit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>
            <button class="btn btn-danger btn-small" onclick="deletePerformance(${r.perf_id})" title="Delete" style="margin-left:4px;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              </svg>
            </button>
          </td>
        </tr>
      `;
    }).join('');
    
    setupSearch('#perfSearch', '#performanceTable');
    
  } catch (err) {
    console.error('❌ loadPerformanceRatings error:', err);
    $('#performanceTable tbody').innerHTML = '<tr><td colspan="6" style="color:red;text-align:center;padding:20px;">Error loading performance ratings.</td></tr>';
  }
}

window.editPerformance = async (perfId) => {
  try {
    const perf = await fetchJSON('get_performance', { perf_id: perfId });
    
    if (!perf || perf.ok === false) {
      alert('Error loading performance data');
      return;
    }
    
    // Load dropdowns first
    await loadPlayersForPerformance();
    await loadActivitiesForPerformance();
    await loadTeamsForPerformance();
    
    // Populate form
    $('#perf_id').value = perf.perf_id;
    $('#perf_person_id').value = perf.person_id;
    $('#perf_activity_id').value = perf.activity_id;
    $('#perf_team_id').value = perf.team_id;
    $('#perf_rating').value = perf.rating;
    $('#perf_date_eval').value = perf.date_eval;
    
    // Show form
    $('#performanceFormCard').style.display = 'block';
    $('#performanceFormCard').scrollIntoView({ behavior: 'smooth' });
    
  } catch (err) {
    console.error('Edit performance error:', err);
    alert('Error loading performance data');
  }
};

window.deletePerformance = async (perfId) => {
  if (!confirm('Are you sure you want to delete this performance rating?')) return;
  
  try {
    const result = await fetchJSON('delete_performance', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ perf_id: perfId }).toString()
    });
    
    if (result && result.ok) {
      loadPerformanceRatings();
    } else {
      alert(result?.message || 'Error deleting performance rating');
    }
  } catch (err) {
    console.error('Delete performance error:', err);
    alert('Error deleting performance rating');
  }
};

// ==========================================
// PLAYER UPDATE FUNCTIONALITY
// ==========================================

window.openUpdateModal = async function(personId) {
  try {
    console.log('🔍 Opening modal for person_id:', personId);
    const data = await fetchJSON('get_player', { person_id: personId });
    
    console.log('📥 Received data:', data);
    
    if (!data || data.ok === false) {
      const errorMsg = data?.message || 'Error loading player information';
      alert(errorMsg);
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
        loadPlayers();
      }, 1500);
    }
  } catch (err) {
    console.error('❌ Update form error:', err);
    msg.textContent = 'Error updating player';
    msg.className = 'msg error';
    msg.style.display = 'block';
  }
});

$('#updatePlayerModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'updatePlayerModal') {
    closeUpdateModal();
  }
});

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

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

// ==========================================
// INITIALIZATION
// ==========================================

(async function init(){
  console.log('🚀 Initializing enhanced coach dashboard...');
  console.log('📊 Session context:', window.COACH_CONTEXT);
  
  try {
    await loadOverview();
    console.log('✅ Initial data loaded successfully');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();