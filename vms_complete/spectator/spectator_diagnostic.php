<?php
// spectator_diagnostic.php - Place this in your spectator folder
// Access via: http://yoursite.com/spectator/spectator_diagnostic.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Spectator API Diagnostics</h1>";
echo "<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.test { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }
.pass { border-left-color: #28a745; }
.fail { border-left-color: #dc3545; }
pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
h2 { color: #333; }
</style>";

// Test 1: Check if files exist
echo "<div class='test'>";
echo "<h2>Test 1: File Check</h2>";

$api_file = __DIR__ . '/api.php';
$dashboard_file = __DIR__ . '/dashboard.php';

if (file_exists($api_file)) {
    echo "✅ api.php exists<br>";
} else {
    echo "❌ api.php NOT FOUND at: $api_file<br>";
}

if (file_exists($dashboard_file)) {
    echo "✅ dashboard.php exists<br>";
} else {
    echo "❌ dashboard.php NOT FOUND at: $dashboard_file<br>";
}
echo "</div>";

// Test 2: Check database connection
echo "<div class='test'>";
echo "<h2>Test 2: Database Connection</h2>";

try {
    require_once __DIR__ . '/../config/db.php';
    echo "✅ Database connected successfully<br>";
    echo "PDO object created<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}
echo "</div>";

// Test 3: Check tables exist
echo "<div class='test'>";
echo "<h2>Test 3: Database Tables</h2>";

$tables = [
    'tbl_tournament',
    'tbl_sports', 
    'tbl_match',
    'tbl_team',
    'tbl_team_standing',
    'tbl_team_athletes',
    'tbl_person',
    'tbl_comp_score',
    'tbl_game_venue',
    'tbl_sports_team',
    'tbl_college',
    'tbl_school'
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        echo "✅ $table exists ($count rows)<br>";
    } catch (PDOException $e) {
        echo "❌ $table ERROR: " . $e->getMessage() . "<br>";
    }
}
echo "</div>";

// Test 4: Check session
echo "<div class='test'>";
echo "<h2>Test 4: Session Check</h2>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    echo "✅ Session active<br>";
    echo "<pre>";
    print_r($_SESSION['user']);
    echo "</pre>";
} else {
    echo "⚠️ No active session - You need to login first<br>";
    echo "<a href='../auth/login.php'>Login Here</a><br>";
}
echo "</div>";

// Test 5: Test API endpoints directly
echo "<div class='test'>";
echo "<h2>Test 5: Direct API Calls</h2>";

$endpoints = [
    'tournaments',
    'sports',
    'matches',
    'teams',
    'stats'
];

foreach ($endpoints as $endpoint) {
    echo "<h3>Testing: $endpoint</h3>";
    
    try {
        // Simulate API call
        $_GET['action'] = $endpoint;
        
        ob_start();
        include __DIR__ . '/api.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        if ($data === null) {
            echo "❌ Invalid JSON response<br>";
            echo "<pre>$output</pre>";
        } elseif (isset($data['ok']) && $data['ok'] === false) {
            echo "❌ Error: " . ($data['message'] ?? 'Unknown error') . "<br>";
            if (isset($data['error'])) {
                echo "Details: " . $data['error'] . "<br>";
            }
        } else {
            $count = is_array($data) ? count($data) : 1;
            echo "✅ Success - $count record(s)<br>";
            if ($count > 0) {
                echo "<pre>" . json_encode(array_slice($data, 0, 2), JSON_PRETTY_PRINT) . "</pre>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "<br>";
    }
    
    echo "<hr>";
}
echo "</div>";

// Test 6: Test with filters
echo "<div class='test'>";
echo "<h2>Test 6: Filtered Queries</h2>";

// Get first tournament
try {
    $stmt = $pdo->query("SELECT tour_id FROM tbl_tournament WHERE is_active = 1 LIMIT 1");
    $tour = $stmt->fetch();
    
    if ($tour) {
        $tour_id = $tour['tour_id'];
        echo "<h3>Testing matches with tour_id=$tour_id</h3>";
        
        $_GET['action'] = 'matches';
        $_GET['tour_id'] = $tour_id;
        
        ob_start();
        include __DIR__ . '/api.php';
        $output = ob_get_clean();
        
        $data = json_decode($output, true);
        
        if ($data === null) {
            echo "❌ Invalid JSON<br>";
        } elseif (isset($data['ok']) && $data['ok'] === false) {
            echo "❌ Error: " . $data['message'] . "<br>";
        } else {
            $count = is_array($data) ? count($data) : 0;
            echo "✅ Found $count matches<br>";
        }
    } else {
        echo "⚠️ No active tournaments found<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "</div>";

// Test 7: Check error log
echo "<div class='test'>";
echo "<h2>Test 7: Recent PHP Errors</h2>";

$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "Error log: $error_log<br>";
    $lines = file($error_log);
    $recent = array_slice($lines, -10);
    echo "<pre>" . implode("", $recent) . "</pre>";
} else {
    echo "⚠️ Cannot find error log. Check /var/log/apache2/error.log or similar<br>";
}

echo "</div>";

echo "<hr>";
echo "<h2>Quick Action Links</h2>";
echo "<a href='?clear_session=1'>Clear Session</a> | ";
echo "<a href='dashboard.php'>Go to Dashboard</a> | ";
echo "<a href='api.php?action=stats'>Test Stats API</a>";

if (isset($_GET['clear_session'])) {
    session_destroy();
    echo "<script>alert('Session cleared!'); window.location.href='spectator_diagnostic.php';</script>";
}
?>