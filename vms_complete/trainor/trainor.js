const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const { person_id, sports_id } = window.TRAINOR_CONTEXT;

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

// Tab Navigation
const pageTitles = {
  'overview': 'Dashboard Overview',
  'schedule': 'Training Schedule',
  'sessions': 'Manage Sessions',
  'attendance': 'Session Attendance',
  'trainees': 'My Trainees',
  'activities': 'Training Activities',
  'reports': 'Training Reports'
};

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
    
    const res = await fetch(url, options);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    
    const result = await res.json();
    return result;
  } catch (err) {
    console.error('API Error:', err);
    return null;
  }
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

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
    case 'schedule': loadSchedule(); break;
    case 'sessions': loadSessions(); break;
    case 'attendance': loadAttendanceSessions(); break;
    case 'trainees': loadTrainees(); break;
    case 'activities': loadActivities(); break;
    case 'reports': break; // Placeholder
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    const stats = await fetchAPI('trainor_stats');
    
    if (stats) {
      $('#statTrainees').textContent = stats.active_trainees || 0;
      $('#statSessions').textContent = stats.sessions_this_month || 0;
      $('#statAttendance').textContent = (stats.avg_attendance || 0) + '%';
      $('#statHours').textContent = (stats.total_hours || 0) + 'h';
    }
    
    const upcoming = await fetchAPI('upcoming_sessions');
    const upcomingEl = $('#upcomingSessions');
    
    if (!upcoming || upcoming.length === 0) {
      upcomingEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No upcoming sessions scheduled</p>
        </div>
      `;
    } else {
      upcomingEl.innerHTML = upcoming.slice(0, 3).map(s => `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(s.training_type)}</div>
            <span class="badge active">Scheduled</span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(s.training_date)}<br>
            ⏰ ${escapeHtml(s.start_time)} - ${escapeHtml(s.end_time)}<br>
            📍 ${escapeHtml(s.location || 'TBA')}<br>
            👥 Team: ${escapeHtml(s.team_name || 'TBA')}
          </div>
        </div>
      `).join('');
    }
    
    // Load recent activity
    const activity = await fetchAPI('recent_activity');
    const activityEl = $('#recentActivity');
    
    if (!activity || activity.length === 0) {
      activityEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No recent activity</p>
        </div>
      `;
    } else {
      activityEl.innerHTML = `
        <div class="data-card">
          ${activity.slice(0, 5).map(a => `
            <div style="padding:12px 0;border-bottom:1px solid var(--line);">
              <div style="font-weight:600;margin-bottom:4px;">${escapeHtml(a.activity)}</div>
              <div style="font-size:12px;color:var(--muted);">${escapeHtml(a.timestamp)}</div>
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
// SCHEDULE
// ==========================================

async function loadSchedule() {
  try {
    const sessions = await fetchAPI('all_sessions');
    const content = $('#scheduleContent');
    
    if (!sessions || sessions.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training sessions scheduled</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = sessions.map(s => {
      const isUpcoming = new Date(s.training_date) > new Date();
      return `
        <div class="data-card" style="margin-bottom:16px;">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(s.training_type)}</div>
            <span class="badge ${isUpcoming ? 'active' : 'inactive'}">
              ${isUpcoming ? 'Upcoming' : 'Past'}
            </span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(s.training_date)}<br>
            ⏰ ${escapeHtml(s.start_time)} - ${escapeHtml(s.end_time)}<br>
            📍 ${escapeHtml(s.location || 'TBA')}<br>
            👥 Team: ${escapeHtml(s.team_name || 'TBA')}
            ${s.description ? '<br>📝 ' + escapeHtml(s.description) : ''}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('loadSchedule error:', err);
  }
}

// ==========================================
// SESSIONS MANAGEMENT
// ==========================================

$('#createSessionBtn')?.addEventListener('click', () => {
  resetSessionForm();
  $('#sessionFormCard').style.display = 'block';
  loadTeamsForSession();
  loadVenuesForSession();
});

$('#addSessionBtn')?.addEventListener('click', () => {
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
    const teams = await fetchAPI('my_teams');
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
    const venues = await fetchAPI('get_venues');
    const select = $('#session_venue_id');
    
    if (!select) return;
    
    if (!venues || venues.length === 0) {
      select.innerHTML = '<option value="">No venues available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Venue (Optional) --</option>' +
      venues.map(v => `<option value="${v.venue_id}">${escapeHtml(v.full_name || v.venue_name)}</option>`).join('');
  } catch (err) {
    console.error('Error loading venues:', err);
  }
}

$('#sessionForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const data = {
    sked_id: $('#session_sked_id').value || null,
    team_id: $('#session_team_id').value,
    training_date: $('#training_date').value,
    start_time: $('#start_time').value,
    venue_id: $('#session_venue_id').value
  };
  
  const result = await fetchAPI('save_session', data, 'POST');
  
  if (result && result.ok) {
    showMsg('#sessionMsg', result.message, 'success');
    $('#sessionForm').reset();
    $('#session_sked_id').value = '';
    
    setTimeout(() => {
      $('#sessionFormCard').style.display = 'none';
      loadSessions();
      loadOverview(); // Refresh stats
    }, 1500);
  } else {
    showMsg('#sessionMsg', result?.message || 'Error saving session', 'error');
  }
});

async function loadSessions() {
  try {
    const sessions = await fetchAPI('my_sessions');
    const content = $('#sessionsListContent');
    
    if (!sessions || sessions.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No sessions created yet. Click "Create New Session" to start.</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = sessions.map(s => `
      <div class="data-card" style="margin-bottom:16px;">
        <div class="data-card-header">
          <div class="data-card-title">${escapeHtml(s.training_type)}</div>
          <div>
            <button class="btn btn-small btn-secondary" onclick="editSession(${s.sked_id})">Edit</button>
            <button class="btn btn-small btn-danger" onclick="deleteSession(${s.sked_id})">Delete</button>
          </div>
        </div>
        <div class="data-card-meta">
          📅 ${escapeHtml(s.training_date)}<br>
          ⏰ ${escapeHtml(s.start_time)} - ${escapeHtml(s.end_time)}<br>
          📍 ${escapeHtml(s.location)}<br>
          👥 Team: ${escapeHtml(s.team_name)}
          ${s.description ? '<br>📝 ' + escapeHtml(s.description) : ''}
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('loadSessions error:', err);
  }
}

window.editSession = async (skedId) => {
  try {
    const session = await fetchAPI('get_session', { sked_id: skedId });
    
    if (!session || session.ok === false) {
      alert('Error loading session');
      return;
    }
    
    // Populate form
    $('#session_sked_id').value = session.sked_id;
    $('#session_team_id').value = session.team_id;
    $('#training_date').value = session.training_date;
    $('#start_time').value = session.start_time;
    $('#session_venue_id').value = session.venue_id || '';
    
    // Show form
    $('#sessionFormCard').style.display = 'block';
    await loadTeamsForSession();
    await loadVenuesForSession();
    
    // Re-set values after loading options
    $('#session_team_id').value = session.team_id;
    $('#session_venue_id').value = session.venue_id || '';
    
  } catch (err) {
    console.error('Edit session error:', err);
    alert('Error loading session');
  }
};

window.deleteSession = async (skedId) => {
  if (!confirm('Are you sure you want to delete this session?')) return;
  
  const result = await fetchAPI('delete_session', { sked_id: skedId }, 'POST');
  
  if (result && result.ok) {
    loadSessions();
  } else {
    alert(result?.message || 'Error deleting session');
  }
};

// ==========================================
// ATTENDANCE
// ==========================================

async function loadAttendanceSessions() {
  try {
    const sessions = await fetchAPI('my_sessions');
    const select = $('#attendanceSessionSelect');
    
    if (!sessions || sessions.length === 0) {
      select.innerHTML = '<option value="">No sessions available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Session --</option>' +
      sessions.map(s => 
        `<option value="${s.sked_id}">${escapeHtml(s.training_type)} - ${escapeHtml(s.training_date)}</option>`
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
    const attendance = await fetchAPI('session_attendance', { sked_id: skedId });
    
    if (!attendance || attendance.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No trainees registered for this session</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = `
      <div class="table-container">
        <table class="user-table">
          <thead>
            <tr>
              <th>Trainee Name</th>
              <th>Present</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            ${attendance.map(a => {
              const isPresent = parseInt(a.is_present) === 1;
              return `
                <tr>
                  <td><strong>${escapeHtml(a.trainee_name)}</strong></td>
                  <td>
                    <input type="checkbox" 
                           class="attendance-checkbox" 
                           data-person-id="${a.person_id}"
                           ${isPresent ? 'checked' : ''}
                           style="width:20px;height:20px;cursor:pointer;">
                  </td>
                  <td>
                    <button class="btn btn-small btn-success" onclick="saveAttendance(${a.person_id}, ${skedId})">Save</button>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  } catch (err) {
    console.error('Error loading attendance:', err);
    content.innerHTML = '<div class="empty-state">Error loading attendance</div>';
  }
});

window.saveAttendance = async (personId, skedId) => {
  const checkboxEl = $(`.attendance-checkbox[data-person-id="${personId}"]`);
  
  if (!checkboxEl) return;
  
  const data = {
    sked_id: skedId,
    person_id: personId,
    is_present: checkboxEl.checked ? 1 : 0
  };
  
  const result = await fetchAPI('mark_attendance', data, 'POST');
  
  if (result && result.ok) {
    alert('Attendance saved successfully');
  } else {
    alert(result?.message || 'Error saving attendance');
  }
};

// ==========================================
// TRAINEES
// ==========================================

async function loadTrainees() {
  try {
    const trainees = await fetchAPI('my_trainees');
    const tbody = $('#traineesTable tbody');
    
    if (!trainees || trainees.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center;padding:40px;">
            <p style="color:var(--muted);">No trainees assigned yet</p>
          </td>
        </tr>
      `;
      return;
    }
    
    tbody.innerHTML = trainees.map(t => `
      <tr>
        <td><strong>${escapeHtml(t.trainee_name)}</strong></td>
        <td>${escapeHtml(t.team_name || 'N/A')}</td>
        <td>${t.attendance_rate || 0}%</td>
        <td>${t.sessions_attended || 0}</td>
        <td>
          <span class="badge ${t.is_active == 1 ? 'active' : 'inactive'}">
            ${t.is_active == 1 ? 'Active' : 'Inactive'}
          </span>
        </td>
        <td>
          <button class="btn btn-small btn-secondary" onclick="viewTraineeDetails(${t.person_id})">View</button>
        </td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('loadTrainees error:', err);
  }
}

window.viewTraineeDetails = (personId) => {
  console.log('View trainee details:', personId);
  // TODO: Show trainee details modal
};

// ==========================================
// ACTIVITIES
// ==========================================

async function loadActivities() {
  try {
    const activities = await fetchAPI('training_activities');
    const content = $('#activitiesContent');
    
    if (!activities || activities.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training activities available</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = activities.map(a => `
      <div class="data-card" style="margin-bottom:16px;">
        <div class="data-card-header">
          <div class="data-card-title">🏋️ ${escapeHtml(a.activity_name)}</div>
          <span class="badge ${a.is_active == 1 ? 'active' : 'inactive'}">
            ${a.is_active == 1 ? 'Active' : 'Inactive'}
          </span>
        </div>
        <div class="data-card-meta">
          ⏱️ Duration: ${escapeHtml(a.duration || 'N/A')}<br>
          🔄 Repetition: ${escapeHtml(a.repetition || 'N/A')}
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('loadActivities error:', err);
  }
}

// Initialize
(async function init() {
  console.log('Trainor dashboard initialized');
  await loadOverview();
})();