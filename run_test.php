<?php
// CLI runner: suppress curl_close deprecation warnings, then include the test
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if ($errno === E_DEPRECATED || $errno === E_WARNING) {
        return true; // swallow
    }
    return false;
});
require_once __DIR__ . '/api/test_db.php';
