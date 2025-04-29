<?php
/**
 * Procore Budget API to AIA G702/G703 Invoice Generator
 * Main entry point for the application
 */

// Use Composer Autoloader
require_once 'vendor/autoload.php';

// Load environment variables
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Project root
    $dotenv->load();
} catch (\Dotenv\Exception\InvalidPathException $e) {
     // Log this critical failure if possible, though logger might not be ready
     error_log("CRITICAL: Could not find .env file."); // Fallback logging
     die("Error: Could not find .env file. Please create one based on .env.example.");
}

require_once 'config/config.php'; // config.php will now use $_ENV
require_once 'includes/session.php'; // Ensure session_start() is called here
require_once 'includes/logger.php'; // Include and instantiate the logger ($logger variable is now available)

// Use Namespaces
use App\ProcoreApi;
use App\AiaGenerator;
use App\FileDownloader;

// Initialize API and message variables
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
// Clear flash messages after retrieving them
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']);
}


// Check if user is logged in
$is_logged_in = isset($_SESSION['access_token']) && isset($_SESSION['config']);

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // *** CSRF Token Validation ***
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token mismatch or missing - potential CSRF attack
        $_SESSION['error_message'] = 'Invalid request. Please try again.';
        // Log this attempt
        $logger->warning('CSRF token validation failed.', [
            'session_token_set' => isset($_SESSION['csrf_token']),
            'post_token_set' => isset($_POST['csrf_token']),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
        ]);
        // Regenerate token to be safe
        unset($_SESSION['csrf_token']); // Force regeneration on next load
        header('Location: index.php'); // Redirect back to the form
        exit;
    }
    // *** End CSRF Token Validation ***

    try {
        if (isset($_POST['action'])) {
            $logger->info('Processing POST action.', ['action' => $_POST['action']]); // Log action start
            switch ($_POST['action']) {
                case 'login':
                    // Basic validation
                    if (empty($_POST['client_id']) || empty($_POST['client_secret'])) {
                        throw new Exception('Client ID and Client Secret are required.');
                    }

                    // Store credentials in session
                    $config['client_id'] = $_POST['client_id'];
                    $config['client_secret'] = $_POST['client_secret'];
                    $_SESSION['config'] = $config;

                    // Test connection and get token
                    // Use fully qualified class name or ensure 'use App\ProcoreApi;' is at the top
                    $api = new ProcoreApi($config, $logger);
                    $token = $api->getAccessToken(); // Get token explicitly
                    if (!$token) { // Check if token was obtained
                         throw new Exception('Failed to obtain access token.');
                    }
                    // Store token in session
                    $_SESSION['access_token'] = $token;


                    $_SESSION['success_message'] = 'API connection successful!';
                    header('Location: index.php'); // Redirect to clear POST data and refresh state
                    exit;

                case 'generate_invoice':
                    if (!$is_logged_in) { // Check login status again
                        throw new Exception('Please log in first');
                    }

                    // --- Enhanced Validation ---
                    $errors = []; // Array to hold validation errors

                    // Required fields check
                    $required_fields = [
                        'project_id', 'owner_name', 'project_name', 'application_number',
                        'contractor_name', 'period_to', 'contract_date', 'original_contract_sum',
                        'change_orders_sum',
                        'retainage_completed_percent', // New
                        'retainage_stored_percent',    // New
                        'previous_payments'
                    ];
                    foreach ($required_fields as $field) {
                        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                            $errors[] = "Missing required field: " . str_replace('_', ' ', ucfirst($field));
                        }
                    }

                    // Numeric validation (allow floats/decimals)
                    $numeric_fields = [
                        'original_contract_sum', 'change_orders_sum',
                        'retainage_completed_percent', // New
                        'retainage_stored_percent',    // New
                        // 'retainage_reduction_threshold', // Add if using threshold
                        // 'reduced_retainage_percent',   // Add if using threshold
                        'previous_payments'
                    ];
                    foreach ($numeric_fields as $field) {
                        if (isset($_POST[$field]) && !is_numeric($_POST[$field])) {
                             $errors[] = str_replace('_', ' ', ucfirst($field)) . " must be a valid number.";
                        }
                    }

                    // Integer validation
                    if (isset($_POST['project_id']) && filter_var($_POST['project_id'], FILTER_VALIDATE_INT) === false) {
                         $errors[] = "Project ID must be a valid integer.";
                    }

                    // Date validation (basic YYYY-MM-DD format check)
                    $date_fields = ['period_to', 'contract_date'];
                    foreach ($date_fields as $field) {
                         if (isset($_POST[$field]) && !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $_POST[$field])) {
                             $errors[] = str_replace('_', ' ', ucfirst($field)) . " must be a valid date in YYYY-MM-DD format.";
                         }
                    }

                    // Check if any errors occurred
                    if (!empty($errors)) {
                        // Combine errors into a single message or handle differently
                        throw new Exception("Validation failed: <br>- " . implode("<br>- ", $errors));
                    }
                    // --- End Enhanced Validation ---


                    $config = $_SESSION['config'];
                    $api = new ProcoreApi($config, $logger, $_SESSION['access_token'] ?? null);
                    $project_id = (int)$_POST['project_id']; // Already validated as int

                    $company_id = $_SESSION['selected_company_id'] ?? null;
                    if (!$company_id) {
                         throw new Exception("Company ID not found. Please select a company first.");
                    }

                    // Get budget data from Procore
                    $budget_data = $api->getBudgetData($project_id, $company_id);

                    // Prepare project info (add new retainage fields)
                    $project_info = [
                        'owner_name' => htmlspecialchars($_POST['owner_name']),
                        'project_name' => htmlspecialchars($_POST['project_name']),
                        'application_number' => htmlspecialchars($_POST['application_number']),
                        'contractor_name' => htmlspecialchars($_POST['contractor_name']),
                        'period_to' => $_POST['period_to'],
                        'contract_date' => $_POST['contract_date'],
                        'original_contract_sum' => floatval($_POST['original_contract_sum']),
                        'change_orders_sum' => floatval($_POST['change_orders_sum']),
                        // Store new retainage percentages
                        'retainage_completed_percent' => floatval($_POST['retainage_completed_percent']),
                        'retainage_stored_percent' => floatval($_POST['retainage_stored_percent']),
                        // 'retainage_reduction_threshold' => isset($_POST['retainage_reduction_threshold']) && is_numeric($_POST['retainage_reduction_threshold']) ? floatval($_POST['retainage_reduction_threshold']) : null, // Add if using
                        // 'reduced_retainage_percent' => isset($_POST['reduced_retainage_percent']) && is_numeric($_POST['reduced_retainage_percent']) ? floatval($_POST['reduced_retainage_percent']) : null, // Add if using
                        'previous_payments' => floatval($_POST['previous_payments'])
                        // 'retainage_percentage' => floatval($_POST['retainage_percentage']), // REMOVE OLD FIELD if no longer used directly
                    ];

                    // Process budget data - IMPORTANT: Adjust keys based on actual API response
                    $processed_budget_data = [];
                    if (is_array($budget_data)) {
                        foreach ($budget_data as $item) {
                            if (is_array($item)) {
                                // --- VERIFY THESE KEYS based on Procore API response for your endpoint ---
                                $description = htmlspecialchars($item['cost_code']['full_code'] ?? $item['description'] ?? $item['name'] ?? 'Unknown Item'); // Example: Use cost code if available
                                $scheduled_value = floatval($item['revised_budget_amount'] ?? $item['original_budget_amount'] ?? 0); // Example: Use revised budget
                                $billed_to_date = floatval($item['amount_billed'] ?? 0); // Example key
                                $current_billed = floatval($item['current_period_amount_billed'] ?? 0); // Example key
                                $material_stored = floatval($item['material_stored'] ?? 0); // Example key
                                // -----------------------------------------------------------------------

                                $processed_budget_data[] = [
                                    'description' => $description,
                                    'scheduled_value' => $scheduled_value,
                                    'previous_completed' => $billed_to_date - $current_billed, // Calculation based on example keys
                                    'current_completed' => $current_billed,
                                    'stored_materials' => $material_stored,
                                    'completed_amount' => $billed_to_date // Calculation based on example keys
                                ];
                            }
                        }
                    } else {
                         throw new Exception("Failed to retrieve valid budget data array from Procore.");
                    }
                    if (empty($processed_budget_data) && !empty($budget_data)) {
                         // This might happen if the outer response was an array, but inner items weren't, or keys didn't match
                         error_log("Procore budget data received but could not be processed. Raw data: " . json_encode($budget_data));
                         throw new Exception("Received budget data from Procore, but failed to process items. Check logs and API response structure.");
                    } elseif (empty($processed_budget_data)) {
                         // No budget items returned from API
                         // Consider if this is an error or just an empty budget
                         // For now, let it proceed, but the Excel file will be mostly empty.
                         // You might want to throw an Exception("No budget items found for this project.") here instead.
                    }


                    // Generate and download Excel file
                    $generator = new AiaGenerator($logger); // Pass logger
                    $excel_file = $generator->generateAiaExcel($processed_budget_data, $project_info);

                    // Sanitize filename components
                    $safe_project_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_info['project_name']);
                    $safe_app_number = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project_info['application_number']);
                    $filename = 'AIA_G702G703_' . $safe_project_name . '_' . $safe_app_number . '.xlsx';

                    $downloader = new FileDownloader($logger); // Pass logger
                    $downloader->downloadFile($excel_file, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    // exit; // Exit is now handled within downloadFile

                case 'logout':
                    // Clear session
                    session_unset();
                    session_destroy();
                    // Start a new session to store the flash message
                    session_start();
                    $_SESSION['success_message'] = 'Logged out successfully';
                    header('Location: index.php'); // Redirect to clear state
                    exit;
            }
        }
    } catch (Exception $e) {
        // Log the exception details
        $logger->error('Exception caught during POST processing.', [
            'action' => $_POST['action'] ?? 'N/A',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            // 'trace' => $e->getTraceAsString() // Optional: Log trace in debug mode
        ]);

        // Store user-friendly error message in session or display directly
        if (isset($_POST['action']) && $_POST['action'] === 'login') {
             $error_message = 'Login Error: ' . htmlspecialchars($e->getMessage()); // Display directly for login
             unset($_SESSION['config']);
             unset($_SESSION['access_token']);
             $is_logged_in = false;
        } else {
             $_SESSION['error_message'] = 'Error: ' . htmlspecialchars($e->getMessage()); // Store for display after redirect/render
             $error_message = 'Error: ' . htmlspecialchars($e->getMessage()); // Also set for current page render if no redirect
        }
    }
}

// Get companies and projects if logged in
$companies = [];
$projects = [];
if ($is_logged_in) {
    try {
        // Use the config and token from the session
        if (!isset($_SESSION['config']) || !isset($_SESSION['access_token'])) {
             throw new Exception("Session configuration or access token is missing.");
        }
        // Pass logger instance
        $api = new ProcoreApi($_SESSION['config'], $logger, $_SESSION['access_token']);
        $logger->debug('Fetching companies.');
        $companies = $api->getCompanies();
        $logger->debug('Companies fetched.', ['count' => count($companies)]);

        // Validate and store company_id from GET request in session
        if (isset($_GET['company_id'])) {
             $company_id = filter_input(INPUT_GET, 'company_id', FILTER_VALIDATE_INT);
             if ($company_id !== false && $company_id > 0) {
                 // Check if this company ID is valid for the user (optional but good)
                 $is_valid_company = false;
                 foreach ($companies as $company) {
                     if ($company['id'] == $company_id) {
                         $is_valid_company = true;
                         break;
                     }
                 }
                 if ($is_valid_company) {
                     $_SESSION['selected_company_id'] = $company_id; // Store in session
                     $logger->debug('Fetching projects for company.', ['company_id' => $company_id]);
                     $projects = $api->getProjects($company_id);
                     $logger->debug('Projects fetched.', ['count' => count($projects)]);
                 } else {
                      unset($_SESSION['selected_company_id']); // Clear invalid selection
                      $error_message = "Invalid or unauthorized Company ID selected.";
                      $logger->warning('Invalid company ID selected.', ['selected_id' => $company_id]);
                 }

             } else {
                 unset($_SESSION['selected_company_id']); // Clear invalid selection
                 $error_message = "Invalid Company ID format specified.";
                 // unset($_GET['company_id']); // Prevent using invalid ID later
             }
        } elseif (isset($_SESSION['selected_company_id'])) {
             // If no company_id in GET, check if one is already in session
             $company_id = $_SESSION['selected_company_id'];
             // Optionally re-fetch projects if needed, or assume they are loaded if form is shown
             // $projects = $api->getProjects($company_id);
        }
    } catch (Exception $e) {
        // Catch API errors during company/project fetch
        $logger->error('API Error during GET processing (companies/projects).', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        $error_message = 'API Error: ' . htmlspecialchars($e->getMessage());
        // Consider logging out the user if API calls fail due to auth issues
        // session_unset(); session_destroy(); $is_logged_in = false;
    }
}

// Include header template
include 'templates/header.php';

// Display error/success messages using Bootstrap alerts
if ($error_message) {
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error_message) . '</div>';
}
if ($success_message) {
    echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($success_message) . '</div>';
}


// Display Login Form or Company/Project Selection/Invoice Form
if (!$is_logged_in) {
    include 'templates/login_form.php';
} else {
    // Display Company Selection if not selected
    if (empty($company_id) && !empty($companies)) {
        echo '<h2 class="text-center">Select Company</h2>';
        echo '<div class="list-group company-list mt-3">'; // Use list-group for better styling
        foreach ($companies as $company) {
            echo '<a href="?company_id=' . urlencode($company['id']) . '" class="list-group-item list-group-item-action">' . htmlspecialchars($company['name']) . '</a>';
        }
        echo '</div>';
    }
    // Display Project Selection if company selected but no project selected for invoice yet
    elseif (!empty($company_id) && empty($_POST['action'] === 'generate_invoice') && !empty($projects)) { // Check we aren't processing invoice
         echo '<h2 class="text-center">Select Project for Company ID: ' . htmlspecialchars($company_id) . '</h2>';
         // Display the invoice form which includes the project dropdown
         include 'templates/invoice_form.php';
    }
    // If company and project are selected (implicitly via form submission), show form again if needed or confirmation
    elseif (!empty($company_id) && !empty($projects)) {
         // If we are here after a POST or if a project was previously selected, show the form
         include 'templates/invoice_form.php';
    }
    elseif (!empty($company_id) && empty($projects)) {
         echo '<div class="alert alert-warning">No projects found for the selected company.</div>';
    }


    // Logout option - styled as a button within its own form
    echo '<form method="post" action="" class="text-center mt-4">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf_token) . '">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn-outline-danger">Logout</button>
    </form>';
}

// Include footer template
include 'templates/footer.php';
?>