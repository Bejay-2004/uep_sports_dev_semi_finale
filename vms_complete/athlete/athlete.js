const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const personId = window.ATHLETE_CONTEXT.person_id;
const sportsId = window.ATHLETE_CONTEXT.sports_id;

// ==========================================
// NAVIGATION
// ==========================================

const pageTitles = {
  'overview': 'Dashboard Overview',
  'teams': 'My Teams',
  'players': 'Team Players',
  'schedule': 'Match Schedule',
  'training': 'Training Schedule',
  'attendance': 'Training Attendance',
  'programs': 'Training Programs',
  'rankings': 'Team Rankings'
};

$$('.nav-link').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    const view = btn.dataset.view;
    
    $('#pageTitle').textContent = pageTitles[view] || 'Dashboard';
    
    $$('.content-view').forEach(v => v.classList.remove('active'));
    $(`#${view}-view`).classList.add('active');
    
    if (window.innerWidth <= 768) {
      $('.sidebar').classList.remove('active');
      $('#sidebarOverlay').classList.remove('active');
    }
    
    loadViewData(view);
  });
});

$('#menuToggle')?.addEventListener('click', () => {
  $('.sidebar').classList.toggle('active');
  $('#sidebarOverlay').classList.toggle('active');
});

$('#sidebarOverlay')?.addEventListener('click', () => {
  $('.sidebar').classList.remove('active');
  $('#sidebarOverlay').classList.remove('active');
});

// ==========================================
// API HELPER
// ==========================================

async function fetchJSON(action) {
  try {
    // Don't encode the entire action string - split it properly
    const parts = action.split('&');
    const mainAction = parts[0];
    const params = parts.slice(1).join('&');
    
    const url = params 
      ? `api.php?action=${encodeURIComponent(mainAction)}&${params}`
      : `api.php?action=${encodeURIComponent(mainAction)}`;
    
    console.log('📡 Fetching:', url);
    const res = await fetch(url);
    
    if (!res.ok) {
      const errorText = await res.text();
      console.error('Response error:', errorText);
      throw new Error(`HTTP ${res.status}`);
    }
    
    const data = await res.json();
    console.log('✅ Response:', data);
    return data;
  } catch (err) {
    console.error('❌ fetchJSON error:', err);
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
    case 'teams': loadMyTeams(); break;
    case 'players': loadTeamPlayers(); break;
    case 'schedule': loadMatches(); break;
    case 'training': loadTrainingSchedule(); break;
    case 'attendance': loadAttendance(); break;
    case 'programs': loadPrograms(); break;
    case 'rankings': break; // Loaded on team selection
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    await Promise.all([
      loadMyTeams(),
      loadMatches(),
      loadTrainingSchedule(),
      loadTraineeStats(),
      loadUpcomingSessions(),
      calculateTotalMedals()
    ]);
  } catch (err) {
    console.error('loadOverview error:', err);
  }
}

// ==========================================
// ATHLETE FUNCTIONS
// ==========================================

async function loadMyTeams() {
  try {
    const data = await fetchJSON('my_teams');
    
    if (!data) {
      $('#statTeams').textContent = '0';
      return;
    }
    
    $('#statTeams').textContent = data.length || 0;
    
    const overviewList = $('#overviewTeams');
    if (!data || data.length === 0) {
      overviewList.innerHTML = '<div class="empty-state">No teams assigned</div>';
    } else {
      overviewList.innerHTML = data.slice(0, 3).map(t => renderTeamCard(t)).join('');
    }
    
    const teamsContent = $('#teamsContent');
    if (!data || data.length === 0) {
      teamsContent.innerHTML = '<div class="empty-state">No teams assigned</div>';
    } else {
      teamsContent.innerHTML = data.map(t => renderTeamCard(t)).join('');
    }
    
    populateTeamFilters(data);
    
    console.log('✅ Teams loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMyTeams error:', err);
    $('#overviewTeams').innerHTML = '<div class="empty-state">Error loading teams</div>';
  }
}

function renderTeamCard(team) {
  const isCaptain = team.is_captain == 1;
  return `
    <div class="data-card">
      <div class="data-card-header">
        <div class="data-card-title">${escapeHtml(team.team_name)}</div>
        ${isCaptain ? '<span class="badge captain">Captain</span>' : ''}
      </div>
      <div class="data-card-meta">${escapeHtml(team.sports_name)}</div>
      <div class="data-card-content">
        <strong>Coach:</strong> ${escapeHtml(team.coach_name || 'TBA')}
      </div>
    </div>
  `;
}

function populateTeamFilters(teams) {
  const filterSelect = $('#teamFilterSelect');
  if (teams && teams.length > 0) {
    filterSelect.innerHTML = '<option value="">All Teams</option>' + 
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  }
  
  const rankingsSelect = $('#rankingsTeamSelect');
  if (teams && teams.length > 0) {
    rankingsSelect.innerHTML = '<option value="">-- Select Team --</option>' + 
      teams.map(t => `<option value="${t.team_id}">${escapeHtml(t.team_name)}</option>`).join('');
  }
}

$('#teamFilterSelect')?.addEventListener('change', (e) => {
  const teamId = e.target.value;
  loadTeamPlayers(teamId);
});

async function loadTeamPlayers(teamId = null) {
  try {
    let action = 'team_players';
    if (teamId) action += `&team_id=${teamId}`;
    
    const data = await fetchJSON(action);
    const tbody = $('#playersTable tbody');
    
    if (!data || data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;"><div class="empty-state">No players found</div></td></tr>';
    } else {
      tbody.innerHTML = data.map(p => renderPlayerRow(p)).join('');
    }
    
    console.log('✅ Players loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTeamPlayers error:', err);
    $('#playersTable tbody').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;"><div class="empty-state">Error loading players</div></td></tr>';
  }
}

function renderPlayerRow(player) {
  const isCaptain = player.is_captain == 1;
  
  return `
    <tr>
      <td><strong>${escapeHtml(player.player_name)}</strong></td>
      <td>${escapeHtml(player.team_name)}</td>
      <td>${escapeHtml(player.sports_name)}</td>
      <td>
        ${isCaptain ? '<span class="badge captain">Captain</span>' : '<span class="badge active">Member</span>'}
      </td>
    </tr>
  `;
}

async function loadMatches() {
  try {
    const data = await fetchJSON('my_matches');
    
    if (!data) {
      $('#statUpcoming').textContent = '0';
      return;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const upcoming = data.filter(m => {
      const matchDate = new Date(m.sked_date);
      matchDate.setHours(0, 0, 0, 0);
      return matchDate >= today;
    });
    $('#statUpcoming').textContent = upcoming.length;
    
    const overviewMatches = $('#overviewMatches');
    if (upcoming.length === 0) {
      overviewMatches.innerHTML = '<div class="empty-state">No upcoming matches</div>';
    } else {
      overviewMatches.innerHTML = upcoming.slice(0, 3).map(m => renderMatchCard(m)).join('');
    }
    
    const scheduleContent = $('#scheduleContent');
    if (!data || data.length === 0) {
      scheduleContent.innerHTML = '<div class="empty-state">No matches scheduled</div>';
    } else {
      scheduleContent.innerHTML = data.map(m => renderMatchCard(m)).join('');
    }
    
    console.log('✅ Matches loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatches error:', err);
    $('#overviewMatches').innerHTML = '<div class="empty-state">Error loading matches</div>';
  }
}

function renderMatchCard(match) {
  const date = new Date(match.sked_date);
  const dateStr = date.toLocaleDateString('en-US', { 
    weekday: 'short',
    month: 'short', 
    day: 'numeric' 
  });
  
  return `
    <div class="match-card">
      <div class="match-card-header">
        <div class="match-date">📅 ${dateStr} • ${match.sked_time}</div>
        <div class="match-sport">${escapeHtml(match.sports_name)}</div>
      </div>
      <div class="match-teams">
        <div class="match-team">${escapeHtml(match.team_a_name || 'TBA')}</div>
        <div class="match-vs">VS</div>
        <div class="match-team">${escapeHtml(match.team_b_name || 'TBA')}</div>
      </div>
      <div class="match-details">
        <div class="match-detail">
          📍 ${escapeHtml(match.venue_name || 'Venue TBA')}
        </div>
        <div class="match-detail">
          🏆 ${escapeHtml(match.match_type || 'Match')}
        </div>
        ${match.winner_name ? `
        <div class="match-detail" style="color: var(--success); font-weight: 600;">
          ✓ Winner: ${escapeHtml(match.winner_name)}
        </div>
        ` : ''}
      </div>
    </div>
  `;
}

async function loadTrainingSchedule() {
  try {
    const data = await fetchJSON('training_schedule');
    
    if (!data || data.ok === false) {
      console.error('Training schedule error:', data);
      $('#statTraining').textContent = '0';
      $('#trainingContent').innerHTML = '<div class="empty-state">Error loading training sessions</div>';
      return;
    }
    
    $('#statTraining').textContent = data.length || 0;
    
    const trainingContent = $('#trainingContent');
    if (!data || data.length === 0) {
      trainingContent.innerHTML = '<div class="empty-state">No training sessions scheduled</div>';
    } else {
      trainingContent.innerHTML = data.map(t => renderTrainingCard(t)).join('');
    }
    
    console.log('✅ Training loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTrainingSchedule error:', err);
    $('#statTraining').textContent = '0';
    $('#trainingContent').innerHTML = '<div class="empty-state">Error loading training</div>';
  }
}

function renderTrainingCard(training) {
  const date = new Date(training.sked_date);
  const dateStr = date.toLocaleDateString('en-US', { 
    weekday: 'short',
    month: 'short', 
    day: 'numeric' 
  });
  
  const attendanceStatus = training.is_present === 1 ? 
    '<span class="badge active">Attended</span>' : 
    training.is_present === 0 ? 
    '<span class="badge inactive">Absent</span>' : 
    '<span class="badge">Pending</span>';
  
  return `
    <div class="training-card">
      <div class="training-card-header">
        <div class="training-team"><strong>${escapeHtml(training.team_name)}</strong></div>
        ${attendanceStatus}
      </div>
      <div class="training-datetime">
        <span>📅 ${dateStr}</span>
        <span>🕐 ${training.sked_time}</span>
      </div>
      <div class="training-venue">
        📍 ${escapeHtml(training.venue_name || 'Venue TBA')}
        ${training.venue_building ? ' - ' + escapeHtml(training.venue_building) : ''}
        ${training.venue_room ? ', ' + escapeHtml(training.venue_room) : ''}
      </div>
    </div>
  `;
}

$('#rankingsTeamSelect')?.addEventListener('change', (e) => {
  const teamId = e.target.value;
  if (teamId) {
    loadRankings(teamId);
  } else {
    $('#rankingsContent').innerHTML = '<div class="empty-state">Select a team to view rankings</div>';
  }
});

async function loadRankings(teamId) {
  try {
    const data = await fetchJSON(`rankings&team_id=${teamId}`);
    const content = $('#rankingsContent');
    
    if (!data) {
      content.innerHTML = '<div class="empty-state">Error loading rankings</div>';
      return;
    }
    
    if (data.length === 0) {
      content.innerHTML = '<div class="empty-state">No rankings available for this team</div>';
    } else {
      content.innerHTML = data.map((r, idx) => renderRankingCard(r, idx + 1)).join('');
    }
    
    console.log('✅ Rankings loaded:', data.length);
  } catch (err) {
    console.error('❌ loadRankings error:', err);
    $('#rankingsContent').innerHTML = '<div class="empty-state">Error loading rankings</div>';
  }
}

function renderRankingCard(ranking, position) {
  let positionClass = '';
  if (position === 1) positionClass = 'gold';
  else if (position === 2) positionClass = 'silver';
  else if (position === 3) positionClass = 'bronze';
  
  const wins = ranking.no_win || 0;
  const losses = ranking.no_loss || 0;
  const draws = ranking.no_draw || 0;
  const record = `${wins}W - ${losses}L${draws > 0 ? ' - ' + draws + 'D' : ''}`;
  
  const gold = ranking.no_gold || 0;
  const silver = ranking.no_silver || 0;
  const bronze = ranking.no_bronze || 0;
  const medals = `🥇${gold} 🥈${silver} 🥉${bronze}`;
  
  return `
    <div class="ranking-card">
      <div class="ranking-position ${positionClass}">${position}</div>
      <div class="ranking-info">
        <div class="ranking-team">${escapeHtml(ranking.team_name)}</div>
        <div class="ranking-sport">${escapeHtml(ranking.sports_name)}</div>
      </div>
      <div class="ranking-stats">
        <div class="ranking-record">${record}</div>
        <div class="ranking-medals">${medals}</div>
      </div>
    </div>
  `;
}

async function calculateTotalMedals() {
  try {
    const teams = await fetchJSON('my_teams');
    
    if (!teams || teams.length === 0) {
      $('#statMedals').textContent = '0';
      return;
    }
    
    let totalMedals = 0;
    
    for (const team of teams) {
      try {
        const standings = await fetchJSON(`rankings&team_id=${team.team_id}`);
        if (standings && Array.isArray(standings)) {
          const teamStanding = standings.find(s => s.team_id === team.team_id);
          
          if (teamStanding) {
            totalMedals += (teamStanding.no_gold || 0);
            totalMedals += (teamStanding.no_silver || 0);
            totalMedals += (teamStanding.no_bronze || 0);
          }
        }
      } catch (err) {
        console.warn('Could not fetch standings for team:', team.team_id);
      }
    }
    
    $('#statMedals').textContent = totalMedals;
  } catch (err) {
    console.error('❌ calculateTotalMedals error:', err);
    $('#statMedals').textContent = '0';
  }
}

// ==========================================
// TRAINEE FUNCTIONS
// ==========================================

async function loadTraineeStats() {
  try {
    const stats = await fetchJSON('trainee_stats');
    
    if (stats) {
      $('#statSessions').textContent = stats.sessions_attended || 0;
      $('#statRate').textContent = (stats.attendance_rate || 0) + '%';
      $('#statStreak').textContent = stats.streak || 0;
      $('#statTotal').textContent = stats.total_hours || 0;
    }
  } catch (err) {
    console.error('loadTraineeStats error:', err);
  }
}

async function loadUpcomingSessions() {
  try {
    const upcoming = await fetchJSON('upcoming_sessions');
    const upcomingEl = $('#upcomingSessions');
    
    if (!upcoming || upcoming.length === 0) {
      upcomingEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No upcoming training sessions</p>
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
    console.error('loadUpcomingSessions error:', err);
  }
}

async function loadAttendance() {
  try {
    await loadTraineeStats();
    
    const attendance = await fetchJSON('my_attendance');
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

async function loadPrograms() {
  try {
    const programs = await fetchJSON('my_programs');
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
// INITIALIZE
// ==========================================

(async function init() {
  console.log('🚀 Initializing athlete dashboard with trainee features...');
  console.log('👤 Person ID:', personId);
  console.log('⚽ Sports ID:', sportsId);
  
  try {
    await loadOverview();
    console.log('✅ All data loaded successfully');
  } catch (err) {
    console.error('❌ Initialization error:', err);
  }
})();