<?php
// config/session_config.php

// Session timeout in seconds (30 minutes = 1800 seconds)
define('SESSION_TIMEOUT', 1800);

// Check if session has timed out
function check_session_timeout() {
    if (isset($_SESSION['user']) && isset($_SESSION['user']['login_time'])) {
        $elapsed = time() - $_SESSION['user']['login_time'];
        
        if ($elapsed > SESSION_TIMEOUT) {
            // Session has expired
            session_unset();
            session_destroy();
            
            // Redirect to login with timeout message
            header("Location: " . BASE_URL . "/auth/login.php?timeout=1");
            exit;
        }
        
        // Update last activity time
        $_SESSION['user']['login_time'] = time();
    }
}