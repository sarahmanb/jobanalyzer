-- Database: job_analyzer
-- Create database first: CREATE DATABASE job_analyzer;

-- Users table (if you want multi-user support)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Jobs table
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 1, -- Foreign key to users table
    job_title VARCHAR(200) NOT NULL,
    company_name VARCHAR(150),
    job_description LONGTEXT NOT NULL,
    resume_path VARCHAR(500),
    cover_letter_path VARCHAR(500),
    
    -- System parsed job details
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    location VARCHAR(200),
    experience_required VARCHAR(100),
    education_required VARCHAR(200),
    employment_type ENUM('full-time', 'part-time', 'contract', 'internship', 'remote'),
    industry VARCHAR(100),
    job_level ENUM('entry', 'mid', 'senior', 'executive'),
    
    -- Parsed requirements (JSON format)
    hard_skills JSON,
    soft_skills JSON,
    languages_required JSON,
    certifications_required JSON,
    
    -- Analysis flags
    is_analyzed BOOLEAN DEFAULT FALSE,
    analysis_completed_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_job_title (job_title),
    INDEX idx_company (company_name),
    INDEX idx_created_at (created_at)
);

-- Analysis results table
CREATE TABLE job_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    
    -- Overall scores
    overall_score DECIMAL(5,2) DEFAULT 0,
    ats_score DECIMAL(5,2) DEFAULT 0,
    resume_match_score DECIMAL(5,2) DEFAULT 0,
    cover_letter_match_score DECIMAL(5,2) DEFAULT 0,
    
    -- Probability scores
    interview_probability DECIMAL(5,2) DEFAULT 0,
    job_securing_probability DECIMAL(5,2) DEFAULT 0,
    goodness_of_fit_score DECIMAL(5,2) DEFAULT 0,
    
    -- AI Assessment
    ai_recommendation ENUM('excellent_match', 'good_match', 'fair_match', 'poor_match', 'not_recommended'),
    ai_confidence_level DECIMAL(5,2) DEFAULT 0,
    
    -- Keyword analysis (JSON format)
    matching_keywords JSON,
    missing_keywords JSON,
    suggested_keywords JSON,
    keyword_density DECIMAL(5,2) DEFAULT 0,
    
    -- Section scores
    contact_info_score DECIMAL(5,2) DEFAULT 0,
    summary_score DECIMAL(5,2) DEFAULT 0,
    experience_score DECIMAL(5,2) DEFAULT 0,
    education_score DECIMAL(5,2) DEFAULT 0,
    skills_score DECIMAL(5,2) DEFAULT 0,
    achievements_score DECIMAL(5,2) DEFAULT 0,
    
    -- Detailed analysis (JSON format)
    skill_match_analysis JSON,
    experience_gap_analysis JSON,
    education_match_analysis JSON,
    
    -- Recommendations
    resume_recommendations JSON,
    cover_letter_recommendations JSON,
    general_recommendations JSON,
    
    -- Analysis metadata
    analysis_type ENUM('basic', 'ai_enhanced', 'combined') DEFAULT 'basic',
    analysis_duration DECIMAL(8,2), -- in seconds
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    INDEX idx_job_id (job_id),
    INDEX idx_overall_score (overall_score)
);

-- Analysis logs for tracking
CREATE TABLE analysis_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    analysis_id INT,
    log_level ENUM('info', 'warning', 'error') DEFAULT 'info',
    message TEXT,
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (analysis_id) REFERENCES job_analysis(id) ON DELETE SET NULL,
    INDEX idx_job_id (job_id),
    INDEX idx_created_at (created_at)
);

-- User preferences/settings
CREATE TABLE user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_setting (user_id, setting_key)
);

-- Create indexes for better performance
CREATE INDEX idx_jobs_analysis_status ON jobs(is_analyzed, created_at);
CREATE INDEX idx_analysis_scores ON job_analysis(overall_score, ats_score, resume_match_score);
CREATE FULLTEXT INDEX idx_job_description ON jobs(job_description);
CREATE FULLTEXT INDEX idx_job_title_company ON jobs(job_title, company_name);

-- Insert default user (for single-user setup)
INSERT INTO users (username, email, password_hash) VALUES 
('admin', 'admin@jobanalyzer.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Sample job data (optional)
INSERT INTO jobs (job_title, company_name, job_description, employment_type, job_level) VALUES 
(
    'Senior Software Engineer',
    'Tech Corp',
    'We are looking for a Senior Software Engineer with 5+ years of experience in PHP, MySQL, and JavaScript. The ideal candidate should have experience with modern frameworks like Laravel, React, and be familiar with cloud technologies.',
    'full-time',
    'senior'
),
(
    'Data Analyst',
    'Analytics Inc',
    'Join our data team as a Data Analyst. Requirements include Python, SQL, Excel proficiency, and experience with data visualization tools like Tableau or Power BI. Bachelor\'s degree in Statistics, Mathematics, or related field required.',
    'full-time',
    'mid'
);