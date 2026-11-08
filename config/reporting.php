<?php

/*
| SQL reporting logins (Power BI). Creating a database login needs a role that may CREATE ROLE;
| the application's own database user normally may not, so a separate administrative connection
| can be given here. Left empty, the application's connection is tried.
*/
return [
    'admin' => [
        'host' => env('DB_REPORTING_ADMIN_HOST', env('DB_HOST', '127.0.0.1')),
        'port' => env('DB_REPORTING_ADMIN_PORT', env('DB_PORT', '5432')),
        'username' => env('DB_REPORTING_ADMIN_USERNAME'),
        'password' => env('DB_REPORTING_ADMIN_PASSWORD'),
    ],
    // What a BI tool should be told to connect to (often a public host, not the app's internal one).
    'public_host' => env('DB_REPORTING_PUBLIC_HOST', env('DB_HOST', '127.0.0.1')),
    'public_port' => env('DB_REPORTING_PUBLIC_PORT', env('DB_PORT', '5432')),
    'sslmode' => env('DB_REPORTING_SSLMODE', 'require'),
    // Concurrent connections allowed per reporting login.
    'connection_limit' => (int) env('DB_REPORTING_CONNECTION_LIMIT', 5),
];
