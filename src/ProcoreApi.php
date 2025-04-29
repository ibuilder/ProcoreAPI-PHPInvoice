<?php
namespace App;

use Exception;
use Monolog\Logger; // Import Logger

class ProcoreApi {
    private $client_id;
    private $client_secret;
    private $access_token;
    private $base_url;
    private Logger $logger; // Add logger property

    // Accept logger instance in constructor
    public function __construct(array $config, Logger $logger, ?string $access_token = null) {
        if (empty($config['client_id']) || empty($config['client_secret'])) {
            throw new Exception("Client ID and Client Secret must be provided in config.");
        }
        $this->client_id = $config['client_id'];
        $this->client_secret = $config['client_secret'];
        $this->access_token = $access_token;
        $this->base_url = $config['base_url'] ?? 'https://api.procore.com/vapid';
        $this->logger = $logger; // Store logger instance
    }

    public function getAccessToken(): ?string {
        $url = $this->base_url . '/oauth/token';
        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ];

        try {
            // Pass null for queryParams, false for auth header, empty array for additional headers, $data for postData
            $response = $this->makeRequest($url, 'POST', null, false, [], $data);

            if (isset($response['access_token'])) {
                $this->access_token = $response['access_token'];
                $this->logger->info('Successfully obtained Procore access token.');
                return $this->access_token;
            } else {
                $this->logger->error('Procore API did not return access_token.', ['response' => $response]);
                return null;
            }
        } catch (Exception $e) {
            // Log the exception from makeRequest
            $this->logger->error('Failed to get Procore access token.', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null; // Indicate failure
        }
    }

    public function getCompanies(): array {
        $url = $this->base_url . '/companies';
        return $this->makeRequest($url, 'GET');
    }

    public function getProjects(int $company_id): array {
        $url = $this->base_url . '/companies/' . $company_id . '/projects';
        return $this->makeRequest($url, 'GET');
    }

    /**
     * Get Budget Data for a specific project.
     *
     * IMPORTANT: Verify the endpoint and field names against the Procore API documentation
     * for your specific needs (e.g., budget_line_items, direct_costs).
     * This example uses 'budget_line_items'.
     *
     * @param int $project_id The Procore Project ID.
     * @param int|null $company_id The Procore Company ID (required for project-level requests).
     * @return array The budget data.
     * @throws Exception If the API request fails or company_id is missing.
     */
    public function getBudgetData(int $project_id, ?int $company_id): array {
         if (!$company_id) {
             // Company ID is usually required for project-specific endpoints
             throw new Exception("Company ID is required to fetch budget data.");
         }

         // --- VERIFY THIS ENDPOINT ---
         // Common endpoints: /budget_line_items, /direct_costs, etc.
         // Check Procore API docs for the correct one for your data.
         $endpoint = '/budget_line_items';
         // --------------------------

         $url = $this->base_url . $endpoint;

         // Parameters often required for project-specific data
         $params = [
             'project_id' => $project_id,
             // Add other filters as needed (e.g., view_name, specific budget views)
             // 'filters[view_name]' => 'Default Budget View',
         ];

         // Pass company_id in the header for project-level requests
         $headers = [
             'Procore-Company-Id: ' . $company_id
         ];

         try {
             $this->logger->debug('Fetching budget data.', ['project_id' => $project_id, 'company_id' => $company_id, 'endpoint' => $endpoint]);
             $response = $this->makeRequest($url, 'GET', $params, true, $headers);

             if (is_array($response)) {
                 $this->logger->debug('Budget data fetched successfully.', ['item_count' => count($response)]);
                 return $response; // Return the array of budget items
             } else {
                 // This case might be less likely now with makeRequest throwing exceptions on bad JSON
                 $this->logger->error('Unexpected non-array response format from Procore budget endpoint.', ['response_type' => gettype($response)]);
                 throw new Exception("Failed to retrieve valid budget data structure from Procore.");
             }
         } catch (Exception $e) {
             $this->logger->error('Failed to get budget data.', [
                 'project_id' => $project_id,
                 'company_id' => $company_id,
                 'error' => $e->getMessage()
             ]);
             throw $e; // Re-throw the exception
         }
    }


    /**
     * Makes an HTTP request to the Procore API using cURL.
     *
     * @param string $url The full URL for the request.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param array|null $queryParams Query parameters for GET requests.
     * @param bool $sendAuthHeader Whether to send the Authorization header.
     * @param array $additionalHeaders Additional headers specific to this request.
     * @param array|null $postData Data to send in the body for POST/PUT requests.
     * @return array Decoded JSON response.
     * @throws Exception If the request fails or token is missing when required.
     */
    private function makeRequest(string $url, string $method, ?array $queryParams = null, bool $sendAuthHeader = true, array $additionalHeaders = [], ?array $postData = null): array {
        if ($sendAuthHeader && !$this->access_token) {
            throw new Exception('Access token is missing for authenticated API request.');
        }

        // Append query parameters to URL for GET requests
        if ($method === 'GET' && !empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();

        $allHeaders = [];
        if ($sendAuthHeader) {
            $allHeaders[] = 'Authorization: Bearer ' . $this->access_token;
        }
        // Merge additional headers
        $allHeaders = array_merge($allHeaders, $additionalHeaders);

        $content = null;
        if ($method !== 'GET' && $postData !== null) {
             $content = json_encode($postData);
             $allHeaders[] = 'Content-Type: application/json';
             // cURL handles Content-Length automatically when using CURLOPT_POSTFIELDS
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return response as string
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);          // Request timeout in seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout in seconds
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); // Set HTTP method
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders); // Set headers

        if ($method !== 'GET' && $content !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }

        // Optional: Disable SSL verification for local development if needed (NOT recommended for production)
        // if ($_ENV['APP_ENV'] !== 'production') {
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // }

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($curl_errno > 0) {
            // Use logger instead of error_log
            $this->logger->error("cURL Error during API request.", [
                'errno' => $curl_errno,
                'error' => $curl_error,
                'method' => $method,
                'url' => $url
            ]);
            throw new Exception("API request failed due to cURL error: $curl_error");
        }

        if ($http_code >= 400) {
             // Use logger instead of error_log
             $this->logger->error("Procore API HTTP Error.", [
                 'method' => $method,
                 'url' => $url,
                 'status' => $http_code,
                 'response_snippet' => substr($result ?: '', 0, 500) // Log snippet
             ]);
             // Throw a more specific exception or return an error structure
             if ($http_code === 401) {
                  throw new Exception("API request failed: Unauthorized (Status $http_code). Token might be invalid or expired.");
             }
             $responseSnippet = substr($result ?: '', 0, 500);
             throw new Exception("API request failed with status $http_code for URL $url. Response snippet: " . $responseSnippet);
        }

        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
             // Use logger instead of error_log
             $this->logger->error("Failed to decode API JSON response.", [
                 'json_error' => json_last_error_msg(),
                 'status' => $http_code,
                 'url' => $url,
                 'response_snippet' => substr($result ?: '', 0, 500) // Log snippet
             ]);
             throw new Exception("Failed to decode API response. JSON Error: " . json_last_error_msg());
        }
        $this->logger->debug('API request successful.', ['method' => $method, 'url' => $url, 'status' => $http_code]);
        return $decoded;
    }
}
?>