<?php
// app/Services/AIAnalysisService.php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

class AIAnalysisService
{
    private $httpClient;
    private $aiServiceUrl;
    private $isServiceRunning = false;
    private $lastError = null;
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => (int)($_ENV['AI_SERVICE_TIMEOUT'] ?? 60),
            'connect_timeout' => 10
        ]);
        
        $this->aiServiceUrl = $_ENV['AI_SERVICE_URL'] ?? 'http://localhost:5000';
        $this->checkServiceStatus();
    }

    /**
     * Perform AI-enhanced analysis of resume and job description
     */
    public function analyzeWithAI(string $jobDescription, string $resumeText, string $coverLetterText = ''): array
    {
        if (!$this->isServiceRunning) {
            throw new Exception('AI service is not running. Please start the service first.');
        }

        try {
            $response = $this->httpClient->post($this->aiServiceUrl . '/analyze', [
                'json' => [
                    'job_description' => $jobDescription,
                    'resume_text' => $resumeText,
                    'cover_letter_text' => $coverLetterText,
                    'analysis_options' => [
                        'include_ats_score' => true,
                        'include_keyword_analysis' => true,
                        'include_recommendations' => true,
                        'include_probability_scores' => true,
                        'detailed_section_analysis' => true
                    ]
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            if (!$responseData || !$responseData['success']) {
                throw new Exception('AI analysis failed: ' . ($responseData['message'] ?? 'Unknown error'));
            }
            
            return $this->processAIResponse($responseData['analysis']);
            
        } catch (RequestException $e) {
            $this->lastError = $e->getMessage();
            throw new Exception('AI service request failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate tailored resume suggestions
     */
    public function generateResumeSuggestions(string $jobDescription, string $resumeText): array
    {
        if (!$this->isServiceRunning) {
            throw new Exception('AI service is not running');
        }

        try {
            $response = $this->httpClient->post($this->aiServiceUrl . '/resume-suggestions', [
                'json' => [
                    'job_description' => $jobDescription,
                    'resume_text' => $resumeText,
                    'focus_areas' => [
                        'ats_optimization',
                        'keyword_matching',
                        'experience_alignment',
                        'skills_enhancement'
                    ]
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!$data['success']) {
                throw new Exception('Resume suggestions failed');
            }
            
            return $data['suggestions'];
            
        } catch (RequestException $e) {
            throw new Exception('Resume suggestions request failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate tailored cover letter suggestions
     */
    public function generateCoverLetterSuggestions(string $jobDescription, string $resumeText, string $coverLetterText = ''): array
    {
        if (!$this->isServiceRunning) {
            throw new Exception('AI service is not running');
        }

        try {
            $response = $this->httpClient->post($this->aiServiceUrl . '/cover-letter-suggestions', [
                'json' => [
                    'job_description' => $jobDescription,
                    'resume_text' => $resumeText,
                    'current_cover_letter' => $coverLetterText,
                    'suggestions_type' => 'comprehensive'
                ]
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!$data['success']) {
                throw new Exception('Cover letter suggestions failed');
            }
            
            return $data['suggestions'];
            
        } catch (RequestException $e) {
            throw new Exception('Cover letter suggestions request failed: ' . $e->getMessage());
        }
    }

    /**
     * Get interview probability prediction
     */
    public function predictInterviewChances(array $analysisResults): array
    {
        $factors = [
            'ats_score' => $analysisResults['ats_score'] ?? 0,
            'keyword_match' => $analysisResults['keyword_analysis']['density'] ?? 0,
            'experience_match' => $analysisResults['section_scores']['experience'] ?? 0,
            'education_match' => $analysisResults['section_scores']['education'] ?? 0,
            'overall_score' => $analysisResults['overall_score'] ?? 0
        ];

        // Weighted scoring for interview prediction
        $weights = [
            'ats_score' => 0.35,        // Most important for getting through screening
            'keyword_match' => 0.25,    // Critical for ATS matching
            'experience_match' => 0.20, // Important for role fit
            'education_match' => 0.10,  // Moderate importance
            'overall_score' => 0.10     // General quality
        ];

        $weightedScore = 0;
        foreach ($factors as $factor => $score) {
            $weightedScore += $score * ($weights[$factor] ?? 0);
        }

        // Apply industry modifiers
        $industryModifier = $this->getIndustryModifier($analysisResults);
        $finalScore = min(100, $weightedScore * $industryModifier);

        return [
            'interview_probability' => round($finalScore, 1),
            'confidence_level' => $this->calculateConfidenceLevel($factors),
            'key_factors' => $this->identifyKeyFactors($factors, $weights),
            'improvement_impact' => $this->calculateImprovementImpact($factors),
            'industry_context' => $industryModifier
        ];
    }

    /**
     * Check if AI service is running
     */
    public function checkStatus(): array
    {
        try {
            $response = $this->httpClient->get($this->aiServiceUrl . '/health', [
                'timeout' => 5
            ]);
            
            if ($response->getStatusCode() === 200) {
                $this->isServiceRunning = true;
                $data = json_decode($response->getBody()->getContents(), true);
                
                return [
                    'status' => 'running',
                    'service_info' => $data,
                    'response_time' => 'good',
                    'features_available' => [
                        'resume_analysis' => true,
                        'ats_scoring' => true,
                        'keyword_matching' => true,
                        'recommendations' => true
                    ]
                ];
            }
            
        } catch (Exception $e) {
            $this->isServiceRunning = false;
            $this->lastError = $e->getMessage();
        }

        return [
            'status' => 'stopped',
            'error' => $this->lastError,
            'service_info' => null,
            'features_available' => []
        ];
    }

    /**
     * Start AI service (if possible)
     */
    public function startService(): array
    {
        try {
            // Try to ping first
            $status = $this->checkStatus();
            if ($status['status'] === 'running') {
                return [
                    'success' => true,
                    'message' => 'Service is already running',
                    'status' => $status
                ];
            }

            // Attempt to start service via subprocess (if script exists)
            $pythonScript = __DIR__ . '/../../python_services/enhanced_resume_analyzer.py';
            
            if (!file_exists($pythonScript)) {
                return [
                    'success' => false,
                    'message' => 'Python AI service script not found',
                    'suggestion' => 'Please ensure enhanced_resume_analyzer.py is in python_services directory'
                ];
            }

            // Try to start Python service
            $command = "python3 {$pythonScript} > /dev/null 2>&1 &";
            exec($command, $output, $returnCode);
            
            // Give it time to start
            sleep(3);
            
            // Check if it's running now
            $newStatus = $this->checkStatus();
            
            return [
                'success' => $newStatus['status'] === 'running',
                'message' => $newStatus['status'] === 'running' ? 
                    'AI service started successfully' : 
                    'Failed to start AI service',
                'status' => $newStatus
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error starting service: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process AI response and normalize format
     */
    private function processAIResponse(array $rawResponse): array
    {
        return [
            'overall_score' => $rawResponse['overall_score'] ?? 0,
            'ats_score' => $rawResponse['ats_compatibility_score'] ?? 0,
            'resume_match_score' => $rawResponse['resume_match_score'] ?? 0,
            'cover_letter_match_score' => $rawResponse['cover_letter_match_score'] ?? 0,
            'interview_probability' => $rawResponse['interview_probability'] ?? 0,
            'job_securing_probability' => $rawResponse['job_securing_probability'] ?? 0,
            'goodness_of_fit_score' => $rawResponse['goodness_of_fit_score'] ?? 0,
            'ai_recommendation' => $this->mapRecommendation($rawResponse['ai_recommendation'] ?? ''),
            'ai_confidence_level' => $rawResponse['ai_confidence_level'] ?? 75,
            'section_scores' => $this->normalizeSectionScores($rawResponse['section_analysis'] ?? []),
            'keyword_analysis' => $this->normalizeKeywordAnalysis($rawResponse['keyword_analysis'] ?? []),
            'skill_match_analysis' => $rawResponse['skill_match_analysis'] ?? [],
            'experience_gap_analysis' => $rawResponse['experience_gap_analysis'] ?? [],
            'education_match_analysis' => $rawResponse['education_match_analysis'] ?? [],
            'recommendations' => $this->normalizeRecommendations($rawResponse['recommendations'] ?? []),
            'analysis_type' => 'ai_enhanced'
        ];
    }

    /**
     * Map AI recommendation to our format
     */
    private function mapRecommendation(string $aiRec): string
    {
        $mapping = [
            'highly_recommended' => 'excellent_match',
            'recommended' => 'good_match',
            'moderately_suitable' => 'fair_match',
            'not_ideal' => 'poor_match',
            'not_recommended' => 'not_recommended'
        ];

        return $mapping[$aiRec] ?? 'fair_match';
    }

    /**
     * Normalize section scores from AI response
     */
    private function normalizeSectionScores(array $sections): array
    {
        return [
            'contact_info' => $sections['contact_info']['score'] ?? 0,
            'summary' => $sections['professional_summary']['score'] ?? 0,
            'experience' => $sections['experience']['score'] ?? 0,
            'education' => $sections['education']['score'] ?? 0,
            'skills' => $sections['skills']['score'] ?? 0,
            'achievements' => $sections['achievements']['score'] ?? 0
        ];
    }

    /**
     * Normalize keyword analysis from AI response
     */
    private function normalizeKeywordAnalysis(array $keywords): array
    {
        $matching = [];
        $missing = [];
        $suggested = [];

        foreach ($keywords as $category => $data) {
            if (isset($data['found_keywords'])) {
                foreach ($data['found_keywords'] as $keyword) {
                    $matching[] = is_array($keyword) ? $keyword['keyword'] : $keyword;
                }
            }
            
            if (isset($data['missing_keywords'])) {
                foreach ($data['missing_keywords'] as $keyword) {
                    $missing[] = is_array($keyword) ? $keyword['keyword'] : $keyword;
                }
            }
        }

        // Generate suggestions from missing keywords
        $suggested = array_slice($missing, 0, 10);

        return [
            'matching_keywords' => array_unique($matching),
            'missing_keywords' => array_unique($missing),
            'suggested_keywords' => $suggested,
            'density' => count($matching) > 0 ? 
                round((count($matching) / (count($matching) + count($missing))) * 100, 1) : 0
        ];
    }

    /**
     * Normalize recommendations from AI response
     */
    private function normalizeRecommendations(array $recommendations): array
    {
        $normalized = [];

        foreach ($recommendations as $rec) {
            if (is_array($rec)) {
                $normalized[] = [
                    'type' => $rec['category'] ?? 'general',
                    'priority' => $rec['priority'] ?? 'medium',
                    'text' => $rec['suggestion'] ?? $rec['text'] ?? '',
                    'action' => $rec['action'] ?? ''
                ];
            } else {
                $normalized[] = [
                    'type' => 'general',
                    'priority' => 'medium',
                    'text' => $rec,
                    'action' => ''
                ];
            }
        }

        return $normalized;
    }

    /**
     * Calculate confidence level for predictions
     */
    private function calculateConfidenceLevel(array $factors): float
    {
        $nonZeroFactors = array_filter($factors, function($value) {
            return $value > 0;
        });

        $completeness = count($nonZeroFactors) / count($factors);
        $variability = $this->calculateVariability($factors);
        
        // Higher completeness and lower variability = higher confidence
        $confidence = ($completeness * 0.7) + ((1 - $variability) * 0.3);
        
        return round($confidence * 100, 1);
    }

    /**
     * Calculate variability in scores
     */
    private function calculateVariability(array $scores): float
    {
        if (count($scores) < 2) return 0;
        
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(function($score) use ($mean) {
            return pow($score - $mean, 2);
        }, $scores)) / count($scores);
        
        return sqrt($variance) / 100; // Normalize to 0-1
    }

    /**
     * Identify key factors affecting interview chances
     */
    private function identifyKeyFactors(array $factors, array $weights): array
    {
        $keyFactors = [];
        
        foreach ($factors as $factor => $score) {
            $impact = $score * ($weights[$factor] ?? 0);
            $keyFactors[] = [
                'factor' => $factor,
                'score' => $score,
                'weight' => $weights[$factor] ?? 0,
                'impact' => round($impact, 1),
                'status' => $score >= 70 ? 'strong' : ($score >= 50 ? 'moderate' : 'weak')
            ];
        }

        // Sort by impact
        usort($keyFactors, function($a, $b) {
            return $b['impact'] - $a['impact'];
        });

        return $keyFactors;
    }

    /**
     * Calculate improvement impact predictions
     */
    private function calculateImprovementImpact(array $factors): array
    {
        $improvements = [];
        
        foreach ($factors as $factor => $score) {
            if ($score < 80) {
                $potentialGain = min(95, $score + 20) - $score;
                $improvements[] = [
                    'factor' => $factor,
                    'current_score' => $score,
                    'potential_improvement' => $potentialGain,
                    'effort_required' => $this->estimateEffortRequired($factor, $score),
                    'impact_on_interview_chance' => round($potentialGain * 0.8, 1)
                ];
            }
        }

        // Sort by potential impact
        usort($improvements, function($a, $b) {
            return $b['impact_on_interview_chance'] - $a['impact_on_interview_chance'];
        });

        return array_slice($improvements, 0, 5);
    }

    /**
     * Estimate effort required for improvement
     */
    private function estimateEffortRequired(string $factor, float $currentScore): string
    {
        $effortMap = [
            'ats_score' => $currentScore < 50 ? 'high' : 'medium',
            'keyword_match' => 'low',
            'experience_match' => $currentScore < 60 ? 'high' : 'medium',
            'education_match' => 'medium',
            'overall_score' => 'medium'
        ];

        return $effortMap[$factor] ?? 'medium';
    }

    /**
     * Get industry-specific modifier
     */
    private function getIndustryModifier(array $analysisResults): float
    {
        // This could be expanded based on job description analysis
        // For now, return neutral modifier
        return 1.0;
    }

    /**
     * Check service status without throwing exceptions
     */
    private function checkServiceStatus(): void
    {
        try {
            $response = $this->httpClient->get($this->aiServiceUrl . '/health', [
                'timeout' => 3
            ]);
            
            $this->isServiceRunning = $response->getStatusCode() === 200;
        } catch (Exception $e) {
            $this->isServiceRunning = false;
            $this->lastError = $e->getMessage();
        }
    }

    /**
     * Get service URL
     */
    public function getServiceUrl(): string
    {
        return $this->aiServiceUrl;
    }

    /**
     * Check if service is running
     */
    public function isRunning(): bool
    {
        return $this->isServiceRunning;
    }

    /**
     * Get last error
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Test connection with simple ping
     */
    public function ping(): bool
    {
        try {
            $response = $this->httpClient->get($this->aiServiceUrl . '/health', [
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get available AI features
     */
    public function getAvailableFeatures(): array
    {
        if (!$this->isServiceRunning) {
            return [];
        }

        try {
            $response = $this->httpClient->get($this->aiServiceUrl . '/features');
            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['features'] ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Update AI service configuration
     */
    public function updateConfig(array $config): bool
    {
        if (!$this->isServiceRunning) {
            return false;
        }

        try {
            $response = $this->httpClient->post($this->aiServiceUrl . '/config', [
                'json' => $config
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}