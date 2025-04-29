<?php
namespace App;

use Exception;
use Monolog\Logger; // Import Logger

class FileDownloader {
    private Logger $logger; // Add logger property

    // Accept logger in constructor
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function downloadFile(string $filePath, string $fileName, string $mimeType): void {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            // Use the injected logger
            $this->logger->error("File not found or not readable for download.", ['file' => $filePath]);
            throw new Exception('Error preparing file for download.');
        }

        // Prevent accidental output before headers
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        $this->logger->info('Attempting to download file.', ['file' => $filePath, 'download_name' => $fileName]);

        // Output the file
        $bytesSent = readfile($filePath);

        // Clean up the temporary file *after* sending it
        if ($bytesSent !== false) {
            unlink($filePath);
            $this->logger->debug('Temporary file deleted after download.', ['file' => $filePath]);
        } else {
            // Use the injected logger
            $this->logger->error("Failed to read file for download using readfile().", ['file' => $filePath]);
            // Don't throw an exception here as headers might already be sent
        }

        exit; // Stop script execution after download attempt
    }
}