<?php

/**
 * ErrorHandlerService
 *
 * Provides a standardized way to wrap API endpoint logic in error handling.
 * Catches exceptions, logs them (via Sentry if available), and returns
 * a clean JSON error response to the client.
 */
class ErrorHandlerService
{
    /**
     * Wrap a callable in try/catch error handling.
     *
     * Executes the given function and catches any exceptions. On failure,
     * the error is logged and a clean JSON error response is returned to
     * the client via the finish() function.
     *
     * @param callable $fn The API logic to execute
     * @param string $context A label for the context (used in log messages)
     * @return void
     */
    public static function wrap(callable $fn, string $context = 'API'): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            // Log to error_log
            error_log(sprintf(
                '[%s] %s error in %s: %s in %s:%d',
                date('Y-m-d H:i:s'),
                $context,
                $context,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            // Log to Sentry if available
            if (function_exists('\Sentry\captureException')) {
                \Sentry\captureException($e);
            }

            // Determine HTTP status code
            $statusCode = 500;
            if (method_exists($e, 'getStatusCode')) {
                $statusCode = $e->getStatusCode();
            }

            // Return clean error response
            http_response_code($statusCode);

            // Use finish() if available (standard AdamRMS API response format)
            if (function_exists('finish')) {
                finish(false, [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred. Please try again later.',
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'result' => false,
                    'error' => [
                        'code' => 'INTERNAL_ERROR',
                        'message' => 'An unexpected error occurred. Please try again later.',
                    ],
                ]);
                exit;
            }
        }
    }
}
