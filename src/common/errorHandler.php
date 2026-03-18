<?php

/**
 * Global Error Handler
 *
 * This file sets up global exception and error handlers that automatically
 * log all uncaught exceptions to the error terminal system.
 *
 * Should be required after head.php and after $DBLIB is initialized.
 */

use Rms\Services\ErrorTerminalService;
use Rms\Services\ErrorLogger;

/**
 * Global exception handler for uncaught exceptions
 */
set_exception_handler(function (Throwable $e) {
    ErrorLogger::capture($e, 'uncaught_exception');

    // Log to PHP error log as well
    error_log("Uncaught exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

    // Return 500 error to client
    http_response_code(500);

    // Show error page or return generic error
    if (function_exists('finish')) {
        finish(false, ['message' => 'Ein interner Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Ein interner Fehler ist aufgetreten.'
        ]);
    }

    exit;
});

/**
 * Global error handler for PHP errors
 * Converts PHP errors to exceptions so they get logged
 */
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Don't handle suppressed errors
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $errorLevel = 'warning';
    switch ($errno) {
        case E_ERROR:
        case E_PARSE:
        case E_CORE_ERROR:
        case E_COMPILE_ERROR:
            $errorLevel = 'critical';
            break;
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_STRICT:
        case E_DEPRECATED:
            $errorLevel = 'warning';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorLevel = 'info';
            break;
    }

    global $DBLIB, $AUTH;

    try {
        if ($DBLIB) {
            $service = new ErrorTerminalService($DBLIB);

            $userId = null;
            $instanceId = 1;

            if ($AUTH) {
                $userId = $AUTH->data['users_userid'] ?? null;
                $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 1);
            }

            $service->log(
                $errorLevel,
                'php_error',
                $errstr,
                null,
                [
                    'file' => $errfile,
                    'line' => $errline,
                    'error_type' => $errno,
                ],
                $userId,
                $instanceId
            );
        }
    } catch (\Exception $e) {
        error_log("Error handler failed: " . $e->getMessage());
    }

    // Return false to allow PHP's default error handler to run as well
    return false;
});

/**
 * Shutdown handler for fatal errors
 */
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        global $DBLIB, $AUTH;

        try {
            if ($DBLIB) {
                $service = new ErrorTerminalService($DBLIB);

                $userId = null;
                $instanceId = 1;

                if ($AUTH) {
                    $userId = $AUTH->data['users_userid'] ?? null;
                    $instanceId = (int)($AUTH->data['instance']['instances_id'] ?? 1);
                }

                $service->log(
                    'critical',
                    'fatal_error',
                    $error['message'],
                    null,
                    [
                        'file' => $error['file'],
                        'line' => $error['line'],
                        'type' => $error['type'],
                    ],
                    $userId,
                    $instanceId
                );
            }
        } catch (\Exception $e) {
            error_log("Shutdown handler failed: " . $e->getMessage());
        }
    }
});
