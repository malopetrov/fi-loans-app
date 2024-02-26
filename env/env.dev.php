<?php

return function (array $settings): array {
    
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');

    $settings['error']['display_error_details'] = true;
    $settings['logger']['level'] = \Monolog\Level::Debug;

    // CREDENTIALS

    $settings['db']['host'] = '127.0.0.1';
    $settings['db']['port'] = '3306';
    $settings['db']['username'] = 'dbUsername';
    $settings['db']['database'] = 'dbDatabase';
    $settings['db']['password'] = 'dbPassword';

    $settings['basic-auth-debt-update'] = [
        'user' => 'theAuthUserNameForDoingUpdates',
        'pass' => 'someNiceLongNumbers123Symbols*(&%Password'
    ];

    $settings['dic_url'] = 'https://api-test.domain.com';
    $settings['dic_path'] = '/debt-information/v1';
    $settings['dic_cert_full_path_file'] = ($settings['path']['env'] ?? '') . '/org_auth.pem';
    $settings['dic_cert_password'] = '1234';

    $settings['authorizedOrgNums'] = ['123456789', '987654321']; // as in the certificate: $_SERVER['SSL_CLIENT_S_DN']

    return $settings;
};