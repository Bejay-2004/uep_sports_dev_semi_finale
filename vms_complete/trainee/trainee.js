const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const { person_id, sports_id } = window.TRAINEE_CONTEXT;

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
  'attendance': 'Attendance History',
  'programs': 'Training Programs',
  'performance': 'Performance Tracking'
};

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

function loadViewData(view) {
  switch(view) {
    case 'overview': loadOverview(); break;
    case 'schedule': loadSchedule(); break;
    case 'attendance': loadAttendance(); break;
    case 'programs': loadPrograms(); break;
    case 'performance': loadPerformance(); break;
  }
}

// ==========================================
// OVERVIEW
// ==========================================

// Replace the loadOverview function in trainee/trainee.js with this:

async function loadOverview() {
  try {
    const stats = await fetchAPI('trainee_stats');
    
    if (stats) {
      $('#statSessions').textContent = stats.sessions_attended || 0;
      $('#statRate').textContent = (stats.attendance_rate || 0) + '%';
      $('#statStreak').textContent = stats.streak || 0;
      $('#statTotal').textContent = stats.total_hours || 0; // Changed from statHours to statTotal
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
            ⏰ ${escapeHtml(s.start_time)}<br>
            📍 ${escapeHtml(s.location || 'TBA')}<br>
            👨‍🏫 ${escapeHtml(s.trainor_name || 'TBA')}
          </div>
        </div>
      `).join('');
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
            ⏰ ${escapeHtml(s.start_time)}<br>
            📍 ${escapeHtml(s.location || 'TBA')}<br>
            👨‍🏫 ${escapeHtml(s.trainor_name || 'TBA')}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('loadSchedule error:', err);
  }
}

// ==========================================
// ATTENDANCE
// ==========================================

async function loadAttendance() {
  try {
    const attendance = await fetchAPI('my_attendance');
    const tbody = $('#attendanceTable tbody');
    
    if (!attendance || attendance.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align:center;padding:40px;">
            <p style="color:var(--muted);">No attendance records found</p>
          </td>
        </tr>
      `;
      return;
    }
    
    tbody.innerHTML = attendance.map(a => {
      const statusClass = a.status === 'present' ? 'active' : 'inactive';
      const statusText = a.status === 'present' ? 'Present' : a.status === 'excused' ? 'Excused' : 'Absent';
      const statusIcon = a.status === 'present' ? '✅' : a.status === 'excused' ? '📝' : '❌';
      
      return `
        <tr>
          <td>${escapeHtml(a.training_date)}</td>
          <td>${escapeHtml(a.training_type)}</td>
          <td>${escapeHtml(a.duration || 'N/A')}</td>
          <td>${escapeHtml(a.trainor_name || 'N/A')}</td>
          <td>
            <span class="badge ${statusClass}">
              ${statusIcon} ${statusText}
            </span>
          </td>
        </tr>
      `;
    }).join('');
  } catch (err) {
    console.error('loadAttendance error:', err);
  }
}

// ==========================================
// PROGRAMS
// ==========================================

async function loadPrograms() {
  try {
    const programs = await fetchAPI('my_programs');
    const content = $('#programsContent');
    
    if (!programs || programs.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training programs assigned yet</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = programs.map(p => `
      <div class="data-card" style="margin-bottom:16px;">
        <div class="data-card-header">
          <div class="data-card-title">🏋️ ${escapeHtml(p.program_name)}</div>
          <span class="badge ${p.is_active == 1 ? 'active' : 'inactive'}">
            ${p.is_active == 1 ? 'Active' : 'Completed'}
          </span>
        </div>
        <div class="data-card-meta">
          ⏱️ Duration: ${escapeHtml(p.duration_weeks)} weeks<br>
          ${p.description ? escapeHtml(p.description) : 'No description'}<br>
          ${p.goals ? '🎯 Goals: ' + escapeHtml(p.goals) : ''}
        </div>
        ${p.progress ? `
          <div style="margin-top:12px;">
            <div style="font-size:12px;color:var(--muted);margin-bottom:6px;">Progress: ${p.progress}%</div>
            <div style="background:#e5e7eb;height:8px;border-radius:4px;overflow:hidden;">
              <div style="background:linear-gradient(135deg,#10b981,#059669);height:100%;width:${p.progress}%;transition:width 0.3s;"></div>
            </div>
          </div>
        ` : ''}
      </div>
    `).join('');
  } catch (err) {
    console.error('loadPrograms error:', err);
  }
}

// ==========================================
// PERFORMANCE
// ==========================================

async function loadPerformance() {
  // Placeholder - will be implemented later
  console.log('Performance tracking coming soon');
}

// Initialize
(async function init() {
  console.log('Trainee dashboard initialized (mobile-responsive)');
  await loadOverview();
})();