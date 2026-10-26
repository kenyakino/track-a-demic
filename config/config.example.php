<?php
/**
 * Track-a-Demic configuration template.
 *
 * 1. Copy this file to config/config.php
 * 2. Fill in your own values
 * 3. Never commit config.php (it is listed in .gitignore)
 */
return [
    'app' => [
        'name'     => 'Track-a-Demic',
        'env'      => 'development',          // use 'production' when deployed
        'base_url' => '/track-a-demic',       // use '' if the site is in the web root
        'timezone' => 'Asia/Manila',          // used for dates such as "overdue"
    ],
    'db' => [
        'host'    => 'localhost',             // InfinityFree gives you a host like sqlXXX.infinityfree.com
        'name'    => 'track_a_demic',
        'user'    => 'root',                  // XAMPP default
        'pass'    => '',                      // XAMPP default is empty
        'charset' => 'utf8mb4',
    ],
    'auth' => [
        // Advisers must enter this code when they register.
        // Change it and share it only with advisers.
        'adviser_code' => 'CHANGE-THIS-ADVISER-CODE',
    ],
    'upload' => [
        'max_size'    => 5 * 1024 * 1024,     // 5 MB
        'allowed_ext' => ['pdf', 'docx'],
        'dir'         => __DIR__ . '/../uploads',
    ],
];
