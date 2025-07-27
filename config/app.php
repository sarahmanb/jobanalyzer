<?php
/**
 * Application Configuration
 * 
 * This file contains the core application settings for the Job Analyzer.
 * It defines various application-wide configurations and constants.
 */

return [
    // Application Information
    'name' => $_ENV['APP_NAME'] ?? 'Job Analyzer Pro',
    'version' => '1.0.0',
    'description' => 'An intelligent job search companion that helps you tailor your resume and cover letter as per keywords in job description.',
    'author' => 'S A Rehman Bukhari',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',

    // Environment Settings
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => $_ENV['APP_DEBUG'] === 'true',
    'testing' => $_ENV['APP_ENV'] === 'testing',

    // Timezone and Locale
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    'locale' => $_ENV['APP_LOCALE'] ?? 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',

    // Security Settings
    'key' => $_ENV['APP_KEY'] ?? 'your-app-key-here',
    'cipher' => 'AES-256-CBC',
    'jwt_secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-this',
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 1440), // minutes
    'bcrypt_rounds' => (int) ($_ENV['BCRYPT_ROUNDS'] ?? 12),

    // File Upload Settings
    'upload' => [
        'max_size' => (int) ($_ENV['MAX_UPLOAD_SIZE'] ?? 10485760), // 10MB in bytes
        'allowed_resume_types' => explode(',', $_ENV['ALLOWED_RESUME_TYPES'] ?? 'pdf,doc,docx'),
        'allowed_cover_letter_types' => explode(',', $_ENV['ALLOWED_COVER_LETTER_TYPES'] ?? 'pdf,doc,docx'),
        'path' => $_ENV['UPLOAD_PATH'] ?? 'public/uploads',
        'resume_path' => 'uploads/resumes',
        'cover_letter_path' => 'uploads/cover_letters',
        'temp_path' => 'uploads/temp',
        'max_files_per_job' => 5,
        'scan_uploads' => true, // Virus scan uploads if available
    ],

    // Analysis Configuration
    'analysis' => [
        'default_type' => $_ENV['DEFAULT_ANALYSIS_TYPE'] ?? 'combined',
        'types' => ['basic', 'ai_enhanced', 'combined'],
        'enable_detailed_logging' => $_ENV['ENABLE_DETAILED_LOGGING'] === 'true',
        'cache_results' => $_ENV['CACHE_ANALYSIS_RESULTS'] === 'true',
        'cache_duration' => 3600, // 1 hour in seconds
        'max_concurrent_analyses' => 3,
        'timeout' => (int) ($_ENV['ANALYSIS_TIMEOUT'] ?? 300), // 5 minutes
        
        // Scoring thresholds
        'thresholds' => [
            'min_match_score' => (int) ($_ENV['MIN_MATCH_SCORE'] ?? 60),
            'excellent_match' => (int) ($_ENV['EXCELLENT_MATCH_THRESHOLD'] ?? 90),
            'good_match' => (int) ($_ENV['GOOD_MATCH_THRESHOLD'] ?? 75),
            'fair_match' => (int) ($_ENV['FAIR_MATCH_THRESHOLD'] ?? 60),
        ],
        
        // Keywords settings
        'min_keywords' => 5,
        'max_keywords' => 50,
        'keyword_weight' => 0.7,
        'context_weight' => 0.3,
    ],

    // AI Service Configuration
    'ai' => [
        'enabled' => $_ENV['AI_SERVICE_ENABLED'] === 'true',
        'service_url' => $_ENV['AI_SERVICE_URL'] ?? 'http://localhost:5000',
        'timeout' => (int) ($_ENV['AI_SERVICE_TIMEOUT'] ?? 30),
        'api_key' => $_ENV['AI_API_KEY'] ?? null,
        'max_retries' => 3,
        'retry_delay' => 1000, // milliseconds
        'models' => [
            'text_analysis' => 'gpt-3.5-turbo',
            'resume_parsing' => 'claude-3-haiku',
            'job_matching' => 'gpt-4-turbo',
        ],
    ],

    // Python Service Configuration
    'python' => [
        'service_path' => $_ENV['PYTHON_SERVICE_PATH'] ?? 'python_services/',
        'executable' => $_ENV['PYTHON_EXECUTABLE'] ?? 'python',
        'auto_start' => $_ENV['PYTHON_SERVICE_AUTO_START'] === 'true',
        'port' => (int) ($_ENV['PYTHON_SERVICE_PORT'] ?? 5000),
        'host' => $_ENV['PYTHON_SERVICE_HOST'] ?? '127.0.0.1',
        'max_workers' => (int) ($_ENV['PYTHON_MAX_WORKERS'] ?? 4),
    ],

    // Logging Configuration
    'logging' => [
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'single',
        'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        'path' => $_ENV['LOG_PATH'] ?? 'storage/logs/',
        'max_files' => 30,
        'max_size' => '100MB',
        'channels' => [
            'single' => [
                'driver' => 'single',
                'path' => storage_path('logs/job-analyzer.log'),
                'level' => 'debug',
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => storage_path('logs/job-analyzer.log'),
                'level' => 'debug',
                'days' => 14,
            ],
            'stderr' => [
                'driver' => 'monolog',
                'handler' => 'Monolog\Handler\StreamHandler',
                'formatter' => 'Monolog\Formatter\LineFormatter',
                'with' => [
                    'stream' => 'php://stderr',
                ],
            ],
        ],
    ],

    // Cache Configuration
    'cache' => [
        'default' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'prefix' => $_ENV['CACHE_PREFIX'] ?? 'job_analyzer',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => storage_path('cache'),
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'cache',
            ],
            'array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
        ],
        'ttl' => 3600, // Default cache TTL in seconds
    ],

    // Queue Configuration
    'queue' => [
        'default' => $_ENV['QUEUE_CONNECTION'] ?? 'sync',
        'connections' => [
            'sync' => [
                'driver' => 'sync',
            ],
            'database' => [
                'driver' => 'database',
                'table' => 'jobs_queue',
                'queue' => 'default',
                'retry_after' => 90,
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
                'queue' => 'default',
                'retry_after' => 90,
                'block_for' => null,
            ],
        ],
        'failed' => [
            'driver' => 'database',
            'table' => 'failed_jobs',
        ],
    ],

    // Mail Configuration
    'mail' => [
        'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
        'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@jobanalyzer.local',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'Job Analyzer',
        ],
        'sendmail' => '/usr/sbin/sendmail -bs',
        'markdown' => [
            'theme' => 'default',
            'paths' => [
                resource_path('views/vendor/mail'),
            ],
        ],
    ],

    // PDF Generation Settings
    'pdf' => [
        'engine' => 'dompdf', // dompdf, tcpdf, mpdf
        'options' => [
            'dpi' => 150,
            'default_font' => 'Arial',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'orientation' => 'portrait',
            'paper_size' => 'A4',
        ],
    ],

    // Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'analysis_requests' => [
            'max_attempts' => 10,
            'decay_minutes' => 60,
        ],
        'api_requests' => [
            'max_attempts' => 100,
            'decay_minutes' => 60,
        ],
        'file_uploads' => [
            'max_attempts' => 20,
            'decay_minutes' => 60,
        ],
    ],

    // Feature Flags
    'features' => [
        'ai_analysis' => $_ENV['AI_SERVICE_ENABLED'] === 'true',
        'bulk_analysis' => true,
        'advanced_filtering' => true,
        'export_data' => true,
        'email_notifications' => $_ENV['MAIL_DRIVER'] !== null,
        'user_registration' => false, // Single user mode for now
        'api_access' => true,
        'file_virus_scan' => false,
        'backup_automation' => false,
    ],

    // API Configuration
    'api' => [
        'version' => 'v1',
        'prefix' => 'api/v1',
        'rate_limit' => 100, // requests per hour
        'require_auth' => false, // Set to true for production
        'cors' => [
            'enabled' => true,
            'origins' => ['*'],
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization'],
        ],
    ],

    // Performance Settings
    'performance' => [
        'enable_opcache' => true,
        'enable_compression' => true,
        'minify_html' => $_ENV['APP_ENV'] === 'production',
        'lazy_loading' => true,
        'database_query_cache' => true,
        'static_file_cache' => 86400, // 24 hours
    ],

    // Maintenance Mode
    'maintenance' => [
        'enabled' => $_ENV['MAINTENANCE_MODE'] === 'true',
        'message' => 'We are currently performing scheduled maintenance. Please try again later.',
        'retry_after' => 3600, // seconds
        'allowed_ips' => explode(',', $_ENV['MAINTENANCE_ALLOWED_IPS'] ?? '127.0.0.1'),
    ],

    // Health Check Settings
    'health_check' => [
        'enabled' => true,
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => true,
            'ai_service' => true,
            'python_service' => true,
            'disk_space' => true,
            'memory_usage' => true,
        ],
        'thresholds' => [
            'disk_space_warning' => 85, // percentage
            'memory_usage_warning' => 80, // percentage
            'response_time_warning' => 2000, // milliseconds
        ],
    ],
];

/**
 * Helper function to get resource path
 */
function resource_path($path = '')
{
    return __DIR__ . '/../resources/' . ltrim($path, '/');
}

/**
 * Helper function to get storage path
 */
function storage_path($path = '')
{
    return __DIR__ . '/../storage/' . ltrim($path, '/');
}