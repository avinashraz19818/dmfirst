<?php
/**
 * Central database configuration.
 * Prefer environment variables on production/cPanel. Existing project values
 * remain as fallbacks so the package can be uploaded without editing dozens of files.
 */
return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'user' => getenv('DB_USER') ?: 'club532583_dmfirst',
    'password' => getenv('DB_PASSWORD') ?: 'club532583_dmfirst',
    'database' => getenv('DB_NAME') ?: 'club532583_dmfirst',
    'charset' => 'utf8mb4',
];
