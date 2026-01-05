<?php
// index.php - Landing Page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// Check for logout success message
$logout_success = isset($_GET['logout']) && $_GET['logout'] === 'success';

// Fetch active sports from database
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

// Get some stats
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
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary: #111827;
      --primary-light: #374151;
      --accent: #3b82f6;
      --text: #111827;
      --text-muted: #6b7280;
      --bg: #ffffff;
      --bg-gray: #f9fafb;
      --border: #e5e7eb;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
      color: var(--text);
      line-height: 1.6;
      background: var(--bg);
    }

    /* Logout Success Banner */
    .logout-banner {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      padding: 16px 5%;
      text-align: center;
      font-weight: 600;
      font-size: 14px;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .logout-banner .close-banner {
      float: right;
      cursor: pointer;
      font-size: 20px;
      font-weight: 700;
      opacity: 0.8;
      transition: opacity 0.2s;
    }

    .logout-banner .close-banner:hover {
      opacity: 1;
    }

    /* Hero Section */
    .hero {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      color: white;
      padding: 0;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><path d="M10 10h80v80H10z" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></svg>');
      opacity: 0.5;
    }

    /* Navigation */
    .navbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 5%;
      position: relative;
      z-index: 10;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-icon {
      width: 48px;
      height: 48px;
      background: white;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    .logo-text h1 {
      font-size: 20px;
      font-weight: 700;
      margin: 0;
    }

    .logo-text p {
      font-size: 12px;
      opacity: 0.8;
      margin: 0;
    }

    .nav-buttons {
      display: flex;
      gap: 12px;
    }

    .btn {
      padding: 10px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: white;
      color: var(--primary);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255,255,255,0.3);
    }

    .btn-secondary {
      background: transparent;
      color: white;
      border: 2px solid rgba(255,255,255,0.3);
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.1);
      border-color: white;
    }

    /* Hero Content */
    .hero-content {
      text-align: center;
      padding: 80px 5% 120px;
      position: relative;
      z-index: 10;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(59, 130, 246, 0.2);
      color: #60a5fa;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 24px;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .hero-content h2 {
      font-size: 56px;
      font-weight: 800;
      margin-bottom: 24px;
      line-height: 1.2;
    }

    .hero-content p {
      font-size: 20px;
      opacity: 0.9;
      max-width: 700px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }

    .hero-image-placeholder {
      max-width: 900px;
      height: 400px;
      margin: 60px auto 0;
      background: rgba(255,255,255,0.05);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px dashed rgba(255,255,255,0.2);
      position: relative;
      overflow: hidden;
    }

    .hero-image-placeholder::before {
      content: '🏆';
      font-size: 120px;
      opacity: 0.3;
    }

    .image-caption {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.5);
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 12px;
      backdrop-filter: blur(10px);
    }

    /* Stats Section */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 30px;
      max-width: 1200px;
      margin: -60px auto 0;
      padding: 0 5%;
      position: relative;
      z-index: 20;
    }

    .stat-card {
      background: white;
      padding: 32px;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.1);
      text-align: center;
      transition: transform 0.3s;
    }

    .stat-card:hover {
      transform: translateY(-5px);
    }

    .stat-icon {
      font-size: 40px;
      margin-bottom: 16px;
    }

    .stat-number {
      font-size: 36px;
      font-weight: 800;
      color: var(--accent);
      margin-bottom: 8px;
    }

    .stat-label {
      font-size: 14px;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Section Styles */
    .section {
      padding: 100px 5%;
      max-width: 1400px;
      margin: 0 auto;
    }

    .section-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .section-badge {
      display: inline-block;
      background: var(--bg-gray);
      color: var(--accent);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .section-header h2 {
      font-size: 42px;
      font-weight: 800;
      margin-bottom: 16px;
    }

    .section-header p {
      font-size: 18px;
      color: var(--text-muted);
      max-width: 700px;
      margin: 0 auto;
    }

    /* About Section */
    .about {
      background: var(--bg-gray);
    }

    .about-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .about-image {
      height: 400px;
      background: white;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px dashed var(--border);
      position: relative;
      overflow: hidden;
    }

    .about-image::before {
      content: '📸';
      font-size: 100px;
      opacity: 0.2;
    }

    .about-content h3 {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 20px;
    }

    .about-content p {
      font-size: 16px;
      color: var(--text-muted);
      margin-bottom: 16px;
      line-height: 1.8;
    }

    .features-list {
      list-style: none;
      margin-top: 32px;
    }

    .features-list li {
      padding: 12px 0;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
    }

    .features-list li::before {
      content: '✓';
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      background: var(--accent);
      color: white;
      border-radius: 50%;
      font-weight: 700;
      flex-shrink: 0;
    }

    /* Sports Grid */
    .sports-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 24px;
    }

    .sport-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      transition: all 0.3s;
      border: 2px solid transparent;
    }

    .sport-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
      border-color: var(--accent);
    }

    .sport-image {
      height: 180px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 64px;
      position: relative;
      overflow: hidden;
    }

    .sport-image::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg width="50" height="50" xmlns="http://www.w3.org/2000/svg"><circle cx="25" cy="25" r="20" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="2"/></svg>');
    }

    .sport-content {
      padding: 24px;
    }

    .sport-content h3 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .sport-meta {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .sport-badge {
      padding: 4px 12px;
      background: var(--bg-gray);
      border-radius: 12px;
      font-size: 12px;
      font-weight: 600;
      color: var(--text-muted);
    }

    /* Features Section */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 32px;
    }

    .feature-card {
      text-align: center;
      padding: 40px 24px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      transition: transform 0.3s;
    }

    .feature-card:hover {
      transform: translateY(-5px);
    }

    .feature-icon {
      width: 80px;
      height: 80px;
      margin: 0 auto 24px;
      background: linear-gradient(135deg, var(--accent) 0%, #2563eb 100%);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }

    .feature-card h3 {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 12px;
    }

    .feature-card p {
      color: var(--text-muted);
      line-height: 1.6;
    }

    /* CTA Section */
    .cta {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      color: white;
      text-align: center;
      padding: 100px 5%;
      position: relative;
      overflow: hidden;
    }

    .cta::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="2"/></svg>');
    }

    .cta-content {
      position: relative;
      z-index: 10;
      max-width: 800px;
      margin: 0 auto;
    }

    .cta h2 {
      font-size: 48px;
      font-weight: 800;
      margin-bottom: 24px;
    }

    .cta p {
      font-size: 20px;
      opacity: 0.9;
      margin-bottom: 40px;
    }

    /* Footer */
    .footer {
      background: var(--primary);
      color: white;
      padding: 60px 5% 30px;
    }

    .footer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      max-width: 1400px;
      margin: 0 auto 40px;
    }

    .footer-section h4 {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 20px;
    }

    .footer-section ul {
      list-style: none;
    }

    .footer-section ul li {
      margin-bottom: 12px;
    }

    .footer-section a {
      color: rgba(255,255,255,0.7);
      text-decoration: none;
      transition: color 0.3s;
      font-size: 14px;
    }

    .footer-section a:hover {
      color: white;
    }

    .footer-bottom {
      text-align: center;
      padding-top: 30px;
      border-top: 1px solid rgba(255,255,255,0.1);
      color: rgba(255,255,255,0.6);
      font-size: 14px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .hero-content h2 {
        font-size: 36px;
      }

      .hero-content p {
        font-size: 16px;
      }

      .about-grid {
        grid-template-columns: 1fr;
      }

      .section-header h2 {
        font-size: 32px;
      }

      .nav-buttons {
        flex-direction: column;
      }

      .stats {
        grid-template-columns: 1fr;
        margin-top: 40px;
      }
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .hero-content,
    .stat-card,
    .sport-card,
    .feature-card {
      animation: fadeInUp 0.6s ease-out;
    }
  </style>
</head>
<body>
  <!-- Logout Success Banner -->
  <?php if ($logout_success): ?>
  <div class="logout-banner" id="logoutBanner">
    <span class="close-banner" onclick="document.getElementById('logoutBanner').style.display='none'">&times;</span>
    ✓ You have been logged out successfully
  </div>
  <?php endif; ?>

  <!-- Hero Section -->
  <section class="hero">
    <nav class="navbar">
      <div class="logo">
        <div class="logo-icon">🏆</div>
        <div class="logo-text">
          <h1>UEP Sports</h1>
          <p>Management System</p>
        </div>
      </div>
      <div class="nav-buttons">
        <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary">Login</a>
      </div>
    </nav>

    <div class="hero-content">
      <div class="hero-badge">🎯 Powered by Modern Technology</div>
      <h2>Manage Your Sports<br>Events with Excellence</h2>
      <p>
        The comprehensive platform for managing tournaments, teams, athletes, 
        and competitions. Track scores, standings, and achievements all in one place.
      </p>
      
      <!-- Hero Image Placeholder -->
<div class="hero-image-placeholder" style="background: url('assets/images/uep_logo.jpg') center/cover;">
  <div class="image-caption">UEP Main Stadium</div>
</div>
    </div>
  </section>

  <!-- Stats Section -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-icon">⚽</div>
      <div class="stat-number"><?= $stats['total_sports'] ?></div>
      <div class="stat-label">Sports Categories</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-number"><?= $stats['total_teams'] ?></div>
      <div class="stat-label">Active Teams</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🏆</div>
      <div class="stat-number"><?= $stats['active_tournaments'] ?></div>
      <div class="stat-label">Live Tournaments</div>
    </div>
  </div>

  <!-- About Section -->
  <section class="about section">
    <div class="about-grid">
<div class="about-image" style="background: url('assets/images/team.jpg') center/cover;">
  <div class="image-caption">Championship Team 2024</div>
</div>
      <div class="about-content">
        <h3>Streamline Your Sports Management</h3>
        <p>
          Our comprehensive sports management system provides everything you need to organize, 
          track, and manage athletic events, teams, and competitions efficiently.
        </p>
        <p>
          From tournament scheduling to real-time score tracking and medal tallies, 
          we've built a platform that handles all aspects of sports administration.
        </p>
        <ul class="features-list">
          <li>Real-time tournament tracking</li>
          <li>Comprehensive athlete profiles</li>
          <li>Automated scheduling & notifications</li>
          <li>Live scoring & standings</li>
          <li>Medal tally & rankings</li>
          <li>Multi-sport support</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Sports Section -->
  <section class="section">
    <div class="section-header">
      <div class="section-badge">🏅 Our Sports</div>
      <h2>Sports We Support</h2>
      <p>
        We support a wide variety of sports, both team-based and individual competitions.
        Each sport is professionally managed with dedicated tracking and reporting.
      </p>
    </div>

    <div class="sports-grid">
      <?php if (empty($sports)): ?>
        <!-- Default sports if none in database -->
        <div class="sport-card">
          <div class="sport-image">🏀</div>
          <div class="sport-content">
            <h3>Basketball</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team Sport</span>
              <span class="sport-badge">Men & Women</span>
            </div>
          </div>
        </div>
        <div class="sport-card">
          <div class="sport-image">⚽</div>
          <div class="sport-content">
            <h3>Football</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team Sport</span>
              <span class="sport-badge">Men & Women</span>
            </div>
          </div>
        </div>
        <div class="sport-card">
          <div class="sport-image">🏐</div>
          <div class="sport-content">
            <h3>Volleyball</h3>
            <div class="sport-meta">
              <span class="sport-badge">Team Sport</span>
              <span class="sport-badge">Men & Women</span>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php 
        // Sport icons mapping
        $sportIcons = [
          'basketball' => '🏀',
          'volleyball' => '🏐',
          'football' => '⚽',
          'soccer' => '⚽',
          'baseball' => '⚾',
          'tennis' => '🎾',
          'badminton' => '🏸',
          'table tennis' => '🏓',
          'swimming' => '🏊',
          'athletics' => '🏃',
          'track and field' => '🏃',
          'chess' => '♟️',
          'boxing' => '🥊',
          'martial arts' => '🥋',
          'karate' => '🥋',
          'taekwondo' => '🥋',
          'judo' => '🥋',
          'default' => '🏅'
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
                <?= $sport['team_individual'] === 'team' ? 'Team Sport' : 'Individual' ?>
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

  <!-- Features Section -->
  <section class="section" style="background: var(--bg-gray);">
    <div class="section-header">
      <div class="section-badge">✨ Features</div>
      <h2>Powerful Features for Every Role</h2>
      <p>
        Designed to serve administrators, coaches, athletes, and officials with 
        specialized tools for each role.
      </p>
    </div>

    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Tournament Management</h3>
        <p>Create, organize, and manage tournaments with ease. Schedule matches, assign venues, and track progress in real-time.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Team & Athlete Tracking</h3>
        <p>Maintain comprehensive profiles for teams and athletes. Track performance, statistics, and achievements.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🏅</div>
        <h3>Live Scoring</h3>
        <p>Enter and update scores in real-time. Automatically calculate standings, rankings, and medal tallies.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <h3>Multi-Role Access</h3>
        <p>Different dashboards for administrators, coaches, athletes, umpires, and spectators with role-based permissions.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">📈</div>
        <h3>Analytics & Reports</h3>
        <p>Generate comprehensive reports, view statistics, and analyze performance trends across tournaments.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">🖨️</div>
        <h3>Print & Export</h3>
        <p>Create professional printable reports for tournaments, schedules, results, and medal tallies.</p>
      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta">
    <div class="cta-content">
      <h2>Ready to Get Started?</h2>
      <p>
        Join hundreds of organizations managing their sports events efficiently. 
        Login to access your dashboard and start managing your tournaments today.
      </p>
      <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary" style="font-size: 16px; padding: 14px 32px;">
        Access Your Dashboard →
      </a>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-grid">
      <div class="footer-section">
        <h4>UEP Sports Management</h4>
        <p style="color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.6; margin-top: 12px;">
          A comprehensive platform for managing sports tournaments, teams, and athletes. 
          Built with modern technology for maximum efficiency.
        </p>
      </div>

      <div class="footer-section">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="#about">About System</a></li>
          <li><a href="#sports">Our Sports</a></li>
          <li><a href="#features">Features</a></li>
          <li><a href="<?= BASE_URL ?>/auth/login.php">Login</a></li>
        </ul>
      </div>

      <div class="footer-section">
        <h4>User Roles</h4>
        <ul>
          <li><a href="#">System Administrator</a></li>
          <li><a href="#">Tournament Manager</a></li>
          <li><a href="#">Sports Director</a></li>
          <li><a href="#">Coach</a></li>
          <li><a href="#">Athlete</a></li>
          <li><a href="#">Umpire</a></li>
        </ul>
      </div>

      <div class="footer-section">
        <h4>Support</h4>
        <ul>
          <li><a href="#">Documentation</a></li>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Contact Support</a></li>
          <li><a href="#">System Status</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> UEP Sports Management System. All rights reserved.</p>
    </div>
  </footer>
</body>
</html>