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

// ==========================================
// USER MANAGEMENT - ENHANCED VERSION
// Lines 197-329 - Replace these functions in your admin.js
// ==========================================

// ==========================================
// USER MANAGEMENT - COMPLETE SECTION
// Add this after line 122 in admin.js
// ==========================================

async function loadUsers() {
  const content = $('#usersContent');
  content.innerHTML = '<div class="loading">Loading users...</div>';
  
  try {
    const data = await fetchAPI('users');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No users found</div>';
      return;
    }
    
    let html = `
      <div class="view-header">
        <h2>All Users</h2>
        <button class="btn btn-primary" onclick="showUserModal()">
          <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 0a1 1 0 011 1v6h6a1 1 0 110 2H9v6a1 1 0 11-2 0V9H1a1 1 0 010-2h6V1a1 1 0 011-1z"/>
          </svg>
          Add User
        </button>
      </div>
      
      <table class="user-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Username</th>
            <th>Role Type</th>
            <th>User Role</th>
            <th>College</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
    `;
    
    data.forEach(user => {
      const fullName = `${user.f_name} ${user.l_name}`;
      const initials = `${user.f_name.charAt(0)}${user.l_name.charAt(0)}`.toUpperCase();
      const statusClass = user.is_active == 1 ? 'active' : 'inactive';
      const statusText = user.is_active == 1 ? 'Active' : 'Inactive';
      
      html += `
        <tr>
          <td>
            <div style="display: flex; align-items: center; gap: 10px;">
              <div class="user-avatar">${escapeHtml(initials)}</div>
              <div>
                <div style="font-weight: 600;">${escapeHtml(fullName)}</div>
                ${user.m_name ? `<div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(user.m_name)}</div>` : ''}
              </div>
            </div>
          </td>
          <td>${escapeHtml(user.username)}</td>
          <td>
            <span class="role-badge">${escapeHtml(user.role_type || 'N/A')}</span>
          </td>
          <td>
            <span class="role-badge">${escapeHtml(user.user_role)}</span>
          </td>
          <td>${escapeHtml(user.college_name || user.college_code || 'N/A')}</td>
          <td>
            <span class="status-dot ${statusClass}"></span>
            ${statusText}
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn-icon" onclick="editUser(${user.user_id})" title="Edit">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/>
                </svg>
              </button>
              <button class="btn-icon ${statusClass === 'active' ? 'danger' : 'success'}" 
                      onclick="toggleUser(${user.user_id}, ${user.is_active})"
                      title="${user.is_active == 1 ? 'Deactivate' : 'Activate'}">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  ${user.is_active == 1 ? 
                    '<path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>' :
                    '<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05z"/>'
                  }
                </svg>
              </button>
              <button class="btn-icon danger" onclick="deleteUser(${user.user_id})" title="Delete">
                <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                  <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    });
    
    html += `
        </tbody>
      </table>
    `;
    
    content.innerHTML = html;
    
  } catch (err) {
    console.error('loadUsers error:', err);
    content.innerHTML = '<div class="empty-state">Error loading users</div>';
  }
}

function showUserModal(id = null) {
  const isEdit = id !== null;
  const title = isEdit ? 'Edit User' : 'Add User';
  
  const modalHTML = `
    <div class="modal active" id="userModal">
      <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('userModal')">×</button>
        </div>
        <div class="modal-body">
          <form id="userForm" onsubmit="saveUser(event, ${id})">
            
            <!-- Personal Information -->
            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 8px;">Personal Information</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" id="user_f_name" required>
              </div>
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="user_l_name" required>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" id="user_m_name">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" id="user_title" placeholder="e.g., Mr., Ms., Dr.">
              </div>
              <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="user_date_birth">
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Blood Type</label>
              <select class="form-control" id="user_blood_type">
                <option value="">Select Blood Type</option>
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
            
            <!-- Academic Information -->
            <h4 style="font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 8px;">Academic Information</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">College</label>
                <select class="form-control" id="user_college_code">
                  <option value="">Select College</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Course</label>
                <input type="text" class="form-control" id="user_course" placeholder="e.g., BSCS, BSIT">
              </div>
            </div>
            
            <!-- Role and Account - EXACT DATABASE VALUES -->
            <h4 style="font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 8px;">Role & Account Information</h4>
            
            <div class="form-group">
              <label class="form-label">Role Type * (for tbl_person)</label>
              <select class="form-control" id="user_role_type" required onchange="toggleAthleteFields()">
                <option value="">Select Role Type</option>
                <option value="athlete">athlete</option>
                <option value="trainee">trainee</option>
                <option value="coach">coach</option>
                <option value="trainor">trainor</option>
                <option value="sports_director">sports_director</option>
                <option value="tournament_manager">tournament_manager</option>
                <option value="umpire">umpire</option>
                <option value="Spectator">Spectator</option>
              </select>
              <small style="color: #6b7280; font-size: 11px;">⚠️ DB values: athlete, trainee, coach, trainor, sports_director, tournament_manager, umpire, Spectator</small>
            </div>
            
            <div class="form-group">
              <label class="form-label">User Role * (for tbl_users - system access)</label>
              <select class="form-control" id="user_role" required>
                <option value="">Select User Role</option>
                <option value="system administrator">system administrator</option>
                <option value="trainor">trainor</option>
                <option value="trainee">trainee</option>
                <option value="coach">coach</option>
                <option value="athlete/player">athlete/player</option>
                <option value="sports director">sports director</option>
                <option value="umpire">umpire</option>
                <option value="Tournament manager">Tournament manager</option>
                <option value="Spectator">Spectator</option>
                <option value="scorer">scorer</option>
              </select>
              <small style="color: #6b7280; font-size: 11px;">⚠️ DB values: system administrator, trainor, trainee, coach, athlete/player, sports director, umpire, Tournament manager, Spectator, scorer</small>
            </div>
            
            <div class="form-group">
              <label class="form-label">Username *</label>
              <input type="text" class="form-control" id="user_username" required>
            </div>
            
            ${!isEdit ? `
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input type="password" class="form-control" id="user_password" required minlength="6">
            </div>
            <div class="form-group">
              <label class="form-label">Confirm Password *</label>
              <input type="password" class="form-control" id="user_password_confirm" required minlength="6">
            </div>
            ` : ''}
            
            <!-- Athlete/Player Assignment (Conditional) -->
            <div id="athleteFields" style="display: none;">
              <h4 style="font-size: 14px; font-weight: 600; margin: 20px 0 12px 0; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 8px;">Team Assignment (Optional)</h4>
              
              <div style="background: #fef3c7; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 12px; color: #92400e;">
                ℹ️ <strong>Note:</strong> You can assign this athlete to a team now, or do it later.
              </div>
              
              <div class="form-group">
                <label class="form-label">
                  <input type="checkbox" id="assign_to_team" onchange="toggleTeamFields()"> 
                  Assign to Team Now
                </label>
              </div>
              
              <div id="teamFields" style="display: none;">
                <div class="form-group">
                  <label class="form-label">Tournament</label>
                  <select class="form-control" id="user_tour_id">
                    <option value="">Select Tournament</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label class="form-label">Sport</label>
                  <select class="form-control" id="user_sports_id" onchange="loadTeamsForSport()">
                    <option value="">Select Sport</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label class="form-label">Team</label>
                  <select class="form-control" id="user_team_id">
                    <option value="">Select Team</option>
                  </select>
                </div>
                
                <div class="form-group">
                  <label class="form-label">
                    <input type="checkbox" id="user_is_captain"> 
                    Team Captain
                  </label>
                </div>
              </div>
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
  
  // Load dropdown data
  loadCollegesDropdown();
  loadTournamentsDropdown();
  loadSportsDropdown();
  
  if (isEdit) {
    loadUserData(id);
  }
}

// Toggle athlete-specific fields
function toggleAthleteFields() {
  const roleType = $('#user_role_type').value;
  const athleteFields = $('#athleteFields');
  
  // Show for "athlete" or "trainee" (exact database values)
  if (roleType === 'athlete' || roleType === 'trainee') {
    athleteFields.style.display = 'block';
  } else {
    athleteFields.style.display = 'none';
    $('#assign_to_team').checked = false;
    $('#teamFields').style.display = 'none';
  }
}

// Toggle team assignment fields
function toggleTeamFields() {
  const assignToTeam = $('#assign_to_team').checked;
  $('#teamFields').style.display = assignToTeam ? 'block' : 'none';
}

// Load colleges for dropdown
async function loadCollegesDropdown() {
  try {
    const colleges = await fetchAPI('colleges');
    const select = $('#user_college_code');
    
    if (colleges && colleges.length > 0) {
      colleges.forEach(college => {
        const option = document.createElement('option');
        option.value = college.college_code;
        option.textContent = `${college.college_code} - ${college.college_name}`;
        select.appendChild(option);
      });
    }
  } catch (err) {
    console.error('Error loading colleges:', err);
  }
}

// Load tournaments for dropdown
async function loadTournamentsDropdown() {
  try {
    const tournaments = await fetchAPI('tournaments');
    const select = $('#user_tour_id');
    
    if (tournaments && tournaments.length > 0) {
      tournaments.forEach(tour => {
        const option = document.createElement('option');
        option.value = tour.tour_id;
        option.textContent = `${tour.tour_name} (${tour.school_year})`;
        select.appendChild(option);
      });
    }
  } catch (err) {
    console.error('Error loading tournaments:', err);
  }
}

// Load sports for dropdown
async function loadSportsDropdown() {
  try {
    const sports = await fetchAPI('sports');
    const select = $('#user_sports_id');
    
    if (sports && sports.length > 0) {
      sports.forEach(sport => {
        const option = document.createElement('option');
        option.value = sport.sports_id;
        option.textContent = sport.sports_name;
        select.appendChild(option);
      });
    }
  } catch (err) {
    console.error('Error loading sports:', err);
  }
}

// Load teams for selected sport
async function loadTeamsForSport() {
  const sportsId = $('#user_sports_id').value;
  const select = $('#user_team_id');
  
  select.innerHTML = '<option value="">Select Team</option>';
  
  if (!sportsId) return;
  
  try {
    const teams = await fetchAPI('teams', { sport_id: sportsId });
    
    if (teams && teams.length > 0) {
      teams.forEach(team => {
        const option = document.createElement('option');
        option.value = team.team_id;
        option.textContent = team.team_name;
        select.appendChild(option);
      });
    }
  } catch (err) {
    console.error('Error loading teams:', err);
  }
}

// Load existing user data for editing
async function loadUserData(id) {
  const data = await fetchAPI('users');
  const user = data.find(u => u.user_id == id);
  
  if (user) {
    $('#user_f_name').value = user.f_name || '';
    $('#user_l_name').value = user.l_name || '';
    $('#user_m_name').value = user.m_name || '';
    $('#user_title').value = user.title || '';
    $('#user_date_birth').value = user.date_birth || '';
    $('#user_blood_type').value = user.blood_type || '';
    $('#user_college_code').value = user.college_code || '';
    $('#user_course').value = user.course || '';
    $('#user_role_type').value = user.role_type || '';
    $('#user_username').value = user.username || '';
    $('#user_role').value = user.user_role || '';
    
    toggleAthleteFields();
  }
}

// Save user (create or update)
async function saveUser(e, id) {
  e.preventDefault();
  
  if (!id) {
    const password = $('#user_password').value;
    const passwordConfirm = $('#user_password_confirm').value;
    
    if (password !== passwordConfirm) {
      alert('Passwords do not match!');
      return;
    }
  }
  
  const data = {
    f_name: $('#user_f_name').value.trim(),
    l_name: $('#user_l_name').value.trim(),
    m_name: $('#user_m_name').value.trim() || null,
    title: $('#user_title').value.trim() || null,
    date_birth: $('#user_date_birth').value || null,
    blood_type: $('#user_blood_type').value || null,
    college_code: $('#user_college_code').value || null,
    course: $('#user_course').value.trim() || null,
    role_type: $('#user_role_type').value,
    username: $('#user_username').value.trim(),
    user_role: $('#user_role').value
  };
  
  if (!data.role_type) {
    alert('Please select a Role Type');
    return;
  }
  
  if (!data.user_role) {
    alert('Please select a User Role');
    return;
  }
  
  if (!id) {
    data.password = $('#user_password').value;
  } else {
    data.user_id = id;
  }
  
  const roleType = $('#user_role_type').value;
  const assignToTeam = $('#assign_to_team')?.checked;
  
  if ((roleType === 'athlete/player' || roleType === 'trainee') && assignToTeam) {
    data.assign_to_team = true;
    data.team_assignment = {
      tour_id: $('#user_tour_id').value || null,
      sports_id: $('#user_sports_id').value || null,
      team_id: $('#user_team_id').value || null,
      is_captain: $('#user_is_captain').checked ? 1 : 0
    };
    
    if (!data.team_assignment.sports_id) {
      alert('Please select a sport for team assignment');
      return;
    }
    if (!data.team_assignment.team_id) {
      alert('Please select a team');
      return;
    }
  }
  
  const action = id ? 'update_user' : 'create_user';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok) {
    alert(id ? 'User updated successfully!' : 'User created successfully!');
    closeModal('userModal');
    loadUsers();
  } else {
    alert(result?.error || 'Failed to save user');
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
// END OF USER MANAGEMENT
// ==========================================

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

// ==========================================
// ENHANCED LOGS - Replace loadLogs() function in admin.js
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
    
    let html = '';
    
    data.forEach(log => {
      const isReverted = log.reverted_at !== null;
      const canRevert = log.can_revert == 1 && !isReverted;
      const hasDetails = log.old_data || log.new_data || log.target_table;
      
      html += `
        <div class="log-card ${isReverted ? 'log-reverted' : ''}" data-log-id="${log.log_id}">
          <div class="log-header">
            <div class="log-main-info">
              <div class="log-icon-wrapper">
                <div class="log-icon ${log.action || 'info'}">${getActionIcon(log.action || 'info')}</div>
              </div>
              <div class="log-details-wrapper">
                <div class="log-action-text">${escapeHtml(log.description)}</div>
                <div class="log-metadata">
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                    </svg>
                    ${escapeHtml(log.user_name || 'System')}
                  </span>
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M8.5 5.5a.5.5 0 0 0-1 0v3.362l-1.429 2.38a.5.5 0 1 0 .858.515l1.5-2.5A.5.5 0 0 0 8.5 9V5.5z"/>
                      <path d="M6.5 0a.5.5 0 0 0 0 1H7v1.07a7.001 7.001 0 0 0-3.273 12.474l-.602.602a.5.5 0 0 0 .707.708l.746-.746A6.97 6.97 0 0 0 8 16a6.97 6.97 0 0 0 3.422-.892l.746.746a.5.5 0 0 0 .707-.708l-.601-.602A7.001 7.001 0 0 0 9 2.07V1h.5a.5.5 0 0 0 0-1h-3zm1.038 3.018a6.093 6.093 0 0 1 .924 0 6 6 0 1 1-.924 0zM0 3.5c0 .753.333 1.429.86 1.887A8.035 8.035 0 0 1 4.387 1.86 2.5 2.5 0 0 0 0 3.5zM13.5 1c-.753 0-1.429.333-1.887.86a8.035 8.035 0 0 1 3.527 3.527A2.5 2.5 0 0 0 13.5 1z"/>
                    </svg>
                    ${formatDetailedTime(log.created_at)}
                  </span>
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                    </svg>
                    ${escapeHtml(log.module_name)}
                  </span>
                </div>
              </div>
            </div>
            <div class="log-actions">
              ${hasDetails ? `
                <button class="btn-icon" onclick="toggleLogDetails(${log.log_id})" title="View details">
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                  </svg>
                </button>
              ` : ''}
              ${canRevert ? `
                <button class="btn-icon btn-revert" onclick="showRevertModal(${log.log_id})" title="Revert this action">
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/>
                    <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/>
                  </svg>
                </button>
              ` : ''}
            </div>
          </div>
          
          ${isReverted ? `
            <div class="log-reverted-banner">
              ⚠️ This action was reverted on ${formatDetailedTime(log.reverted_at)}
            </div>
          ` : ''}
          
          ${hasDetails ? `
            <div class="log-details-panel" id="logDetails${log.log_id}" style="display: none;">
              <div class="log-detail-grid">
                <div class="log-detail-item">
                  <strong>Log ID:</strong> #${log.log_id}
                </div>
                <div class="log-detail-item">
                  <strong>Action Type:</strong> 
                  <span class="badge badge-${getActionBadgeClass(log.action || 'info')}">${escapeHtml(log.action || 'info')}</span>
                </div>
                ${log.target_table ? `
                  <div class="log-detail-item">
                    <strong>Target Table:</strong> ${escapeHtml(log.target_table)}
                  </div>
                ` : ''}
                ${log.target_id ? `
                  <div class="log-detail-item">
                    <strong>Target ID:</strong> ${log.target_id}
                  </div>
                ` : ''}
              </div>
              
              ${log.old_data ? `
                <div class="log-data-section">
                  <strong>📋 Previous Data (Before Change):</strong>
                  <pre class="log-data-json">${formatJSON(log.old_data)}</pre>
                </div>
              ` : ''}
              
              ${log.new_data ? `
                <div class="log-data-section">
                  <strong>📄 New Data (After Change):</strong>
                  <pre class="log-data-json">${formatJSON(log.new_data)}</pre>
                </div>
              ` : ''}
            </div>
          ` : ''}
        </div>
      `;
    });
    
    content.innerHTML = html;
  } catch (err) {
    console.error('loadLogs error:', err);
    $('#logsContent').innerHTML = '<div class="empty-state">Error loading logs</div>';
  }
}

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

async function loadLogs() {
  try {
    const filter = $('#logFilter')?.value || '';
    const data = await fetchAPI('logs', filter ? { filter } : {});
    const content = $('#logsContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = '<div class="empty-state">No activity logs found</div>';
      return;
    }
    
    let html = '';
    
    data.forEach(log => {
      const isReverted = log.reverted_at !== null;
      const canRevert = log.can_revert == 1 && !isReverted;
      const hasDetails = log.old_data || log.new_data || log.target_table;
      
      html += `
        <div class="log-card ${isReverted ? 'log-reverted' : ''}" data-log-id="${log.log_id}">
          <div class="log-header">
            <div class="log-main-info">
              <div class="log-icon-wrapper">
                <div class="log-icon ${log.action || 'info'}">${getActionIcon(log.action || 'info')}</div>
              </div>
              <div class="log-details-wrapper">
                <div class="log-action-text">${escapeHtml(log.description)}</div>
                <div class="log-metadata">
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                    </svg>
                    ${escapeHtml(log.user_name || 'System')}
                  </span>
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M8.5 5.5a.5.5 0 0 0-1 0v3.362l-1.429 2.38a.5.5 0 1 0 .858.515l1.5-2.5A.5.5 0 0 0 8.5 9V5.5z"/>
                      <path d="M6.5 0a.5.5 0 0 0 0 1H7v1.07a7.001 7.001 0 0 0-3.273 12.474l-.602.602a.5.5 0 0 0 .707.708l.746-.746A6.97 6.97 0 0 0 8 16a6.97 6.97 0 0 0 3.422-.892l.746.746a.5.5 0 0 0 .707-.708l-.601-.602A7.001 7.001 0 0 0 9 2.07V1h.5a.5.5 0 0 0 0-1h-3zm1.038 3.018a6.093 6.093 0 0 1 .924 0 6 6 0 1 1-.924 0zM0 3.5c0 .753.333 1.429.86 1.887A8.035 8.035 0 0 1 4.387 1.86 2.5 2.5 0 0 0 0 3.5zM13.5 1c-.753 0-1.429.333-1.887.86a8.035 8.035 0 0 1 3.527 3.527A2.5 2.5 0 0 0 13.5 1z"/>
                    </svg>
                    ${formatDetailedTime(log.created_at)}
                  </span>
                  <span class="log-meta-item">
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                      <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z"/>
                    </svg>
                    ${escapeHtml(log.module_name)}
                  </span>
                </div>
              </div>
            </div>
            <div class="log-actions">
              ${hasDetails ? `
                <button class="btn-icon" onclick="toggleLogDetails(${log.log_id})" title="View details">
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                  </svg>
                </button>
              ` : ''}
              ${canRevert ? `
                <button class="btn-icon btn-revert" onclick="showRevertModal(${log.log_id})" title="Revert this action">
                  <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/>
                    <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/>
                  </svg>
                </button>
              ` : ''}
            </div>
          </div>
          
          ${isReverted ? `
            <div class="log-reverted-banner">
              ⚠️ This action was reverted on ${formatDetailedTime(log.reverted_at)}
            </div>
          ` : ''}
          
          ${hasDetails ? `
            <div class="log-details-panel" id="logDetails${log.log_id}" style="display: none;">
              <div class="log-detail-grid">
                <div class="log-detail-item">
                  <strong>Log ID:</strong> #${log.log_id}
                </div>
                <div class="log-detail-item">
                  <strong>Action Type:</strong> 
                  <span class="badge badge-${getActionBadgeClass(log.action || 'info')}">${escapeHtml(log.action || 'info')}</span>
                </div>
                ${log.target_table ? `
                  <div class="log-detail-item">
                    <strong>Target Table:</strong> ${escapeHtml(log.target_table)}
                  </div>
                ` : ''}
                ${log.target_id ? `
                  <div class="log-detail-item">
                    <strong>Target ID:</strong> ${log.target_id}
                  </div>
                ` : ''}
              </div>
              
              ${log.old_data ? `
                <div class="log-data-section">
                  <strong>📋 Previous Data (Before Change):</strong>
                  <pre class="log-data-json">${formatJSON(log.old_data)}</pre>
                </div>
              ` : ''}
              
              ${log.new_data ? `
                <div class="log-data-section">
                  <strong>📄 New Data (After Change):</strong>
                  <pre class="log-data-json">${formatJSON(log.new_data)}</pre>
                </div>
              ` : ''}
            </div>
          ` : ''}
        </div>
      `;
    });
    
    content.innerHTML = html;
  } catch (err) {
    console.error('loadLogs error:', err);
    $('#logsContent').innerHTML = '<div class="empty-state">Error loading logs</div>';
  }
}

// ==========================================
// HELPER FUNCTIONS - Add to admin.js
// ==========================================

function toggleLogDetails(logId) {
  const panel = $(`#logDetails${logId}`);
  const btn = event.target.closest('button');
  
  if (panel) {
    const isHidden = panel.style.display === 'none';
    panel.style.display = isHidden ? 'block' : 'none';
    
    // Rotate icon
    if (btn) {
      const svg = btn.querySelector('svg');
      if (svg) {
        svg.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
        svg.style.transition = 'transform 0.2s';
      }
    }
  }
}

function showRevertModal(logId) {
  const modalHTML = `
    <div class="modal active" id="revertModal">
      <div class="modal-content modal-sm">
        <div class="modal-icon" style="color: #d97706; font-size: 48px;">⚠️</div>
        <h3>Revert This Action?</h3>
        <p style="margin-bottom: 16px;">This will undo the logged action and restore the previous state.</p>
        <p style="color: #dc2626; font-weight: 600; font-size: 12px;">⚠️ Warning: This operation cannot be undone!</p>
        <div class="modal-actions">
          <button class="btn btn-secondary" onclick="closeModal('revertModal')">Cancel</button>
          <button class="btn" style="background: #d97706; color: white; border-color: #d97706;" 
                  onclick="revertAction(${logId})">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/>
              <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/>
            </svg>
            Revert Action
          </button>
        </div>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modalHTML;
}

async function revertAction(logId) {
  try {
    const result = await fetchAPI('revert_action', { log_id: logId }, 'POST');
    
    if (result && result.ok) {
      closeModal('revertModal');
      alert('✅ Action reverted successfully!');
      loadLogs(); // Reload logs to show the revert
    } else {
      alert('❌ ' + (result?.error || 'Failed to revert action'));
    }
  } catch (err) {
    console.error('Revert error:', err);
    alert('❌ Error reverting action');
  }
}

function formatDetailedTime(timestamp) {
  if (!timestamp) return 'N/A';
  const date = new Date(timestamp);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);
  
  // Relative time
  let relative = '';
  if (diff < 60) relative = 'Just now';
  else if (diff < 3600) relative = Math.floor(diff / 60) + ' min ago';
  else if (diff < 86400) relative = Math.floor(diff / 3600) + ' hours ago';
  else if (diff < 604800) relative = Math.floor(diff / 86400) + ' days ago';
  else relative = date.toLocaleDateString();
  
  // Full timestamp
  const full = date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
  
  return `<span title="${full}">${relative}</span>`;
}

function getActionBadgeClass(action) {
  const classes = {
    'create': 'success',
    'update': 'info',
    'delete': 'danger',
    'activate': 'success',
    'deactivate': 'warning',
    'login': 'info',
    'logout': 'secondary',
    'revert': 'warning'
  };
  return classes[action] || 'secondary';
}

function formatJSON(jsonString) {
  try {
    const obj = JSON.parse(jsonString);
    return JSON.stringify(obj, null, 2);
  } catch (e) {
    return jsonString;
  }
}

// Update getActionIcon if it doesn't exist in your admin.js
function getActionIcon(action) {
  const icons = {
    login: '🔐',
    logout: '👋',
    create: '➕',
    update: '✏️',
    delete: '🗑️',
    activate: '✅',
    deactivate: '❌',
    revert: '↩️',
    info: '📝'
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