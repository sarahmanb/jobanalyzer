<?php
// app/Services/JobAnalyzerService.php

namespace App\Services;

use App\Models\Job;
use App\Models\JobAnalysis;
use App\Models\AnalysisLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class JobAnalyzerService
{
    private $httpClient;
    private $aiServiceUrl;
    private $pdfParser;
    private $aiAnalysis;
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => (int)($_ENV['AI_SERVICE_TIMEOUT'] ?? 30),
            'connect_timeout' => 10
        ]);
        
        $this->aiServiceUrl = $_ENV['AI_SERVICE_URL'] ?? 'http://localhost:5000';
        $this->pdfParser = new PDFParserService();
        $this->aiAnalysis = new AIAnalysisService();
    }

    public function analyzeJob(Job $job): array
    {
        $startTime = microtime(true);
        
        try {
            $this->logAnalysis($job->id, 'info', 'Starting job analysis', [
                'job_title' => $job->job_title,
                'has_resume' => (bool)$job->resume_path,
                'has_cover_letter' => (bool)$job->cover_letter_path
            ]);

            // Extract text from uploaded files
            $resumeText = '';
            $coverLetterText = '';

            if ($job->hasResume()) {
                $resumeText = $this->pdfParser->extractText(public_path($job->resume_path));
                $this->logAnalysis($job->id, 'info', 'Resume text extracted', [
                    'word_count' => str_word_count($resumeText)
                ]);
            }

            if ($job->hasCoverLetter()) {
                $coverLetterText = $this->pdfParser->extractText(public_path($job->cover_letter_path));
                $this->logAnalysis($job->id, 'info', 'Cover letter text extracted', [
                    'word_count' => str_word_count($coverLetterText)
                ]);
            }

            // Perform comprehensive analysis
            $analysisResults = $this->performComprehensiveAnalysis(
                $job->job_description,
                $resumeText,
                $coverLetterText,
                $job
            );

            // Save analysis results
            $analysis = $this->saveAnalysisResults($job, $analysisResults, microtime(true) - $startTime);

            // Mark job as analyzed
            $job->markAsAnalyzed();

            $this->logAnalysis($job->id, 'info', 'Analysis completed successfully', [
                'overall_score' => $analysis->overall_score,
                'analysis_type' => $analysis->analysis_type,
                'duration' => $analysis->analysis_duration
            ]);

            return $analysisResults;

        } catch (\Exception $e) {
            $this->logAnalysis($job->id, 'error', 'Analysis failed', [
                'error' => $e->getMessage(),
                'duration' => microtime(true) - $startTime
            ]);
            
            throw $e;
        }
    }

    public function queueAnalysis(int $jobId): void
    {
        // For now, perform immediate analysis
        // In production, you might want to use a queue system
        $job = Job::find($jobId);
        if ($job) {
            $this->analyzeJob($job);
        }
    }

    public function parseJobDescription(string $jobDescription): array
    {
        $parsed = [
            'location' => $this->extractLocation($jobDescription),
            'salary_min' => null,
            'salary_max' => null,
            'experience_required' => $this->extractExperience($jobDescription),
            'education_required' => $this->extractEducation($jobDescription),
            'industry' => $this->extractIndustry($jobDescription),
            'hard_skills' => $this->extractHardSkills($jobDescription),
            'soft_skills' => $this->extractSoftSkills($jobDescription),
            'languages_required' => $this->extractLanguages($jobDescription),
            'certifications_required' => $this->extractCertifications($jobDescription)
        ];

        // Extract salary
        $salaryRange = $this->extractSalary($jobDescription);
        if ($salaryRange) {
            $parsed['salary_min'] = $salaryRange['min'];
            $parsed['salary_max'] = $salaryRange['max'];
        }

        return $parsed;
    }

    private function performComprehensiveAnalysis(string $jobDescription, string $resumeText, string $coverLetterText, Job $job): array
    {
        // 1. Get basic analysis (internal logic)
        $basicAnalysis = $this->performBasicAnalysis($jobDescription, $resumeText, $coverLetterText);
        
        // 2. Try AI-enhanced analysis
        $aiAnalysis = null;
        if ($_ENV['AI_SERVICE_ENABLED'] === 'true') {
            try {
                $aiAnalysis = $this->aiAnalysis->analyzeWithAI($jobDescription, $resumeText, $coverLetterText);
            } catch (\Exception $e) {
                $this->logAnalysis($job->id, 'warning', 'AI analysis failed, using basic analysis', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 3. Combine analyses
        if ($aiAnalysis) {
            return $this->combineAnalyses($basicAnalysis, $aiAnalysis);
        }

        return $this->enhanceBasicAnalysis($basicAnalysis);
    }

    private function performBasicAnalysis(string $jobDescription, string $resumeText, string $coverLetterText): array
    {
        $jobDesc = strtolower($jobDescription);
        $resume = strtolower($resumeText);
        $coverLetter = strtolower($coverLetterText);

        $analysis = [
            'overall_score' => 0,
            'ats_score' => 0,
            'resume_match_score' => 0,
            'cover_letter_match_score' => 0,
            'section_scores' => [
                'contact_info' => 0,
                'summary' => 0,
                'experience' => 0,
                'education' => 0,
                'skills' => 0,
                'achievements' => 0
            ],
            'keyword_analysis' => [
                'matching_keywords' => [],
                'missing_keywords' => [],
                'suggested_keywords' => []
            ],
            'recommendations' => [],
            'analysis_type' => 'basic'
        ];

        // Extract job keywords
        $jobKeywords = $this->extractJobKeywords($jobDesc);
        
        // Analyze resume sections
        if (!empty($resumeText)) {
            $analysis['section_scores'] = $this->analyzeResumeSections($resume);
            $analysis['resume_match_score'] = $this->calculateResumeMatch($jobKeywords, $resume);
        }

        // Analyze cover letter
        if (!empty($coverLetterText)) {
            $analysis['cover_letter_match_score'] = $this->calculateCoverLetterMatch($jobKeywords, $coverLetter);
        }

        // Keyword analysis
        $keywordAnalysis = $this->performKeywordAnalysis($jobKeywords, $resume, $coverLetter);
        $analysis['keyword_analysis'] = $keywordAnalysis;

        // Calculate scores
        $analysis['ats_score'] = $this->calculateATSScore($resume);
        $analysis['overall_score'] = $this->calculateOverallScore($analysis);

        // Generate recommendations
        $analysis['recommendations'] = $this->generateBasicRecommendations($analysis);

        return $analysis;
    }

    private function combineAnalyses(array $basic, array $ai): array
    {
        return [
            'overall_score' => round(($basic['overall_score'] * 0.3) + ($ai['overall_score'] * 0.7)),
            'ats_score' => $ai['ats_score'] ?? $basic['ats_score'],
            'resume_match_score' => round(($basic['resume_match_score'] * 0.3) + ($ai['resume_match_score'] * 0.7)),
            'cover_letter_match_score' => round(($basic['cover_letter_match_score'] * 0.3) + ($ai['cover_letter_match_score'] * 0.7)),
            'interview_probability' => $ai['interview_probability'] ?? $this->calculateInterviewProbability($basic['overall_score']),
            'job_securing_probability' => $ai['job_securing_probability'] ?? $this->calculateJobProbability($basic['overall_score']),
            'goodness_of_fit_score' => $ai['goodness_of_fit_score'] ?? $basic['overall_score'],
            'ai_recommendation' => $ai['ai_recommendation'] ?? $this->determineRecommendation($basic['overall_score']),
            'ai_confidence_level' => $ai['ai_confidence_level'] ?? 75,
            'section_scores' => $this->mergeSectionScores($basic['section_scores'], $ai['section_scores'] ?? []),
            'keyword_analysis' => array_merge($basic['keyword_analysis'], $ai['keyword_analysis'] ?? []),
            'skill_match_analysis' => $ai['skill_match_analysis'] ?? [],
            'experience_gap_analysis' => $ai['experience_gap_analysis'] ?? [],
            'education_match_analysis' => $ai['education_match_analysis'] ?? [],
            'recommendations' => array_merge(
                $ai['recommendations'] ?? [],
                array_slice($basic['recommendations'], 0, 3)
            ),
            'analysis_type' => 'combined'
        ];
    }

    private function enhanceBasicAnalysis(array $basic): array
    {
        $basic['interview_probability'] = $this->calculateInterviewProbability($basic['overall_score']);
        $basic['job_securing_probability'] = $this->calculateJobProbability($basic['overall_score']);
        $basic['goodness_of_fit_score'] = $basic['overall_score'];
        $basic['ai_recommendation'] = $this->determineRecommendation($basic['overall_score']);
        $basic['ai_confidence_level'] = 60; // Lower confidence for basic analysis
        $basic['skill_match_analysis'] = [];
        $basic['experience_gap_analysis'] = [];
        $basic['education_match_analysis'] = [];
        
        return $basic;
    }

    private function saveAnalysisResults(Job $job, array $results, float $duration): JobAnalysis
    {
        // Delete existing analysis
        if ($job->analysis) {
            $job->analysis->delete();
        }

        return JobAnalysis::create([
            'job_id' => $job->id,
            'overall_score' => $results['overall_score'] ?? 0,
            'ats_score' => $results['ats_score'] ?? 0,
            'resume_match_score' => $results['resume_match_score'] ?? 0,
            'cover_letter_match_score' => $results['cover_letter_match_score'] ?? 0,
            'interview_probability' => $results['interview_probability'] ?? 0,
            'job_securing_probability' => $results['job_securing_probability'] ?? 0,
            'goodness_of_fit_score' => $results['goodness_of_fit_score'] ?? 0,
            'ai_recommendation' => $results['ai_recommendation'] ?? 'fair_match',
            'ai_confidence_level' => $results['ai_confidence_level'] ?? 60,
            'matching_keywords' => $results['keyword_analysis']['matching_keywords'] ?? [],
            'missing_keywords' => $results['keyword_analysis']['missing_keywords'] ?? [],
            'suggested_keywords' => $results['keyword_analysis']['suggested_keywords'] ?? [],
            'keyword_density' => $results['keyword_analysis']['density'] ?? 0,
            'contact_info_score' => $results['section_scores']['contact_info'] ?? 0,
            'summary_score' => $results['section_scores']['summary'] ?? 0,
            'experience_score' => $results['section_scores']['experience'] ?? 0,
            'education_score' => $results['section_scores']['education'] ?? 0,
            'skills_score' => $results['section_scores']['skills'] ?? 0,
            'achievements_score' => $results['section_scores']['achievements'] ?? 0,
            'skill_match_analysis' => $results['skill_match_analysis'] ?? [],
            'experience_gap_analysis' => $results['experience_gap_analysis'] ?? [],
            'education_match_analysis' => $results['education_match_analysis'] ?? [],
            'resume_recommendations' => $this->filterRecommendationsByType($results['recommendations'] ?? [], 'resume'),
            'cover_letter_recommendations' => $this->filterRecommendationsByType($results['recommendations'] ?? [], 'cover_letter'),
            'general_recommendations' => $this->filterRecommendationsByType($results['recommendations'] ?? [], 'general'),
            'analysis_type' => $results['analysis_type'] ?? 'basic',
            'analysis_duration' => $duration
        ]);
    }

    // Basic analysis helper methods
    private function extractJobKeywords(string $jobDescription): array
    {
        // Extract technical skills, tools, technologies
        $patterns = [
            'technologies' => '/\b(php|python|java|javascript|react|angular|vue|node|mysql|postgresql|mongodb|aws|azure|docker|kubernetes|git|linux|windows|macos)\b/i',
            'skills' => '/\b(leadership|management|communication|teamwork|problem.solving|analytical|creative|strategic|planning|organization)\b/i',
            'tools' => '/\b(photoshop|illustrator|figma|sketch|tableau|powerbi|excel|word|powerpoint|jira|confluence|slack|teams)\b/i'
        ];

        $keywords = [];
        foreach ($patterns as $category => $pattern) {
            preg_match_all($pattern, $jobDescription, $matches);
            $keywords[$category] = array_unique(array_map('strtolower', $matches[0]));
        }

        return $keywords;
    }

    private function analyzeResumeSections(string $resume): array
    {
        $sections = [
            'contact_info' => $this->analyzeContactInfo($resume),
            'summary' => $this->analyzeSummarySection($resume),
            'experience' => $this->analyzeExperienceSection($resume),
            'education' => $this->analyzeEducationSection($resume),
            'skills' => $this->analyzeSkillsSection($resume),
            'achievements' => $this->analyzeAchievementsSection($resume)
        ];

        return $sections;
    }

    private function analyzeContactInfo(string $resume): float
    {
        $score = 0;
        
        // Email
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $resume)) {
            $score += 40;
        }
        
        // Phone
        if (preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}/', $resume)) {
            $score += 30;
        }
        
        // Address/Location
        if (preg_match('/(street|road|avenue|city|state|country|karachi|lahore|islamabad)/', $resume)) {
            $score += 30;
        }
        
        return min(100, $score);
    }

    private function analyzeSummarySection(string $resume): float
    {
        $summaryKeywords = ['summary', 'profile', 'objective', 'overview', 'about'];
        
        foreach ($summaryKeywords as $keyword) {
            if (strpos($resume, $keyword) !== false) {
                return 85;
            }
        }
        
        return 0;
    }

    private function analyzeExperienceSection(string $resume): float
    {
        $score = 0;
        $experienceKeywords = ['experience', 'employment', 'work', 'career', 'position'];
        
        // Check for experience section
        foreach ($experienceKeywords as $keyword) {
            if (strpos($resume, $keyword) !== false) {
                $score += 20;
                break;
            }
        }
        
        // Check for job titles
        $jobTitles = ['manager', 'director', 'analyst', 'developer', 'engineer', 'consultant', 'specialist'];
        $titleCount = 0;
        foreach ($jobTitles as $title) {
            $titleCount += substr_count($resume, $title);
        }
        $score += min(40, $titleCount * 10);
        
        // Check for dates (experience timeline)
        preg_match_all('/\b(19|20)\d{2}\b/', $resume, $matches);
        $score += min(40, count($matches[0]) * 5);
        
        return min(100, $score);
    }

    private function analyzeEducationSection(string $resume): float
    {
        $educationKeywords = ['education', 'degree', 'university', 'college', 'school', 'bachelor', 'master', 'phd', 'diploma'];
        
        $score = 0;
        foreach ($educationKeywords as $keyword) {
            if (strpos($resume, $keyword) !== false) {
                $score += 15;
            }
        }
        
        return min(100, $score);
    }

    private function analyzeSkillsSection(string $resume): float
    {
        $skillsKeywords = ['skills', 'competencies', 'expertise', 'proficient', 'technologies', 'tools'];
        
        $score = 0;
        foreach ($skillsKeywords as $keyword) {
            if (strpos($resume, $keyword) !== false) {
                $score += 20;
            }
        }
        
        return min(100, $score);
    }

    private function analyzeAchievementsSection(string $resume): float
    {
        $achievementKeywords = ['achievement', 'award', 'recognition', 'accomplished', 'improved', 'increased', 'reduced', 'led', 'managed'];
        
        $score = 0;
        foreach ($achievementKeywords as $keyword) {
            $score += substr_count($resume, $keyword) * 10;
        }
        
        // Look for quantified achievements (numbers, percentages)
        preg_match_all('/\b\d+%|\b\d+\+|\b\d{1,3}(,\d{3})*|\$\d+/', $resume, $numbers);
        $score += count($numbers[0]) * 15;
        
        return min(100, $score);
    }

    private function calculateResumeMatch(array $jobKeywords, string $resume): float
    {
        $totalKeywords = 0;
        $matchedKeywords = 0;
        
        foreach ($jobKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $totalKeywords++;
                if (strpos($resume, $keyword) !== false) {
                    $matchedKeywords++;
                }
            }
        }
        
        return $totalKeywords > 0 ? round(($matchedKeywords / $totalKeywords) * 100, 1) : 0;
    }

    private function calculateCoverLetterMatch(array $jobKeywords, string $coverLetter): float
    {
        if (empty($coverLetter)) {
            return 0;
        }
        
        $totalKeywords = 0;
        $matchedKeywords = 0;
        
        foreach ($jobKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $totalKeywords++;
                if (strpos($coverLetter, $keyword) !== false) {
                    $matchedKeywords++;
                }
            }
        }
        
        return $totalKeywords > 0 ? round(($matchedKeywords / $totalKeywords) * 100, 1) : 0;
    }

    private function performKeywordAnalysis(array $jobKeywords, string $resume, string $coverLetter): array
    {
        $matching = [];
        $missing = [];
        $suggested = [];
        
        foreach ($jobKeywords as $category => $keywords) {
            foreach ($keywords as $keyword) {
                $inResume = strpos($resume, $keyword) !== false;
                $inCoverLetter = strpos($coverLetter, $keyword) !== false;
                
                if ($inResume || $inCoverLetter) {
                    $matching[] = $keyword;
                } else {
                    $missing[] = $keyword;
                }
            }
        }
        
        // Generate suggested keywords based on missing ones
        $suggested = array_slice($missing, 0, 10);
        
        return [
            'matching_keywords' => array_unique($matching),
            'missing_keywords' => array_unique($missing),
            'suggested_keywords' => $suggested,
            'density' => count($matching) > 0 ? round((count($matching) / (count($matching) + count($missing))) * 100, 1) : 0
        ];
    }

    private function calculateATSScore(string $resume): float
    {
        $score = 100;
        
        // Penalize for formatting issues
        if (strpos($resume, '�') !== false) {
            $score -= 20; // Bad character encoding
        }
        
        // Check for standard sections
        $requiredSections = ['experience', 'education', 'skills'];
        foreach ($requiredSections as $section) {
            if (strpos($resume, $section) === false) {
                $score -= 15;
            }
        }
        
        // Word count check
        $wordCount = str_word_count($resume);
        if ($wordCount < 200) {
            $score -= 25;
        } elseif ($wordCount > 1000) {
            $score -= 10;
        }
        
        return max(0, $score);
    }

    private function calculateOverallScore(array $analysis): float
    {
        $sectionScores = array_values($analysis['section_scores']);
        $avgSectionScore = array_sum($sectionScores) / count($sectionScores);
        
        $resumeMatchWeight = 0.3;
        $coverLetterMatchWeight = 0.2;
        $sectionWeight = 0.5;
        
        $overallScore = ($analysis['resume_match_score'] * $resumeMatchWeight) +
                       ($analysis['cover_letter_match_score'] * $coverLetterMatchWeight) +
                       ($avgSectionScore * $sectionWeight);
        
        return round($overallScore, 1);
    }

    private function generateBasicRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        // Section-based recommendations
        foreach ($analysis['section_scores'] as $section => $score) {
            if ($score < 50) {
                $recommendations[] = "Improve your " . str_replace('_', ' ', $section) . " section";
            }
        }
        
        // Keyword recommendations
        if (count($analysis['keyword_analysis']['missing_keywords']) > 5) {
            $recommendations[] = "Include more relevant keywords from the job description";
        }
        
        // Match score recommendations
        if ($analysis['resume_match_score'] < 60) {
            $recommendations[] = "Better align your resume with the job requirements";
        }
        
        if ($analysis['cover_letter_match_score'] < 60 && $analysis['cover_letter_match_score'] > 0) {
            $recommendations[] = "Customize your cover letter to better match the job";
        }
        
        return array_slice($recommendations, 0, 5);
    }

    // Utility methods for scoring
    private function calculateInterviewProbability(float $overallScore): float
    {
        // Simple formula: higher overall score = higher interview probability
        return min(100, max(0, ($overallScore - 20) * 1.2));
    }

    private function calculateJobProbability(float $overallScore): float
    {
        // Job probability is typically lower than interview probability
        return min(100, max(0, ($overallScore - 30) * 1.0));
    }

    private function determineRecommendation(float $overallScore): string
    {
        if ($overallScore >= 85) return 'excellent_match';
        if ($overallScore >= 70) return 'good_match';
        if ($overallScore >= 55) return 'fair_match';
        if ($overallScore >= 40) return 'poor_match';
        return 'not_recommended';
    }

    private function mergeSectionScores(array $basic, array $ai): array
    {
        $merged = [];
        
        foreach ($basic as $section => $score) {
            $aiScore = $ai[$section] ?? $score;
            $merged[$section] = round(($score * 0.3) + ($aiScore * 0.7), 1);
        }
        
        return $merged;
    }

    private function filterRecommendationsByType(array $recommendations, string $type): array
    {
        $filtered = [];
        
        foreach ($recommendations as $recommendation) {
            if (is_array($recommendation) && isset($recommendation['type']) && $recommendation['type'] === $type) {
                $filtered[] = $recommendation['text'];
            } elseif (is_string($recommendation)) {
                // For basic recommendations, categorize based on content
                if ($type === 'general') {
                    $filtered[] = $recommendation;
                }
            }
        }
        
        return array_slice($filtered, 0, 5);
    }

    // Text extraction helper methods
    private function extractLocation(string $text): ?string
    {
        $locationPatterns = [
            '/(?:location|based in|located in)[:\s]*([a-zA-Z\s,]+)/i',
            '/\b(karachi|lahore|islamabad|rawalpindi|faisalabad|multan|peshawar|quetta|hyderabad|gujranwala)\b/i',
            '/\b(remote|work from home|wfh)\b/i'
        ];
        
        foreach ($locationPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }

    private function extractSalary(string $text): ?array
    {
        $salaryPatterns = [
            '/(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)\s*(?:to|-)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i',
            '/salary[:\s]*(\d{1,3}(?:,\d{3})*)/i',
            '/(\d{1,3})k?\s*(?:to|-)\s*(\d{1,3})k?/i'
        ];
        
        foreach ($salaryPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $min = (float)str_replace(',', '', $matches[1]);
                $max = isset($matches[2]) ? (float)str_replace(',', '', $matches[2]) : null;
                
                // Handle 'k' notation
                if (strpos($matches[1], 'k') !== false) {
                    $min *= 1000;
                }
                if ($max && isset($matches[2]) && strpos($matches[2], 'k') !== false) {
                    $max *= 1000;
                }
                
                return ['min' => $min, 'max' => $max];
            }
        }
        
        return null;
    }

    private function extractExperience(string $text): ?string
    {
        $experiencePatterns = [
            '/(\d+)\+?\s*years?\s*(?:of\s*)?experience/i',
            '/experience[:\s]*(\d+)\+?\s*years?/i',
            '/minimum\s*(\d+)\s*years?/i'
        ];
        
        foreach ($experiencePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1] . '+ years';
            }
        }
        
        return null;
    }

    private function extractEducation(string $text): ?string
    {
        $educationPatterns = [
            '/\b(bachelor|master|phd|doctorate|diploma)\b[\'s\s]*(?:degree)?[:\s]*(?:in\s*)?([a-zA-Z\s]+)/i',
            '/\b(bs|ms|mba|ma|ba)\b[:\s]*([a-zA-Z\s]+)/i'
        ];
        
        foreach ($educationPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1] . ' ' . ($matches[2] ?? ''));
            }
        }
        
        return null;
    }

    private function extractIndustry(string $text): ?string
    {
        $industries = [
            'technology', 'healthcare', 'finance', 'education', 'retail', 'manufacturing',
            'consulting', 'marketing', 'sales', 'construction', 'automotive', 'aerospace',
            'telecommunications', 'media', 'entertainment', 'hospitality', 'real estate'
        ];
        
        foreach ($industries as $industry) {
            if (stripos($text, $industry) !== false) {
                return ucfirst($industry);
            }
        }
        
        return null;
    }

    private function extractHardSkills(string $text): array
    {
        $hardSkillsPatterns = [
            'programming' => '/\b(php|python|java|javascript|c\+\+|c#|ruby|go|swift|kotlin|scala)\b/i',
            'frameworks' => '/\b(react|angular|vue|laravel|django|spring|express|flask)\b/i',
            'databases' => '/\b(mysql|postgresql|mongodb|redis|sqlite|oracle|sql server)\b/i',
            'tools' => '/\b(git|docker|kubernetes|jenkins|aws|azure|gcp|linux|windows)\b/i',
            'design' => '/\b(photoshop|illustrator|figma|sketch|adobe creative|autocad)\b/i'
        ];
        
        $skills = [];
        foreach ($hardSkillsPatterns as $category => $pattern) {
            preg_match_all($pattern, $text, $matches);
            $skills = array_merge($skills, array_map('strtolower', $matches[0]));
        }
        
        return array_unique($skills);
    }

    private function extractSoftSkills(string $text): array
    {
        $softSkillsPattern = '/\b(leadership|communication|teamwork|problem.solving|analytical|creative|strategic|planning|organization|time.management|adaptability|critical.thinking)\b/i';
        
        preg_match_all($softSkillsPattern, $text, $matches);
        return array_unique(array_map('strtolower', $matches[0]));
    }

    private function extractLanguages(string $text): array
    {
        $languagePattern = '/\b(english|urdu|arabic|french|german|spanish|chinese|japanese|hindi|punjabi)\b/i';
        
        preg_match_all($languagePattern, $text, $matches);
        return array_unique(array_map('strtolower', $matches[0]));
    }

    private function extractCertifications(string $text): array
    {
        $certificationPatterns = [
            '/\b(pmp|cissp|cisa|cism|comptia|cisco|microsoft|aws|google|oracle)\b[:\s]*([a-zA-Z\s]+)/i',
            '/certified[:\s]*([a-zA-Z\s]+)/i'
        ];
        
        $certifications = [];
        foreach ($certificationPatterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $certifications = array_merge($certifications, $matches[0]);
        }
        
        return array_unique($certifications);
    }

    private function logAnalysis(int $jobId, string $level, string $message, array $data = []): void
    {
        try {
            AnalysisLog::create([
                'job_id' => $jobId,
                'log_level' => $level,
                'message' => $message,
                'additional_data' => $data
            ]);
        } catch (\Exception $e) {
            // Silent fail for logging errors
            error_log("Failed to log analysis: " . $e->getMessage());
        }
    }
}

// Helper function for file paths
function public_path($path = '')
{
    return __DIR__ . '/../../public/' . ltrim($path, '/');
}