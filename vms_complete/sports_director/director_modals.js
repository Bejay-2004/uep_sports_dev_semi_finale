// ==========================================
// MODAL MANAGEMENT
// ==========================================

function closeModal() {
  $('#modalContainer').innerHTML = '';
}

// ==========================================
// ATHLETE MODAL - Complete Form
// ==========================================

function showAthleteModal(athlete = null) {
  const isEdit = !!athlete;
  
  const modal = `
    <div class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${isEdit ? 'Edit Athlete' : 'Add New Athlete'}</h3>
          <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form onsubmit="saveAthlete(event, ${isEdit})" id="athleteForm">
          <div class="modal-body">
            
            <!-- Personal Information -->
            <h4 style="margin:0 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Personal Information</h4>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" name="l_name" value="${athlete?.l_name || ''}" required>
              </div>
              
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" name="f_name" value="${athlete?.f_name || ''}" required>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" name="m_name" value="${athlete?.m_name || ''}">
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="form-group">
                <label class="form-label">Title</label>
                <select class="form-control" name="title">
                  <option value="">None</option>
                  <option value="Mr." ${athlete?.title === 'Mr.' ? 'selected' : ''}>Mr.</option>
                  <option value="Ms." ${athlete?.title === 'Ms.' ? 'selected' : ''}>Ms.</option>
                  <option value="Mrs." ${athlete?.title === 'Mrs.' ? 'selected' : ''}>Mrs.</option>
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Date of Birth *</label>
                <input type="date" class="form-control" name="date_birth" value="${athlete?.date_birth || ''}" required>
              </div>
            </div>
            
            <div class="form-group">
              <label class="form-label">Blood Type</label>
              <select class="form-control" name="blood_type">
                <option value="">Select Blood Type</option>
                <option value="A+" ${athlete?.blood_type === 'A+' ? 'selected' : ''}>A+</option>
                <option value="A-" ${athlete?.blood_type === 'A-' ? 'selected' : ''}>A-</option>
                <option value="B+" ${athlete?.blood_type === 'B+' ? 'selected' : ''}>B+</option>
                <option value="B-" ${athlete?.blood_type === 'B-' ? 'selected' : ''}>B-</option>
                <option value="AB+" ${athlete?.blood_type === 'AB+' ? 'selected' : ''}>AB+</option>
                <option value="AB-" ${athlete?.blood_type === 'AB-' ? 'selected' : ''}>AB-</option>
                <option value="O+" ${athlete?.blood_type === 'O+' ? 'selected' : ''}>O+</option>
                <option value="O-" ${athlete?.blood_type === 'O-' ? 'selected' : ''}>O-</option>
              </select>
            </div>
            
            <!-- Academic Information -->
            <h4 style="margin:20px 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Academic Information</h4>
            
            <div class="form-group">
              <label class="form-label">College Code *</label>
              <select class="form-control" name="college_code" required>
                <option value="">Select College</option>
                <option value="CAS" ${athlete?.college_code === 'CAS' ? 'selected' : ''}>CAS - College of Arts and Sciences</option>
                <option value="CED" ${athlete?.college_code === 'CED' ? 'selected' : ''}>CED - College of Education</option>
                <option value="COE" ${athlete?.college_code === 'COE' ? 'selected' : ''}>COE - College of Engineering</option>
                <option value="CICS" ${athlete?.college_code === 'CICS' ? 'selected' : ''}>CICS - College of Informatics and Computing Sciences</option>
                <option value="CBMA" ${athlete?.college_code === 'CBMA' ? 'selected' : ''}>CBMA - College of Business Management and Accountancy</option>
                <option value="CON" ${athlete?.college_code === 'CON' ? 'selected' : ''}>CON - College of Nursing</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Course *</label>
              <input type="text" class="form-control" name="course" value="${athlete?.course || ''}" placeholder="e.g., BSIT, BSN, BSBA" required>
            </div>
            
            <!-- Sport Assignment -->
            <h4 style="margin:20px 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Sport Assignment</h4>
            
            <div class="form-group">
              <label class="form-label">Sport *</label>
              <select class="form-control" name="sports_id" id="athleteSportSelect" required>
                <option value="">Select Sport</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Team *</label>
              <select class="form-control" name="team_id" id="athleteTeamSelect" required disabled>
                <option value="">Select Sport First</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">
                <input type="checkbox" name="is_captain" ${athlete?.is_captain ? 'checked' : ''}>
                Team Captain
              </label>
            </div>
            
            ${isEdit ? `<input type="hidden" name="person_id" value="${athlete.person_id}">` : ''}
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Athlete</button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modal;
  
  // Load sports for dropdown
  loadSportsDropdown('athleteSportSelect');
  
  // Setup team loading when sport changes
  $('#athleteSportSelect').addEventListener('change', function() {
    const sportId = this.value;
    const teamSelect = $('#athleteTeamSelect');
    
    if (sportId) {
      teamSelect.disabled = false;
      loadTeamsDropdown('athleteTeamSelect', sportId);
    } else {
      teamSelect.disabled = true;
      teamSelect.innerHTML = '<option value="">Select Sport First</option>';
    }
  });
  
  // If editing, load the athlete's current sport and team
  if (isEdit && athlete.sports_id) {
    setTimeout(() => {
      $('#athleteSportSelect').value = athlete.sports_id;
      $('#athleteSportSelect').dispatchEvent(new Event('change'));
      setTimeout(() => {
        $('#athleteTeamSelect').value = athlete.team_id;
      }, 300);
    }, 200);
  }
}

async function saveAthlete(event, isEdit) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  const data = {
    l_name: formData.get('l_name'),
    f_name: formData.get('f_name'),
    m_name: formData.get('m_name'),
    title: formData.get('title'),
    date_birth: formData.get('date_birth'),
    blood_type: formData.get('blood_type'),
    college_code: formData.get('college_code'),
    course: formData.get('course'),
    role_type: 'athlete/player',
    sports_id: formData.get('sports_id'),
    team_id: formData.get('team_id'),
    is_captain: formData.get('is_captain') ? 1 : 0
  };
  
  if (isEdit) {
    data.person_id = formData.get('person_id');
  }
  
  const action = isEdit ? 'update_athlete' : 'create_athlete';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok !== false) {
    closeModal();
    loadAthletes();
    showToast(isEdit ? 'Athlete updated successfully' : 'Athlete created successfully', 'success');
  } else {
    showToast('Error saving athlete: ' + (result?.error || 'Unknown error'), 'error');
  }
}

// ==========================================
// TEAM MODAL - Complete Form
// ==========================================

function showTeamModal(team = null) {
  const isEdit = !!team;
  
  const modal = `
    <div class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h3>${isEdit ? 'Edit Team' : 'Add New Team'}</h3>
          <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form onsubmit="saveTeam(event, ${isEdit})" id="teamForm">
          <div class="modal-body">
            
            <!-- Team Information -->
            <h4 style="margin:0 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Team Information</h4>
            
            <div class="form-group">
              <label class="form-label">Team Name *</label>
              <input type="text" class="form-control" name="team_name" value="${team?.team_name || ''}" placeholder="e.g., Wildcats, Warriors" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Sport *</label>
              <select class="form-control" name="sports_id" id="teamSportSelect" required>
                <option value="">Select Sport</option>
              </select>
            </div>
            
            <!-- Coaching Staff -->
            <h4 style="margin:20px 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Coaching Staff (Optional)</h4>
            
            <div class="form-group">
              <label class="form-label">Head Coach</label>
              <select class="form-control" name="coach_id" id="coachSelect">
                <option value="">Select Coach</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Assistant Coach</label>
              <select class="form-control" name="asst_coach_id" id="asstCoachSelect">
                <option value="">Select Assistant Coach</option>
              </select>
            </div>
            
            <!-- Training Staff -->
            <h4 style="margin:20px 0 12px 0;font-size:14px;font-weight:700;color:var(--text);border-bottom:2px solid var(--line);padding-bottom:8px;">Training Staff (Optional)</h4>
            
            <div class="form-group">
              <label class="form-label">Trainor 1</label>
              <select class="form-control" name="trainor1_id" id="trainor1Select">
                <option value="">Select Trainor</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Trainor 2</label>
              <select class="form-control" name="trainor2_id" id="trainor2Select">
                <option value="">Select Trainor</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Trainor 3</label>
              <select class="form-control" name="trainor3_id" id="trainor3Select">
                <option value="">Select Trainor</option>
              </select>
            </div>
            
            ${isEdit ? `<input type="hidden" name="team_id" value="${team.team_id}">` : ''}
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Team</button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  $('#modalContainer').innerHTML = modal;
  
  // Load dropdowns
  loadSportsDropdown('teamSportSelect');
  loadCoachesDropdown('coachSelect');
  loadCoachesDropdown('asstCoachSelect');
  loadTrainorsDropdown('trainor1Select');
  loadTrainorsDropdown('trainor2Select');
  loadTrainorsDropdown('trainor3Select');
  
  // If editing, set current values
  if (isEdit) {
    setTimeout(() => {
      if (team.sports_id) $('#teamSportSelect').value = team.sports_id;
      if (team.coach_id) $('#coachSelect').value = team.coach_id;
      if (team.asst_coach_id) $('#asstCoachSelect').value = team.asst_coach_id;
      if (team.trainor1_id) $('#trainor1Select').value = team.trainor1_id;
      if (team.trainor2_id) $('#trainor2Select').value = team.trainor2_id;
      if (team.trainor3_id) $('#trainor3Select').value = team.trainor3_id;
    }, 300);
  }
}

async function saveTeam(event, isEdit) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  const data = {
    team_name: formData.get('team_name'),
    sports_id: formData.get('sports_id'),
    coach_id: formData.get('coach_id') || null,
    asst_coach_id: formData.get('asst_coach_id') || null,
    trainor1_id: formData.get('trainor1_id') || null,
    trainor2_id: formData.get('trainor2_id') || null,
    trainor3_id: formData.get('trainor3_id') || null
  };
  
  if (isEdit) {
    data.team_id = formData.get('team_id');
  }
  
  const action = isEdit ? 'update_team' : 'create_team';
  const result = await fetchAPI(action, data, 'POST');
  
  if (result && result.ok !== false) {
    closeModal();
    loadTeams();
    showToast(isEdit ? 'Team updated successfully' : 'Team created successfully', 'success');
  } else {
    showToast('Error saving team: ' + (result?.error || 'Unknown error'), 'error');
  }
}

// ==========================================
// HELPER FUNCTIONS - Load Dropdowns
// ==========================================

async function loadSportsDropdown(selectId) {
  const sports = await fetchAPI('sports');
  const select = $('#' + selectId);
  
  if (sports && sports.length > 0) {
    const currentValue = select.value;
    select.innerHTML = '<option value="">Select Sport</option>' +
      sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
    if (currentValue) select.value = currentValue;
  }
}

async function loadTeamsDropdown(selectId, sportId) {
  const teams = await fetchAPI('teams', { sport_id: sportId });
  const select = $('#' + selectId);
  
  if (teams && teams.length > 0) {
    select.innerHTML = '<option value="">Select Team</option>' +
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  } else {
    select.innerHTML = '<option value="">No teams available</option>';
  }
}

async function loadCoachesDropdown(selectId) {
  const coaches = await fetchAPI('staff', { role: 'coach' });
  const select = $('#' + selectId);
  
  if (coaches && coaches.length > 0) {
    select.innerHTML = '<option value="">Select Coach</option>' +
      coaches.map(c => `<option value="${c.person_id}">${escapeHtml(c.full_name)}</option>`).join('');
  }
}

async function loadTrainorsDropdown(selectId) {
  const trainors = await fetchAPI('staff', { role: 'trainor' });
  const select = $('#' + selectId);
  
  if (trainors && trainors.length > 0) {
    select.innerHTML = '<option value="">Select Trainor</option>' +
      trainors.map(t => `<option value="${t.person_id}">${escapeHtml(t.full_name)}</option>`).join('');
  }
}

// ==========================================
// TOAST NOTIFICATIONS
// ==========================================

function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 10000;
    font-size: 14px;
    font-weight: 600;
    animation: slideIn 0.3s ease-out;
  `;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(400px); opacity: 0; }
  }
`;
document.head.appendChild(style);