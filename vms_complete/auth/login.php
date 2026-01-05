<?php
session_start();
require_once "../config/db.php";
require_once __DIR__ . '/../config/config.php';

// Check if user is already logged in
if (isset($_SESSION['user']) && isset($_SESSION['user']['user_id'])) {
  $role = trim($_SESSION['user']['user_role'] ?? '');
  
  // Redirect based on role (case-insensitive)
  if (strcasecmp($role, 'admin') === 0 || 
      strcasecmp($role, 'administrator') === 0 || 
      strcasecmp($role, 'system administrator') === 0) {
    header("Location: " . BASE_URL . "/admin/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'coach') === 0) {
    header("Location: " . BASE_URL . "/coach/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'Tournament manager') === 0) {
    header("Location: " . BASE_URL . "/tournament_manager/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'sports director') === 0 || strcasecmp($role, 'sports_director') === 0) {
    header("Location: " . BASE_URL . "/sports_director/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'athlete/player') === 0 || strcasecmp($role, 'athlete') === 0) {
    header("Location: " . BASE_URL . "/athlete/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'trainee') === 0) {
    header("Location: " . BASE_URL . "/trainee/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'trainor') === 0) {
    header("Location: " . BASE_URL . "/trainor/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'umpire') === 0) {
    header("Location: " . BASE_URL . "/umpire/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'scorer') === 0) {
    header("Location: " . BASE_URL . "/scorer/dashboard.php");
    exit;
  } elseif (strcasecmp($role, 'Spectator') === 0) {
    header("Location: " . BASE_URL . "/spectator/dashboard.php");
    exit;
  }
}

// Check for logout success message
$success = isset($_GET['logout']) && $_GET['logout'] === 'success' 
  ? 'You have been logged out successfully.' 
  : '';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username  = trim($_POST['username'] ?? '');
  $password  = trim($_POST['password'] ?? '');
  $role_or_sport = trim($_POST['sports_id'] ?? '');

  if (!$username || !$password) {
    $error = "Username and password are required.";
  } else {

    $stmt = $pdo->prepare("
      SELECT 
        u.user_id,
        u.person_id,
        u.username,
        u.password,
        u.user_role,
        u.is_active,
        p.f_name,
        p.l_name
      FROM tbl_users u
      JOIN tbl_person p ON p.person_id = u.person_id
      WHERE u.username = :username
      LIMIT 1
    ");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // Check password (plain text for now - should be hashed in production)
    if (!$user || $user['password'] !== $password) {
      $error = "Invalid username or password.";
    } elseif ($user['is_active'] != 1) {
      $error = "Your account has been deactivated.";
    } else {
      $role = trim($user['user_role']);
      $sports_id = null;

      // ==========================================
      // AUTO-DETECT SPORT FROM DATABASE
      // ==========================================

      // Normalize role for comparison
      $normalized_role = strtolower(str_replace(['/', ' '], '_', $role));

      // Management roles (no sport needed)
      $no_sport_roles = ['tournament_manager', 'sports_director', 'admin', 'administrator', 'system_administrator', 'spectator'];

      if (in_array($normalized_role, $no_sport_roles)) {
        // Management/Spectator roles don't need sport
        $sports_id = null;
      } else {
        // For other roles, auto-detect sport from database
        
        // Try to get sport from various tables based on role
        if (strcasecmp($role, 'coach') === 0) {
          // Get coach's sport from tbl_sports_team
          $sport_stmt = $pdo->prepare("
            SELECT sports_id 
            FROM tbl_sports_team 
            WHERE coach_id = :pid
            LIMIT 1
          ");
          $sport_stmt->execute(['pid' => $user['person_id']]);
          $sport_row = $sport_stmt->fetch();
          if ($sport_row) {
            $sports_id = (int)$sport_row['sports_id'];
          }
        } 
        elseif (in_array($normalized_role, ['athlete', 'athlete_player'])) {
          // Get athlete's sport from tbl_team_athletes
          $sport_stmt = $pdo->prepare("
            SELECT sports_id 
            FROM tbl_team_athletes 
            WHERE person_id = :pid AND is_active = 1
            LIMIT 1
          ");
          $sport_stmt->execute(['pid' => $user['person_id']]);
          $sport_row = $sport_stmt->fetch();
          if ($sport_row) {
            $sports_id = (int)$sport_row['sports_id'];
          }
        }
        // TRAINEE - separate logic
        elseif (strcasecmp($role, 'trainee') === 0) {
          $sport_stmt = $pdo->prepare("
            SELECT st.sports_id
            FROM tbl_team_trainees tt
            JOIN tbl_sports_team st ON st.team_id = tt.team_id
            WHERE tt.trainee_id = :pid AND tt.is_active = 1
            ORDER BY st.tour_id DESC
            LIMIT 1
          ");
          $sport_stmt->execute(['pid' => $user['person_id']]);
          $sport_row = $sport_stmt->fetch();
          if ($sport_row) {
            $sports_id = (int)$sport_row['sports_id'];
          }
        }
        // TRAINOR - get from tbl_sports_team
        elseif (strcasecmp($role, 'trainor') === 0) {
          $sport_stmt = $pdo->prepare("
            SELECT sports_id
            FROM tbl_sports_team
            WHERE trainor1_id = ? 
               OR trainor2_id = ? 
               OR trainor3_id = ?
            ORDER BY tour_id DESC
            LIMIT 1
          ");
          $sport_stmt->execute([$user['person_id'], $user['person_id'], $user['person_id']]);
          $sport_row = $sport_stmt->fetch();
          if ($sport_row) {
            $sports_id = (int)$sport_row['sports_id'];
          }
        }
        
        elseif (in_array($normalized_role, ['umpire'])) {
          // Get sport from match assignments or recent activity
          $sport_stmt = $pdo->prepare("
            SELECT sports_id 
            FROM tbl_match 
            WHERE match_umpire_id = :pid 
            ORDER BY sked_date DESC
            LIMIT 1
          ");
          $sport_stmt->execute(['pid' => $user['person_id']]);
          $sport_row = $sport_stmt->fetch();
          if ($sport_row) {
            $sports_id = (int)$sport_row['sports_id'];
          }
        }

        // If sport not found but role requires it, show error
        if ($sports_id === null || $sports_id <= 0) {
          $error = "Your account is not assigned to any sport. Please contact administrator.";
        }
      }

      // If no errors, create session
      if (!$error) {
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user'] = [
          'user_id'   => $user['user_id'],
          'person_id' => $user['person_id'],
          'full_name' => $user['f_name'] . ' ' . $user['l_name'],
          'user_role' => $user['user_role'], // Keep original case from DB
          'sports_id' => $sports_id,
          'login_time' => time()
        ];

        // Redirect based on role (case-insensitive comparison)
        if (strcasecmp($role, 'admin') === 0 || 
            strcasecmp($role, 'administrator') === 0 || 
            strcasecmp($role, 'system administrator') === 0) {
          header("Location: " . BASE_URL . "/system_administrator/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'coach') === 0) {
          header("Location: " . BASE_URL . "/coach/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'Tournament manager') === 0) {
          header("Location: " . BASE_URL . "/tournament_manager/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'sports director') === 0 || strcasecmp($role, 'sports_director') === 0) {
          header("Location: " . BASE_URL . "/sports_director/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'athlete/player') === 0 || strcasecmp($role, 'athlete') === 0) {
          header("Location: " . BASE_URL . "/athlete/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'trainee') === 0) {
          header("Location: " . BASE_URL . "/trainee/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'trainor') === 0) {
          header("Location: " . BASE_URL . "/trainor/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'umpire') === 0) {
          header("Location: " . BASE_URL . "/umpire/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'scorer') === 0) {
          header("Location: " . BASE_URL . "/scorer/dashboard.php");
          exit;
        } elseif (strcasecmp($role, 'Spectator') === 0) {
          header("Location: " . BASE_URL . "/spectator/dashboard.php");
          exit;
        } else {
          $error = "Unknown user role: $role";
        }
      }
    }
  }
}

// No need to load sports list - auto-detected from database
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Login - UEP Sports Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  /* ===== RESET ===== */
  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    color: #111827;
  }

  .header {
    text-align: center;
    margin-bottom: 16px;
  }

  .header h1 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
    color: #111827;
  }

  .header p {
    margin-top: 4px;
    font-size: 13px;
    color: #6b7280;
  }

  /* ===== CARD ===== */
  .card {
    width: 100%;
    max-width: 400px;
    background: #ffffff;
    border-radius: 10px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  }

  /* ===== HEADINGS ===== */
  h2 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
  }

  .subtitle {
    margin: 0 0 16px;
    font-size: 13px;
    color: #6b7280;
    line-height: 1.4;
  }

  /* ===== FORM LABELS ===== */
  label {
    display: block;
    margin-top: 14px;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
  }

  /* ===== INPUTS ===== */
  input,
  select {
    width: 100%;
    margin-top: 6px;
    padding: 10px 12px;
    font-size: 14px;
    font-family: inherit;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
  }

  input:focus,
  select:focus {
    outline: none;
    border-color: #111827;
  }

  /* ===== BUTTON ===== */
  button {
    width: 100%;
    margin-top: 20px;
    padding: 11px 14px;
    font-size: 14px;
    font-weight: 500;
    font-family: inherit;
    border-radius: 6px;
    border: none;
    background: #111827;
    color: #ffffff;
    cursor: pointer;
  }

  button:hover {
    background: #000000;
  }

  button:active {
    background: #000000;
  }

  /* ===== MESSAGE BOXES ===== */
  .msg {
    margin-top: 14px;
    padding: 10px 12px;
    font-size: 13px;
    border-radius: 6px;
    border: 1px solid transparent;
  }

  .err {
    color: #7f1d1d;
    background: #ffffff;
    border-color: #e5e7eb;
  }

  .success {
    color: #065f46;
    background: #ffffff;
    border-color: #e5e7eb;
  }

  /* ===== SELECT GROUP ===== */
  optgroup {
    font-weight: 600;
    font-style: normal;
    color: #374151;
  }

  /* ===== BACK TO HOME LINK ===== */
  .back-home {
    text-align: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
  }

  .back-home a {
    color: #6b7280;
    text-decoration: none;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.2s;
  }

  .back-home a:hover {
    color: #111827;
  }
</style>

</head>
<body>
  <form class="card" method="post">
   <div class="header">
  <h1>UEP Sports Management</h1>
  <p>Sign in to your account</p>
</div>


    <label>Username</label>
    <input name="username" id="username" required autocomplete="username">

    <label>Password</label>
    <input type="password" name="password" id="password" required autocomplete="current-password">

    <button type="submit">Login</button>

    <?php if ($success): ?>
      <div class="msg success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="back-home">
      <a href="<?= BASE_URL ?>/index.php">← Back to Home</a>
    </div>
  </form>
</body>
</html>