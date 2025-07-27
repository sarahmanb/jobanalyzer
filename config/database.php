<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings for the Job Analyzer application.
 * It supports multiple database connections and environments.
 */

return [
    // Default database connection name
    'default' => $_ENV['DB_CONNECTION'] ?? 'mysql',

    // Database connections configuration
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'job_analyzer',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $_ENV['DB_DATABASE'] ?? database_path('job_analyzer.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => $_ENV['DB_FOREIGN_KEYS'] ?? true,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'database' => $_ENV['DB_DATABASE'] ?? 'job_analyzer',
            'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'testing' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ],
    ],

    // Database migration settings
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    // Redis configuration (for caching and sessions)
    'redis' => [
        'default' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_DB'] ?? 0,
            'prefix' => $_ENV['REDIS_PREFIX'] ?? 'job_analyzer:',
        ],

        'cache' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_CACHE_DB'] ?? 1,
            'prefix' => $_ENV['REDIS_PREFIX'] ?? 'job_analyzer:cache:',
        ],

        'session' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['REDIS_PORT'] ?? 6379,
            'password' => $_ENV['REDIS_PASSWORD'] ?? null,
            'database' => $_ENV['REDIS_SESSION_DB'] ?? 2,
            'prefix' => $_ENV['REDIS_PREFIX'] ?? 'job_analyzer:session:',
        ],
    ],

    // Connection pool settings
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
        'connect_timeout' => 10.0,
        'wait_timeout' => 3.0,
        'heartbeat' => -1,
        'max_idle_time' => 60.0,
    ],

    // Query logging
    'log_queries' => $_ENV['DB_LOG_QUERIES'] ?? ($_ENV['APP_DEBUG'] === 'true'),
    'slow_query_threshold' => $_ENV['DB_SLOW_QUERY_THRESHOLD'] ?? 2000, // milliseconds

    // Backup settings
    'backup' => [
        'enabled' => $_ENV['DB_BACKUP_ENABLED'] ?? false,
        'path' => storage_path('backups'),
        'retention_days' => $_ENV['DB_BACKUP_RETENTION_DAYS'] ?? 30,
        'schedule' => $_ENV['DB_BACKUP_SCHEDULE'] ?? 'daily',
    ],
];

/**
 * Helper function to get database path for SQLite
 */
function database_path($path = '')
{
    return __DIR__ . '/../database/' . ltrim($path, '/');
}

/**
 * Helper function to get storage path
 */
function storage_path($path = '')
{
    return __DIR__ . '/../storage/' . ltrim($path, '/');
}

/**
 * Helper function to get environment variable with default
 */
function env($key, $default = null)
{
    return $_ENV[$key] ?? $default;
}