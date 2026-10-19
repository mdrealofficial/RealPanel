<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported PHP Versions
    |--------------------------------------------------------------------------
    |
    | PHP versions available for hosting accounts. The 'default' version is
    | pre-selected when creating new packages and accounts. In the future,
    | this will be auto-detected by the Go agent from installed versions.
    |
    */

    'php_versions' => ['8.2', '8.3', '8.4', '8.5'],

    'default_php_version' => '8.4',

    /*
    |--------------------------------------------------------------------------
    | Agent Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the Go agent running on managed servers.
    | The agent_port is used when auto-generating the agent URL.
    |
    */

    'agent_port' => env('REALPANEL_AGENT_PORT', env('OPTERIUS_AGENT_PORT', 7443)),
    'agent_timeout' => env('REALPANEL_AGENT_TIMEOUT', env('OPTERIUS_AGENT_TIMEOUT', 30)),
    'agent_secret' => env('REALPANEL_AGENT_SECRET', env('OPTERIUS_AGENT_SECRET', '')),

    /*
    |--------------------------------------------------------------------------
    | Licensing
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Nameservers
    |--------------------------------------------------------------------------
    */

    'ns1' => env('REALPANEL_NS1', env('OPTERIUS_NS1', 'ns1.realpanel.io')),
    'ns2' => env('REALPANEL_NS2', env('OPTERIUS_NS2', 'ns2.realpanel.io')),

    /*
    |--------------------------------------------------------------------------
    | Webmail
    |--------------------------------------------------------------------------
    */

    'webmail_url'         => env('REALPANEL_WEBMAIL_URL', env('OPTERIUS_WEBMAIL_URL', 'http://SERVER_IP:8090')),

    // Shared secret for one-click SSO into the RealPanel Mail webmail.
    // Must match PANEL_SSO_SECRET in the webmail's .env.
    // Leave empty to disable SSO (webmail link will open the login page instead).
    'webmail_sso_secret'  => env('REALPANEL_WEBMAIL_SSO_SECRET', env('OPTERIUS_WEBMAIL_SSO_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | phpMyAdmin
    |--------------------------------------------------------------------------
    */

    // The installer sets up phpMyAdmin on plain HTTP at port 8081. If you put
    // it behind SSL, override via REALPANEL_PHPMYADMIN_URL=https://your-host in .env
    'phpmyadmin_url' => env('REALPANEL_PHPMYADMIN_URL', env('OPTERIUS_PHPMYADMIN_URL', 'http://SERVER_IP:8081')),

    // Shared secret for one-click SSO into phpMyAdmin. Must match the value in
    // /etc/realpanel/pma-signon-secret (or /etc/opterius/pma-signon-secret) on each managed server.
    // Leave empty to disable SSO (clicking phpMyAdmin opens the login page instead).
    'phpmyadmin_sso_secret' => env('REALPANEL_PMA_SSO_SECRET', env('OPTERIUS_PMA_SSO_SECRET')),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'api' => [
        'rate_limit' => env('REALPANEL_API_RATE_LIMIT', env('OPTERIUS_API_RATE_LIMIT', 60)),
    ],

    // Shared secret for SSO.
    'sso_secret' => env('REALPANEL_SSO_SECRET', env('OPTERIUS_SSO_SECRET')),

    'version' => file_exists(base_path('version.txt')) ? trim(file_get_contents(base_path('version.txt'))) : env('REALPANEL_VERSION', '2.9.37'),
    'github_repo' => env('REALPANEL_GITHUB_REPO', 'mdrealofficial/RealPanel'),
    'update_version_url' => env('REALPANEL_UPDATE_URL', 'https://raw.githubusercontent.com/mdrealofficial/RealPanel/main/version.txt'),
    'license_key' => env('REALPANEL_LICENSE_KEY', env('OPTERIUS_LICENSE_KEY', '')),
    'license_server_url' => env('REALPANEL_LICENSE_URL', env('OPTERIUS_LICENSE_URL', 'https://realpanel.io')),

];
