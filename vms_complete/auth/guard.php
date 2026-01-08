<?php
// auth/guard.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';

/**
 * Require a specific user role
 * Validates session and redirects if unauthorized
 */
function require_role($required_role) {
    global $pdo;
    
    // Check if user is logged in
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        // Not logged in - redirect to login
        session_destroy();
        header("Location: " . BASE_URL . "/auth/login.php");
        exit;
    }

    $user = $_SESSION['user'];

    // Validate required session fields exist
    $required_fields = ['user_id', 'person_id', 'user_role'];
    
    foreach ($required_fields as $field) {
        if (!isset($user[$field])) {
            // Invalid session - destroy and redirect
            session_destroy();
            header("Location: " . BASE_URL . "/auth/login.php");
            exit;
        }
    }

    $current_role = trim($user['user_role']);

    // Normalize roles for comparison (handles variations)
    // Special handling for 'athlete/player' -> 'athlete_player'
    $normalized_role = strtolower(str_replace(['/', ' '], '_', $current_role));
    $normalized_required = strtolower(str_replace(['/', ' '], '_', $required_role));

    // ==========================================
    // ROLE-SPECIFIC VALIDATION
    // ==========================================

    // Roles that DON'T need sports_id (management + spectator)
    // Note: 'sports director' in DB becomes 'sports_director' after normalization
    // Note: 'system administrator' in DB becomes 'system_administrator' after normalization
    $no_sport_roles = [
        'Tournament manager',
        'sports director',      // Handles both 'sports director' and 'sports_director'
        'admin',                // System administrator (short form)
        'administrator',        // Alias for admin
        'system administrator', // Handles 'system administrator' from DB
        'spectator'             // Spectators can view ALL sports
    ];

    // Roles that DO need sports_id (everyone else)
    // Note: 'athlete/player' becomes 'athlete_player' after normalization
    $sport_required_roles = [
        'coach',
        'athlete',
        'athlete_player',  // Handles 'athlete/player' from DB
        'trainee',
        'trainor',
        'umpire',
        'scorer'
    ];

    // Check if current role requires sports_id
    if (in_array($normalized_role, $sport_required_roles)) {
        if (!isset($user['sports_id']) || $user['sports_id'] === null) {
            // Role requires sport but it's missing
            session_destroy();
            header("Location: " . BASE_URL . "/auth/login.php");
            exit;
        }
    }

    // Check if user has the required role (case-insensitive)
    if ($normalized_role !== $normalized_required) {
        // Wrong role - show error page
        if (headers_sent()) {
            // Headers already sent, output JSON error
            echo json_encode([
                'ok' => false,
                'message' => "Access denied. Required role: $required_role, Your role: $current_role"
            ]);
            exit;
        } else {
            // Show styled error page
            http_response_code(403);
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Access Denied</title>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        margin: 0;
                        display: flex;
                        min-height: 100vh;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                    }
                    .card {
                        background: #fff;
                        padding: 40px;
                        border-radius: 16px;
                        box-shadow: 0 20px 60px rgba(0,0,0,.25);
                        max-width: 500px;
                        text-align: center;
                    }
                    .icon {
                        font-size: 64px;
                        margin-bottom: 20px;
                    }
                    h1 {
                        margin: 0 0 16px 0;
                        color: #111;
                        font-size: 28px;
                    }
                    p {
                        color: #6b7280;
                        margin: 8px 0;
                        line-height: 1.6;
                    }
                    .btn {
                        display: inline-block;
                        margin-top: 24px;
                        padding: 12px 24px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: #fff;
                        text-decoration: none;
                        border-radius: 10px;
                        font-weight: 600;
                        transition: transform 0.2s;
                    }
                    .btn:hover {
                        transform: translateY(-2px);
                    }
                    .roles {
                        margin-top: 20px;
                        padding: 16px;
                        background: #f9fafb;
                        border-radius: 8px;
                        font-size: 14px;
                    }
                </style>
            </head>
            <body>
                <div class="card">
                    <div class="icon">🚫</div>
                    <h1>Access Denied</h1>
                    <p>You do not have permission to access this page.</p>
                    <div class="roles">
                        <strong>Required Role:</strong> <?= htmlspecialchars($required_role) ?><br>
                        <strong>Your Role:</strong> <?= htmlspecialchars($current_role) ?>
                    </div>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn">Logout</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // All checks passed - access granted
    return true;
}

/**
 * Check if user is logged in (any role)
 */
function is_logged_in() {
    return isset($_SESSION['user']) && 
           isset($_SESSION['user']['user_id']) && 
           isset($_SESSION['user']['user_role']);
}

/**
 * Get current user's role
 */
function get_user_role() {
    return $_SESSION['user']['user_role'] ?? null;
}

/**
 * Get current user's sports_id (may be null for some roles)
 */
function get_user_sport() {
    return $_SESSION['user']['sports_id'] ?? null;
}

/**
 * Check if current user's role requires sport selection
 */
function role_requires_sport($role = null) {
    $role = $role ?? get_user_role();
    // Normalize: 'athlete/player' -> 'athlete_player', 'Tournament manager' -> 'tournament_manager'
    $normalized = strtolower(str_replace(['/', ' '], '_', $role));
    $sport_required_roles = ['coach', 'athlete', 'athlete_player', 'trainee', 'trainor', 'umpire', 'scorer'];
    return in_array($normalized, $sport_required_roles);
}