<?php
// Configure session security settings (BEFORE session_start())
session_set_cookie_params([
    'lifetime' => 0, // Expire when browser closes
    'path' => '/',
    // 'domain' => '.yourdomain.com', // Uncomment and set your domain for production
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Send only over HTTPS in production
    'httponly' => true, // Prevent JavaScript access to session cookie
    'samesite' => 'Lax' // Mitigate CSRF attacks
]);

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Handle error if random_bytes fails (highly unlikely)
        error_log('Failed to generate CSRF token: ' . $e->getMessage());
        // You might want to display an error or halt execution here
        // For simplicity, we'll proceed, but this is a critical failure point
        $_SESSION['csrf_token'] = 'fallback_token_error_' . time(); // Less secure fallback
    }
}
$csrf_token = $_SESSION['csrf_token']; // Make it available for forms

// Regenerate session ID periodically to prevent session fixation (optional but recommended)
// Example: Regenerate every 30 minutes
// Note: Adjust the time interval as needed
if (!isset($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} elseif (time() - $_SESSION['last_regen'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}
