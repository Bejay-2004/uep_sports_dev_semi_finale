<?php
// coach/add_player.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . "/../auth/guard.php";
require_role("coach");

$coach_person_id = (int)$_SESSION['user']['person_id'];
$sports_id = (int)$_SESSION['user']['sports_id'];
$full_name = $_SESSION['user']['full_name'] ?? 'Coach';

// Get initials for avatar
$names = explode(' ', $full_name);
$initials = '';
foreach ($names as $n) {
    $initials .= strtoupper(substr($n, 0, 1));
}
$initials = substr($initials, 0, 2);

// Get coach's teams for dropdown
$teams = [];
try {
  $stmt = $pdo->prepare("
    SELECT st.team_id, t.team_name, st.tour_id
    FROM tbl_sports_team st
    JOIN tbl_team t ON t.team_id = st.team_id
    WHERE st.coach_id = :coach_id AND st.sports_id = :sports_id
    ORDER BY t.team_name
  ");
  $stmt->execute(['coach_id' => $coach_person_id, 'sports_id' => $sports_id]);
  $teams = $stmt->fetchAll();
} catch (PDOException $e) {
  $teams = [];
}

// Get colleges for dropdown
$colleges = [];
try {
  $stmt = $pdo->query("SELECT college_id, college_code, college_name FROM tbl_college WHERE is_active = 1 ORDER BY college_name");
  $colleges = $stmt->fetchAll();
} catch (PDOException $e) {
  $colleges = [];
}

// Get all departments (will be filtered by college via JavaScript)
$departments = [];
try {
  $stmt = $pdo->query("SELECT dept_id, dept_code, dept_name, college_id FROM tbl_department WHERE is_active = 1 ORDER BY dept_name");
  $departments = $stmt->fetchAll();
} catch (PDOException $e) {
  $departments = [];
}

// Get all courses (will be filtered by department via JavaScript)
$courses = [];
try {
  $stmt = $pdo->query("SELECT course_id, course_code, course_name, dept_id FROM tbl_course ORDER BY course_name");
  $courses = $stmt->fetchAll();
} catch (PDOException $e) {
  $courses = [];
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $pdo->beginTransaction();
    
    // Step 1: Insert into tbl_person
$stmt = $pdo->prepare("
  INSERT INTO tbl_person 
  (l_name, f_name, m_name, role_type, title, date_birth, college_code, course, blood_type, is_active)
  VALUES 
  (:l_name, :f_name, :m_name, 'athlete', :title, :date_birth, :college_code, :course, :blood_type, 1)
");
    
    // Get course name from course_id if provided
    $course_name = '';
    if (!empty($_POST['course_id'])) {
      $course_stmt = $pdo->prepare("SELECT course_name FROM tbl_course WHERE course_id = :course_id LIMIT 1");
      $course_stmt->execute(['course_id' => $_POST['course_id']]);
      $course_data = $course_stmt->fetch();
      if ($course_data) {
        $course_name = $course_data['course_name'];
      }
    }
    
    $personData = [
      'l_name' => trim($_POST['l_name']),
      'f_name' => trim($_POST['f_name']),
      'm_name' => trim($_POST['m_name'] ?? ''),
      'title' => trim($_POST['title'] ?? ''),
      'date_birth' => $_POST['date_birth'],
      'college_code' => !empty($_POST['college_code']) ? $_POST['college_code'] : null,
      'course' => $course_name,
      'blood_type' => !empty($_POST['blood_type']) ? $_POST['blood_type'] : null
    ];
    
    $stmt->execute($personData);
    $person_id = $pdo->lastInsertId();
    
    if (!$person_id) {
      throw new Exception("Failed to insert person record");
    }
    
    // Step 2: Insert vital signs if provided
    if (!empty($_POST['height']) || !empty($_POST['weight']) || 
        !empty($_POST['b_pressure']) || !empty($_POST['b_sugar']) || !empty($_POST['b_choles'])) {
      
      $stmt = $pdo->prepare("
        INSERT INTO tbl_vital_signs 
        (person_id, height, weight, b_pressure, b_sugar, b_choles, date_taken)
        VALUES 
        (:person_id, :height, :weight, :b_pressure, :b_sugar, :b_choles, CURDATE())
      ");
      
      $stmt->execute([
        'person_id' => $person_id,
        'height' => !empty($_POST['height']) ? $_POST['height'] : null,
        'weight' => !empty($_POST['weight']) ? $_POST['weight'] : null,
        'b_pressure' => !empty($_POST['b_pressure']) ? trim($_POST['b_pressure']) : null,
        'b_sugar' => !empty($_POST['b_sugar']) ? trim($_POST['b_sugar']) : null,
        'b_choles' => !empty($_POST['b_choles']) ? trim($_POST['b_choles']) : null
      ]);
    }
    
    // Step 3: Insert athlete status
    $stmt = $pdo->prepare("
      INSERT INTO tbl_ath_status 
      (person_id, scholarship_name, semester, school_year)
      VALUES 
      (:person_id, :scholarship_name, :semester, :school_year)
    ");
    
    $stmt->execute([
      'person_id' => $person_id,
      'scholarship_name' => !empty($_POST['scholarship_name']) ? $_POST['scholarship_name'] : 'varsity',
      'semester' => !empty($_POST['semester']) ? $_POST['semester'] : null,
      'school_year' => !empty($_POST['school_year']) ? trim($_POST['school_year']) : null
    ]);
    
    // Step 4: Add to team
    if (!empty($_POST['team_id']) && !empty($_POST['tour_id'])) {
      $stmt = $pdo->prepare("
        INSERT INTO tbl_team_athletes 
        (tour_id, team_id, sports_id, person_id, is_captain, is_active)
        VALUES 
        (:tour_id, :team_id, :sports_id, :person_id, :is_captain, 1)
      ");
      
      $stmt->execute([
        'tour_id' => (int)$_POST['tour_id'],
        'team_id' => (int)$_POST['team_id'],
        'sports_id' => $sports_id,
        'person_id' => $person_id,
        'is_captain' => isset($_POST['is_captain']) ? 1 : 0
      ]);
    }
    
    // Step 5: Create user account
    $username = strtolower($_POST['f_name'] . '.' . $_POST['l_name']);
    $username = preg_replace('/[^a-z0-9.]/', '', $username);
    $default_password = password_hash('player123', PASSWORD_DEFAULT);
    
$stmt = $pdo->prepare("
  INSERT INTO tbl_users 
  (person_id, username, password, user_role, is_active)
  VALUES 
  (:person_id, :username, :password, 'athlete/player', 1)
");
    
    $stmt->execute([
      'person_id' => $person_id,
      'username' => $username,
      'password' => $default_password
    ]);
    
    // Step 6: Log activity
    $log_event = "Coach {$full_name} added new player: {$_POST['f_name']} {$_POST['l_name']}";
    $stmt = $pdo->prepare("
      INSERT INTO tbl_logs 
      (user_id, log_event, log_date, module_name)
      VALUES 
      ((SELECT user_id FROM tbl_users WHERE person_id = :coach_id LIMIT 1), :log_event, NOW(), 'Team Management')
    ");
    
    $stmt->execute([
      'coach_id' => $coach_person_id,
      'log_event' => $log_event
    ]);
    
    $pdo->commit();
    
    header("Location: " . BASE_URL . "/coach/add_player.php?success=1");
    exit;
    
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $message = "Database error occurred. Please try again.";
    $messageType = 'error';
    error_log("Add Player Error - Coach ID {$coach_person_id}: " . $e->getMessage());
    
  } catch (Exception $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    $message = "An error occurred. Please try again.";
    $messageType = 'error';
    error_log("Add Player Error - Coach ID {$coach_person_id}: " . $e->getMessage());
  }
}

if (isset($_GET['success'])) {
  $message = "Player successfully added! The new player will appear in your Players list.";
  $messageType = 'success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Player - UEP Sports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="<?= BASE_URL ?>/coach/coach.css">
  <style>
    .form-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
      gap: 16px; 
    }
    
    .form-grid-3 { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
      gap: 16px; 
    }
    
    .academic-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
    }
    
    .full-width { 
      grid-column: 1 / -1; 
    }
    
    .section-title { 
      font-size: 16px; 
      font-weight: 700; 
      margin: 24px 0 16px 0; 
      padding-bottom: 8px;
      border-bottom: 2px solid var(--primary);
      grid-column: 1 / -1;
      color: var(--text);
    }
    
    .section-title:first-child {
      margin-top: 0;
    }
    
    .alert {
      padding: 14px 16px;
      border-radius: var(--radius);
      margin-bottom: 20px;
      font-weight: 600;
      font-size: 14px;
    }
    
    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #6ee7b7;
    }
    
    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fca5a5;
    }
    
    .required::after {
      content: " *";
      color: var(--danger);
    }
    
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
      cursor: pointer;
    }
    
    .checkbox-label input[type="checkbox"] {
      width: 20px;
      height: 20px;
      margin: 0;
      cursor: pointer;
    }
    
    @media (max-width: 1024px) {
      .academic-grid {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .form-grid,
      .form-grid-3 {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">⚽</div>
    <div class="sidebar-title">
      <h3>UEP Sports</h3>
      <p>Coach Portal</p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="<?= BASE_URL ?>/coach/dashboard.php" class="nav-link">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
      </svg>
      <span>Dashboard</span>
    </a>

    <a href="<?= BASE_URL ?>/coach/add_player.php" class="nav-link active">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="8.5" cy="7" r="4"></circle>
        <line x1="20" y1="8" x2="20" y2="14"></line>
        <line x1="23" y1="11" x2="17" y2="11"></line>
      </svg>
      <span>Add Player</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <form method="post" action="<?= BASE_URL ?>/auth/logout.php" style="margin:0;width:100%;">
      <button type="submit" class="logout-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>Logout</span>
      </button>
    </form>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content">
  
  <!-- Top Bar -->
  <div class="top-bar">
    <button class="menu-toggle" id="menuToggle">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
    </button>
    
    <h1>Add New Player</h1>
    
    <div class="user-info">
      <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($full_name) ?></div>
        <div class="user-role">Coach</div>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content-view active" style="display: block;">
    
    <div class="card">
      <p style="color: var(--muted); margin-bottom: 20px; font-size: 14px;">
        Complete all required fields marked with <span style="color: var(--danger); font-weight: 700;">*</span>. 
        The player will be automatically assigned to your team.
      </p>

      <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
          <?= htmlspecialchars($message) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="form">
        
        <!-- Personal Information -->
        <div class="section-title">📋 Personal Information</div>
        <div class="form-grid">
          <div>
            <label class="required">First Name</label>
            <input type="text" name="f_name" required maxlength="100" 
                   placeholder="Juan" value="<?= htmlspecialchars($_POST['f_name'] ?? '') ?>">
          </div>
          
          <div>
            <label class="required">Last Name</label>
            <input type="text" name="l_name" required maxlength="100" 
                   placeholder="Dela Cruz" value="<?= htmlspecialchars($_POST['l_name'] ?? '') ?>">
          </div>
          
          <div>
            <label>Middle Name</label>
            <input type="text" name="m_name" maxlength="100" 
                   placeholder="Santos" value="<?= htmlspecialchars($_POST['m_name'] ?? '') ?>">
          </div>
          
          <div>
            <label>Title</label>
            <select name="title">
              <option value="">-- Select --</option>
              <option value="Mr." <?= (($_POST['title'] ?? '') === 'Mr.') ? 'selected' : '' ?>>Mr.</option>
              <option value="Ms." <?= (($_POST['title'] ?? '') === 'Ms.') ? 'selected' : '' ?>>Ms.</option>
            </select>
          </div>
          
          <div>
            <label class="required">Date of Birth</label>
            <input type="date" name="date_birth" required 
                   max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['date_birth'] ?? '') ?>">
          </div>
          
          <div>
            <label>Blood Type</label>
            <select name="blood_type">
              <option value="">-- Select --</option>
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
        </div>

        <!-- Academic Information -->
        <div class="section-title">🎓 Academic Information</div>
        <div class="academic-grid">
          <div>
            <label>College</label>
            <select name="college_code" id="collegeSelect">
              <option value="">-- Select College --</option>
              <?php foreach ($colleges as $college): ?>
                <option value="<?= htmlspecialchars($college['college_code']) ?>" 
                        data-id="<?= $college['college_id'] ?>">
                  <?= htmlspecialchars($college['college_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div>
            <label>Department</label>
            <select name="dept_id" id="deptSelect" disabled>
              <option value="">-- Select College First --</option>
            </select>
          </div>
          
          <div>
            <label>Course/Program</label>
            <select name="course_id" id="courseSelect" disabled>
              <option value="">-- Select Department First --</option>
            </select>
          </div>
        </div>

        <!-- Athlete Status -->
        <div class="section-title">🏅 Athlete Status</div>
        <div class="form-grid-3">
          <div>
            <label>Scholarship Type</label>
            <select name="scholarship_name">
              <option value="varsity">Varsity</option>
              <option value="trainee">Trainee</option>
              <option value="none">None</option>
            </select>
          </div>
          
          <div>
            <label>Semester</label>
            <select name="semester" id="semesterSelect">
              <option value="">-- Select --</option>
              <option value="1">1st Semester</option>
              <option value="2">2nd Semester</option>
              <option value="Summer">Summer</option>
            </select>
          </div>
          
          <div>
            <label>School Year</label>
            <input type="text" name="school_year" id="schoolYearInput" placeholder="2024" 
                   maxlength="9" value="<?= htmlspecialchars($_POST['school_year'] ?? '') ?>">
            <small style="display:block;margin-top:4px;color:var(--muted);font-size:11px;">
              Enter year (e.g., 2024). Will auto-format to 2024-2025 (except Summer)
            </small>
          </div>
        </div>

        <!-- Vital Signs -->
        <div class="section-title">💪 Vital Signs (Optional)</div>
        <div class="form-grid-3">
          <div>
            <label>Height (cm)</label>
            <input type="number" name="height" step="0.1" min="0" max="300" 
                   placeholder="170.5" value="<?= htmlspecialchars($_POST['height'] ?? '') ?>">
          </div>
          
          <div>
            <label>Weight (kg)</label>
            <input type="number" name="weight" step="0.1" min="0" max="300" 
                   placeholder="65.0" value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
          </div>
          
          <div>
            <label>Blood Pressure</label>
            <input type="text" name="b_pressure" maxlength="20" 
                   placeholder="120/80" value="<?= htmlspecialchars($_POST['b_pressure'] ?? '') ?>">
          </div>
          
          <div>
            <label>Blood Sugar</label>
            <input type="text" name="b_sugar" maxlength="20" 
                   placeholder="90 mg/dL" value="<?= htmlspecialchars($_POST['b_sugar'] ?? '') ?>">
          </div>
          
          <div>
            <label>Cholesterol</label>
            <input type="text" name="b_choles" maxlength="20" 
                   placeholder="180 mg/dL" value="<?= htmlspecialchars($_POST['b_choles'] ?? '') ?>">
          </div>
        </div>

        <!-- Team Assignment -->
        <div class="section-title">👥 Team Assignment</div>
        <div class="form-grid">
          <div>
            <label class="required">Assign to Team</label>
            <select name="team_id" id="teamSelect" required>
              <option value="">-- Select Team --</option>
              <?php foreach ($teams as $team): ?>
                <option value="<?= $team['team_id'] ?>" data-tour="<?= $team['tour_id'] ?>">
                  <?= htmlspecialchars($team['team_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="tour_id" id="tourId">
          </div>
          
          <div>
            <label class="checkbox-label">
              <input type="checkbox" name="is_captain" value="1">
              <span>Assign as Team Captain</span>
            </label>
          </div>
        </div>

        <div class="full-width" style="margin-top: 32px;">
          <button type="submit" class="btn" style="width: 100%; padding: 14px; font-size: 16px;">
            ✓ Add Player
          </button>
        </div>

      </form>
    </div>
    
    <div class="footnote">
      <strong>Database Tables:</strong> tbl_person • tbl_vital_signs • tbl_ath_status • tbl_team_athletes • tbl_users • tbl_logs
    </div>
  </div>

</main>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
  // Mobile menu toggle
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.querySelector('.sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

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

  // Auto-populate tour_id when team is selected
  document.getElementById('teamSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const tourId = selectedOption.getAttribute('data-tour');
    document.getElementById('tourId').value = tourId || '';
  });

  // Cascading dropdowns for College -> Department -> Course
  const allDepartments = <?= json_encode($departments) ?>;
  const allCourses = <?= json_encode($courses) ?>;
  
  const collegeSelect = document.getElementById('collegeSelect');
  const deptSelect = document.getElementById('deptSelect');
  const courseSelect = document.getElementById('courseSelect');
  
  // School Year Auto-Formatting
  const semesterSelect = document.getElementById('semesterSelect');
  const schoolYearInput = document.getElementById('schoolYearInput');
  
  function formatSchoolYear() {
    const semester = semesterSelect.value;
    const input = schoolYearInput.value.trim();
    
    // Remove any non-digit characters except dash
    let cleaned = input.replace(/[^\d-]/g, '');
    
    // If already formatted or empty, leave it
    if (cleaned.includes('-') || cleaned.length === 0) {
      return;
    }
    
    // If 4 digits entered and not Summer semester
    if (cleaned.length === 4 && semester !== 'Summer') {
      const year = parseInt(cleaned);
      if (year >= 2000 && year <= 2100) {
        schoolYearInput.value = year + '-' + (year + 1);
      }
    }
    // If Summer, just keep the single year
    else if (cleaned.length === 4 && semester === 'Summer') {
      const year = parseInt(cleaned);
      if (year >= 2000 && year <= 2100) {
        schoolYearInput.value = year.toString();
      }
    }
  }
  
  // Format on blur (when user leaves the field)
  schoolYearInput.addEventListener('blur', formatSchoolYear);
  
  // Also format when semester changes
  semesterSelect.addEventListener('change', function() {
    const input = schoolYearInput.value.trim();
    
    if (input.length > 0) {
      // If changing to Summer and has range format, convert to single year
      if (this.value === 'Summer' && input.includes('-')) {
        const firstYear = input.split('-')[0];
        schoolYearInput.value = firstYear;
      }
      // If changing from Summer to 1st/2nd and has single year, convert to range
      else if (this.value !== 'Summer' && this.value !== '' && !input.includes('-')) {
        const cleaned = input.replace(/[^\d]/g, '');
        if (cleaned.length === 4) {
          const year = parseInt(cleaned);
          if (year >= 2000 && year <= 2100) {
            schoolYearInput.value = year + '-' + (year + 1);
          }
        }
      }
    }
  });
  
  // Allow only numbers and dash while typing
  schoolYearInput.addEventListener('input', function(e) {
    let value = this.value;
    // Remove any character that's not a digit or dash
    this.value = value.replace(/[^\d-]/g, '');
  });
  
  // When college changes, populate departments
  collegeSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const collegeId = selectedOption.getAttribute('data-id');
    
    // Reset dependent dropdowns
    deptSelect.innerHTML = '<option value="">-- Select Department --</option>';
    courseSelect.innerHTML = '<option value="">-- Select Department First --</option>';
    deptSelect.disabled = true;
    courseSelect.disabled = true;
    
    if (collegeId) {
      // Filter departments by college
      const filteredDepts = allDepartments.filter(dept => dept.college_id == collegeId);
      
      if (filteredDepts.length > 0) {
        filteredDepts.forEach(dept => {
          const option = document.createElement('option');
          option.value = dept.dept_id;
          option.textContent = dept.dept_name;
          deptSelect.appendChild(option);
        });
        deptSelect.disabled = false;
      } else {
        deptSelect.innerHTML = '<option value="">No departments available</option>';
      }
    }
  });
  
  // When department changes, populate courses
  deptSelect.addEventListener('change', function() {
    const deptId = this.value;
    
    // Reset course dropdown
    courseSelect.innerHTML = '<option value="">-- Select Course --</option>';
    courseSelect.disabled = true;
    
    if (deptId) {
      // Filter courses by department
      const filteredCourses = allCourses.filter(course => course.dept_id == deptId);
      
      if (filteredCourses.length > 0) {
        filteredCourses.forEach(course => {
          const option = document.createElement('option');
          option.value = course.course_id;
          option.textContent = course.course_name + ' (' + course.course_code + ')';
          courseSelect.appendChild(option);
        });
        courseSelect.disabled = false;
      } else {
        courseSelect.innerHTML = '<option value="">No courses available</option>';
      }
    }
  });
</script>

</body>
</html>