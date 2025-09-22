<?php

/**
 * Bootstrap file for Nostrbots
 * 
 * Sets up autoloading and initializes the system.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Set up PSR-4 autoloading for Nostrbots namespace
spl_autoload_register(function ($class) {
    $prefix = 'Nostrbots\\';
    $base_dir = __DIR__ . '/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone (can be overridden in bot configuration)
date_default_timezone_set('UTC');
