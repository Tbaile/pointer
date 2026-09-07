<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Administrator Account
    |--------------------------------------------------------------------------
    |
    | DatabaseSeeder creates a single administrator using these credentials.
    | Override them via the ADMIN_EMAIL / ADMIN_PASSWORD environment variables
    | before provisioning a real deployment.
    |
    */

    'email' => env('ADMIN_EMAIL', 'admin@example.com'),

    'password' => env('ADMIN_PASSWORD', 'password'),

];
