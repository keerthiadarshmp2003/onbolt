<?php
/**
 * OnBolt Digital Platform - Serverless Master Router
 */

// Enable temporary session directory for serverless environments
if (is_dir('/tmp') && is_writable('/tmp')) {
    @session_save_path('/tmp');
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/index.php';

