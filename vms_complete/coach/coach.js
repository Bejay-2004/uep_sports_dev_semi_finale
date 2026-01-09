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
  'trainees': 'Trainees',
  'training': 'Training Sessions',
  'activities': 'Training Activities',
  'attendance': 'Session Attendance',
  'performance': 'Performance Ratings',
  'standings': 'Statistics & Rankings',
  'tournaments': 'Tournament Schedules',
  'match-history': 'Match History',  // ADD THIS
  'reports': 'Reports & Analytics'
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
    case 'trainees': loadTrainees(); break;
    case 'training': loadSessions(); break;
    case 'activities': loadActivities(); break;
    case 'attendance': loadAttendanceSessions(); break;
    case 'performance': loadPerformanceRatings(); break;
    case 'standings': loadStandings(); break;
    case 'tournaments': loadMatches(); break;
    case 'match-history': loadMatchHistory(); break;  // ADD THIS
    case 'reports': loadReportsView(); break;
  }
}

function error_log(msg) {
  console.log('🔍 ' + msg);
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

        // Calculate actual attendance rate
    try {
      const attendanceData = await fetchJSON('overview_attendance_rate');
      if (attendanceData && attendanceData.avg_attendance_rate !== null) {
        $('#statAttendance').textContent = attendanceData.avg_attendance_rate + '%';
      } else {
        $('#statAttendance').textContent = 'N/A';
      }
    } catch (err) {
      console.error('Error loading attendance rate:', err);
      $('#statAttendance').textContent = 'N/A';
    }

    
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


let showOnlyMyMatches = false;

$('#toggleMyMatches')?.addEventListener('click', function() {
  showOnlyMyMatches = !showOnlyMyMatches;
  const textEl = $('#toggleMatchesText');
  const btn = this;
  
  if (showOnlyMyMatches) {
    textEl.textContent = 'Show All Matches';
    btn.classList.remove('btn-secondary');
    btn.classList.add('btn-primary');
    
    // Hide rows that don't have the highlight style
    document.querySelectorAll('#matchesTable tbody tr').forEach(row => {
      const style = row.getAttribute('style') || '';
      if (!style.includes('linear-gradient')) {
        row.style.display = 'none';
      }
    });
  } else {
    textEl.textContent = 'Show My Matches Only';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-secondary');
    
    // Show all rows
    document.querySelectorAll('#matchesTable tbody tr').forEach(row => {
      row.style.display = '';
    });
  }
});

// ==========================================
// MATCHES
// ==========================================


async function loadMatches(){
  try {
    // First, get the coach's teams to know which matches to highlight
    const teamsData = await fetchJSON('teams');
    const coachTeamIds = teamsData.map(t => t.team_id);
    
    console.log('Coach team IDs:', coachTeamIds);
    
    // Then get all matches
    const data = await fetchJSON('matches');
    const tbody = $('#matchesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      console.warn('⚠️ No matches data');
      renderRows(tbody, '');
      return;
    }
    
    const html = data.map(r => {
      // Check if this match involves any of the coach's teams
      const isCoachMatch = coachTeamIds.includes(r.team_a_id) || coachTeamIds.includes(r.team_b_id);
      
      // Determine which team is the coach's team
      const isTeamA = coachTeamIds.includes(r.team_a_id);
      const isTeamB = coachTeamIds.includes(r.team_b_id);
      
      // Apply highlight styling to the entire row
      const rowStyle = isCoachMatch ? 
        'background: linear-gradient(90deg, #dbeafe 0%, #eff6ff 100%); border-left: 4px solid var(--primary);' : 
        '';
      
      return `
        <tr style="${rowStyle}">
          <td>${escapeHtml(r.sked_date ?? 'TBA')}</td>
          <td>${escapeHtml(r.sked_time ?? 'TBA')}</td>
          <td><strong>${escapeHtml(String(r.game_no ?? '-'))}</strong></td>
          <td><span style="padding:4px 8px;background:#dbeafe;color:#1e40af;border-radius:4px;font-size:11px;font-weight:700;">${escapeHtml(r.match_type ?? 'TBA')}</span></td>
          <td>${escapeHtml(r.venue_name ?? 'TBA')}</td>
          <td>
            <strong style="${isTeamA ? 'color:var(--primary);font-weight:900;' : ''}">
              ${escapeHtml(r.team_a ?? 'TBA')}
              ${isTeamA ? ' 👉' : ''}
            </strong>
          </td>
          <td>
            <strong style="${isTeamB ? 'color:var(--primary);font-weight:900;' : ''}">
              ${escapeHtml(r.team_b ?? 'TBA')}
              ${isTeamB ? ' 👉' : ''}
            </strong>
          </strong>
          </td>
        </tr>
      `;
    }).join('');
    
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

// ==========================================
// MATCH HISTORY
// ==========================================

async function loadMatchHistory() {
  try {
    const data = await fetchJSON('coach_match_history');
    const container = $('#matchHistoryContainer');
    
    if (!Array.isArray(data) || data.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <div style="font-size:48px;margin-bottom:16px;">🏆</div>
          <p>No match history found for your teams.</p>
        </div>
      `;
      return;
    }
    
    // Separate matches into completed and upcoming
    const completed = data.filter(m => m.match_result !== 'PENDING');
    const upcoming = data.filter(m => m.match_result === 'PENDING');
    
    let html = '';
    
    // Completed matches section
    if (completed.length > 0) {
      html += `
        <div class="match-section">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:var(--text);display:flex;align-items:center;gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="9 11 12 14 22 4"></polyline>
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>
            Completed Matches (${completed.length})
          </h3>
          <div class="match-grid">
            ${completed.map(m => renderMatchCard(m)).join('')}
          </div>
        </div>
      `;
    }
    
    // Upcoming matches section
    if (upcoming.length > 0) {
      html += `
        <div class="match-section" style="margin-top:32px;">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:var(--primary);display:flex;align-items:center;gap:8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            Upcoming Matches (${upcoming.length})
          </h3>
          <div class="match-grid">
            ${upcoming.map(m => renderMatchCard(m)).join('')}
          </div>
        </div>
      `;
    }
    
    container.innerHTML = html;
    
    console.log('✅ Match history loaded:', data.length);
  } catch (err) {
    console.error('❌ loadMatchHistory error:', err);
    $('#matchHistoryContainer').innerHTML = `
      <div class="card">
        <p style="color:var(--danger);text-align:center;padding:20px;">
          Error loading match history. Please refresh the page.
        </p>
      </div>
    `;
  }
}

function renderMatchCard(match) {
  const isPending = match.match_result === 'PENDING';
  const isWin = match.match_result === 'WON';
  const isLoss = match.match_result === 'LOST';
  
  // Format date
  const matchDate = new Date(match.sked_date);
  const formattedDate = matchDate.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  });
  
  // Result badge
  let resultBadge = '';
  if (!isPending) {
    if (isWin) {
      resultBadge = '<span class="result-badge win">✓ WON</span>';
    } else {
      resultBadge = '<span class="result-badge loss">✗ LOST</span>';
    }
  } else {
    resultBadge = '<span class="result-badge pending">⏳ UPCOMING</span>';
  }
  
  // Medal badge
  let medalBadge = '';
  if (match.medal_won) {
    const medalEmoji = {
      'gold': '🥇',
      'silver': '🥈',
      'bronze': '🥉'
    };
    const emoji = medalEmoji[match.medal_won.toLowerCase()] || '🏅';
    medalBadge = `<span class="medal-badge ${match.medal_won.toLowerCase()}">${emoji} ${match.medal_won}</span>`;
  }
  
  return `
    <div class="match-card ${isPending ? 'upcoming' : (isWin ? 'win-card' : 'loss-card')}">
      <div class="match-card-header">
        <div class="match-info">
          <div class="match-game-no">Game #${escapeHtml(String(match.game_no || '-'))}</div>
          <div class="match-type">${escapeHtml(match.match_type || 'Match')}</div>
        </div>
        ${resultBadge}
      </div>
      
      <div class="match-card-body">
        <div class="match-teams">
          <div class="team-display my-team">
            <div class="team-label">YOUR TEAM</div>
            <div class="team-name">${escapeHtml(match.coach_team_name)}</div>
            ${!isPending && match.coach_team_scores ? `
              <div class="team-score">${escapeHtml(match.coach_team_scores)}</div>
            ` : ''}
          </div>
          
          <div class="vs-divider">VS</div>
          
<div class="team-display opponent-team">
  <div class="team-label">OPPONENT</div>
  <div class="team-name">${escapeHtml(match.opponent_name || 'TBA')}</div>
  ${!isPending && match.opponent_scores ? `
    <div class="team-score opponent-score">${escapeHtml(match.opponent_scores)}</div>
  ` : (isPending ? '<div class="team-score-pending">Not yet played</div>' : '<div class="team-score-none">No score recorded</div>')}
</div>
        </div>
        
        ${medalBadge}
        
        <div class="match-details">
          <div class="detail-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <span>${formattedDate}</span>
          </div>
          <div class="detail-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>${escapeHtml(match.sked_time || 'TBA')}</span>
          </div>
          <div class="detail-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
              <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <span>${escapeHtml(match.venue_name || 'TBA')}</span>
          </div>
        </div>
      </div>
    </div>
  `;
}

// ==========================================
// ENHANCED TRAINING SESSIONS WITH MULTI-STEP FLOW
// ==========================================

let currentTrainingStep = 1;
let sessionData = {
  team_id: null,
  date: null,
  time: null,
  venue_id: null,
  activities: [],
  equipment: [],  // ADD THIS
  participants: []
};

$('#createSessionBtn')?.addEventListener('click', async () => {
  resetSessionForm();
  $('#sessionFormCard').style.display = 'block';
  await loadTeamsForSession();
  await loadVenuesForSession();
  goToTrainingStep(1);
});

$('#cancelSessionBtn')?.addEventListener('click', () => {
  $('#sessionFormCard').style.display = 'none';
  resetSessionForm();
});

function resetSessionForm() {
  currentTrainingStep = 1;
  sessionData = {
    team_id: null,
    date: null,
    time: null,
    venue_id: null,
    activities: [],
    equipment: [],  // ADD THIS
    participants: []
  };
  
  // Reset form fields
  if ($('#session_team_id')) $('#session_team_id').value = '';
  if ($('#training_date')) $('#training_date').value = '';
  if ($('#start_time')) $('#start_time').value = '';
  if ($('#session_venue_id')) $('#session_venue_id').value = '';
  
  // Clear selections
  document.querySelectorAll('.selection-card, .equipment-card').forEach(card => {
    card.classList.remove('selected');
    const checkbox = card.querySelector('input[type="checkbox"]');
    if (checkbox) checkbox.checked = false;
  });
  
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
      teams.map(t => `<option value="${t.team_id}" data-sports-id="${t.sports_id}" data-tour-id="${t.tour_id}">${escapeHtml(t.display_name)}</option>`).join('');
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

window.goToTrainingStep = async function(step) {
  // Validate current step before moving forward
  if (step > currentTrainingStep) {
    if (!validateTrainingStep(currentTrainingStep)) {
      return;
    }
    await collectStepData(currentTrainingStep);
  }
  
  // Update step indicators
  document.querySelectorAll('.training-step').forEach((el, idx) => {
    el.classList.remove('active', 'completed');
    if (idx + 1 < step) {
      el.classList.add('completed');
    } else if (idx + 1 === step) {
      el.classList.add('active');
    }
  });
  
  // Show/hide step content
  document.querySelectorAll('.training-step-content').forEach((el, idx) => {
    el.classList.remove('active');
    if (idx + 1 === step) {
      el.classList.add('active');
    }
  });
  
  currentTrainingStep = step;
  
  // Load data for current step
  if (step === 2) {
    await loadActivitiesForStep();
  } else if (step === 3) {
    await loadEquipmentForStep();  // ADD THIS
  } else if (step === 4) {
    await loadParticipantsForStep();
  } else if (step === 5) {
    updateSessionSummary();
  }
  
  // Scroll to top
  $('#sessionFormCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

async function validateTrainingStep(step) {
  switch(step) {
    case 1:
      const team = $('#session_team_id').value;
      const date = $('#training_date').value;
      const time = $('#start_time').value;
      const venue = $('#session_venue_id').value;
      
      if (!team || !date || !time || !venue) {
        showMsg('#sessionMsg', 'Please fill in all required fields', 'error');
        return false;
      }
      break;
      
    case 2:
      const selectedActivities = document.querySelectorAll('#activitiesSelectionGrid input:checked');
      if (selectedActivities.length === 0) {
        showMsg('#sessionMsg', 'Please select at least one training activity', 'error');
        return false;
      }
      break;
      
    case 3:
      // Validate equipment quantities don't exceed available stock
      const selectedEquipment = document.querySelectorAll('#equipmentSelectionGrid .equipment-checkbox:checked');
      for (const checkbox of selectedEquipment) {
        const card = checkbox.closest('.equipment-card');
        const available = parseInt(card.dataset.available) || 0;
        const quantityInput = card.querySelector('.quantity-input');
        const requested = parseInt(quantityInput.value) || 1;
        
        if (requested > available) {
          const equipName = card.querySelector('.equipment-name').textContent.trim();
          showMsg('#sessionMsg', `${equipName}: Only ${available} available, but you requested ${requested}`, 'error');
          return false;
        }
      }
      break;
      
    case 4:
      const selectedParticipants = document.querySelectorAll('#athletesSelectionGrid input:checked, #traineesSelectionGrid input:checked');
      if (selectedParticipants.length === 0) {
        showMsg('#sessionMsg', 'Please select at least one participant', 'error');
        return false;
      }
      break;
  }
  return true;
}

async function collectStepData(step) {
  switch(step) {
    case 1:
      sessionData.team_id = $('#session_team_id').value;
      sessionData.date = $('#training_date').value;
      sessionData.time = $('#start_time').value;
      sessionData.venue_id = $('#session_venue_id').value;
      break;
      
    case 2:
      const activities = Array.from(document.querySelectorAll('#activitiesSelectionGrid input:checked'))
        .map(cb => cb.value);
      sessionData.activities = activities;
      break;
      
    case 3:  // ADD EQUIPMENT STEP
      const equipment = [];
      document.querySelectorAll('#equipmentSelectionGrid .equipment-checkbox:checked').forEach(cb => {
        const equipId = cb.value;
        const quantityInput = document.querySelector(`.quantity-input[data-equip-id="${equipId}"]`);
        const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
        
        equipment.push({
          equip_id: equipId,
          quantity: quantity
        });
      });
      sessionData.equipment = equipment;
      break;
      
    case 4:
      const participants = Array.from(document.querySelectorAll('#athletesSelectionGrid input:checked, #traineesSelectionGrid input:checked'))
        .map(cb => ({
          person_id: cb.value,
          type: cb.dataset.type
        }));
      sessionData.participants = participants;
      break;
  }
}

// Add equipment loading function
async function loadEquipmentForStep() {
  try {
    // Get the selected date from the form
    const selectedDate = $('#training_date').value;
    const skedId = sessionData.sked_id || 0;
    
    if (!selectedDate) {
      $('#equipmentSelectionGrid').innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px;">Please select a training date first (Step 1)</p>';
      return;
    }
    
    // Format date for display - parse manually to avoid timezone issues
    const [year, month, day] = selectedDate.split('-');
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    const formattedDate = `${monthNames[parseInt(month) - 1]} ${parseInt(day)}, ${year}`;
    
    console.log('📅 LOAD_EQUIPMENT: selectedDate=' + selectedDate + ', formatted=' + formattedDate);
    
    // Fetch equipment availability for the selected date
    const response = await fetchJSON('check_equipment_availability', { 
      date: selectedDate,
      sked_id: skedId 
    });
    
    if (!response.ok || !response.equipment || response.equipment.length === 0) {
      $('#equipmentSelectionGrid').innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px;">No equipment available in the system.</p>';
      return;
    }
    
    const equipment = response.equipment;
    const grid = $('#equipmentSelectionGrid');
    
    grid.innerHTML = equipment.map(e => {
      const selectedEquip = sessionData.equipment.find(eq => eq.equip_id == e.equip_id);
      const isSelected = !!selectedEquip;
      const quantity = selectedEquip ? selectedEquip.quantity : 1;
      const isOutOfStock = !e.in_stock || e.available <= 0;
      
      return `
        <div class="equipment-card ${isSelected ? 'selected' : ''} ${isOutOfStock ? 'out-of-stock' : ''}" 
             data-equip-id="${e.equip_id}"
             data-available="${e.available}">
          <div class="equipment-header">
            <input type="checkbox" 
                   class="equipment-checkbox" 
                   value="${e.equip_id}"
                   ${isSelected ? 'checked' : ''}
                   ${isOutOfStock ? 'disabled' : ''}
                   onchange="toggleEquipment(this)">
            <div class="equipment-info">
              <div class="equipment-name">
                ${escapeHtml(e.equip_name)}
                ${isOutOfStock ? '<span class="stock-badge out">Out of Stock</span>' : ''}
              </div>
              <div class="equipment-available ${isOutOfStock ? 'stock-zero' : 'stock-ok'}">
                ${isOutOfStock ? 
                  `❌ Not Available (${e.borrowed_on_date}/${e.total_quantity} borrowed on ${formattedDate})` : 
                  `✅ ${e.available} available on ${formattedDate}`
                }
              </div>
            </div>
          </div>
          ${e.description ? `<div class="equipment-desc">${escapeHtml(e.description)}</div>` : ''}
          <div class="equipment-quantity" style="display:${isSelected ? 'flex' : 'none'};">
            <label>Qty to use:</label>
            <input type="number" 
                   class="quantity-input" 
                   min="1" 
                   max="${e.available}" 
                   value="${Math.min(quantity, e.available)}"
                   data-equip-id="${e.equip_id}"
                   ${isOutOfStock ? 'disabled' : ''}
                   onchange="validateQuantity(this, ${e.available})">
            <span class="max-qty">Max: ${e.available}</span>
          </div>
          ${isOutOfStock ? 
            `<div class="stock-warning">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
              All units are borrowed for ${formattedDate}
            </div>` : ''
          }
        </div>
      `;
    }).join('');
    
  } catch (err) {
    console.error('Error loading equipment:', err);
    $('#equipmentSelectionGrid').innerHTML = '<p style="color:var(--danger);padding:20px;">Error loading equipment</p>';
  }
}

window.validateQuantity = function(input, maxAvailable) {
  const value = parseInt(input.value) || 1;
  
  if (value < 1) {
    input.value = 1;
    showMsg('#sessionMsg', 'Quantity must be at least 1', 'error');
    return;
  }
  
  if (value > maxAvailable) {
    input.value = maxAvailable;
    showMsg('#sessionMsg', `Only ${maxAvailable} units available. Quantity adjusted.`, 'error');
    return;
  }
};

// Add equipment toggle function
window.toggleEquipment = function(checkbox) {
  const card = checkbox.closest('.equipment-card');
  const quantityDiv = card.querySelector('.equipment-quantity');
  const available = parseInt(card.dataset.available) || 0;
  
  if (checkbox.checked) {
    if (available <= 0) {
      checkbox.checked = false;
      showMsg('#sessionMsg', 'This equipment is out of stock for the selected date', 'error');
      return;
    }
    card.classList.add('selected');
    quantityDiv.style.display = 'flex';
    
    // Set max value for quantity input
    const quantityInput = quantityDiv.querySelector('.quantity-input');
    if (quantityInput) {
      quantityInput.max = available;
      if (parseInt(quantityInput.value) > available) {
        quantityInput.value = available;
      }
    }
  } else {
    card.classList.remove('selected');
    quantityDiv.style.display = 'none';
  }
};

async function loadActivitiesForStep() {
  try {
    const activities = await fetchJSON('training_activities');
    const grid = $('#activitiesSelectionGrid');
    
    if (!activities || activities.length === 0) {
      grid.innerHTML = '<p style="color:var(--muted);text-align:center;padding:20px;">No activities available. Create activities first.</p>';
      return;
    }
    
    grid.innerHTML = activities.map(a => `
      <div class="selection-card" onclick="toggleSelection(this)">
        <input type="checkbox" value="${a.activity_id}" ${sessionData.activities.includes(String(a.activity_id)) ? 'checked' : ''}>
        <div class="selection-info">
          <div class="selection-name">${escapeHtml(a.activity_name)}</div>
          <div class="selection-meta">
            ${a.duration ? escapeHtml(a.duration) : ''} 
            ${a.duration && a.repetition ? ' • ' : ''} 
            ${a.repetition ? escapeHtml(a.repetition) : ''}
          </div>
        </div>
      </div>
    `).join('');
    
    // Mark pre-selected
    document.querySelectorAll('#activitiesSelectionGrid .selection-card').forEach(card => {
      const checkbox = card.querySelector('input[type="checkbox"]');
      if (checkbox && checkbox.checked) {
        card.classList.add('selected');
      }
    });
  } catch (err) {
    console.error('Error loading activities:', err);
  }
}

async function loadParticipantsForStep() {
  try {
    const teamId = sessionData.team_id;
    if (!teamId) {
      console.error('No team_id in sessionData');
      return;
    }
    
    console.log('Loading participants for team_id:', teamId);
    
    const response = await fetchJSON('session_available_members', { team_id: teamId });
    
    console.log('Participants response:', response);
    
    // Check if response has ok property and handle accordingly
    const members = response.ok !== false ? response : { athletes: [], trainees: [] };
    
    // Load athletes
    const athletesGrid = $('#athletesSelectionGrid');
    if (!members.athletes || members.athletes.length === 0) {
      athletesGrid.innerHTML = '<p style="color:var(--muted);padding:12px;">No athletes available for this team</p>';
    } else {
      athletesGrid.innerHTML = members.athletes.map(a => `
        <div class="selection-card" onclick="toggleSelection(this)">
          <input type="checkbox" value="${a.person_id}" data-type="athlete" ${isParticipantSelected(a.person_id) ? 'checked' : ''}>
          <div class="selection-info">
            <div class="selection-name">
              ${escapeHtml(a.full_name)}
              ${a.is_captain == 1 ? '<span class="badge badge-captain" style="background:#dbeafe;color:#1e40af;font-size:10px;padding:2px 6px;border-radius:4px;margin-left:4px;">Captain</span>' : ''}
            </div>
            <div class="selection-meta">Athlete</div>
          </div>
        </div>
      `).join('');
    }
    
    // Load trainees
    const traineesGrid = $('#traineesSelectionGrid');
    if (!members.trainees || members.trainees.length === 0) {
      traineesGrid.innerHTML = '<p style="color:var(--muted);padding:12px;">No trainees available for this team</p>';
    } else {
      traineesGrid.innerHTML = members.trainees.map(t => `
        <div class="selection-card" onclick="toggleSelection(this)">
          <input type="checkbox" value="${t.person_id}" data-type="trainee" ${isParticipantSelected(t.person_id) ? 'checked' : ''}>
          <div class="selection-info">
            <div class="selection-name">
              ${escapeHtml(t.full_name)}
              <span class="badge badge-trainee" style="background:#d1fae5;color:#047857;font-size:10px;padding:2px 6px;border-radius:4px;margin-left:4px;">Trainee</span>
            </div>
            <div class="selection-meta">Trainee</div>
          </div>
        </div>
      `).join('');
    }
    
    // Mark pre-selected
    document.querySelectorAll('#athletesSelectionGrid .selection-card, #traineesSelectionGrid .selection-card').forEach(card => {
      const checkbox = card.querySelector('input[type="checkbox"]');
      if (checkbox && checkbox.checked) {
        card.classList.add('selected');
      }
    });
    
    console.log('✅ Participants loaded successfully');
  } catch (err) {
    console.error('❌ Error loading participants:', err);
    $('#athletesSelectionGrid').innerHTML = '<p style="color:var(--danger);padding:12px;">Error loading participants</p>';
    $('#traineesSelectionGrid').innerHTML = '';
  }
}

function isParticipantSelected(personId) {
  return sessionData.participants.some(p => p.person_id == personId);
}

window.toggleSelection = function(card) {
  const checkbox = card.querySelector('input[type="checkbox"]');
  if (event.target !== checkbox) {
    checkbox.checked = !checkbox.checked;
  }
  card.classList.toggle('selected', checkbox.checked);
};

function updateSessionSummary() {
  const teamSelect = $('#session_team_id');
  const venueSelect = $('#session_venue_id');
  
  $('#summary-team').textContent = teamSelect.options[teamSelect.selectedIndex]?.text || '-';
  $('#summary-date').textContent = sessionData.date || '-';
  $('#summary-time').textContent = sessionData.time || '-';
  $('#summary-venue').textContent = venueSelect.options[venueSelect.selectedIndex]?.text || '-';
  $('#summary-activities').textContent = `${sessionData.activities.length} selected`;
  $('#summary-equipment').textContent = `${sessionData.equipment.length} items`;  // ADD THIS
  
  const athletes = sessionData.participants.filter(p => p.type === 'athlete').length;
  const trainees = sessionData.participants.filter(p => p.type === 'trainee').length;
  $('#summary-participants').textContent = `${athletes} athletes, ${trainees} trainees`;
}

$('#saveCompleteSessionBtn')?.addEventListener('click', async function() {
  await collectStepData(5);
  
  const btn = this;
  btn.disabled = true;
  btn.textContent = 'Saving...';
  
  try {
    // Step 1: Create the basic session
    const formData = new URLSearchParams();
    formData.set('team_id', sessionData.team_id);
    formData.set('sked_date', sessionData.date);
    formData.set('sked_time', sessionData.time);
    formData.set('venue_id', sessionData.venue_id);
    
    const sessionResponse = await fetchJSON('training_create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });
    
    if (!sessionResponse.ok) {
      throw new Error(sessionResponse.message || 'Failed to create session');
    }
    
    const skedId = sessionResponse.sked_id;
    
    // Step 2: Add activities
    if (sessionData.activities.length > 0) {
      await fetchJSON('session_add_activities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          sked_id: skedId,
          activity_ids: JSON.stringify(sessionData.activities)
        }).toString()
      });
    }
    
    // Step 3: Add equipment (with enhanced error handling)
    if (sessionData.equipment.length > 0) {
      const equipmentResponse = await fetchJSON('session_add_equipment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          sked_id: skedId,
          equipment: JSON.stringify(sessionData.equipment)
        }).toString()
      });
      
      if (!equipmentResponse.ok) {
        // Show specific stock errors
        let errorMsg = equipmentResponse.message || 'Failed to assign equipment';
        if (equipmentResponse.errors && equipmentResponse.errors.length > 0) {
          errorMsg += '\n\n' + equipmentResponse.errors.join('\n');
        }
        throw new Error(errorMsg);
      }
    }
    
    // Step 4: Add participants
    if (sessionData.participants.length > 0) {
      await fetchJSON('session_add_participants', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          sked_id: skedId,
          participants: JSON.stringify(sessionData.participants)
        }).toString()
      });
    }
    
    showMsg('#sessionMsg', '✅ Training session created successfully!', 'success');
    
    setTimeout(() => {
      $('#sessionFormCard').style.display = 'none';
      resetSessionForm();
      loadSessions();
      loadOverview();
    }, 2000);
    
  } catch (error) {
    console.error('Error:', error);
    
    // Format error message for display
    const errorLines = error.message.split('\n');
    let displayMsg = errorLines[0];
    if (errorLines.length > 1) {
      displayMsg += '\n\nDetails:\n' + errorLines.slice(1).join('\n');
    }
    
    showMsg('#sessionMsg', 'Error: ' + displayMsg, 'error');
    btn.disabled = false;
    btn.textContent = '✓ Save Training Session';
  }
});

// Update loadSessions to keep existing implementation
// ==========================================
// LOAD SESSIONS WITH SORTING
// ==========================================

// ==========================================
// LOAD SESSIONS WITH SORTING
// ==========================================

// ==========================================
// LOAD SESSIONS WITH SORTING
// ==========================================

async function loadSessions(sortBy = 'upcoming') {
  try {
    const data = await fetchJSON('training_list');
    const content = $('#sessionsListContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = `
        <div class="card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training sessions. Click "Create New Session" to start.</p>
        </div>
      `;
      return;
    }
    
    console.log('📊 Sessions loaded:', data.length, 'Sort by:', sortBy);
    
    // Make a copy for sorting
    let sortedData = [...data];
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    // Apply sorting based on selection
    switch(sortBy) {
      case 'upcoming':
        sortedData.sort((a, b) => {
          const dateA = new Date(a.sked_date + 'T00:00:00');
          const dateB = new Date(b.sked_date + 'T00:00:00');
          const isPastA = dateA < today ? 1 : 0;
          const isPastB = dateB < today ? 1 : 0;
          
          if (isPastA !== isPastB) return isPastA - isPastB;
          
          if (isPastA === 0) {
            return dateA - dateB;
          } else {
            return dateB - dateA;
          }
        });
        break;
        
      case 'recent':
        sortedData.sort((a, b) => {
          const dateTimeA = new Date(a.sked_date + 'T' + (a.sked_time || '00:00'));
          const dateTimeB = new Date(b.sked_date + 'T' + (b.sked_time || '00:00'));
          return dateTimeB - dateTimeA;
        });
        break;
        
      case 'oldest':
        sortedData.sort((a, b) => {
          const dateTimeA = new Date(a.sked_date + 'T' + (a.sked_time || '00:00'));
          const dateTimeB = new Date(b.sked_date + 'T' + (b.sked_time || '00:00'));
          return dateTimeA - dateTimeB;
        });
        break;
        
      case 'team':
        sortedData.sort((a, b) => a.team_name.localeCompare(b.team_name));
        break;
    }
    
    console.log('✅ Sorted', sortedData.length, 'sessions by', sortBy);
    
    // For 'upcoming' view, separate into sections
    if (sortBy === 'upcoming') {
      const upcoming = [];
      const past = [];
      
      sortedData.forEach(s => {
        const sessionDate = new Date(s.sked_date + 'T00:00:00');
        if (sessionDate >= today) {
          upcoming.push(s);
        } else {
          past.push(s);
        }
      });
      
      console.log('📅 Upcoming:', upcoming.length, '📋 Past:', past.length);
      
      let html = '';
      
      if (upcoming.length > 0) {
        html += `
          <div style="margin-bottom:24px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;color:var(--primary);display:flex;align-items:center;gap:8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              Upcoming Sessions (${upcoming.length})
            </h3>
            ${renderSessionCards(upcoming, false)}
          </div>
        `;
      }
      
      if (past.length > 0) {
        html += `
          <div>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;color:var(--muted);display:flex;align-items:center;gap:8px;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
              </svg>
              Past Sessions (${past.length})
            </h3>
            ${renderSessionCards(past, true)}
          </div>
        `;
      }
      
      content.innerHTML = html;
    } else {
      // For other sorts, show all in one list without sections
      const html = `
        <div style="margin-bottom:16px;">
          <h3 style="font-size:14px;font-weight:700;margin-bottom:12px;color:var(--muted);">
            All Sessions (${sortedData.length})
          </h3>
          ${renderSessionCards(sortedData, false)}
        </div>
      `;
      content.innerHTML = html;
    }
    
  } catch (err) {
    console.error('❌ loadSessions error:', err);
    $('#sessionsListContent').innerHTML = `
      <div class="card">
        <p style="color:var(--danger);text-align:center;padding:20px;">
          Error loading sessions. Please refresh the page.
        </p>
      </div>
    `;
  }
}

// Event listener for sort dropdown
$('#sessionSortSelect')?.addEventListener('change', function() {
  console.log('🔄 Sort changed to:', this.value);
  loadSessions(this.value);
});

function renderSessionCards(sessions, isPast) {
  return sessions.map(s => {
    const isActive = s.is_active == 1;
    
    // Format date nicely
    const [year, month, day] = s.sked_date.split('-');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const formattedDate = `${monthNames[parseInt(month) - 1]} ${parseInt(day)}, ${year}`;
    
    return `
      <div class="card" style="margin-bottom:16px;${isPast ? 'opacity:0.85;background:#f9fafb;' : 'background:white;'}">
        <div class="data-card-header">
          <div class="data-card-title" style="display:flex;align-items:center;gap:8px;">
            ${!isPast ? 
              '<span style="color:#10b981;font-size:20px;">📅</span>' : 
              '<span style="color:#6b7280;font-size:20px;">✅</span>'
            }
            ${escapeHtml(s.team_name)}
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            ${!isPast ? 
              '<span class="badge" style="background:#dbeafe;color:#1e40af;font-weight:700;">Upcoming</span>' : 
              '<span class="badge" style="background:#f3f4f6;color:#6b7280;font-weight:700;">Completed</span>'
            }
            <span class="badge ${isActive ? 'active' : 'inactive'}">
              ${isActive ? '● Active' : '● Inactive'}
            </span>
          </div>
        </div>
        <div class="data-card-meta" style="margin-top:12px;">
          <div style="display:grid;grid-template-columns:auto 1fr;gap:8px 12px;font-size:13px;">
            <span style="color:var(--muted);font-weight:600;">📅 Date:</span>
            <span style="font-weight:700;color:var(--text);">${formattedDate}</span>
            
            <span style="color:var(--muted);font-weight:600;">⏰ Time:</span>
            <span style="font-weight:700;color:var(--text);">${escapeHtml(s.sked_time)}</span>
            
            <span style="color:var(--muted);font-weight:600;">📍 Venue:</span>
            <span style="font-weight:700;color:var(--text);">${escapeHtml(s.venue_name || 'TBA')}</span>
          </div>
        </div>
        <div class="session-card-actions" style="margin-top:16px;">
          <button class="btn btn-secondary btn-small" onclick="viewSessionDetails(${s.sked_id})" style="flex:1;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            View Details
          </button>
          <button class="btn ${isPast ? 'btn-primary' : 'btn-success'} btn-small" 
                  onclick="goToAttendanceForSession(${s.sked_id})" 
                  style="flex:1;">
            ${isPast ? '📋 View Attendance' : '✏️ Mark Attendance'}
          </button>
        </div>
      </div>
    `;
  }).join('');
}

// ==========================================
// ATTENDANCE
// ==========================================

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

// ==========================================
// UPDATE ATTENDANCE TO USE PARTICIPANTS
// ==========================================

// Replace the attendanceSessionSelect change handler in coach.js with this enhanced version

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
    
    const attendance = await fetchJSON('session_attendance_v2', { sked_id: skedId });
    
    console.log('📥 Attendance response:', attendance);
    
    // Check if response indicates an error with migration option
    if (!attendance || !Array.isArray(attendance)) {
      const errorMsg = (attendance && attendance.message) ? attendance.message : 'Could not load attendance';
      const canMigrate = attendance && attendance.can_migrate;
      const sessionId = attendance && attendance.sked_id;
      
      console.error('❌ Attendance error:', errorMsg);
      
      content.innerHTML = `
        <div class="card">
          <div style="text-align:center;padding:20px;">
            <div style="font-size:48px;margin-bottom:16px;">📋</div>
            <p style="color:var(--danger);font-weight:600;margin-bottom:12px;">
              ${escapeHtml(errorMsg)}
            </p>
            ${canMigrate ? `
              <div style="margin-top:20px;">
                <button class="btn btn-primary" onclick="migrateSingleSession(${sessionId})">
                  🔄 Auto-Assign Team Members to This Session
                </button>
              </div>
              <p style="color:var(--muted);font-size:12px;margin-top:12px;">
                Or use the "Migrate All Sessions" button above to fix all sessions at once.
              </p>
            ` : `
              <p style="color:var(--muted);font-size:13px;margin-top:12px;">
                Please add athletes or trainees to this team first.
              </p>
            `}
          </div>
        </div>
      `;
      return;
    }
    
    if (attendance.length === 0) {
      console.warn('⚠️ No participants found');
      content.innerHTML = `
        <div class="card">
          <div style="text-align:center;padding:40px;">
            <div style="font-size:48px;margin-bottom:16px;">👥</div>
            <p style="color:var(--muted);font-size:16px;font-weight:600;margin-bottom:8px;">
              No participants assigned to this session
            </p>
            <p style="color:var(--muted);font-size:13px;">
              Use the "Migrate All Sessions" button above to automatically assign team members.
            </p>
          </div>
        </div>
      `;
      return;
    }
    
    console.log('✅ Loaded', attendance.length, 'participants');
    
    content.innerHTML = `
      <div class="card">
        <div style="margin-bottom:16px;">
          <button class="btn btn-success" id="saveAllAttendanceBtn" style="margin-right:8px;">
            💾 Save All Attendance
          </button>
          <span style="color:var(--muted);font-size:13px;">Check participants who attended, then click Save</span>
        </div>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Participant Name</th>
                <th>Type</th>
                <th style="text-align:center;width:150px;">Present</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${attendance.map(a => {
                const isPresent = parseInt(a.is_present) === 1;
                const isAthlete = a.member_type === 'athlete';
                return `
                  <tr>
                    <td><strong>${escapeHtml(a.full_name)}</strong></td>
                    <td>
                      <span class="badge" style="background:${isAthlete ? '#dbeafe' : '#d1fae5'};color:${isAthlete ? '#1e40af' : '#047857'};">
                        ${isAthlete ? '👤 Athlete' : '🎓 Trainee'}
                      </span>
                    </td>
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
    
    // Add event listener for save button
    const saveBtn = $('#saveAllAttendanceBtn');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        await saveAllAttendance(skedId, attendance);
      });
    }
    
    // Update badge when checkbox changes
    const checkboxes = document.querySelectorAll('.attendance-checkbox');
    checkboxes.forEach(checkbox => {
      checkbox.addEventListener('change', function() {
        const row = this.closest('tr');
        const badge = row.querySelector('.badge.present, .badge.absent');
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

// Add this function to coach.js
window.migrateSingleSession = async function(skedId) {
  if (!skedId) return;
  
  const content = $('#attendanceContent');
  
  content.innerHTML = '<div class="loading">Assigning team members to this session...</div>';
  
  try {
    const result = await fetchJSON('migrate_session_participants', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ sked_id: skedId }).toString()
    });
    
    if (result.ok) {
      content.innerHTML = `
        <div class="card">
          <div style="text-align:center;padding:20px;">
            <div style="font-size:48px;margin-bottom:16px;">✅</div>
            <p style="color:var(--success);font-weight:600;margin-bottom:12px;">
              Success! Assigned ${result.count} participants to this session.
            </p>
            <button class="btn btn-primary" onclick="reloadAttendanceForSession()">
              📋 View Attendance
            </button>
          </div>
        </div>
      `;
    } else {
      throw new Error(result.message || 'Migration failed');
    }
  } catch (err) {
    console.error('Migration error:', err);
    content.innerHTML = `
      <div class="card">
        <p style="color:var(--danger);text-align:center;padding:20px;">
          <strong>Error:</strong> ${escapeHtml(err.message)}
        </p>
      </div>
    `;
  }
};

window.reloadAttendanceForSession = function() {
  const select = $('#attendanceSessionSelect');
  if (select && select.value) {
    select.dispatchEvent(new Event('change'));
  }
};

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
// VIEW SESSION DETAILS
// ==========================================

let currentSessionId = null;

window.viewSessionDetails = async function(skedId) {
  currentSessionId = skedId;
  
  try {
    // Show modal with loading state
    $('#sessionDetailsModal').classList.add('active');
    $('#detailActivitiesList').innerHTML = '<p style="color:var(--muted);">Loading activities...</p>';
    $('#detailParticipantsList').innerHTML = '<p style="color:var(--muted);">Loading participants...</p>';
    $('#detailEquipmentList').innerHTML = '<p style="color:var(--muted);">Loading equipment...</p>';  // ADD THIS
    
    // Fetch session details
    const details = await fetchJSON('session_details', { sked_id: skedId });
    
    if (!details || details.ok === false) {
      alert('Error loading session details');
      closeSessionDetailsModal();
      return;
    }
    
    // Populate basic info
    $('#detail-team').textContent = details.team_name || '-';
    $('#detail-date').textContent = details.sked_date || '-';
    $('#detail-time').textContent = details.sked_time || '-';
    
    let venueText = details.venue_name || 'TBA';
    if (details.venue_building) {
      venueText += ' - ' + details.venue_building;
    }
    if (details.venue_room) {
      venueText += ' (' + details.venue_room + ')';
    }
    $('#detail-venue').textContent = venueText;
    
    // Display activities
    const activitiesEl = $('#detailActivitiesList');
    if (!details.activities || details.activities.length === 0) {
      activitiesEl.innerHTML = '<div class="empty-details">No activities assigned to this session</div>';
    } else {
      activitiesEl.innerHTML = details.activities.map((a, idx) => `
        <div class="detail-item">
          <div class="detail-icon activity">${idx + 1}</div>
          <div class="detail-content">
            <div class="detail-name">${escapeHtml(a.activity_name)}</div>
            <div class="detail-meta">
              ${a.duration ? escapeHtml(a.duration) : 'No duration'} 
              ${a.duration && a.repetition ? ' • ' : ''} 
              ${a.repetition ? escapeHtml(a.repetition) : ''}
            </div>
          </div>
        </div>
      `).join('');
    }
    
    // Display equipment (NEW)
    const equipment = await fetchJSON('session_equipment', { sked_id: skedId });
    const equipmentEl = $('#detailEquipmentList');
    
    if (!equipment || equipment.length === 0) {
      equipmentEl.innerHTML = '<div class="empty-details">No equipment assigned to this session</div>';

      } else {
  equipmentEl.innerHTML = equipment.map(e => `
    <div class="detail-item">
      <div class="detail-icon equipment">🏋️</div>
      <div class="detail-content">
        <div class="detail-name">${escapeHtml(e.equip_name)}</div>
        <div class="detail-meta">
          Quantity: ${e.quantity_used}
          ${e.description ? ' • ' + escapeHtml(e.description) : ''}
        </div>
      </div>
    </div>
  `).join('');
}

// Display participants
const participantsEl = $('#detailParticipantsList');
if (!details.participants || details.participants.length === 0) {
  participantsEl.innerHTML = '<div class="empty-details">No participants assigned to this session</div>';
  $('#participantsSummary').innerHTML = '<span class="summary-badge">0 Participants</span>';
} else {
  // Count athletes and trainees
  const athletes = details.participants.filter(p => p.participant_type === 'athlete');
  const trainees = details.participants.filter(p => p.participant_type === 'trainee');
  
  // Update summary
  $('#participantsSummary').innerHTML = `
    <span class="summary-badge" style="background:#dbeafe;color:#1e40af;">👤 ${athletes.length} Athletes</span>
    <span class="summary-badge" style="background:#d1fae5;color:#047857;">🎓 ${trainees.length} Trainees</span>
  `;
  
  // Display list
  participantsEl.innerHTML = details.participants.map(p => {
    const isAthlete = p.participant_type === 'athlete';
    return `
      <div class="detail-item">
        <div class="detail-icon ${isAthlete ? 'athlete' : 'trainee'}">
          ${isAthlete ? '👤' : '🎓'}
        </div>
        <div class="detail-content">
          <div class="detail-name">${escapeHtml(p.full_name)}</div>
          <div class="detail-meta">${isAthlete ? 'Athlete' : 'Trainee'}</div>
        </div>
      </div>
    `;
  }).join('');
}
    
} catch (err) {
console.error('Error loading session details:', err);
alert('Error loading session details: ' + err.message);
closeSessionDetailsModal();
}
};

window.closeSessionDetailsModal = function() {
  $('#sessionDetailsModal').classList.remove('active');
  currentSessionId = null;
};

window.goToAttendanceFromDetails = function() {
  if (!currentSessionId) return;
  
  // Close modal
  closeSessionDetailsModal();
  
  // Switch to attendance view
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  document.querySelector('.nav-link[data-view="attendance"]')?.classList.add('active');
  
  document.querySelectorAll('.content-view').forEach(p => p.classList.remove('active'));
  $('#attendance-view').classList.add('active');
  
  // Load attendance for this session
  loadAttendanceSessions().then(() => {
    const select = $('#attendanceSessionSelect');
    if (select) {
      select.value = currentSessionId;
      select.dispatchEvent(new Event('change'));
    }
  });
  
  // Update page title
  const pageTitle = $('#pageTitle');
  if (pageTitle) {
    pageTitle.textContent = 'Session Attendance';
  }
};

// Close modal when clicking outside
$('#sessionDetailsModal')?.addEventListener('click', (e) => {
  if (e.target.id === 'sessionDetailsModal') {
    closeSessionDetailsModal();
  }
});

// ==========================================
// UPDATE LOAD SESSIONS TO INCLUDE VIEW BUTTON
// ==========================================

async function loadSessions() {
  try {
    const data = await fetchJSON('training_list');
    const content = $('#sessionsListContent');
    
    if (!data || data.length === 0) {
      content.innerHTML = `
        <div class="card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No training sessions. Click "Create New Session" to start.</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = data.map(s => {
      const isActive = s.is_active == 1;
      const isPast = new Date(s.sked_date) < new Date();
      
      return `
        <div class="card" style="margin-bottom:16px;">
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
          <div class="session-card-actions">
            <button class="btn btn-secondary btn-small" onclick="viewSessionDetails(${s.sked_id})" style="flex:1;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              View Details
            </button>
            ${isPast ? `
              <button class="btn btn-primary btn-small" onclick="goToAttendanceForSession(${s.sked_id})" style="flex:1;">
                📝 Attendance
              </button>
            ` : ''}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('❌ loadSessions error:', err);
  }
}

window.goToAttendanceForSession = function(skedId) {
  currentSessionId = skedId;
  
  // Switch to attendance view
  document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
  document.querySelector('.nav-link[data-view="attendance"]')?.classList.add('active');
  
  document.querySelectorAll('.content-view').forEach(p => p.classList.remove('active'));
  $('#attendance-view').classList.add('active');
  
  // Load attendance for this session
  loadAttendanceSessions().then(() => {
    const select = $('#attendanceSessionSelect');
    if (select) {
      select.value = skedId;
      select.dispatchEvent(new Event('change'));
    }
  });
  
  // Update page title
  const pageTitle = $('#pageTitle');
  if (pageTitle) {
    pageTitle.textContent = 'Session Attendance';
  }
  
  // Scroll to top
  window.scrollTo({ top: 0, behavior: 'smooth' });
};

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

// Add these functions to your existing coach.js file

// Update pageTitles object


// Update loadViewData function


// ==========================================
// TRAINEES
// ==========================================

async function loadTrainees() {
  try {
    const data = await fetchJSON('trainees');
    const tbody = $('#traineesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      renderRows(tbody, '<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--muted);">No trainees or athletes found</td></tr>');
      return;
    }
    
    const html = data.map(r => {
      const isAthlete = r.member_type === 'Athlete';
      const typeColor = isAthlete ? '#3b82f6' : '#10b981';
      const typeIcon = isAthlete ? '🏃' : '🎓';
      
      return `
        <tr>
          <td>
            <strong>${escapeHtml(r.trainee_name)}</strong>
          </td>
          <td>${escapeHtml(r.team_name)}</td>
          <td>
            <span class="badge" style="background: ${isAthlete ? '#dbeafe' : '#d1fae5'}; color: ${isAthlete ? '#1e40af' : '#047857'}; border: none;">
              ${typeIcon} ${r.member_type}
            </span>
          </td>
          <td>${r.semester ? escapeHtml(r.semester) : 'N/A'}</td>
          <td>${escapeHtml(r.school_year || 'N/A')}</td>
          <td>${escapeHtml(r.date_applied || 'N/A')}</td>
          <td>
            <span class="badge ${r.is_active == 1 ? 'active' : 'inactive'}">
              ${r.is_active == 1 ? '✓ Active' : '✗ Inactive'}
            </span>
          </td>
        </tr>
      `;
    }).join('');
    
    renderRows(tbody, html);
    setupSearch('#traineesSearch', '#traineesTable');
    console.log('✅ Trainees/Athletes loaded:', data.length);
  } catch (err) {
    console.error('❌ loadTrainees error:', err);
    $('#traineesTable tbody').innerHTML = '<tr><td colspan="7" style="color:red;text-align:center;padding:20px;">Error loading trainees</td></tr>';
  }
}

// ==========================================
// TRAINING ACTIVITIES
// ==========================================

$('#addActivityBtn')?.addEventListener('click', () => {
  resetActivityForm();
  $('#activityFormCard').style.display = 'block';
});

$('#cancelActivityBtn')?.addEventListener('click', () => {
  $('#activityFormCard').style.display = 'none';
  resetActivityForm();
});

function resetActivityForm() {
  $('#activityForm').reset();
  $('#activity_id').value = '';
  $('#activity_active').checked = true;
  $('#activityMsg').style.display = 'none';
}

$('#activityForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new URLSearchParams();
  formData.set('activity_id', $('#activity_id').value || '');
  formData.set('activity_name', $('#activity_name').value);
  formData.set('duration', $('#activity_duration').value);
  formData.set('repetition', $('#activity_repetition').value);
  if ($('#activity_active').checked) {
    formData.set('is_active', '1');
  }

  try {
    const data = await fetchJSON('save_activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData.toString()
    });

    showMsg('#activityMsg', data.message || (data.ok ? "Activity saved successfully!" : "Failed to save."), data.ok ? 'success' : 'error');
    
    if (data.ok) {
      setTimeout(() => {
        $('#activityFormCard').style.display = 'none';
        resetActivityForm();
        loadActivities();
      }, 1500);
    }
  } catch (err) {
    console.error('❌ Form submit error:', err);
    showMsg('#activityMsg', 'Error saving activity', 'error');
  }
});

async function loadActivities() {
  try {
    const data = await fetchJSON('activities');
    const tbody = $('#activitiesTable tbody');
    
    if (!Array.isArray(data) || data.length === 0) {
      renderRows(tbody, '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--muted);">No activities found. Click "Add Activity" to create one.</td></tr>');
      return;
    }
    
    const html = data.map(r => `
      <tr>
        <td><strong>${escapeHtml(r.activity_name)}</strong></td>
        <td>${escapeHtml(r.duration || 'Not specified')}</td>
        <td>${escapeHtml(r.repetition || 'Not specified')}</td>
        <td>
          <span class="badge ${r.is_active == 1 ? 'active' : 'inactive'}">
            ${r.is_active == 1 ? '✓ Active' : '✗ Inactive'}
          </span>
        </td>
        <td>
          <button class="btn btn-secondary" style="padding:6px 12px;font-size:12px;" onclick="editActivity(${r.activity_id})">
            Edit
          </button>
          <button class="btn btn-danger" style="padding:6px 12px;font-size:12px;margin-left:4px;" onclick="deleteActivity(${r.activity_id})">
            Deactivate
          </button>
        </td>
      </tr>
    `).join('');
    
    renderRows(tbody, html);
    console.log('✅ Activities loaded:', data.length);
  } catch (err) {
    console.error('❌ loadActivities error:', err);
    $('#activitiesTable tbody').innerHTML = '<tr><td colspan="5" style="color:red;text-align:center;padding:20px;">Error loading activities</td></tr>';
  }
}

window.editActivity = async (activityId) => {
  try {
    const activity = await fetchJSON('get_activity', { activity_id: activityId });
    
    if (!activity || activity.ok === false) {
      alert('Error loading activity data');
      return;
    }
    
    $('#activity_id').value = activity.activity_id;
    $('#activity_name').value = activity.activity_name || '';
    $('#activity_duration').value = activity.duration || '';
    $('#activity_repetition').value = activity.repetition || '';
    $('#activity_active').checked = activity.is_active == 1;
    
    $('#activityFormCard').style.display = 'block';
    $('#activityFormCard').scrollIntoView({ behavior: 'smooth' });
    
  } catch (err) {
    console.error('Edit activity error:', err);
    alert('Error loading activity data');
  }
};

window.deleteActivity = async (activityId) => {
  if (!confirm('Are you sure you want to deactivate this training activity?')) return;
  
  try {
    const result = await fetchJSON('delete_activity', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ activity_id: activityId }).toString()
    });
    
    if (result && result.ok) {
      loadActivities();
    } else {
      alert(result?.message || 'Error deactivating activity');
    }
  } catch (err) {
    console.error('Delete activity error:', err);
    alert('Error deactivating activity');
  }
};

// ==========================================
// REPORTS
// ==========================================

async function loadReportsView() {
  try {
    // Load sessions for selection
    const sessions = await fetchJSON('report_sessions_list');
    const select = $('#reportSessionSelect');
    
    if (!sessions || sessions.length === 0) {
      select.innerHTML = '<option value="">No sessions available</option>';
      return;
    }
    
    select.innerHTML = '<option value="">-- Select Session --</option>' +
      sessions.map(s => {
        const date = new Date(s.sked_date).toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        });
        return `<option value="${s.sked_id}">${date} - ${escapeHtml(s.sked_time)} - ${escapeHtml(s.team_name)}</option>`;
      }).join('');
  } catch (err) {
    console.error('Error loading reports view:', err);
  }
}

$('#reportSessionSelect')?.addEventListener('change', async function() {
  const skedId = this.value;
  const reportDisplay = $('#reportDisplay');
  const reportContent = $('#reportTextContent');
  
  if (!skedId) {
    reportDisplay.style.display = 'none';
    return;
  }
  
  reportContent.textContent = 'Loading report...';
  reportDisplay.style.display = 'block';
  
  try {
    const data = await fetchJSON('report_session_details', { sked_id: skedId });
    
    if (!data || data.ok === false) {
      reportContent.textContent = 'Error loading report: ' + (data?.message || 'Unknown error');
      return;
    }
    
    const reportText = generatePlainTextReport(data);
    reportContent.textContent = reportText;
    
    // Store for printing
    window.currentReport = reportText;
    
  } catch (err) {
    console.error('Error generating report:', err);
    reportContent.textContent = 'Error generating report: ' + err.message;
  }
});

function generatePlainTextReport(data) {
  const divider = '='.repeat(80);
  const smallDivider = '-'.repeat(80);
  
  // Format date
  const sessionDate = new Date(data.sked_date);
  const formattedDate = sessionDate.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
  
  // Build venue info
  let venueInfo = data.venue_name || 'Not specified';
  if (data.venue_building) venueInfo += `, ${data.venue_building}`;
  if (data.venue_room) venueInfo += ` (${data.venue_room})`;
  
  let report = `
${divider}
                        TRAINING SESSION REPORT
${divider}

SESSION INFORMATION
${smallDivider}
Team:           ${data.team_name}
Date:           ${formattedDate}
Time:           ${data.sked_time}
Venue:          ${venueInfo}
Coach:          ${data.coach_name || 'Not specified'}

ATTENDANCE SUMMARY
${smallDivider}
Total Participants:     ${data.stats.total_participants}
Present:                ${data.stats.total_present}
Absent:                 ${data.stats.total_absent}
Attendance Rate:        ${data.stats.attendance_rate}%

Athletes:               ${data.stats.athletes_present}/${data.stats.athletes_total} present
Trainees:               ${data.stats.trainees_present}/${data.stats.trainees_total} present
`;

  // Add activities section
  if (data.activities && data.activities.length > 0) {
    report += `
TRAINING ACTIVITIES
${smallDivider}
`;
    data.activities.forEach((activity, idx) => {
      report += `${idx + 1}. ${activity.activity_name}\n`;
      if (activity.duration || activity.repetition) {
        const details = [];
        if (activity.duration) details.push(`Duration: ${activity.duration}`);
        if (activity.repetition) details.push(`Repetition: ${activity.repetition}`);
        report += `   ${details.join(' | ')}\n`;
      }
      report += '\n';
    });
  } else {
    report += `
TRAINING ACTIVITIES
${smallDivider}
No activities recorded for this session.

`;
  }

  // Add participants section
  if (data.participants && data.participants.length > 0) {
    // Separate athletes and trainees
    const athletes = data.participants.filter(p => p.participant_type === 'athlete');
    const trainees = data.participants.filter(p => p.participant_type === 'trainee');
    
    if (athletes.length > 0) {
      report += `ATHLETES ATTENDANCE
${smallDivider}
`;
      report += `${'Name'.padEnd(35)} ${'Status'.padEnd(10)} ${'Blood Type'.padEnd(12)} Course\n`;
      report += smallDivider + '\n';
      
      athletes.forEach(p => {
        const status = p.is_present == 1 ? '[PRESENT]' : '[ABSENT]';
        const name = p.full_name.padEnd(35);
        const statusPad = status.padEnd(10);
        const bloodType = (p.blood_type || 'N/A').padEnd(12);
        const course = p.course || 'N/A';
        
        report += `${name} ${statusPad} ${bloodType} ${course}\n`;
      });
      report += '\n';
    }
    
    if (trainees.length > 0) {
      report += `TRAINEES ATTENDANCE
${smallDivider}
`;
      report += `${'Name'.padEnd(35)} ${'Status'.padEnd(10)} ${'Blood Type'.padEnd(12)} Course\n`;
      report += smallDivider + '\n';
      
      trainees.forEach(p => {
        const status = p.is_present == 1 ? '[PRESENT]' : '[ABSENT]';
        const name = p.full_name.padEnd(35);
        const statusPad = status.padEnd(10);
        const bloodType = (p.blood_type || 'N/A').padEnd(12);
        const course = p.course || 'N/A';
        
        report += `${name} ${statusPad} ${bloodType} ${course}\n`;
      });
      report += '\n';
    }
  } else {
    report += `PARTICIPANTS
${smallDivider}
No participants recorded for this session.

`;
  }

  // Footer
  const now = new Date();
  const generatedDate = now.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
  
  report += `${divider}
Report generated on: ${generatedDate}
UEP Sports Management System
${divider}
`;

  return report;
}

window.printSessionReport = function() {
  window.print();
};

window.copyReportText = function() {
  const reportText = window.currentReport;
  if (!reportText) {
    alert('No report to copy');
    return;
  }
  
  navigator.clipboard.writeText(reportText).then(() => {
    alert('Report copied to clipboard!');
  }).catch(err => {
    console.error('Failed to copy:', err);
    alert('Failed to copy report. Please select and copy manually.');
  });
};

window.generateReport = async function(reportType) {
  const dateFrom = $('#report_date_from').value;
  const dateTo = $('#report_date_to').value;
  const teamId = $('#report_team_filter').value;
  
  const resultsDiv = $('#reportResults');
  const contentDiv = $('#reportContent');
  const titleEl = $('#reportTitle');
  
  resultsDiv.style.display = 'block';
  contentDiv.innerHTML = '<div class="loading">Generating report...</div>';
  
  const params = {};
  if (dateFrom) params.date_from = dateFrom;
  if (dateTo) params.date_to = dateTo;
  if (teamId) params.team_id = teamId;
  
  try {
    let data, title, content;
    
    switch(reportType) {
      case 'attendance':
        data = await fetchJSON('report_attendance', params);
        title = '📊 Attendance Report';
        content = generateAttendanceReportHTML(data, dateFrom, dateTo);
        break;
        
      case 'performance':
        data = await fetchJSON('report_performance', params);
        title = '⭐ Performance Report';
        content = generatePerformanceReportHTML(data, dateFrom, dateTo);
        break;
        
      case 'sessions':
        data = await fetchJSON('report_sessions', params);
        title = '📅 Training Sessions Report';
        content = generateSessionsReportHTML(data, dateFrom, dateTo);
        break;
        
      case 'player_attendance':
        data = await fetchJSON('report_player_attendance', params);
        title = '👥 Player Attendance Detail Report';
        content = generatePlayerAttendanceReportHTML(data, dateFrom, dateTo);
        break;
        
      default:
        throw new Error('Unknown report type');
    }
    
    titleEl.textContent = title;
    contentDiv.innerHTML = content;
    window.currentReportData = data;
    window.currentReportType = reportType;
    
    resultsDiv.scrollIntoView({ behavior: 'smooth' });
    
  } catch (err) {
    console.error('Error generating report:', err);
    contentDiv.innerHTML = `
      <div style="color:var(--danger);text-align:center;padding:40px;">
        Error generating report: ${escapeHtml(err.message)}
      </div>
    `;
  }
};

function generateAttendanceReportHTML(data, dateFrom, dateTo) {
  if (!data || data.length === 0) {
    return '<p style="text-align:center;padding:40px;color:var(--muted);">No attendance data found for the selected period.</p>';
  }
  
  const totalSessions = data.length;
  const avgAttendance = (data.reduce((sum, r) => sum + parseFloat(r.attendance_rate || 0), 0) / totalSessions).toFixed(1);
  
  return `
    <div class="report-summary">
      <h4>Summary</h4>
      <div class="report-stat">
        <span class="report-stat-label">Period:</span>
        <span class="report-stat-value">${dateFrom || 'All time'} to ${dateTo || 'Present'}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Total Sessions:</span>
        <span class="report-stat-value">${totalSessions}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Average Attendance Rate:</span>
        <span class="report-stat-value">${avgAttendance}%</span>
      </div>
    </div>
    
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Time</th>
            <th>Team</th>
            <th>Venue</th>
            <th>Total Members</th>
            <th>Present</th>
            <th>Attendance Rate</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(r => `
            <tr>
              <td>${escapeHtml(r.sked_date || 'N/A')}</td>
              <td>${escapeHtml(r.sked_time || 'N/A')}</td>
              <td><strong>${escapeHtml(r.team_name)}</strong></td>
              <td>${escapeHtml(r.venue_name || 'N/A')}</td>
              <td>${r.total_members || 0}</td>
              <td>${r.present_count || 0}</td>
              <td>
                <span class="rating-display ${getAttendanceClass(r.attendance_rate)}">
                  ${parseFloat(r.attendance_rate || 0).toFixed(1)}%
                </span>
              </td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function generatePerformanceReportHTML(data, dateFrom, dateTo) {
  if (!data || data.length === 0) {
    return '<p style="text-align:center;padding:40px;color:var(--muted);">No performance data found for the selected period.</p>';
  }
  
  const totalEvaluations = data.reduce((sum, r) => sum + parseInt(r.total_evaluations || 0), 0);
  const overallAvg = (data.reduce((sum, r) => sum + parseFloat(r.avg_rating || 0), 0) / data.length).toFixed(2);
  
  return `
    <div class="report-summary">
      <h4>Summary</h4>
      <div class="report-stat">
        <span class="report-stat-label">Period:</span>
        <span class="report-stat-value">${dateFrom || 'All time'} to ${dateTo || 'Present'}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Players Evaluated:</span>
        <span class="report-stat-value">${data.length}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Total Evaluations:</span>
        <span class="report-stat-value">${totalEvaluations}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Overall Average Rating:</span>
        <span class="report-stat-value">${overallAvg}/10</span>
      </div>
    </div>
    
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Evaluations</th>
            <th>Avg Rating</th>
            <th>Max Rating</th>
            <th>Min Rating</th>
            <th>Last Evaluated</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(r => {
            const avgRating = parseFloat(r.avg_rating || 0);
            return `
              <tr>
                <td><strong>${escapeHtml(r.player_name)}</strong></td>
                <td>${escapeHtml(r.team_name)}</td>
                <td>${r.total_evaluations || 0}</td>
                <td>
                  <span class="rating-display ${getRatingClass(avgRating)}">
                    ${avgRating.toFixed(2)}/10
                  </span>
                </td>
                <td>${parseFloat(r.max_rating || 0).toFixed(1)}</td>
                <td>${parseFloat(r.min_rating || 0).toFixed(1)}</td>
                <td>${escapeHtml(r.last_evaluated || 'N/A')}</td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function generateSessionsReportHTML(data, dateFrom, dateTo) {
  if (!data || data.length === 0) {
    return '<p style="text-align:center;padding:40px;color:var(--muted);">No sessions data found for the selected period.</p>';
  }
  
  const totalSessions = data.reduce((sum, r) => sum + parseInt(r.total_sessions || 0), 0);
  
  return `
    <div class="report-summary">
      <h4>Summary</h4>
      <div class="report-stat">
        <span class="report-stat-label">Period:</span>
        <span class="report-stat-value">${dateFrom || 'All time'} to ${dateTo || 'Present'}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Total Sessions:</span>
        <span class="report-stat-value">${totalSessions}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Teams:</span>
        <span class="report-stat-value">${data.length}</span>
      </div>
    </div>
    
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Team</th>
            <th>Total Sessions</th>
            <th>Months Active</th>
            <th>First Session</th>
            <th>Last Session</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(r => `
            <tr>
              <td><strong>${escapeHtml(r.team_name)}</strong></td>
              <td>${r.total_sessions || 0}</td>
              <td>${r.months_active || 0}</td>
              <td>${escapeHtml(r.first_session || 'N/A')}</td>
              <td>${escapeHtml(r.last_session || 'N/A')}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function generatePlayerAttendanceReportHTML(data, dateFrom, dateTo) {
  if (!data || data.length === 0) {
    return '<p style="text-align:center;padding:40px;color:var(--muted);">No player attendance data found for the selected period.</p>';
  }
  
  const avgAttendance = (data.reduce((sum, r) => sum + parseFloat(r.attendance_rate || 0), 0) / data.length).toFixed(1);
  
  return `
    <div class="report-summary">
      <h4>Summary</h4>
      <div class="report-stat">
        <span class="report-stat-label">Period:</span>
        <span class="report-stat-value">${dateFrom || 'All time'} to ${dateTo || 'Present'}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Players Tracked:</span>
        <span class="report-stat-value">${data.length}</span>
      </div>
      <div class="report-stat">
        <span class="report-stat-label">Average Attendance:</span>
        <span class="report-stat-value">${avgAttendance}%</span>
      </div>
    </div>
    
    <div class="table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Type</th>
            <th>Total Sessions</th>
            <th>Attended</th>
            <th>Missed</th>
            <th>Attendance Rate</th>
          </tr>
        </thead>
        <tbody>
          ${data.map(r => {
            const isAthlete = r.member_type === 'athlete';
            return `
              <tr>
                <td><strong>${escapeHtml(r.player_name)}</strong></td>
                <td>${escapeHtml(r.team_name)}</td>
                <td>
                  <span class="badge" style="background:${isAthlete ? '#dbeafe' : '#d1fae5'};color:${isAthlete ? '#1e40af' : '#047857'};">
                    ${isAthlete ? '👤 Athlete' : '🎓 Trainee'}
                  </span>
                </td>
                <td>${r.total_sessions || 0}</td>
                <td style="color:#10b981;font-weight:700;">${r.sessions_attended || 0}</td>
                <td style="color:#ef4444;font-weight:700;">${r.sessions_missed || 0}</td>
                <td>
                  <span class="rating-display ${getAttendanceClass(r.attendance_rate)}">
                    ${parseFloat(r.attendance_rate || 0).toFixed(1)}%
                  </span>
                </td>
              </tr>
            `;
          }).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function getRatingClass(rating) {
  if (rating >= 8) return 'rating-excellent';
  if (rating >= 6) return 'rating-good';
  if (rating >= 4) return 'rating-average';
  return 'rating-poor';
}

function getAttendanceClass(rate) {
  const r = parseFloat(rate || 0);
  if (r >= 80) return 'rating-excellent';
  if (r >= 60) return 'rating-good';
  if (r >= 40) return 'rating-average';
  return 'rating-poor';
}

window.printReport = function() {
  window.print();
};

window.exportReportCSV = function() {
  const data = window.currentReportData;
  const type = window.currentReportType;
  
  if (!data || data.length === 0) {
    alert('No data to export');
    return;
  }
  
  let csv = '';
  let filename = 'report.csv';
  
  switch(type) {
    case 'attendance':
      filename = 'attendance_report.csv';
      csv = 'Date,Time,Team,Venue,Total Members,Present,Attendance Rate\n';
      data.forEach(r => {
        csv += `"${r.sked_date || ''}","${r.sked_time || ''}","${r.team_name || ''}","${r.venue_name || 'N/A'}",${r.total_members || 0},${r.present_count || 0},${r.attendance_rate || 0}\n`;
      });
      break;
      
    case 'performance':
      filename = 'performance_report.csv';
      csv = 'Player,Team,Evaluations,Avg Rating,Max Rating,Min Rating,Last Evaluated\n';
      data.forEach(r => {
        csv += `"${r.player_name || ''}","${r.team_name || ''}",${r.total_evaluations || 0},${r.avg_rating || 0},${r.max_rating || 0},${r.min_rating || 0},"${r.last_evaluated || 'N/A'}"\n`;
      });
      break;
      
    case 'sessions':
      filename = 'sessions_report.csv';
      csv = 'Team,Total Sessions,Months Active,First Session,Last Session\n';
      data.forEach(r => {
        csv += `"${r.team_name || ''}",${r.total_sessions || 0},${r.months_active || 0},"${r.first_session || 'N/A'}","${r.last_session || 'N/A'}"\n`;
      });
      break;
      
    case 'player_attendance':
      filename = 'player_attendance_report.csv';
      csv = 'Player,Team,Type,Total Sessions,Attended,Missed,Attendance Rate\n';
      data.forEach(r => {
        const memberType = r.member_type === 'athlete' ? 'Athlete' : 'Trainee';
        csv += `"${r.player_name || ''}","${r.team_name || ''}","${memberType}",${r.total_sessions || 0},${r.sessions_attended || 0},${r.sessions_missed || 0},${r.attendance_rate || 0}\n`;
      });
      break;
  }
  
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  window.URL.revokeObjectURL(url);
};

// Add to initialization
console.log('🚀 Enhanced coach dashboard with trainees, activities, and reports loaded');

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