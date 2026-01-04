const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.ADMIN_CONTEXT.person_id;

// Navigation
$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    $$('.content-view').forEach(v => v.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
    // Update page title
    const titles = {
      overview: 'System Overview',
      users: 'User Management',
      roles: 'Roles & Permissions',
      logs: 'Activity Logs',
      tournaments: 'Tournaments',
      teams: 'Teams',
      athletes: 'Athletes',
      'sports-setup': 'Sports Setup',
      system: 'System Settings'
    };
    $('#pageTitle').textContent = titles[view] || 'Dashboard';
    
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
    case 'users': loadUsers(); break;
    case 'roles': loadRoles(); break;
    case 'logs': loadLogs(); break;
    case 'tournaments': loadTournaments(); break;
    case 'teams': loadTeams(); break;
    case 'athletes': loadAthletes(); break;
    case 'sports-setup': loadSports(); break;
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    const data = await fetchAPI('admin_stats');
    
    if (data) {
      $('#statUsers').textContent = data.total_users || 0;
      $('#statActiveUsers').textContent = data.active_users || 0;
      $('#statAthletes').textContent = data.total_athletes || 0;
      $('#statSports').textContent = data.active_sports || 0;
    }
    
    const activities = await fetchAPI('recent_activities', { limit: 10 });
    const content = $('#overviewContent');
    
    if (!activities || activities.length === 0) {
      content.innerHTML = '<div class="empty-state">No recent activities</div>';
    } else {
      content.innerHTML = activities.map(a => `
        <div class="log-entry">
          <div class="log-icon">${getActionIcon(a.action)}</div>
          <div class="log-details">
            <div class="log-action">${escapeHtml(a.description)}</div>
            <div class="log-meta">By ${escapeHtml(a.user_name)}</div>
          </div>
          <div class="log-time">${formatTime(a.created_at)}</div>
        </div>
      `).join('');
    }
  } catch (err) {
    console.error('loadOverview error:', err);
  }
}

// ==========================================
// USER MANAGEMENT
// ==========================================

async function loadUsers() {
  try {
    const data = await fetchAPI('users');
    const content = $('#usersContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No users found</div>';
      return;
    }
    
    let html = `
      <table class="user-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
    `;
    
    data.forEach(u => {
      const initial = (u.f_name || 'U')[0].toUpperCase();
      const statusClass = u.is_active == 1 ? 'active' : 'inactive';
      const statusText = u.is_active == 1 ? 'Active' : 'Inactive';
      
      html += `
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="user-avatar">${initial}</div>
              <span>${escapeHtml(u.f_name + ' ' + u.l_name)}</span>
            </div>
          </td>
          <td>${escapeHtml(u.username)}</td>
          <td><span class="role-badge">${escapeHtml(u.user_role)}</span></td>
          <td>
            <span class="status-dot ${statusClass}"></span>
            ${statusText}
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn-icon" onclick="editUser(${u.user_id})" title="Edit">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                </svg>
              </button>
              <button class="btn-icon ${u.is_active == 1 ? 'danger' : 'success'}" 
                      onclick="toggleUser(${u.user_id}, ${u.is_active})" 
                      title="${u.is_active == 1 ? 'Deactivate' : 'Activate'}">
                ${u.is_active == 1 ? '🔒' : '✓'}
              </button>
              <button class="btn-icon danger" onclick="deleteUser(${u.user_id})" title="Delete">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                  <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    });
    
    html += '</tbody></table>';
    content.innerHTML = html;
  } catch (err) {
    console.error('loadUsers error:', err);
  }
}

function showUserModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit User' : 'Add User';
  
  const modalHTML = `
    <div class="modal active" id="userModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('userModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="userForm" onsubmit="saveUser(event, ${id})">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" class="form-control" id="user_f_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" class="form-control" id="user_l_name" required>
            </div>
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" class="form-control" id="user_username" required>
            </div>
            ${!isEdit ? `
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" id="user_password" required>
            </div>
            ` : ''}
            <div class="form-group">
              <label class="form-label">Role *</label>
              <select class="form-control" id="user_role" required>
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="sports director">Sports Director</option>
                <option value="Tournament manager">Tournament Manager</option>
                <option value="coach">Coach</option>
                <option value="athlete/player">Athlete/Player</option>
                <option value="trainee">Trainee</option>
                <option value="trainor">Trainor</option>
                <option value="umpire">Umpire</option>
                <option value="scorer">Scorer</option>
                <option value="Spectator">Spectator</option>
              </select>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" onclick="closeModal('userModal')">Cancel</button>
          <button class="btn btn-primary" onclick="$('#userForm').requestSubmit()">
            ${isEdit ? 'Update' : 'Create'} User
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
  
  if (isEdit) {
    loadUserData(id);
  }
}

async function loadUserData(id) {
  const data = await fetchAPI('users');
  const user = data.find(u => u.user_id == id);
  
  if (user) {
    $('#user_f_name').value = user.f_name || '';
    $('#user_l_name').value = user.l_name || '';
    $('#user_username').value = user.username || '';
    $('#user_role').value = user.user_role || '';
  }
}

async function saveUser(e, id) {
  e.preventDefault();
  
  const data = {
    f_name: $('#user_f_name').value,
    l_name: $('#user_l_name').value,
    username: $('#user_username').value,
    user_role: $('#user_role').value
  };
  
  if (!id) {
    data.password = $('#user_password').value;
  } else {
    data.user_id = id;
  }
  
  const action = id ? 'update_user' : 'create_user';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'User updated!' : 'User created!');
    closeModal('userModal');
    loadUsers();
  }
}

async function toggleUser(id, currentStatus) {
  const newStatus = currentStatus == 1 ? 0 : 1;
  const action = newStatus == 1 ? 'activate' : 'deactivate';
  
  if (!confirm(`Are you sure you want to ${action} this user?`)) return;
  
  const result = await fetchAPI('toggle_user', { user_id: id, is_active: newStatus }, 'POST');
  
  if (result && result.ok) {
    alert(`User ${action}d!`);
    loadUsers();
  }
}

async function deleteUser(id) {
  if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
  
  const result = await fetchAPI('delete_user', { user_id: id }, 'POST');
  
  if (result && result.ok) {
    alert('User deleted!');
    loadUsers();
  }
}

function editUser(id) {
  showUserModal(id);
}

// ==========================================
// ROLES & PERMISSIONS
// ==========================================

async function loadRoles() {
  const content = $('#rolesContent');
  
  const roles = [
    { name: 'admin', description: 'Full system access', users: 0 },
    { name: 'sports director', description: 'Manage all sports', users: 0 },
    { name: 'Tournament manager', description: 'Manage tournaments', users: 0 },
    { name: 'coach', description: 'Manage team', users: 0 },
    { name: 'athlete/player', description: 'Athlete access', users: 0 },
    { name: 'Spectator', description: 'View-only access', users: 0 }
  ];
  
  try {
    const users = await fetchAPI('users');
    roles.forEach(role => {
      role.users = users.filter(u => u.user_role === role.name).length;
    });
  } catch (err) {
    console.error('Error loading role counts:', err);
  }
  
  let html = '<div class="data-grid">';
  
  roles.forEach(role => {
    html += `
      <div class="data-card">
        <div class="data-card-header">
          <div class="data-card-title">${escapeHtml(role.name)}</div>
          <span class="badge badge-active">${role.users} users</span>
        </div>
        <div class="data-card-meta">${escapeHtml(role.description)}</div>
      </div>
    `;
  });
  
  html += '</div>';
  content.innerHTML = html;
}

// ==========================================
// ACTIVITY LOGS
// ==========================================

async function loadLogs() {
  try {
    const filter = $('#logFilter')?.value || '';
    const data = await fetchAPI('logs', filter ? { filter } : {});
    const content = $('#logsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No activity logs found</div>';
      return;
    }
    
    content.innerHTML = data.map(log => `
      <div class="log-entry">
        <div class="log-icon">${getActionIcon(log.action)}</div>
        <div class="log-details">
          <div class="log-action">${escapeHtml(log.description)}</div>
          <div class="log-meta">By ${escapeHtml(log.user_name)} • ${escapeHtml(log.ip_address || 'N/A')}</div>
        </div>
        <div class="log-time">${formatTime(log.created_at)}</div>
      </div>
    `).join('');
  } catch (err) {
    console.error('loadLogs error:', err);
  }
}

$('#logFilter')?.addEventListener('change', () => loadLogs());

// ==========================================
// TOURNAMENTS (Full Access)
// ==========================================

async function loadTournaments() {
  try {
    const data = await fetchAPI('tournaments');
    const content = $('#tournamentsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No tournaments found</div>';
      return;
    }
    
    let html = '<div class="data-grid">';
    
    data.forEach(t => {
      const statusClass = t.is_active == 1 ? 'active' : 'inactive';
      const statusText = t.is_active == 1 ? 'Active' : 'Inactive';
      
      html += `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(t.tour_name)}</div>
            <span class="badge badge-${statusClass}">${statusText}</span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(t.tour_date || 'No date')} • ${escapeHtml(t.school_year)}
          </div>
          <div class="data-card-actions">
            <button class="btn btn-sm btn-secondary" onclick="editTournament(${t.tour_id})">Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deleteTournament(${t.tour_id})">Delete</button>
          </div>
        </div>
      `;
    });
    
    html += '</div>';
    content.innerHTML = html;
  } catch (err) {
    console.error('loadTournaments error:', err);
  }
}

function showTournamentModal(id = null) {
  // Same as director dashboard - reuse the modal
  const isEdit = id !== null;
  // Implementation similar to director.js
}

function editTournament(id) {
  showTournamentModal(id);
}

async function deleteTournament(id) {
  if (!confirm('Delete this tournament?')) return;
  const result = await fetchAPI('delete_tournament', { tour_id: id }, 'POST');
  if (result && result.ok) {
    alert('Tournament deleted!');
    loadTournaments();
  }
}

// ==========================================
// TEAMS & ATHLETES (Same pattern)
// ==========================================

async function loadTeams() {
  const data = await fetchAPI('teams');
  const content = $('#teamsContent');
  
  if (!data || data.length === 0) {
    content.innerHTML = '<div class="empty-state">No teams found</div>';
    return;
  }
  
  let html = '<div class="data-grid">';
  data.forEach(t => {
    html += `
      <div class="data-card">
        <div class="data-card-header">
          <div class="data-card-title">${escapeHtml(t.team_name)}</div>
        </div>
        <div class="data-card-meta">
          ⚽ ${escapeHtml(t.sports_name)} • 👥 ${t.num_players || 0} players
        </div>
      </div>
    `;
  });
  html += '</div>';
  content.innerHTML = html;
}

async function loadAthletes() {
  const data = await fetchAPI('athletes');
  const content = $('#athletesContent');
  
  if (!data || data.length === 0) {
    content.innerHTML = '<div class="empty-state">No athletes found</div>';
    return;
  }
  
  let html = '<div class="data-grid">';
  data.forEach(a => {
    html += `
      <div class="data-card">
        <div class="data-card-header">
          <div class="data-card-title">${escapeHtml(a.athlete_name)}</div>
        </div>
        <div class="data-card-meta">
          👥 ${escapeHtml(a.team_name || 'No team')} • ⚽ ${escapeHtml(a.sports_name || 'No sport')}
        </div>
      </div>
    `;
  });
  html += '</div>';
  content.innerHTML = html;
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function getActionIcon(action) {
  const icons = {
    login: '🔐',
    logout: '👋',
    create: '➕',
    update: '✏️',
    delete: '🗑️',
    activate: '✅',
    deactivate: '❌'
  };
  return icons[action] || '📝';
}

function formatTime(timestamp) {
  if (!timestamp) return 'N/A';
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);
  
  if (diff < 60) return 'Just now';
  if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
  if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
  if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
  
  return date.toLocaleDateString();
}

function closeModal(modalId) {
  const modal = $(`#${modalId}`);
  if (modal) modal.remove();
}

// User search
$('#userSearch')?.addEventListener('input', (e) => {
  const query = e.target.value.toLowerCase();
  $$('.user-table tbody tr').forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
});

// ==========================================
// SPORTS SETUP
// ==========================================

async function loadSports() {
  try {
    const data = await fetchAPI('sports');
    const container = $('#sportsContent');
    
    if (!data || data.length === 0) {
      container.innerHTML = '<div class="empty-state">No sports found</div>';
      return;
    }
    
    // Group by active/inactive
    const active = data.filter(s => s.is_active == 1);
    const inactive = data.filter(s => s.is_active == 0);
    
    let html = '';
    
    // Active sports
    if (active.length > 0) {
      html += `
        <div class="group-header">
          <div class="group-title">Active Sports</div>
          <div class="group-badge">${active.length}</div>
        </div>
        <div class="data-grid">
      `;
      
      active.forEach(sport => {
        html += renderSportCard(sport);
      });
      
      html += '</div>';
    }
    
    // Inactive sports
    if (inactive.length > 0) {
      html += `
        <div class="group-header">
          <div class="group-title">Inactive Sports</div>
          <div class="group-badge">${inactive.length}</div>
        </div>
        <div class="data-grid">
      `;
      
      inactive.forEach(sport => {
        html += renderSportCard(sport);
      });
      
      html += '</div>';
    }
    
    container.innerHTML = html;
    setupSportsSearch();
    
  } catch (err) {
    console.error('Error loading sports:', err);
    $('#sportsContent').innerHTML = '<div class="empty-state">Error loading sports</div>';
  }
}

function renderSportCard(sport) {
  const typeLabel = sport.team_individual === 'team' ? '👥 Team Sport' : '👤 Individual Sport';
  const genderLabel = sport.men_women || 'Co-ed';
  const statusBadge = sport.is_active == 1 
    ? '<span class="badge badge-active">Active</span>' 
    : '<span class="badge badge-inactive">Inactive</span>';
  
  const sportData = JSON.stringify(sport).replace(/'/g, "\\'").replace(/"/g, '&quot;');
  
  return `
    <div class="data-card" data-sport-name="${sport.sports_name.toLowerCase()}">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(sport.sports_name)}</div>
        ${statusBadge}
      </div>
      <div class="data-card-meta">
        ${typeLabel} • ${genderLabel}<br>
        ${sport.team_individual === 'team' ? `Required: ${sport.num_req_players || 'N/A'} • Reserve: ${sport.num_res_players || 'N/A'}` : ''}
        ${sport.weight_class ? `<br>Weight Class: ${sport.weight_class}` : ''}
      </div>
      <div class="data-card-actions">
        <button class="btn btn-sm" onclick='editSport(${sportData})'>
          <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
          </svg>
          Edit
        </button>
        <button class="btn btn-sm ${sport.is_active == 1 ? 'btn-danger' : 'btn-success'}" 
                onclick="toggleSport(${sport.sports_id}, ${sport.is_active == 1 ? 0 : 1})">
          ${sport.is_active == 1 ? 'Deactivate' : 'Activate'}
        </button>
      </div>
    </div>
  `;
}

function setupSportsSearch() {
  const searchInput = $('#sportsSearch');
  if (!searchInput) return;
  
  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.toLowerCase();
    const cards = document.querySelectorAll('#sportsContent .data-card');
    
    cards.forEach(card => {
      const sportName = card.dataset.sportName;
      if (sportName.includes(query)) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
}

function showSportModal(sport = null) {
  const isEdit = sport !== null;
  const modalHTML = `
    <div class="modal active" id="sportModal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${isEdit ? 'Edit Sport' : 'Add New Sport'}</h3>
          <button class="modal-close" onclick="closeSportModal()">×</button>
        </div>
        <div class="modal-body">
          <form id="sportForm" onsubmit="saveSport(event, ${isEdit})">
            <input type="hidden" id="sport_id" value="${isEdit ? sport.sports_id : ''}">
            
            <div class="form-group">
              <label class="form-label">Sport Name *</label>
              <input type="text" class="form-control" id="sports_name" 
                     value="${isEdit ? escapeHtml(sport.sports_name) : ''}" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Type *</label>
              <select class="form-control" id="team_individual" required onchange="togglePlayerFields()">
                <option value="">-- Select Type --</option>
                <option value="team" ${isEdit && sport.team_individual === 'team' ? 'selected' : ''}>Team Sport</option>
                <option value="individual" ${isEdit && sport.team_individual === 'individual' ? 'selected' : ''}>Individual Sport</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Gender Category *</label>
              <select class="form-control" id="men_women" required>
                <option value="">-- Select Category --</option>
                <option value="Men" ${isEdit && sport.men_women === 'Men' ? 'selected' : ''}>Men</option>
                <option value="Women" ${isEdit && sport.men_women === 'Women' ? 'selected' : ''}>Women</option>
                <option value="Co-ed" ${isEdit && sport.men_women === 'Co-ed' ? 'selected' : ''}>Co-ed</option>
              </select>
            </div>
            
            <div id="teamFields" style="${isEdit && sport.team_individual === 'team' ? '' : 'display:none'}">
              <div class="form-group">
                <label class="form-label">Required Players</label>
                <input type="number" class="form-control" id="num_req_players" min="1"
                       value="${isEdit && sport.num_req_players ? sport.num_req_players : ''}">
              </div>
              
              <div class="form-group">
                <label class="form-label">Reserve Players</label>
                <input type="number" class="form-control" id="num_res_players" min="0"
                       value="${isEdit && sport.num_res_players ? sport.num_res_players : ''}">
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Weight Class (Optional)</label>
              <input type="text" class="form-control" id="weight_class" 
                     value="${isEdit && sport.weight_class ? escapeHtml(sport.weight_class) : ''}" 
                     placeholder="e.g., Light, Medium, Heavy">
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeSportModal()">Cancel</button>
          <button type="submit" form="sportForm" class="btn btn-primary">
            ${isEdit ? 'Update Sport' : 'Add Sport'}
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
}

function togglePlayerFields() {
  const type = $('#team_individual').value;
  const teamFields = $('#teamFields');
  if (type === 'team') {
    teamFields.style.display = '';
  } else {
    teamFields.style.display = 'none';
  }
}

async function saveSport(e, isEdit) {
  e.preventDefault();
  
  const data = {
    sports_name: $('#sports_name').value.trim(),
    team_individual: $('#team_individual').value,
    men_women: $('#men_women').value,
    weight_class: $('#weight_class').value.trim() || null,
    num_req_players: $('#num_req_players').value || null,
    num_res_players: $('#num_res_players').value || null
  };
  
  if (isEdit) {
    data.sports_id = parseInt($('#sport_id').value);
  }
  
  try {
    const action = isEdit ? 'update_sport' : 'create_sport';
    const result = await fetchAPI(action, data, 'POST');
    
    if (result.ok) {
      closeSportModal();
      loadSports();
      alert(isEdit ? 'Sport updated successfully' : 'Sport added successfully');
    } else {
      alert(result.error || 'Failed to save sport');
    }
  } catch (err) {
    console.error('Error saving sport:', err);
    alert('Error saving sport');
  }
}

function editSport(sport) {
  showSportModal(sport);
}

async function toggleSport(sports_id, is_active) {
  const action = is_active == 1 ? 'activate' : 'deactivate';
  if (!confirm(`Are you sure you want to ${action} this sport?`)) return;
  
  try {
    const result = await fetchAPI('toggle_sport', { sports_id, is_active }, 'POST');
    
    if (result.ok) {
      loadSports();
      alert(`Sport ${action}d successfully`);
    } else {
      alert(result.error || `Failed to ${action} sport`);
    }
  } catch (err) {
    console.error(`Error ${action}ing sport:`, err);
    alert(`Error ${action}ing sport`);
  }
}

function closeSportModal() {
  $('#modalContainer').innerHTML = '';
}

// Initialize
(async function init() {
  console.log('Admin dashboard initialized');
  await loadOverview();
})();