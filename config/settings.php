<?php

// Error reporting
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Timezone
date_default_timezone_set('Europe/Oslo');

// Settings
$settings = [];

// DEFAULT ---

// Path settings
$settings['root'] = dirname(__DIR__);
$settings['temp'] = $settings['root'] . '/tmp';
$settings['public'] = $settings['root'] . '/public';

$settings['path']= [
    'data' => $settings['root'] . '/data',
    'env' => $settings['root'] . '/env'
];
$settings['file_loans_name'] = 'loans';
$settings['file_ssns_name'] = 'ssns';

// Error Handling Middleware settings
$settings['error'] = [

    'display_error_details' => false,

    'log_errors' => true,

    // Display error details in error log
    'log_error_details' => true,
];

$settings['logger'] = [
    'name' => 'applogs',
    'path' => $settings['root'] . '/logs/app',
    'level' => \Monolog\Level::Info, 
];

// Database settings
$settings['db'] = [
    'driver' => \Cake\Database\Driver\Mysql::class,
    'encoding' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    // Enable identifier quoting
    'quoteIdentifiers' => true,
    // Set to null to use MySQL servers timezone
    'timezone' => null,
    // Disable meta data cache
    'cacheMetadata' => false,
    // Disable query logging
    'log' => false,
    // Turn off persistent connections
    'persistent' => false,
    // PDO options
    'flags' => [
        // Turn off persistent connections
        PDO::ATTR_PERSISTENT => false,
        // Enable exceptions
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Emulate prepared statements
        PDO::ATTR_EMULATE_PREPARES => true,
        // Set default fetch mode to array
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Convert numeric values to strings when fetching.
        // Since PHP 8.1 integers and floats in result sets will be returned using native PHP types.
        // This option restores the previous behavior.
        PDO::ATTR_STRINGIFY_FETCHES => true,
    ],
];


$settings['commands'] = [
    \App\Console\DailyDataSetsCommand::class,
];


$settings['providerID'] = '1234:987654321';
$settings['financialInstitutionID'] = '987654321'; //SP == FI, just one single FI, so we fix it here

// ---

// Detect environment
$_ENV['APP_ENV'] ??= $_SERVER['APP_ENV'] ?? 'dev';

// Overwrite default settings with environment specific local settings
$configFiles = [
    $settings['path']['env'] . sprintf('/env.%s.php', $_ENV['APP_ENV']),
];

foreach ($configFiles as $configFile) {
    if (!file_exists($configFile)) {
        continue;
    }

    $local = require $configFile;
    
    if (is_callable($local)) {
        $settings = $local($settings);
    }
}

return $settings;