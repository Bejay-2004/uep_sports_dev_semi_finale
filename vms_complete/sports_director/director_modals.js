// ==========================================
// MODAL MANAGEMENT
// ==========================================

function closeModal() {
  $('#modalContainer').innerHTML = '';
  // Also remove any dynamically added modals
  document.querySelectorAll('.modal').forEach(m => {
    if (m.id !== 'logoutModal') m.remove();
  });
}

// ==========================================
// ATHLETE CONTEXT SELECTION MODAL
// ==========================================

async function showAthleteContextModal() {
  try {
    // Fetch tournaments, teams, sports, and existing athletes
    const [tournaments, teams, sports, athletes] = await Promise.all([
      fetchAPI('tournaments'),
      fetchAPI('teams'),
      fetchAPI('sports'),
      fetchAPI('athletes') // Get all athletes
    ]);
    
    const modal = `
      <div class="modal active" id="athleteContextModal">
        <div class="modal-content" style="max-width: 600px;">
          <div class="modal-header">
            <h3>➕ Add Athlete</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
          </div>
          <form onsubmit="proceedToAthleteSelection(event)" id="contextForm">
            <div class="modal-body">
              
              <!-- Selection Type -->
              <div class="form-group">
                <label class="form-label">Choose Option *</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                  <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f0f9ff; border: 2px solid #3b82f6; border-radius: 8px; cursor: pointer;">
                    <input type="radio" name="athlete_option" value="existing" checked onchange="toggleAthleteOption()" style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 600; color: #1e40af;">Select Existing</span>
                  </label>
                  <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer;">
                    <input type="radio" name="athlete_option" value="new" onchange="toggleAthleteOption()" style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 600; color: #374151;">Create New</span>
                  </label>
                </div>
              </div>
              
              <!-- Existing Athlete Selection -->
              <div id="existingAthleteSection" style="display: block;">
                <div class="form-group">
                  <label class="form-label">Select Athlete *</label>
                  <select class="form-control" name="person_id" id="select_person_id">
                    <option value="">Choose an athlete...</option>
                    ${athletes && athletes.length > 0 ? athletes.map(a => 
                      `<option value="${a.person_id}">
                        ${escapeHtml(a.athlete_name)} - ${escapeHtml(a.college_code || 'N/A')} - ${escapeHtml(a.course || 'N/A')}
                      </option>`
                    ).join('') : '<option value="" disabled>No athletes available</option>'}
                  </select>
                  <small style="color: #6b7280; font-size: 11px; margin-top: 4px; display: block;">
                    🔍 Showing all registered athletes in the system
                  </small>
                </div>
              </div>
              
              <!-- Tournament/Team/Sport Context (always shown) -->
              <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
              <p style="color: var(--text-muted); margin-bottom: 16px; font-size: 13px;">
                📋 Select tournament, team, and sport:
              </p>
              
              <div class="form-group">
                <label class="form-label">Tournament *</label>
                <select class="form-control" name="tour_id" id="context_tour_id" required onchange="updateContextTeams()">
                  <option value="">Select Tournament</option>
                  ${tournaments.filter(t => t.is_active == 1).map(t => 
                    `<option value="${t.tour_id}">${escapeHtml(t.tour_name)} (${escapeHtml(t.school_year)})</option>`
                  ).join('')}
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Team *</label>
                <select class="form-control" name="team_id" id="context_team_id" required disabled onchange="updateContextSports()">
                  <option value="">Select Team</option>
                  ${teams.filter(t => t.is_active == 1).map(t => 
                    `<option value="${t.team_id}" data-tour-id="">${escapeHtml(t.team_name)}</option>`
                  ).join('')}
                </select>
              </div>
              
              <div class="form-group">
                <label class="form-label">Sport *</label>
                <select class="form-control" name="sports_id" id="context_sports_id" required disabled>
                  <option value="">Select Sport</option>
                  ${sports.filter(s => s.is_active == 1).map(s => 
                    `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`
                  ).join('')}
                </select>
              </div>
            </div>
            
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
              <button type="submit" class="btn btn-primary">
                <span id="submitButtonText">Add Existing Athlete</span> →
              </button>
            </div>
          </form>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = modal;
    
    // Store data for dynamic updates
    window.contextModalData = { tournaments, teams, sports, athletes };
    
  } catch (error) {
    console.error('Error loading athlete context modal:', error);
    showToast('❌ Error loading form', 'error');
  }
}

function toggleAthleteOption() {
  const option = document.querySelector('input[name="athlete_option"]:checked').value;
  const existingSection = $('#existingAthleteSection');
  const personSelect = $('#select_person_id');
  const submitButton = $('#submitButtonText');
  
  if (option === 'existing') {
    existingSection.style.display = 'block';
    personSelect.required = true;
    submitButton.textContent = 'Add Existing Athlete';
  } else {
    existingSection.style.display = 'none';
    personSelect.required = false;
    submitButton.textContent = 'Create New Athlete';
  }
}

async function updateContextTeams() {
  const tourSelect = $('#context_tour_id');
  const teamSelect = $('#context_team_id');
  const sportsSelect = $('#context_sports_id');
  
  if (!tourSelect.value) {
    teamSelect.disabled = true;
    sportsSelect.disabled = true;
    teamSelect.innerHTML = '<option value="">Select Team</option>';
    sportsSelect.innerHTML = '<option value="">Select Sport</option>';
    return;
  }
  
  try {
    // Fetch teams registered in this tournament
    const tournamentTeams = await fetchAPI('get_tournament_teams', { tour_id: tourSelect.value });
    
    if (!tournamentTeams || tournamentTeams.length === 0) {
      teamSelect.innerHTML = '<option value="">No teams in this tournament</option>';
      teamSelect.disabled = true;
      sportsSelect.disabled = true;
      showToast('⚠️ No teams registered in this tournament yet', 'warning');
      return;
    }
    
    teamSelect.innerHTML = '<option value="">Select Team</option>' +
      tournamentTeams.map(t => 
        `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`
      ).join('');
    teamSelect.disabled = false;
    
    // Reset sports
    sportsSelect.innerHTML = '<option value="">Select Sport</option>';
    sportsSelect.disabled = true;
    
  } catch (error) {
    console.error('Error loading teams:', error);
    showToast('❌ Error loading teams', 'error');
  }
}

async function updateContextSports() {
  const tourSelect = $('#context_tour_id');
  const teamSelect = $('#context_team_id');
  const sportsSelect = $('#context_sports_id');
  
  if (!tourSelect.value || !teamSelect.value) {
    sportsSelect.disabled = true;
    sportsSelect.innerHTML = '<option value="">Select Sport</option>';
    return;
  }
  
  try {
    // Fetch sports for this team in this tournament
    const teamSports = await fetchAPI('get_team_sports', { 
      tour_id: tourSelect.value, 
      team_id: teamSelect.value 
    });
    
    if (!teamSports || teamSports.length === 0) {
      sportsSelect.innerHTML = '<option value="">No sports assigned to this team</option>';
      sportsSelect.disabled = true;
      showToast('⚠️ No sports assigned to this team yet', 'warning');
      return;
    }
    
    sportsSelect.innerHTML = '<option value="">Select Sport</option>' +
      teamSports.map(s => 
        `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`
      ).join('');
    sportsSelect.disabled = false;
    
  } catch (error) {
    console.error('Error loading sports:', error);
    showToast('❌ Error loading sports', 'error');
  }
}

async function proceedToAthleteSelection(event) {
  event.preventDefault();
  
  const form = event.target;
  const option = document.querySelector('input[name="athlete_option"]:checked').value;
  const tourId = form.tour_id.value;
  const teamId = form.team_id.value;
  const sportsId = form.sports_id.value;
  
  if (!tourId || !teamId || !sportsId) {
    showToast('❌ Please select tournament, team, and sport', 'error');
    return;
  }
  
  if (option === 'existing') {
    // Add existing athlete to the tournament/team/sport
    const personId = form.person_id.value;
    
    if (!personId) {
      showToast('❌ Please select an athlete', 'error');
      return;
    }
    
    try {
      const result = await fetchAPI('add_existing_athlete', {
        person_id: personId,
        tour_id: tourId,
        team_id: teamId,
        sports_id: sportsId
      }, 'POST');
      
      if (result && result.ok !== false) {
        closeModal();
        showToast('✅ Athlete added successfully', 'success');
        
        // Reload the athletes view if active
        const athletesView = document.querySelector('#athletes-view');
        if (athletesView && athletesView.classList.contains('active')) {
          if (typeof loadAthletes === 'function') {
            loadAthletes();
          }
        }
        
        // Reload sport athletes if in that context
        if (window.currentContext && typeof loadSportAthletes === 'function') {
          const context = window.currentContext;
          if (context.tour_id && context.team_id && context.sports_id) {
            loadSportAthletes(context.tour_id, context.team_id, context.sports_id);
          }
        }
      } else {
        showToast('❌ Error adding athlete: ' + (result?.error || 'Unknown error'), 'error');
      }
    } catch (error) {
      console.error('Error adding existing athlete:', error);
      showToast('❌ Error adding athlete', 'error');
    }
  } else {
    // Close context modal and open athlete creation form
    closeModal();
    showAthleteModal(null, tourId, teamId, sportsId);
  }
}

// ==========================================
// COMPREHENSIVE TOURNAMENT VIEW
// ==========================================

async function showComprehensiveTournamentView(tourId) {
  try {
    const data = await fetchAPI('get_tournament_comprehensive', { tour_id: tourId });
    
    if (!data || !data.ok) {
      showToast('Error loading tournament data', 'error');
      return;
    }
    
    const tournament = data.tournament;
    let teams = data.teams;
    
    // Apply team filter if selected
    if (window.currentFilters && window.currentFilters.team) {
      teams = teams.filter(t => t.team_id == window.currentFilters.team);
    }
    
    // Apply sport filter if selected - filter sports within each team
    if (window.currentFilters && window.currentFilters.sport) {
      teams = teams.map(team => ({
        ...team,
        sports: team.sports.filter(s => s.sports_id == window.currentFilters.sport)
      })).filter(team => team.sports.length > 0); // Remove teams with no matching sports
    }
    
    // Calculate total statistics (after filtering)
    let totalAthletes = 0;
    let totalSports = 0;
    const sportsSet = new Set();
    
    teams.forEach(team => {
      team.sports.forEach(sport => {
        sportsSet.add(sport.sports_id);
        totalAthletes += sport.athletes.length;
      });
      totalSports += team.sports.length;
    });
    
    // Show filter indicator if filters are active
    let filterIndicator = '';
    if (window.currentFilters) {
      const activeFilters = [];
      if (window.currentFilters.team) activeFilters.push('Team filtered');
      if (window.currentFilters.sport) activeFilters.push('Sport filtered');
      if (activeFilters.length > 0) {
        filterIndicator = `<div style="background: #fef3c7; padding: 8px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #92400e;">
          🔍 Active filters: ${activeFilters.join(', ')}
        </div>`;
      }
    }
    
    let html = `
      <div class="modal active" id="comprehensiveModal" style="overflow-y: auto;">
        <div class="modal-content" style="max-width: 1200px; max-height: 90vh;">
          <div class="modal-header">
            <h3>📋 ${escapeHtml(tournament.tour_name)} - Complete Overview</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
          </div>
          <div class="modal-body" style="padding: 20px;">
            
            ${filterIndicator}
            
            <!-- Tournament Info Header -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 24px; border-radius: 12px; color: white; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
              <h2 style="margin: 0 0 12px 0; font-size: 28px; font-weight: 700;">${escapeHtml(tournament.tour_name)}</h2>
              <p style="margin: 0 0 16px 0; opacity: 0.9; font-size: 14px;">School Year ${escapeHtml(tournament.school_year)}</p>
              
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-top: 20px;">
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px; border-radius: 10px;">
                  <div style="opacity: 0.9; font-size: 12px; margin-bottom: 4px;">📅 Tournament Date</div>
                  <div style="font-size: 18px; font-weight: 600;">${escapeHtml(tournament.tour_date || 'TBD')}</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px; border-radius: 10px;">
                  <div style="opacity: 0.9; font-size: 12px; margin-bottom: 4px;">🏆 Teams</div>
                  <div style="font-size: 18px; font-weight: 600;">${teams.length}</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px; border-radius: 10px;">
                  <div style="opacity: 0.9; font-size: 12px; margin-bottom: 4px;">⚽ Sports</div>
                  <div style="font-size: 18px; font-weight: 600;">${sportsSet.size}</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px; border-radius: 10px;">
                  <div style="opacity: 0.9; font-size: 12px; margin-bottom: 4px;">👥 Athletes</div>
                  <div style="font-size: 18px; font-weight: 600;">${totalAthletes}</div>
                </div>
                <div style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 14px; border-radius: 10px;">
                  <div style="opacity: 0.9; font-size: 12px; margin-bottom: 4px;">Status</div>
                  <div style="font-size: 18px; font-weight: 600;">${tournament.is_active == 1 ? '✅ Active' : '⏸️ Inactive'}</div>
                </div>
              </div>
            </div>
    `;
    
    if (teams.length === 0) {
      html += '<div class="empty-state" style="padding: 60px 20px; text-align: center; color: #9ca3af;">No teams match the selected filters.</div>';
    } else {
      // Render each team
      teams.forEach((team, teamIdx) => {
        const teamTotalAthletes = team.sports.reduce((sum, sport) => sum + sport.athletes.length, 0);
        
        html += `
          <div style="background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #f3f4f6;">
              <div>
                <h3 style="margin: 0 0 6px 0; font-size: 22px; color: #111827; font-weight: 700;">
                  🏆 ${escapeHtml(team.team_name)}
                </h3>
                <p style="margin: 0; font-size: 13px; color: #6b7280;">
                  Registered: ${escapeHtml(team.registration_date || 'N/A')}
                </p>
              </div>
              <div style="text-align: right;">
                <div style="background: #f3f4f6; padding: 8px 16px; border-radius: 8px; display: inline-block;">
                  <div style="font-size: 11px; color: #6b7280; margin-bottom: 2px;">Total Athletes</div>
                  <div style="font-size: 20px; font-weight: 700; color: #111827;">${teamTotalAthletes}</div>
                </div>
              </div>
            </div>
        `;
        
        if (team.sports.length === 0) {
          html += '<div style="padding: 30px; text-align: center; color: #9ca3af; background: #f9fafb; border-radius: 8px;">No sports assigned to this team yet.</div>';
        } else {
          // Render each sport
          team.sports.forEach((sport, sportIdx) => {
            const totalAthletes = sport.athletes.length;
            const captains = sport.athletes.filter(a => a.is_captain).length;
            
            html += `
              <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                  <h4 style="margin: 0; font-size: 18px; color: #374151; font-weight: 600;">
                    ⚽ ${escapeHtml(sport.sports_name)}
                  </h4>
                  <div style="display: flex; gap: 8px; align-items: center;">
                    <span style="background: white; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #6b7280; border: 1px solid #e5e7eb;">
                      ${totalAthletes} Athlete${totalAthletes !== 1 ? 's' : ''}
                    </span>
                    ${captains > 0 ? `<span style="background: #fef3c7; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; color: #92400e; border: 1px solid #fde68a;">⭐ ${captains} Captain${captains !== 1 ? 's' : ''}</span>` : ''}
                  </div>
                </div>
                
                <!-- Staff Section -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; padding: 16px; background: white; border-radius: 8px; border: 1px solid #e5e7eb;">
                  <div>
                    <div style="font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">👨‍🏫 HEAD COACH</div>
                    <div style="font-size: 13px; font-weight: 600; color: ${sport.coach_name ? '#111827' : '#9ca3af'};">${escapeHtml(sport.coach_name || 'Not assigned')}</div>
                  </div>
                  <div>
                    <div style="font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">👨‍💼 ASSISTANT COACH</div>
                    <div style="font-size: 13px; font-weight: 600; color: ${sport.asst_coach_name ? '#111827' : '#9ca3af'};">${escapeHtml(sport.asst_coach_name || 'Not assigned')}</div>
                  </div>
                  <div>
                    <div style="font-size: 11px; color: #6b7280; margin-bottom: 4px; font-weight: 600;">🎯 TOURNAMENT MANAGER</div>
                    <div style="font-size: 13px; font-weight: 600; color: ${sport.tournament_manager_name ? '#111827' : '#9ca3af'};">${escapeHtml(sport.tournament_manager_name || 'Not assigned')}</div>
                  </div>
                </div>
                
                <!-- Trainors -->
                ${sport.trainor1_name || sport.trainor2_name || sport.trainor3_name ? `
                  <div style="padding: 12px 16px; background: white; border-radius: 8px; margin-bottom: 16px; border: 1px solid #e5e7eb;">
                    <div style="font-size: 11px; color: #6b7280; margin-bottom: 8px; font-weight: 600;">🏋️ TRAINORS</div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                      ${sport.trainor1_name ? `<span style="font-size: 13px; color: #111827; font-weight: 500;">• ${escapeHtml(sport.trainor1_name)}</span>` : ''}
                      ${sport.trainor2_name ? `<span style="font-size: 13px; color: #111827; font-weight: 500;">• ${escapeHtml(sport.trainor2_name)}</span>` : ''}
                      ${sport.trainor3_name ? `<span style="font-size: 13px; color: #111827; font-weight: 500;">• ${escapeHtml(sport.trainor3_name)}</span>` : ''}
                    </div>
                  </div>
                ` : ''}
                
                <!-- Athletes Roster -->
                <div style="background: white; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb;">
                  <h5 style="margin: 0 0 12px 0; font-size: 14px; color: #374151; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">👥 Athletes Roster</h5>
                  ${sport.athletes.length === 0 ? 
                    '<div style="padding: 20px; text-align: center; color: #9ca3af; font-size: 13px; background: #f9fafb; border-radius: 6px;">No athletes registered yet</div>' :
                    `<div style="display: grid; gap: 10px;">
                      ${sport.athletes.map(athlete => {
                        const age = athlete.date_birth ? new Date().getFullYear() - new Date(athlete.date_birth).getFullYear() : null;
                        return `
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; transition: all 0.2s;">
                          <div style="flex: 1;">
                            <div style="font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 4px;">
                              ${athlete.is_captain ? '⭐ ' : ''}${escapeHtml(athlete.athlete_name)}
                              ${athlete.is_captain ? '<span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 10px; margin-left: 6px; font-weight: 700;">CAPTAIN</span>' : ''}
                            </div>
                            <div style="font-size: 12px; color: #6b7280; display: flex; gap: 12px; flex-wrap: wrap;">
                              <span>🏫 ${escapeHtml(athlete.college_code || 'N/A')}</span>
                              <span>📚 ${escapeHtml(athlete.course || 'N/A')}</span>
                              ${age ? `<span>🎂 ${age} yrs</span>` : ''}
                              ${athlete.scholarship_name ? `<span>🎓 ${escapeHtml(athlete.scholarship_name)}</span>` : ''}
                            </div>
                          </div>
                          <div style="display: flex; gap: 12px; font-size: 12px; color: #6b7280; font-weight: 500;">
                            ${athlete.height && athlete.height > 0 ? `<span style="background: white; padding: 4px 10px; border-radius: 6px; border: 1px solid #e5e7eb;">📏 ${athlete.height}cm</span>` : ''}
                            ${athlete.weight && athlete.weight > 0 ? `<span style="background: white; padding: 4px 10px; border-radius: 6px; border: 1px solid #e5e7eb;">⚖️ ${athlete.weight}kg</span>` : ''}
                          </div>
                        </div>
                      `}).join('')}
                    </div>`
                  }
                </div>
              </div>
            `;
          });
        }
        
        html += '</div>'; // Close team card
      });
    }
    
    html += `
          </div>
          <div class="modal-footer" style="background: #f9fafb;">
            <button class="btn btn-primary" onclick="printTournamentOverview()">
              🖨️ Print Overview
            </button>
            <button class="btn btn-secondary" onclick="closeModal()">Close</button>
          </div>
        </div>
      </div>
    `;
    
    $('#modalContainer').innerHTML = html;
    
  } catch (error) {
    console.error('Error in showComprehensiveTournamentView:', error);
    showToast('Error loading comprehensive view', 'error');
  }
}

function printTournamentOverview() {
  window.print();
}

// ==========================================
// CREATE TEAM MODAL
// ==========================================

function showCreateTeamModal(tourId) {
  const modal = `
    <div class="modal active" id="createTeamModal">
      <div class="modal-content modal-sm">
        <div class="modal-header">
          <h3>➕ Create New Team</h3>
          <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form onsubmit="createAndAddTeam(event, ${tourId})" id="createTeamForm">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Team Name *</label>
              <input type="text" class="form-control" name="team_name" placeholder="e.g., Phoenix Warriors" required autofocus>
            </div>
            <div style="padding: 14px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 6px; font-size: 12px; color: #1e40af; line-height: 1.6;">
              <strong>💡 Tip:</strong> Choose a unique team name. This team will be automatically added to the tournament after creation.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary">Create & Add Team</button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  // Remove existing modal and add new one
  closeModal();
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modal;
  document.body.appendChild(tempDiv.firstElementChild);
}

async function createAndAddTeam(event, tourId) {
  event.preventDefault();
  const formData = new FormData(event.target);
  const teamName = formData.get('team_name').trim();
  
  if (!teamName) {
    showToast('Please enter a team name', 'error');
    return;
  }
  
  try {
    // First create the team
    const createResult = await fetchAPI('create_team', { 
      team_name: teamName 
    }, 'POST');
    
    if (createResult && createResult.ok) {
      // Then add it to the tournament
      const addResult = await fetchAPI('add_team_to_tournament', { 
        tour_id: tourId, 
        team_id: createResult.team_id 
      }, 'POST');
      
      if (addResult && addResult.ok) {
        closeModal();
        loadTournamentTeams(tourId);
        showToast(`✅ Team "${teamName}" created and added to tournament!`, 'success');
      } else {
        showToast('⚠️ Team created but failed to add to tournament', 'error');
      }
    } else {
      showToast('❌ Error creating team: ' + (createResult?.error || 'Unknown error'), 'error');
    }
  } catch (error) {
    console.error('Error creating team:', error);
    showToast('❌ Error creating team', 'error');
  }
}

// ==========================================
// CREATE TEAM MODAL (COMPLETE WITH ALL FIELDS)
// ==========================================

async function showCreateTeamModal(tourId) {
  // Fetch schools for dropdown
  const schools = await fetchAPI('schools') || [];
  
  const modal = `
    <div class="modal active" id="createTeamModal">
      <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
          <h3>➕ Create New Team</h3>
          <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <form onsubmit="createAndAddTeam(event, ${tourId})" id="createTeamForm">
          <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            
            <!-- Team Basic Information -->
            <h4 class="modal-section-title">🏆 Team Information</h4>
            
            <div class="form-group">
              <label class="form-label">Team Name *</label>
              <input type="text" class="form-control" name="team_name" placeholder="e.g., Phoenix Warriors" required autofocus>
              <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 4px;">
                Enter a unique and descriptive name for the team
              </small>
            </div>
            
            ${schools.length > 0 ? `
              <div class="form-group">
                <label class="form-label">School/Institution</label>
                <select class="form-control" name="school_id">
                  <option value="">Select School (Optional)</option>
                  ${schools.map(s => `<option value="${s.school_id}">${escapeHtml(s.school_name)}</option>`).join('')}
                </select>
                <small style="color: #6b7280; font-size: 11px; display: block; margin-top: 4px;">
                  Associate this team with a specific school if applicable
                </small>
              </div>
            ` : ''}
            
            <!-- Team Manager/Contact Information -->
            <h4 class="modal-section-title">👤 Team Contact & Management</h4>
            
            <div style="padding: 12px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 16px;">
              <p style="font-size: 12px; color: #6b7280; margin: 0; line-height: 1.5;">
                <strong>Note:</strong> Coaches, managers, and other staff can be assigned later when adding sports to this team.
              </p>
            </div>
            
            <!-- Team Status -->
            <h4 class="modal-section-title">⚙️ Team Status</h4>
            
            <div class="form-group">
              <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #f0fdf4; border-radius: 8px; border: 1px solid #86efac;">
                <input type="checkbox" name="is_active" checked style="width: 20px; height: 20px; cursor: pointer;">
                <div>
                  <div style="font-weight: 600; color: #065f46;">Active Team</div>
                  <div style="font-size: 11px; color: #047857; margin-top: 2px;">Check this to make the team immediately active and visible</div>
                </div>
              </label>
            </div>
            
            <!-- Tournament Auto-Add Notice -->
            <div style="padding: 14px; background: #dbeafe; border-left: 4px solid #3b82f6; border-radius: 6px; margin-top: 16px;">
              <div style="display: flex; gap: 10px;">
                <span style="font-size: 20px;">ℹ️</span>
                <div>
                  <strong style="color: #1e40af; font-size: 13px; display: block; margin-bottom: 4px;">Automatic Tournament Registration</strong>
                  <p style="font-size: 12px; color: #1e3a8a; margin: 0; line-height: 1.5;">
                    This team will be automatically registered to the selected tournament after creation. You can then add sports and athletes to the team.
                  </p>
                </div>
              </div>
            </div>
            
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" class="btn btn-primary" style="min-width: 180px;">
              ➕ Create Team & Register
            </button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  closeModal();
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modal;
  document.body.appendChild(tempDiv.firstElementChild);
}

// ==========================================
// ENHANCED ATHLETE MODAL
// ==========================================

async function showAthleteModal(athlete = null, tourId = null, teamId = null, sportsId = null) {
  const isEdit = athlete !== null;
  const title = isEdit ? 'Edit Athlete' : 'Create New Athlete';
  
  // Fetch colleges for dropdown
  const colleges = await fetchAPI('colleges') || [];
  
  // If creating and context is provided, use it
  const contextTourId = isEdit ? (athlete.tour_id || currentContext.tour_id) : (tourId || currentContext.tour_id);
  const contextTeamId = isEdit ? (athlete.team_id || currentContext.team_id) : (teamId || currentContext.team_id);
  const contextSportsId = isEdit ? (athlete.sports_id || currentContext.sports_id) : (sportsId || currentContext.sports_id);
  
  const modalHTML = `
    <div class="modal active" id="athleteModal" style="z-index: 10001;">
      <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
          <h3>${title}</h3>
          <button class="modal-close" onclick="closeModal('athleteModal')">×</button>
        </div>
        <form onsubmit="saveAthlete(event, ${isEdit ? athlete.person_id : 'null'}, ${isEdit ? athlete.team_ath_id : 'null'}, ${contextTourId}, ${contextTeamId}, ${contextSportsId})" id="athleteForm">
          <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
            
            <!-- Personal Information -->
            <h4 class="modal-section-title">👤 Personal Information</h4>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
              <div class="form-group">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" name="l_name" value="${escapeHtml(athlete?.l_name || '')}" required>
              </div>
              
              <div class="form-group">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" name="f_name" value="${escapeHtml(athlete?.f_name || '')}" required>
              </div>
              
              <div class="form-group">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="m_name" value="${escapeHtml(athlete?.m_name || '')}">
              </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-control" name="title" value="${escapeHtml(athlete?.title || '')}" placeholder="e.g., Mr., Ms., Jr.">
              </div>
              
              <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" class="form-control" name="date_birth" value="${athlete?.date_birth || ''}">
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
            <h4 class="modal-section-title">🎓 Academic Information</h4>
            
            <div class="form-group">
              <label class="form-label">College</label>
              <select class="form-control" name="college_code">
                <option value="">Select College (Optional)</option>
                ${colleges.map(c => `
                  <option value="${escapeHtml(c.college_code)}" ${athlete?.college_code === c.college_code ? 'selected' : ''}>
                    ${escapeHtml(c.college_name)} (${escapeHtml(c.college_code)})
                  </option>
                `).join('')}
              </select>
            </div>
            
            <div class="form-group">
              <label class="form-label">Course/Program</label>
              <input type="text" class="form-control" name="course" value="${escapeHtml(athlete?.course || '')}" placeholder="e.g., BSIT, BSCS">
            </div>
            
            <!-- Physical Information -->
            <h4 class="modal-section-title">📏 Physical Information</h4>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div class="form-group">
                <label class="form-label">Height (cm)</label>
                <input type="number" class="form-control" name="height" value="${athlete?.height || ''}" step="0.1" min="0" placeholder="e.g., 170">
              </div>
              
              <div class="form-group">
                <label class="form-label">Weight (kg)</label>
                <input type="number" class="form-control" name="weight" value="${athlete?.weight || ''}" step="0.1" min="0" placeholder="e.g., 65">
              </div>
            </div>
            
            <!-- Scholarship Information -->
            <h4 class="modal-section-title">🎓 Scholarship Information</h4>
            
            <div class="form-group">
              <label class="form-label">Scholarship Type</label>
              <select class="form-control" name="scholarship_name">
                <option value="">No Scholarship</option>
                <option value="Varsity" ${athlete?.scholarship_name === 'Varsity' ? 'selected' : ''}>Varsity</option>
                <option value="Academic" ${athlete?.scholarship_name === 'Academic' ? 'selected' : ''}>Academic</option>
                <option value="Athletic" ${athlete?.scholarship_name === 'Athletic' ? 'selected' : ''}>Athletic</option>
                <option value="Full" ${athlete?.scholarship_name === 'Full' ? 'selected' : ''}>Full Scholarship</option>
                <option value="Partial" ${athlete?.scholarship_name === 'Partial' ? 'selected' : ''}>Partial Scholarship</option>
              </select>
            </div>
            
            <!-- Team Role -->
            ${!isEdit ? `
              <h4 class="modal-section-title">⭐ Team Role</h4>
              
              <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #fef3c7; border-radius: 8px; border: 1px solid #fbbf24;">
                  <input type="checkbox" name="is_captain" value="1" style="width: 20px; height: 20px; cursor: pointer;">
                  <div>
                    <div style="font-weight: 600; color: #92400e;">Team Captain</div>
                    <div style="font-size: 11px; color: #78350f; margin-top: 2px;">Check if this athlete will be the team captain</div>
                  </div>
                </label>
              </div>
            ` : ''}
            
            ${isEdit ? `<input type="hidden" name="person_id" value="${athlete.person_id}">` : ''}
            ${isEdit && athlete.team_ath_id ? `<input type="hidden" name="team_ath_id" value="${athlete.team_ath_id}">` : ''}
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('athleteModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">
              ${isEdit ? '💾 Update' : '➕ Create'} Athlete
            </button>
          </div>
        </form>
      </div>
    </div>
  `;
  
  const tempDiv = document.createElement('div');
  tempDiv.innerHTML = modalHTML;
  document.body.appendChild(tempDiv.firstElementChild);
}

async function saveAthlete(event, personId, teamAthId, tourId, teamId, sportsId) {
  event.preventDefault();
  
  const form = event.target;
  const formData = new FormData(form);
  
  const data = {
    l_name: formData.get('l_name'),
    f_name: formData.get('f_name'),
    m_name: formData.get('m_name') || '',
    title: formData.get('title') || '',
    date_birth: formData.get('date_birth') || null,
    college_code: formData.get('college_code') || '',
    course: formData.get('course') || '',
    blood_type: formData.get('blood_type') || '',
    height: formData.get('height') || null,
    weight: formData.get('weight') || null,
    scholarship_name: formData.get('scholarship_name') || '',
    is_captain: formData.get('is_captain') ? 1 : 0
  };
  
  console.log('💾 Saving athlete:', data);
  
  try {
    let result;
    
    if (personId) {
      // Update existing athlete
      data.person_id = personId;
      if (teamAthId) data.team_ath_id = teamAthId;
      if (tourId) data.tour_id = tourId;
      result = await fetchAPI('update_athlete', data, 'POST');
    } else {
      // Create new athlete
      data.tour_id = tourId;
      data.team_id = teamId;
      data.sports_id = sportsId;
      result = await fetchAPI('create_athlete', data, 'POST');
    }
    
    console.log('📥 Save result:', result);
    
    if (result && result.ok !== false) {
      closeModal('athleteModal');
      
      // Check which modal/view is currently open and reload appropriately
      const sportAthletesModal = document.getElementById('sportAthletesModal');
      
      if (sportAthletesModal && tourId && teamId && sportsId) {
        // We're in the sport athletes modal - reload that list
        await loadSportAthletes(tourId, teamId, sportsId);
      }
      
      showToast(personId ? '✅ Athlete updated successfully' : '✅ Athlete created successfully', 'success');
      
      // Trigger events
      if (personId) {
        DashboardEvents.trigger(EVENTS.ATHLETE_UPDATED, { person_id: personId, ...data });
      } else {
        DashboardEvents.trigger(EVENTS.ATHLETE_CREATED, { person_id: result.person_id, ...data });
      }
    } else {
      showToast('❌ ' + (result?.error || 'Error saving athlete'), 'error');
    }
  } catch (error) {
    console.error('Error saving athlete:', error);
    showToast('❌ Error saving athlete: ' + error.message, 'error');
  }
}

function closeModal(modalId) {
  if (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.remove();
    }
  } else {
    // Close all modals
    document.querySelectorAll('.modal').forEach(m => m.remove());
  }
}

// ==========================================
// TOAST NOTIFICATIONS
// ==========================================

function showToast(message, type = 'info') {
  // Remove existing toasts
  document.querySelectorAll('.toast-notification').forEach(t => t.remove());
  
  const colors = {
    success: '#10b981',
    error: '#ef4444',
    info: '#3b82f6',
    warning: '#f59e0b'
  };
  
  const icons = {
    success: '✅',
    error: '❌',
    info: 'ℹ️',
    warning: '⚠️'
  };
  
  const toast = document.createElement('div');
  toast.className = 'toast-notification';
  toast.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    background: ${colors[type]};
    color: white;
    padding: 14px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 10000;
    font-size: 14px;
    font-weight: 600;
    animation: slideIn 0.3s ease-out;
    min-width: 300px;
    max-width: 500px;
  `;
  toast.innerHTML = `${icons[type]} ${message}`;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

async function loadSportsDropdown(selectId) {
  try {
    const sports = await fetchAPI('sports');
    const select = $('#' + selectId);
    
    if (sports && sports.length > 0 && select) {
      const currentValue = select.value;
      select.innerHTML = '<option value="">Select Sport</option>' +
        sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
      if (currentValue) select.value = currentValue;
    }
  } catch (error) {
    console.error('Error loading sports dropdown:', error);
  }
}

async function loadCoachesDropdown(selectId) {
  try {
    const coaches = await fetchAPI('staff', { role: 'coach' });
    const select = $('#' + selectId);
    
    if (coaches && coaches.length > 0 && select) {
      select.innerHTML = '<option value="">Select Coach</option>' +
        coaches.map(c => `<option value="${c.person_id}">${escapeHtml(c.full_name)}</option>`).join('');
    }
  } catch (error) {
    console.error('Error loading coaches dropdown:', error);
  }
}

async function loadTrainorsDropdown(selectId) {
  try {
    const trainors = await fetchAPI('staff', { role: 'trainor' });
    const select = $('#' + selectId);
    
    if (trainors && trainors.length > 0 && select) {
      select.innerHTML = '<option value="">Select Trainor</option>' +
        trainors.map(t => `<option value="${t.person_id}">${escapeHtml(t.full_name)}</option>`).join('');
    }
  } catch (error) {
    console.error('Error loading trainors dropdown:', error);
  }
}

// ==========================================
// PRINT STYLES
// ==========================================

if (!document.getElementById('printStyles')) {
  const printStyles = document.createElement('style');
  printStyles.id = 'printStyles';
  printStyles.textContent = `
    @media print {
      .sidebar, .top-bar, .modal-header, .modal-footer, .btn, .modal-close {
        display: none !important;
      }
      .modal {
        position: static !important;
        background: white !important;
      }
      .modal-content {
        max-width: 100% !important;
        max-height: none !important;
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
      }
      .modal-body {
        padding: 20px !important;
        max-height: none !important;
        overflow: visible !important;
      }
      body {
        font-size: 11pt;
      }
      @page {
        margin: 1cm;
      }
    }
  `;
  document.head.appendChild(printStyles);
}

console.log('✅ Enhanced director_modals.js loaded');