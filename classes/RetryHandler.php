<?php
/**
 * Retry Handler Class
 * Handles retry logic for failed operations with exponential backoff
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/Email.php';

class RetryHandler {
    private $maxAttempts;
    private $baseDelay;
    private $emailService;
    
    public function __construct() {
        $this->maxAttempts = MAX_RETRY_ATTEMPTS;
        $this->baseDelay = RETRY_DELAY_SECONDS;
        $this->emailService = new Email();
    }
    
    /**
     * Execute operation with retry logic
     * 
     * @param callable $operation The operation to execute
     * @param string $operationName Name of the operation for logging
     * @param array $context Additional context for error reporting
     * @return mixed Result of the operation
     * @throws Exception If operation fails after all retries
     */
    public function executeWithRetry(callable $operation, $operationName = 'Operation', $context = []) {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt <= $this->maxAttempts) {
            try {
                $result = $operation();
                
                // Log successful retry if it wasn't the first attempt
                if ($attempt > 0) {
                    error_log("RetryHandler: {$operationName} succeeded on attempt " . ($attempt + 1));
                }
                
                return $result;
                
            } catch (Exception $e) {
                $lastError = $e;
                $attempt++;
                
                if ($attempt <= $this->maxAttempts) {
                    // Calculate exponential backoff delay
                    $delay = $this->baseDelay * pow(2, $attempt - 1);
                    
                    error_log("RetryHandler: {$operationName} failed (attempt {$attempt}/{$this->maxAttempts}). Retrying in {$delay} seconds...");
                    error_log("Error: " . $e->getMessage());
                    
                    // Wait before retrying
                    sleep($delay);
                } else {
                    // All retries exhausted
                    error_log("RetryHandler: {$operationName} failed after {$this->maxAttempts} retries");
                    
                    // Send error notification email
                    $this->sendErrorNotification($operationName, $e, $context);
                    
                    throw new Exception("Operation '{$operationName}' failed after {$this->maxAttempts} retries: " . $e->getMessage());
                }
            }
        }
        
        throw $lastError;
    }
    
    /**
     * Send error notification email to admin
     */
    private function sendErrorNotification($operationName, $exception, $context = []) {
        try {
            require_once __DIR__ . '/../config/email.php';
            
            $subject = "Operation Failed: {$operationName}";
            $message = "
                <h2>Operation Failed After Retries</h2>
                <p><strong>Operation:</strong> {$operationName}</p>
                <p><strong>Error:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>
                <p><strong>File:</strong> " . $exception->getFile() . "</p>
                <p><strong>Line:</strong> " . $exception->getLine() . "</p>
                <p><strong>Attempts:</strong> " . ($this->maxAttempts + 1) . "</p>
            ";
            
            if (!empty($context)) {
                $message .= "<h3>Context:</h3><pre>" . htmlspecialchars(print_r($context, true)) . "</pre>";
            }
            
            $message .= "<h3>Stack Trace:</h3><pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
            
            $this->emailService->send(ADMIN_EMAIL, $subject, $message);
            
        } catch (Exception $e) {
            error_log("Failed to send error notification email: " . $e->getMessage());
        }
    }
}

