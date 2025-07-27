# .env file - Main configuration
APP_NAME="Job Analyzer Pro"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/jobanalyzer

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=job_analyzer
DB_USERNAME=root
DB_PASSWORD=

# File Upload Settings
MAX_UPLOAD_SIZE=10485760
ALLOWED_RESUME_TYPES=pdf,doc,docx
ALLOWED_COVER_LETTER_TYPES=pdf,doc,docx
UPLOAD_PATH=public/uploads

# AI Service Configuration
AI_SERVICE_ENABLED=true
AI_SERVICE_URL=http://localhost:5000
AI_SERVICE_TIMEOUT=30

# Python Service Configuration
PYTHON_SERVICE_PATH=python_services/
PYTHON_EXECUTABLE=python
PYTHON_SERVICE_AUTO_START=true

# Analysis Configuration
DEFAULT_ANALYSIS_TYPE=combined
ENABLE_DETAILED_LOGGING=true
CACHE_ANALYSIS_RESULTS=true

# Security
JWT_SECRET=your-super-secret-jwt-key-change-this
SESSION_LIFETIME=1440
BCRYPT_ROUNDS=12

# Email Configuration (for notifications)
MAIL_DRIVER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@jobanalyzer.local
MAIL_FROM_NAME="Job Analyzer"

# Logging
LOG_CHANNEL=single
LOG_LEVEL=debug
LOG_PATH=storage/logs/

# Cache Configuration
CACHE_DRIVER=file
CACHE_PREFIX=job_analyzer

# Analysis Thresholds
MIN_MATCH_SCORE=60
EXCELLENT_MATCH_THRESHOLD=90
GOOD_MATCH_THRESHOLD=75
FAIR_MATCH_THRESHOLD=60