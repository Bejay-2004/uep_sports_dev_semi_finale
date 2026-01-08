<?php
// index.php - Landing Page (Compact Mobile-Friendly Design)
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$logout_success = isset($_GET['logout']) && $_GET['logout'] === 'success';

try {
  $sports_stmt = $pdo->query("
    SELECT sports_id, sports_name, team_individual, men_women, is_active 
    FROM tbl_sports 
    WHERE is_active = 1 
    ORDER BY sports_name
  ");
  $sports = $sports_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $sports = [];
}

try {
  $stats_stmt = $pdo->query("
    SELECT 
      (SELECT COUNT(*) FROM tbl_sports WHERE is_active = 1) as total_sports,
      (SELECT COUNT(*) FROM tbl_team WHERE is_active = 1) as total_teams,
      (SELECT COUNT(DISTINCT tour_id) FROM tbl_tournament WHERE is_active = 1) as active_tournaments
  ");
  $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $stats = ['total_sports' => 0, 'total_teams' => 0, 'active_tournaments' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UEP Sports Management System</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
      --primary: #111827;
      --accent: #3b82f6;
      --text: #111827;
      --text-muted: #6b7280;
      --bg: #ffffff;
      --bg-gray: #f9fafb;
      --border: #e5e7eb;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text);
      line-height: 1.5;
      background: var(--bg);
      font-size: 14px;
    }

    /* Logout Banner */
    .logout-banner {
      background: #059669;
      color: white;
      padding: 12px 16px;
      text-align: center;
      font-weight: 600;
      font-size: 13px;
      position: relative;
    }

    .close-banner {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      font-size: 20px;
      opacity: 0.8;
    }

    /* Header */
    .header {
      background: var(--primary);
      color: white;
      padding: 16px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo-icon {
      width: 36px;
      height: 36px;
      background: white;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }

    .logo-text h1 {
      font-size: 16px;
      font-weight: 700;
    }

    .logo-text p {
      font-size: 11px;
      opacity: 0.8;
    }

    .btn {
      padding: 8px 20px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.2s;
      display: inline-block;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: white;
      color: var(--primary);
    }

    .btn-primary:hover {
      background: #f3f4f6;
    }

    /* Hero Section - Compact */
    .hero {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      color: white;
      padding: 32px 16px 48px;
      text-align: center;
    }

    .hero-content {
      max-width: 800px;
      margin: 0 auto;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(59, 130, 246, 0.2);
      color: #60a5fa;
      padding: 6px 14px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 16px;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .hero h2 {
      font-size: 28px;
      font-weight: 800;
      margin-bottom: 12px;
      line-height: 1.2;
    }

    .hero p {
      font-size: 15px;
      opacity: 0.9;
      margin-bottom: 20px;
      line-height: 1.6;
    }

    /* Stats - Compact */
    .stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      max-width: 1200px;
      margin: -32px auto 0;
      padding: 0 16px;
      position: relative;
      z-index: 20;
    }

    .stat-card {
      background: white;
      padding: 20px 12px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      text-align: center;
    }

    .stat-icon {
      font-size: 28px;
      margin-bottom: 8px;
    }

    .stat-number {
      font-size: 24px;
      font-weight: 800;
      color: var(--accent);
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 11px;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    /* Section */
    .section {
      padding: 48px 16px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-header {
      text-align: center;
      margin-bottom: 32px;
    }

    .section-badge {
      display: inline-block;
      background: var(--bg-gray);
      color: var(--accent);
      padding: 6px 14px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 12px;
    }

    .section-header h2 {
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 8px;
    }

    .section-header p {
      font-size: 14px;
      color: var(--text-muted);
      max-width: 600px;
      margin: 0 auto;
    }

    /* About - Compact */
    .about {
      background: var(--bg-gray);
    }

    .about-content {
      max-width: 700px;
      margin: 0 auto;
    }

    .about-content h3 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 12px;
      text-align: center;
    }

    .about-content p {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 12px;
      line-height: 1.6;
      text-align: center;
    }

    .features-list {
      list-style: none;
      margin-top: 24px;
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px 12px;
    }

    .features-list li {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 500;
    }

    .features-list li::before {
      content: '✓';
      display: flex;
      align-items: center;
      justify-content: center;
      width: 20px;
      height: 20px;
      background: var(--accent);
      color: white;
      border-radius: 50%;
      font-size: 11px;
      font-weight: 700;
      flex-shrink: 0;
    }

    /* Sports Grid - Compact */
    .sports-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
      gap: 12px;
    }

    .sport-card {
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.2s;
      border: 2px solid transparent;
    }

    .sport-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
      border-color: var(--accent);
    }

    .sport-image {
      height: 100px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
    }

    .sport-content {
      padding: 12px;
    }

    .sport-content h3 {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .sport-meta {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    .sport-badge {
      padding: 3px 8px;
      background: var(--bg-gray);
      border-radius: 10px;
      font-size: 10px;
      font-weight: 600;
      color: var(--text-muted);
    }

    /* Features - Compact */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
    }

    .feature-card {
      text-align: center;
      padding: 24px 16px;
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .feature-icon {
      width: 56px;
      height: 56px;
      margin: 0 auto 16px;
      background: linear-gradient(135deg, var(--accent) 0%, #2563eb 100%);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
    }

    .feature-card h3 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .feature-card p {
      font-size: 13px;
      color: var(--text-muted);
      line-height: 1.5;
    }

    /* CTA - Compact */
    .cta {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      color: white;
      text-align: center;
      padding: 48px 16px;
    }

    .cta h2 {
      font-size: 24px;
      font-weight: 800;
      margin-bottom: 12px;
    }

    .cta p {
      font-size: 14px;
      opacity: 0.9;
      margin-bottom: 24px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    /* Footer - Compact */
    .footer {
      background: var(--primary);
      color: white;
      padding: 40px 16px 24px;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 24px;
      max-width: 1200px;
      margin: 0 auto 24px;
    }

    .footer-section h4 {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .footer-section ul {
      list-style: none;
    }

    .footer-section ul li {
      margin-bottom: 8px;
    }

    .footer-section a {
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      font-size: 12px;
    }

    .footer-section a:hover {
      color: white;
    }

    .footer-section p {
      color: rgba(255,255,255,0.7);
      font-size: 12px;
      line-height: 1.5;
      margin-top: 8px;
    }

    .footer-bottom {
      text-align: center;
      padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.6);
      font-size: 12px;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      .hero h2 { font-size: 24px; }
      .hero p { font-size: 14px; }
      
      .stats {
        grid-template-columns: 1fr;
        margin-top: 16px;
        gap: 8px;
      }
      
      .stat-card {
        padding: 16px 12px;
      }
      
      .features-list {
        grid-template-columns: 1fr;
      }
      
      .sports-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .features-grid {
        grid-template-columns: 1fr;
      }
      
      .footer-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .section {
        padding: 32px 16px;
      }
      
      .logo-text p {
        display: none;
      }
    }

    @media (max-width: 480px) {
      .hero h2 { font-size: 20px; }
      .section-header h2 { font-size: 20px; }
      .sports-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <?php if ($logout_success): ?>
  <div class="logout-banner" id="logoutBanner">
    <span class="close-banner" onclick="this.parentElement.style.display='none'">&times;</span>
    ✓ Logged out successfully
  </div>
  <?php endif; ?>

  <!-- Header -->
  <header class="header">
    <div class="header-content">
      <div class="logo">
        <div class="logo-icon">🏆</div>
        <div class="logo-text">
          <h1>UEP Sports</h1>
          <p>Management System</p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">Login</a>
    </div>
  </header>

  <!-- Hero -->
  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge">Developed by UEP-BSIT-STUDENTS</div>
      <h2>Manage Your Sports Events with Excellence</h2>
      <p>Comprehensive platform for managing tournaments, teams, athletes, and competitions.</p>
    </div>
  </section>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon">⚽</div>
      <div class="stat-number"><?= $stats['total_sports'] ?></div>
      <div class="stat-label">Sports</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-number"><?= $stats['total_teams'] ?></div>
      <div class="stat-label">Teams</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🏆</div>
      <div class="stat-number"><?= $stats['active_tournaments'] ?></div>
      <div class="stat-label">Tournaments</div>
    </div>
  </div>

  <!-- About -->
  <section class="about section">
    <div class="about-content">
      <h3>Streamline Your Sports Management</h3>
      <p>
        Our comprehensive sports management system provides everything you need to organize, 
        track, and manage athletic events, teams, and competitions efficiently.
      </p>
      <ul class="features-list">
        <li>Real-time tracking</li>
        <li>Athlete profiles</li>
        <li>Auto scheduling</li>
        <li>Live scoring</li>
        <li>Medal tallies</li>
        <li>Multi-sport support</li>
      </ul>
    </div>
  </section>

  <!-- Sports -->
  <section class="section">
    <div class="section-header">
      <div class="section-badge">🏅 Our Sports</div>
      <h2>Sports We Support</h2>
      <p>Wide variety of sports, both team-based and individual competitions.</p>
    </div>

    <div class="sports-grid">
      <?php if (empty($sports)): ?>
        <div class="sport-card">
          <div class="sport-image">🏀</div>
          <div class="sport-content">
            <h3>Basketball</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team</span>
              <span class="sport-badge">M & W</span>
            </div>
          </div>
        </div>
        <div class="sport-card">
          <div class="sport-image">⚽</div>
          <div class="sport-content">
            <h3>Football</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team</span>
              <span class="sport-badge">M & W</span>
            </div>
          </div>
        </div>
        <div class="sport-card">
          <div class="sport-image">🏐</div>
          <div class="sport-content">
            <h3>Volleyball</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team</span>
              <span class="sport-badge">M & W</span>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php 
        $sportIcons = [
          'basketball' => '🏀', 'volleyball' => '🏐', 'football' => '⚽',
          'soccer' => '⚽', 'baseball' => '⚾', 'tennis' => '🎾',
          'badminton' => '🏸', 'table tennis' => '🏓', 'swimming' => '🏊',
          'athletics' => '🏃', 'track and field' => '🏃', 'chess' => '♟️',
          'boxing' => '🥊', 'martial arts' => '🥋', 'karate' => '🥋',
          'taekwondo' => '🥋', 'judo' => '🥋', 'default' => '🏅'
        ];

        foreach ($sports as $sport): 
          $sportNameLower = strtolower($sport['sports_name']);
          $icon = $sportIcons['default'];
          
          foreach ($sportIcons as $key => $value) {
            if (strpos($sportNameLower, $key) !== false) {
              $icon = $value;
              break;
            }
          }
        ?>
        <div class="sport-card">
          <div class="sport-image"><?= $icon ?></div>
          <div class="sport-content">
            <h3><?= htmlspecialchars($sport['sports_name']) ?></h3>
            <div class="sport-meta">
              <span class="sport-badge">
                <?= $sport['team_individual'] === 'team' ? 'Team' : 'Individual' ?>
              </span>
              <?php if ($sport['men_women']): ?>
                <span class="sport-badge"><?= htmlspecialchars($sport['men_women']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- Features -->
  <section class="section" style="background: var(--bg-gray);">
    <div class="section-header">
      <div class="section-badge">✨ Features</div>
      <h2>Powerful Features</h2>
      <p>Designed to serve administrators, coaches, athletes, and officials.</p>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Tournament Management</h3>
        <p>Create, organize, and manage tournaments with ease.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Team & Athlete Tracking</h3>
        <p>Comprehensive profiles and performance tracking.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🏅</div>
        <h3>Live Scoring</h3>
        <p>Real-time score updates and standings calculation.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <h3>Multi-Role Access</h3>
        <p>Different dashboards with role-based permissions.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📈</div>
        <h3>Analytics & Reports</h3>
        <p>Comprehensive reports and performance analysis.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🖨️</div>
        <h3>Print & Export</h3>
        <p>Professional printable reports and schedules.</p>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta">
    <h2>Ready to Get Started?</h2>
    <p>
      Login to access your dashboard and start managing your tournaments today.
    </p>
    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary" style="font-size: 14px; padding: 10px 24px;">
      Access Dashboard →
    </a>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-grid">
      <div class="footer-section">
        <h4>UEP Sports</h4>
        <p>Comprehensive platform for managing sports events.</p>
      </div>

      <div class="footer-section">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#about">About</a></li>
          <li><a href="#sports">Sports</a></li>
          <li><a href="#features">Features</a></li>
          <li><a href="<?= BASE_URL ?>/auth/login.php">Login</a></li>
        </ul>
      </div>

      <div class="footer-section">
        <h4>User Roles</h4>
        <ul>
          <li><a href="#">Administrator</a></li>
          <li><a href="#">Tournament Manager</a></li>
          <li><a href="#">Coach</a></li>
          <li><a href="#">Athlete</a></li>
        </ul>
      </div>

      <div class="footer-section">
        <h4>Support</h4>
        <ul>
          <li><a href="#">Documentation</a></li>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Contact</a></li>
          <li><a href="#">Status</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> UEP Sports Management System. All rights reserved.</p>
    </div>
  </footer>
</body>
</html>