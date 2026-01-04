const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

const { person_id } = window.UMPIRE_CONTEXT;

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
  'schedule': 'Match Schedule',
  'results': 'Match Results',
  'rankings': 'Rankings & Standings',
  'medals': 'Medal Tally',
  'tournaments': 'Tournaments'
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

function loadViewData(view) {
  switch(view) {
    case 'overview': loadOverview(); break;
    case 'schedule': loadSchedule(); break;
    case 'results': loadResults(); break;
    case 'rankings': loadRankingsFilters(); break;
    case 'medals': loadMedalsFilters(); break;
    case 'tournaments': loadTournaments(); break;
  }
}

// ==========================================
// OVERVIEW
// ==========================================

async function loadOverview() {
  try {
    const stats = await fetchAPI('umpire_stats');
    
    if (stats) {
      $('#statMatches').textContent = stats.total_matches || 0;
      $('#statUpcoming').textContent = stats.upcoming_matches || 0;
      $('#statCompleted').textContent = stats.completed_matches || 0;
      $('#statTournaments').textContent = stats.active_tournaments || 0;
    }
    
    const upcoming = await fetchAPI('upcoming_matches');
    const upcomingEl = $('#upcomingMatches');
    
    if (!upcoming || upcoming.length === 0) {
      upcomingEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No upcoming matches scheduled</p>
        </div>
      `;
    } else {
      upcomingEl.innerHTML = upcoming.slice(0, 3).map(m => `
        <div class="data-card">
          <div class="data-card-header">
            <div class="data-card-title">${escapeHtml(m.sports_name)} - ${escapeHtml(m.match_type)}</div>
            <span class="badge upcoming">Upcoming</span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(m.sked_date)}<br>
            ⏰ ${escapeHtml(m.sked_time)}<br>
            🏟️ ${escapeHtml(m.venue_name || 'TBA')}<br>
            🆚 ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)}
          </div>
        </div>
      `).join('');
    }
    
    const recent = await fetchAPI('recent_results');
    const recentEl = $('#recentResults');
    
    if (!recent || recent.length === 0) {
      recentEl.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No recent results</p>
        </div>
      `;
    } else {
      recentEl.innerHTML = `
        <div class="data-card">
          ${recent.slice(0, 5).map(r => `
            <div style="padding:12px 0;border-bottom:1px solid var(--line);">
              <div style="font-weight:600;margin-bottom:4px;">
                ${escapeHtml(r.team_a_name)} vs ${escapeHtml(r.team_b_name)}
              </div>
              <div style="font-size:12px;color:var(--muted);">
                Winner: <strong>${escapeHtml(r.winner_name)}</strong> | ${escapeHtml(r.sked_date)}
              </div>
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
    // Load filter options
    const tournaments = await fetchAPI('tournaments');
    const sports = await fetchAPI('sports');
    
    const tourSelect = $('#scheduleTournamentFilter');
    const sportSelect = $('#scheduleSportFilter');
    
    if (tournaments) {
      tourSelect.innerHTML = '<option value="">All Tournaments</option>' +
        tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)}</option>`).join('');
    }
    
    if (sports) {
      sportSelect.innerHTML = '<option value="">All Sports</option>' +
        sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
    }
    
    // Load matches
    loadScheduleMatches();
  } catch (err) {
    console.error('loadSchedule error:', err);
  }
}

$('#scheduleTournamentFilter')?.addEventListener('change', loadScheduleMatches);
$('#scheduleSportFilter')?.addEventListener('change', loadScheduleMatches);

async function loadScheduleMatches() {
  const tourId = $('#scheduleTournamentFilter')?.value || '';
  const sportId = $('#scheduleSportFilter')?.value || '';
  
  try {
    const matches = await fetchAPI('all_matches', { tour_id: tourId, sports_id: sportId });
    const content = $('#scheduleContent');
    
    if (!matches || matches.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No matches found</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = matches.map(m => {
      const isUpcoming = new Date(m.sked_date + 'T' + m.sked_time) > new Date();
      return `
        <div class="data-card" style="margin-bottom:16px;">
          <div class="data-card-header">
            <div class="data-card-title">
              ${escapeHtml(m.sports_name)} - ${escapeHtml(m.match_type)}
            </div>
            <span class="badge ${isUpcoming ? 'upcoming' : 'completed'}">
              ${isUpcoming ? 'Upcoming' : 'Completed'}
            </span>
          </div>
          <div class="data-card-meta">
            📅 ${escapeHtml(m.sked_date)} at ${escapeHtml(m.sked_time)}<br>
            🏟️ ${escapeHtml(m.venue_name || 'TBA')}<br>
            🆚 ${escapeHtml(m.team_a_name)} vs ${escapeHtml(m.team_b_name)}
            ${m.winner_name ? '<br>🏆 Winner: <strong>' + escapeHtml(m.winner_name) + '</strong>' : ''}
          </div>
        </div>
      `;
    }).join('');
  } catch (err) {
    console.error('loadScheduleMatches error:', err);
  }
}

// ==========================================
// RESULTS
// ==========================================

async function loadResults() {
  try {
    const tournaments = await fetchAPI('tournaments');
    const sports = await fetchAPI('sports');
    
    const tourSelect = $('#resultsTournamentFilter');
    const sportSelect = $('#resultsSportFilter');
    
    if (tournaments) {
      tourSelect.innerHTML = '<option value="">All Tournaments</option>' +
        tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)}</option>`).join('');
    }
    
    if (sports) {
      sportSelect.innerHTML = '<option value="">All Sports</option>' +
        sports.map(s => `<option value="${s.sports_id}">${escapeHtml(s.sports_name)}</option>`).join('');
    }
    
    loadResultsTable();
  } catch (err) {
    console.error('loadResults error:', err);
  }
}

$('#resultsTournamentFilter')?.addEventListener('change', loadResultsTable);
$('#resultsSportFilter')?.addEventListener('change', loadResultsTable);

async function loadResultsTable() {
  const tourId = $('#resultsTournamentFilter')?.value || '';
  const sportId = $('#resultsSportFilter')?.value || '';
  
  try {
    const results = await fetchAPI('match_results', { tour_id: tourId, sports_id: sportId });
    const tbody = $('#resultsTable tbody');
    
    if (!results || results.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align:center;padding:40px;">
            <p style="color:var(--muted);">No results found</p>
          </td>
        </tr>
      `;
      return;
    }
    
    tbody.innerHTML = results.map(r => `
      <tr>
        <td>${escapeHtml(r.sked_date)}</td>
        <td>${escapeHtml(r.sports_name)}</td>
        <td>${escapeHtml(r.match_type)}</td>
        <td>${escapeHtml(r.team_a_name)}</td>
        <td>${escapeHtml(r.team_b_name)}</td>
        <td><strong>${escapeHtml(r.winner_name || 'TBD')}</strong></td>
        <td>${escapeHtml(r.venue_name || 'N/A')}</td>
      </tr>
    `).join('');
  } catch (err) {
    console.error('loadResultsTable error:', err);
  }
}

// ==========================================
// RANKINGS
// ==========================================

async function loadRankingsFilters() {
  try {
    const tournaments = await fetchAPI('tournaments');
    const select = $('#rankingsTournamentFilter');
    
    if (tournaments) {
      select.innerHTML = '<option value="">Select Tournament</option>' +
        tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)}</option>`).join('');
    }
  } catch (err) {
    console.error('loadRankingsFilters error:', err);
  }
}

$('#rankingsTournamentFilter')?.addEventListener('change', async function() {
  const tourId = this.value;
  const content = $('#rankingsContent');
  
  if (!tourId) {
    content.innerHTML = '<div class="empty-state">Select a tournament to view standings</div>';
    return;
  }
  
  content.innerHTML = '<div class="loading">Loading standings...</div>';
  
  try {
    const standings = await fetchAPI('standings', { tour_id: tourId });
    
    if (!standings || standings.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No standings available</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = `
      <div class="table-container">
        <table class="user-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Team</th>
              <th>Sport</th>
              <th>GP</th>
              <th>W</th>
              <th>L</th>
              <th>D</th>
              <th>🥇</th>
              <th>🥈</th>
              <th>🥉</th>
            </tr>
          </thead>
          <tbody>
            ${standings.map((s, idx) => `
              <tr>
                <td><strong>${idx + 1}</strong></td>
                <td><strong>${escapeHtml(s.team_name)}</strong></td>
                <td>${escapeHtml(s.sports_name)}</td>
                <td>${s.no_games_played || 0}</td>
                <td>${s.no_win || 0}</td>
                <td>${s.no_loss || 0}</td>
                <td>${s.no_draw || 0}</td>
                <td>${s.no_gold || 0}</td>
                <td>${s.no_silver || 0}</td>
                <td>${s.no_bronze || 0}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  } catch (err) {
    console.error('Error loading standings:', err);
    content.innerHTML = '<div class="empty-state">Error loading standings</div>';
  }
});

// ==========================================
// MEDALS
// ==========================================

async function loadMedalsFilters() {
  try {
    const tournaments = await fetchAPI('tournaments');
    const select = $('#medalsTournamentFilter');
    
    if (tournaments) {
      select.innerHTML = '<option value="">Select Tournament</option>' +
        tournaments.map(t => `<option value="${t.tour_id}">${escapeHtml(t.tour_name)}</option>`).join('');
    }
  } catch (err) {
    console.error('loadMedalsFilters error:', err);
  }
}

$('#medalsTournamentFilter')?.addEventListener('change', async function() {
  const tourId = this.value;
  const content = $('#medalsContent');
  
  if (!tourId) {
    content.innerHTML = '<div class="empty-state">Select a tournament to view medal tally</div>';
    return;
  }
  
  content.innerHTML = '<div class="loading">Loading medals...</div>';
  
  try {
    const medals = await fetchAPI('medal_tally', { tour_id: tourId });
    
    if (!medals || medals.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No medals awarded yet</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = `
      <div class="table-container">
        <table class="user-table">
          <thead>
            <tr>
              <th>Rank</th>
              <th>Team</th>
              <th>🥇 Gold</th>
              <th>🥈 Silver</th>
              <th>🥉 Bronze</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${medals.map((m, idx) => {
              const total = (m.gold || 0) + (m.silver || 0) + (m.bronze || 0);
              return `
                <tr>
                  <td><strong>${idx + 1}</strong></td>
                  <td><strong>${escapeHtml(m.team_name)}</strong></td>
                  <td>${m.gold || 0}</td>
                  <td>${m.silver || 0}</td>
                  <td>${m.bronze || 0}</td>
                  <td><strong>${total}</strong></td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  } catch (err) {
    console.error('Error loading medals:', err);
    content.innerHTML = '<div class="empty-state">Error loading medal tally</div>';
  }
});

// ==========================================
// TOURNAMENTS
// ==========================================

async function loadTournaments() {
  try {
    const tournaments = await fetchAPI('tournaments');
    const content = $('#tournamentsContent');
    
    if (!tournaments || tournaments.length === 0) {
      content.innerHTML = `
        <div class="data-card">
          <p style="color:var(--muted);text-align:center;padding:20px;">No tournaments available</p>
        </div>
      `;
      return;
    }
    
    content.innerHTML = tournaments.map(t => `
      <div class="data-card">
        <div class="data-card-header">
          <div class="data-card-title">🏆 ${escapeHtml(t.tour_name)}</div>
          <span class="badge ${t.is_active == 1 ? 'upcoming' : 'completed'}">
            ${t.is_active == 1 ? 'Active' : 'Completed'}
          </span>
        </div>
        <div class="data-card-meta">
          📅 Date: ${escapeHtml(t.tour_date)}<br>
          📚 School Year: ${escapeHtml(t.school_year)}
        </div>
      </div>
    `).join('');
  } catch (err) {
    console.error('loadTournaments error:', err);
  }
}

// Initialize
(async function init() {
  console.log('Umpire dashboard initialized');
  await loadOverview();
})();