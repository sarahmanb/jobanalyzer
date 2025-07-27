<?php
/**
 * Services Configuration
 * 
 * This file contains the configuration for various third-party services
 * and external integrations used by the Job Analyzer application.
 */

return [
    // AI and Machine Learning Services
    'ai_services' => [
        'openai' => [
            'api_key' => $_ENV['OPENAI_API_KEY'] ?? null,
            'organization' => $_ENV['OPENAI_ORGANIZATION'] ?? null,
            'base_url' => 'https://api.openai.com/v1',
            'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-3.5-turbo',
            'max_tokens' => (int) ($_ENV['OPENAI_MAX_TOKENS'] ?? 2000),
            'temperature' => (float) ($_ENV['OPENAI_TEMPERATURE'] ?? 0.7),
            'timeout' => 30,
        ],

        'anthropic' => [
            'api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? null,
            'base_url' => 'https://api.anthropic.com',
            'model' => $_ENV['ANTHROPIC_MODEL'] ?? 'claude-3-haiku-20240307',
            'max_tokens' => (int) ($_ENV['ANTHROPIC_MAX_TOKENS'] ?? 1000),
            'timeout' => 30,
        ],

        'huggingface' => [
            'api_key' => $_ENV['HUGGINGFACE_API_KEY'] ?? null,
            'base_url' => 'https://api-inference.huggingface.co',
            'models' => [
                'resume_parser' => 'microsoft/DialoGPT-medium',
                'skill_extractor' => 'sentence-transformers/all-MiniLM-L6-v2',
                'text_classifier' => 'cardiffnlp/twitter-roberta-base-sentiment-latest',
            ],
            'timeout' => 30,
        ],

        'local_ai' => [
            'enabled' => $_ENV['LOCAL_AI_ENABLED'] === 'true',
            'base_url' => $_ENV['LOCAL_AI_URL'] ?? 'http://localhost:8080',
            'api_key' => $_ENV['LOCAL_AI_API_KEY'] ?? null,
            'models' => [
                'text_analysis' => 'llama2',
                'embedding' => 'all-MiniLM-L6-v2',
            ],
            'timeout' => 60,
        ],
    ],

    // Document Processing Services
    'document_services' => [
        'pdf_parser' => [
            'engine' => $_ENV['PDF_PARSER_ENGINE'] ?? 'smalot', // smalot, spatie
            'ocr_enabled' => $_ENV['PDF_OCR_ENABLED'] === 'true',
            'tesseract_path' => $_ENV['TESSERACT_PATH'] ?? '/usr/bin/tesseract',
            'temp_dir' => storage_path('temp/pdf'),
            'max_file_size' => 50 * 1024 * 1024, // 50MB
            'timeout' => 120,
        ],

        'word_parser' => [
            'engine' => 'phpword',
            'temp_dir' => storage_path('temp/word'),
            'max_file_size' => 25 * 1024 * 1024, // 25MB
            'timeout' => 60,
        ],

        'text_extractor' => [
            'min_confidence' => 0.7,
            'clean_text' => true,
            'preserve_formatting' => false,
            'extract_images' => false,
        ],
    ],

    // Email Services
    'email_services' => [
        'smtp' => [
            'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
            'username' => $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        ],

        'sendgrid' => [
            'api_key' => $_ENV['SENDGRID_API_KEY'] ?? null,
            'from_email' => $_ENV['SENDGRID_FROM_EMAIL'] ?? null,
            'from_name' => $_ENV['SENDGRID_FROM_NAME'] ?? 'Job Analyzer',
        ],

        'mailgun' => [
            'domain' => $_ENV['MAILGUN_DOMAIN'] ?? null,
            'api_key' => $_ENV['MAILGUN_API_KEY'] ?? null,
            'endpoint' => $_ENV['MAILGUN_ENDPOINT'] ?? 'api.mailgun.net',
        ],

        'ses' => [
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
        ],
    ],

    // File Storage Services
    'storage_services' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],

        'aws_s3' => [
            'driver' => 's3',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
            'bucket' => $_ENV['AWS_BUCKET'] ?? null,
            'url' => $_ENV['AWS_URL'] ?? null,
            'endpoint' => $_ENV['AWS_ENDPOINT'] ?? null,
            'use_path_style_endpoint' => $_ENV['AWS_USE_PATH_STYLE_ENDPOINT'] === 'true',
        ],

        'google_cloud' => [
            'driver' => 'gcs',
            'project_id' => $_ENV['GOOGLE_CLOUD_PROJECT_ID'] ?? null,
            'key_file' => $_ENV['GOOGLE_CLOUD_KEY_FILE'] ?? null,
            'bucket' => $_ENV['GOOGLE_CLOUD_STORAGE_BUCKET'] ?? null,
            'path_prefix' => $_ENV['GOOGLE_CLOUD_STORAGE_PATH_PREFIX'] ?? null,
        ],

        'dropbox' => [
            'driver' => 'dropbox',
            'access_token' => $_ENV['DROPBOX_ACCESS_TOKEN'] ?? null,
        ],
    ],

    // Analytics and Monitoring Services
    'analytics_services' => [
        'google_analytics' => [
            'tracking_id' => $_ENV['GOOGLE_ANALYTICS_TRACKING_ID'] ?? null,
            'view_id' => $_ENV['GOOGLE_ANALYTICS_VIEW_ID'] ?? null,
            'service_account_json' => $_ENV['GOOGLE_ANALYTICS_SERVICE_ACCOUNT_JSON'] ?? null,
        ],

        'mixpanel' => [
            'token' => $_ENV['MIXPANEL_TOKEN'] ?? null,
            'secret' => $_ENV['MIXPANEL_SECRET'] ?? null,
        ],

        'segment' => [
            'write_key' => $_ENV['SEGMENT_WRITE_KEY'] ?? null,
        ],
    ],

    // Monitoring and Error Tracking
    'monitoring_services' => [
        'sentry' => [
            'dsn' => $_ENV['SENTRY_DSN'] ?? null,
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'release' => $_ENV['APP_VERSION'] ?? '1.0.0',
            'traces_sample_rate' => (float) ($_ENV['SENTRY_TRACES_SAMPLE_RATE'] ?? 0.1),
        ],

        'bugsnag' => [
            'api_key' => $_ENV['BUGSNAG_API_KEY'] ?? null,
            'app_version' => $_ENV['APP_VERSION'] ?? '1.0.0',
            'release_stage' => $_ENV['APP_ENV'] ?? 'production',
        ],

        'rollbar' => [
            'access_token' => $_ENV['ROLLBAR_TOKEN'] ?? null,
            'environment' => $_ENV['APP_ENV'] ?? 'production',
        ],
    ],

    // Search and Indexing Services
    'search_services' => [
        'elasticsearch' => [
            'enabled' => $_ENV['ELASTICSEARCH_ENABLED'] === 'true',
            'hosts' => explode(',', $_ENV['ELASTICSEARCH_HOSTS'] ?? 'localhost:9200'),
            'username' => $_ENV['ELASTICSEARCH_USERNAME'] ?? null,
            'password' => $_ENV['ELASTICSEARCH_PASSWORD'] ?? null,
            'index_prefix' => $_ENV['ELASTICSEARCH_INDEX_PREFIX'] ?? 'job_analyzer',
            'timeout' => 30,
        ],

        'algolia' => [
            'app_id' => $_ENV['ALGOLIA_APP_ID'] ?? null,
            'secret' => $_ENV['ALGOLIA_SECRET'] ?? null,
            'search_key' => $_ENV['ALGOLIA_SEARCH_KEY'] ?? null,
        ],

        'meilisearch' => [
            'host' => $_ENV['MEILISEARCH_HOST'] ?? 'http://localhost:7700',
            'key' => $_ENV['MEILISEARCH_KEY'] ?? null,
        ],
    ],

    // Notification Services
    'notification_services' => [
        'slack' => [
            'webhook_url' => $_ENV['SLACK_WEBHOOK_URL'] ?? null,
            'channel' => $_ENV['SLACK_CHANNEL'] ?? '#general',
            'username' => $_ENV['SLACK_USERNAME'] ?? 'Job Analyzer Bot',
            'icon' => $_ENV['SLACK_ICON'] ?? ':robot_face:',
        ],

        'discord' => [
            'webhook_url' => $_ENV['DISCORD_WEBHOOK_URL'] ?? null,
            'username' => $_ENV['DISCORD_USERNAME'] ?? 'Job Analyzer Bot',
        ],

        'teams' => [
            'webhook_url' => $_ENV['TEAMS_WEBHOOK_URL'] ?? null,
        ],

        'telegram' => [
            'bot_token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? null,
            'chat_id' => $_ENV['TELEGRAM_CHAT_ID'] ?? null,
        ],
    ],

    // API Integration Services
    'api_services' => [
        'job_boards' => [
            'indeed' => [
                'api_key' => $_ENV['INDEED_API_KEY'] ?? null,
                'base_url' => 'https://secure.indeed.com/v2',
                'rate_limit' => 100, // requests per day
            ],

            'linkedin' => [
                'client_id' => $_ENV['LINKEDIN_CLIENT_ID'] ?? null,
                'client_secret' => $_ENV['LINKEDIN_CLIENT_SECRET'] ?? null,
                'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'] ?? null,
                'scope' => 'r_liteprofile,r_emailaddress',
            ],

            'glassdoor' => [
                'partner_id' => $_ENV['GLASSDOOR_PARTNER_ID'] ?? null,
                'api_key' => $_ENV['GLASSDOOR_API_KEY'] ?? null,
                'base_url' => 'https://api.glassdoor.com/api/api.htm',
            ],
        ],

        'resume_databases' => [
            'monster' => [
                'api_key' => $_ENV['MONSTER_API_KEY'] ?? null,
                'base_url' => 'https://api.monster.com/v1',
            ],

            'careerbuilder' => [
                'developer_key' => $_ENV['CAREERBUILDER_DEVELOPER_KEY'] ?? null,
                'base_url' => 'https://api.careerbuilder.com/v1',
            ],

            'ziprecruiter' => [
                'api_key' => $_ENV['ZIPRECRUITER_API_KEY'] ?? null,
                'base_url' => 'https://api.ziprecruiter.com/jobs/v1',
            ],
        ],

        'skill_databases' => [
            'burning_glass' => [
                'client_id' => $_ENV['BURNING_GLASS_CLIENT_ID'] ?? null,
                'client_secret' => $_ENV['BURNING_GLASS_CLIENT_SECRET'] ?? null,
                'base_url' => 'https://skills.emsidata.com',
            ],

            'onet' => [
                'username' => $_ENV['ONET_USERNAME'] ?? null,
                'password' => $_ENV['ONET_PASSWORD'] ?? null,
                'base_url' => 'https://services.onetcenter.org/ws',
            ],
        ],
    ],

    // Background Processing Services
    'queue_services' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => $_ENV['REDIS_QUEUE'] ?? 'default',
            'retry_after' => 90,
            'block_for' => null,
        ],

        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'host' => $_ENV['RABBITMQ_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
            'user' => $_ENV['RABBITMQ_USER'] ?? 'guest',
            'password' => $_ENV['RABBITMQ_PASSWORD'] ?? 'guest',
            'vhost' => $_ENV['RABBITMQ_VHOST'] ?? '/',
            'queue' => $_ENV['RABBITMQ_QUEUE'] ?? 'job_analyzer',
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => $_ENV['AWS_ACCESS_KEY_ID'] ?? null,
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'] ?? null,
            'prefix' => $_ENV['SQS_PREFIX'] ?? 'https://sqs.us-east-1.amazonaws.com/your-account-id',
            'queue' => $_ENV['SQS_QUEUE'] ?? 'default',
            'suffix' => $_ENV['SQS_SUFFIX'] ?? null,
            'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
        ],
    ],

    // Security Services
    'security_services' => [
        'virus_scan' => [
            'enabled' => $_ENV['VIRUS_SCAN_ENABLED'] === 'true',
            'engine' => $_ENV['VIRUS_SCAN_ENGINE'] ?? 'clamav', // clamav, virustotal
            'clamav_socket' => $_ENV['CLAMAV_SOCKET'] ?? '/var/run/clamav/clamd.ctl',
            'virustotal_api_key' => $_ENV['VIRUSTOTAL_API_KEY'] ?? null,
            'quarantine_path' => storage_path('quarantine'),
        ],

        'recaptcha' => [
            'enabled' => $_ENV['RECAPTCHA_ENABLED'] === 'true',
            'site_key' => $_ENV['RECAPTCHA_SITE_KEY'] ?? null,
            'secret_key' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? null,
            'version' => $_ENV['RECAPTCHA_VERSION'] ?? 'v2',
        ],

        'csrf' => [
            'enabled' => true,
            'token_lifetime' => 3600, // 1 hour
            'regenerate_token' => true,
        ],
    ],

    // Backup Services
    'backup_services' => [
        'local' => [
            'enabled' => $_ENV['BACKUP_LOCAL_ENABLED'] === 'true',
            'path' => storage_path('backups'),
            'retention_days' => (int) ($_ENV['BACKUP_RETENTION_DAYS'] ?? 30),
            'compress' => true,
        ],

        's3' => [
            'enabled' => $_ENV['BACKUP_S3_ENABLED'] === 'true',
            'bucket' => $_ENV['BACKUP_S3_BUCKET'] ?? null,
            'path' => $_ENV['BACKUP_S3_PATH'] ?? 'backups',
            'retention_days' => (int) ($_ENV['BACKUP_S3_RETENTION_DAYS'] ?? 90),
        ],

        'google_drive' => [
            'enabled' => $_ENV['BACKUP_GDRIVE_ENABLED'] === 'true',
            'folder_id' => $_ENV['BACKUP_GDRIVE_FOLDER_ID'] ?? null,
            'service_account_json' => $_ENV['BACKUP_GDRIVE_SERVICE_ACCOUNT_JSON'] ?? null,
        ],
    ],

    // Development and Testing Services
    'development_services' => [
        'faker' => [
            'locale' => $_ENV['FAKER_LOCALE'] ?? 'en_US',
            'seed' => $_ENV['FAKER_SEED'] ?? null,
        ],

        'telescope' => [
            'enabled' => $_ENV['TELESCOPE_ENABLED'] === 'true',
            'path' => $_ENV['TELESCOPE_PATH'] ?? 'telescope',
            'storage' => [
                'driver' => $_ENV['TELESCOPE_DRIVER'] ?? 'file',
            ],
        ],

        'debugbar' => [
            'enabled' => $_ENV['DEBUGBAR_ENABLED'] === 'true' && $_ENV['APP_DEBUG'] === 'true',
            'collectors' => [
                'phpinfo' => true,
                'messages' => true,
                'time' => true,
                'memory' => true,
                'exceptions' => true,
                'log' => true,
                'db' => true,
                'views' => true,
                'route' => true,
                'auth' => true,
                'gate' => true,
                'session' => true,
                'symfony_request' => true,
                'mail' => true,
            ],
        ],
    ],

    // Performance and Optimization Services
    'optimization_services' => [
        'cdn' => [
            'enabled' => $_ENV['CDN_ENABLED'] === 'true',
            'url' => $_ENV['CDN_URL'] ?? null,
            'pull_zone' => $_ENV['CDN_PULL_ZONE'] ?? null,
            'api_key' => $_ENV['CDN_API_KEY'] ?? null,
        ],

        'image_optimization' => [
            'enabled' => $_ENV['IMAGE_OPTIMIZATION_ENABLED'] === 'true',
            'service' => $_ENV['IMAGE_OPTIMIZATION_SERVICE'] ?? 'intervention', // intervention, imagemin, tinify
            'tinify_api_key' => $_ENV['TINIFY_API_KEY'] ?? null,
            'quality' => (int) ($_ENV['IMAGE_QUALITY'] ?? 85),
            'formats' => ['webp', 'jpg', 'png'],
        ],

        'minification' => [
            'enabled' => $_ENV['MINIFICATION_ENABLED'] === 'true',
            'css' => true,
            'js' => true,
            'html' => $_ENV['APP_ENV'] === 'production',
        ],
    ],

    // Integration Testing Services
    'testing_services' => [
        'browserstack' => [
            'username' => $_ENV['BROWSERSTACK_USERNAME'] ?? null,
            'access_key' => $_ENV['BROWSERSTACK_ACCESS_KEY'] ?? null,
        ],

        'sauce_labs' => [
            'username' => $_ENV['SAUCE_LABS_USERNAME'] ?? null,
            'access_key' => $_ENV['SAUCE_LABS_ACCESS_KEY'] ?? null,
        ],

        'selenium' => [
            'host' => $_ENV['SELENIUM_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['SELENIUM_PORT'] ?? 4444),
            'browser' => $_ENV['SELENIUM_BROWSER'] ?? 'chrome',
        ],
    ],

    // External API Rate Limits
    'rate_limits' => [
        'openai' => [
            'requests_per_minute' => 50,
            'tokens_per_minute' => 40000,
        ],
        'anthropic' => [
            'requests_per_minute' => 60,
            'tokens_per_minute' => 50000,
        ],
        'indeed' => [
            'requests_per_day' => 1000,
        ],
        'linkedin' => [
            'requests_per_day' => 500,
        ],
    ],

    // Service Health Checks
    'health_checks' => [
        'enabled' => $_ENV['HEALTH_CHECKS_ENABLED'] === 'true',
        'endpoints' => [
            'database' => true,
            'redis' => $_ENV['REDIS_HOST'] !== null,
            'elasticsearch' => $_ENV['ELASTICSEARCH_ENABLED'] === 'true',
            'ai_service' => $_ENV['AI_SERVICE_ENABLED'] === 'true',
            'python_service' => $_ENV['PYTHON_SERVICE_AUTO_START'] === 'true',
            'mail' => $_ENV['MAIL_DRIVER'] !== null,
            'storage' => true,
        ],
        'timeout' => 5, // seconds
        'cache_ttl' => 60, // cache health check results for 60 seconds
    ],

    // Service Fallbacks
    'fallbacks' => [
        'ai_analysis' => [
            'primary' => 'openai',
            'secondary' => 'anthropic',
            'tertiary' => 'local_ai',
        ],
        'email' => [
            'primary' => 'smtp',
            'secondary' => 'sendgrid',
            'tertiary' => 'log',
        ],
        'storage' => [
            'primary' => 'local',
            'secondary' => 'aws_s3',
        ],
        'search' => [
            'primary' => 'elasticsearch',
            'secondary' => 'database',
        ],
    ],
];

/**
 * Helper function to get storage path
 */
function storage_path($path = '')
{
    return __DIR__ . '/../storage/' . ltrim($path, '/');
}