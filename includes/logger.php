<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Creates and configures a Monolog logger instance.
 *
 * @return Logger
 */
function getLogger(): Logger
{
    // Define the log file path (ensure this directory exists and is writable by the web server)
    $logFilePath = __DIR__ . '/../logs/app.log'; // Log file in a 'logs' directory at the project root

    // Create a log channel
    $log = new Logger('ProcoreInvoiceApp');

    // Create a handler (logging to a file)
    // Set the minimum logging level based on environment (e.g., DEBUG for dev, WARNING/ERROR for prod)
    $logLevel = ($_ENV['APP_ENV'] ?? 'development') === 'production' ? Logger::WARNING : Logger::DEBUG;
    $handler = new StreamHandler($logFilePath, $logLevel);

    // Optional: Customize the log format
    $formatter = new LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        "Y-m-d H:i:s", // Date format
        true,          // Allow inline line breaks in messages
        true           // Ignore empty context/extra arrays
    );
    $handler->setFormatter($formatter);

    // Push the handler to the logger
    $log->pushHandler($handler);

    return $log;
}

// Create the global logger instance (or use dependency injection in a more complex app)
$logger = getLogger();

// Optional: Set a global exception handler to log uncaught exceptions
set_exception_handler(function (Throwable $exception) use ($logger) {
     $logger->critical(
         'Uncaught Exception: ' . $exception->getMessage(),
         [
             'exception' => get_class($exception),
             'file' => $exception->getFile(),
             'line' => $exception->getLine(),
             // 'trace' => $exception->getTraceAsString() // Be careful logging full traces in production (can be large)
         ]
     );
     // Display a generic error message in production
     if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
         // Ensure headers haven't been sent
         if (!headers_sent()) {
             header('HTTP/1.1 500 Internal Server Error');
         }
         echo "<h1>Internal Server Error</h1>";
         echo "<p>An unexpected error occurred. Please try again later or contact support.</p>";
     } else {
         // Display detailed error in development
         echo "<h1>Uncaught Exception</h1>";
         echo "<pre>" . htmlspecialchars($exception->__toString()) . "</pre>";
     }
     exit; // Stop execution
});
?>