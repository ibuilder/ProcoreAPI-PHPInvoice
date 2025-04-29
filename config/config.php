<?php
// Configuration settings for the application

// API credentials from environment variables
$config = [
    'client_id' => $_ENV['PROCORE_CLIENT_ID'] ?? '',
    'client_secret' => $_ENV['PROCORE_CLIENT_SECRET'] ?? '',
    'base_url' => $_ENV['PROCORE_BASE_URL'] ?? 'https://api.procore.com/vapid',
];

// Error reporting based on environment
if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}


// Other constants
define('APP_NAME', 'Procore API Invoice Generator');
define('APP_VERSION', '1.0.0');
?>