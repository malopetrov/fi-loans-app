<?php

return function (array $settings): array {

    $settings['authorizedOrgNums'] = ['123123123']; // as in the certificate: $_SERVER['SSL_CLIENT_S_DN']

    $settings['db']['host'] = '127.0.0.1';
    $settings['db']['port'] = '3306';
    $settings['db']['username'] = 'liveDbUsername';
    $settings['db']['database'] = 'liveDbDatabase';
    $settings['db']['password'] = 'liveDbPassword';

    $settings['basic-auth-debt-update'] = [
        'user' => 'theAuthUserNameForDoingUpdates',
        'pass' => 'someNiceLongNumbers123Symbols*(&%Password'
    ];

    $settings['dic_url'] = 'https://api-live.domain.com';
    $settings['dic_path'] = '/debt-information/v1';
    $settings['dic_cert_full_path_file'] = $settings['path']['env'] . '/org_auth.pem';
    $settings['dic_cert_password'] = '1234';

    return $settings;
};