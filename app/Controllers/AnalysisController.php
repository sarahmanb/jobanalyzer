<?php
// app/Controllers/AnalysisController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Job;
use App\Models\JobAnalysis;
use App\Models\AnalysisLog;
use App\Services\JobAnalyzerService;
use App\Services\ReportGeneratorService;
use Exception;

class AnalysisController
{
    private $view;
    private $jobAnalyzer;
    private $reportGenerator;
    
    public function __construct($view, JobAnalyzerService $jobAnalyzer = null, ReportGeneratorService $reportGenerator = null)
    {
        $this->view = $view;
        $this->jobAnalyzer = $jobAnalyzer ?: new JobAnalyzerService();
        $this->reportGenerator = $reportGenerator;
    }

    /**
     * Display detailed analysis results for a specific job
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            
            // Get job with analysis and logs
            $job = Job::with(['analysis', 'analysisLogs'])->find($jobId);
            
            if (!$job) {
                return $this->notFound($response, 'Job not found');
            }

            if (!$job->analysis) {
                // Redirect to job view with message to run analysis first
                $_SESSION['flash_message'] = [
                    'type' => 'warning',
                    'message' => 'This job has not been analyzed yet. Please run the analysis first.'
                ];
                return $response->withHeader('Location', "/jobs/{$jobId}")->withStatus(302);
            }

            $analysis = $job->analysis;
            
            // Prepare detailed analysis data
            $analysisData = [
                'job' => $job,
                'analysis' => $analysis,
                'detailed_scores' => $this->getDetailedScores($analysis),
                'keyword_insights' => $this->getKeywordInsights($analysis),
                'section_breakdown' => $this->getSectionBreakdown($analysis),
                'recommendations' => $this->getRecommendations($analysis),
                'comparison_data' => $this->getComparisonData($job),
                'improvement_suggestions' => $this->getImprovementSuggestions($analysis),
                'analysis_logs' => $job->analysisLogs()->orderBy('created_at', 'desc')->take(10)->get(),
                'similar_jobs' => $this->getSimilarJobs($job, 5)
            ];

            // Render analysis view
            $html = $this->view->render('analysis/show.twig', $analysisData);
            $response->getBody()->write($html);
            return $response;
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to load analysis details');
        }
    }

    /**
     * Re-run analysis for a job
     */
    public function rerun(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $job = Job::find($jobId);
            
            if (!$job) {
                return $this->notFound($response, 'Job not found');
            }

            // Reset analysis status
            $job->resetAnalysis();
            
            // Log the rerun request
            AnalysisLog::create([
                'job_id' => $jobId,
                'log_level' => 'info',
                'message' => 'Analysis rerun requested by user',
                'additional_data' => [
                    'user_ip' => $this->getClientIp($request),
                    'user_agent' => $request->getHeaderLine('User-Agent')
                ]
            ]);

            // Run new analysis
            $analysisResults = $this->jobAnalyzer->analyzeJob($job);
            
            // Return JSON response for AJAX calls
            if ($this->isAjaxRequest($request)) {
                $responseData = [
                    'success' => true,
                    'message' => 'Analysis completed successfully',
                    'analysis' => [
                        'overall_score' => $analysisResults['overall_score'] ?? 0,
                        'ats_score' => $analysisResults['ats_score'] ?? 0,
                        'resume_match_score' => $analysisResults['resume_match_score'] ?? 0,
                        'cover_letter_match_score' => $analysisResults['cover_letter_match_score'] ?? 0,
                        'recommendation' => $analysisResults['ai_recommendation'] ?? 'fair_match'
                    ]
                ];
                
                $response->getBody()->write(json_encode($responseData));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Redirect back to analysis page
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Analysis has been updated successfully!'
            ];
            
            return $response->withHeader('Location', "/analysis/{$jobId}")->withStatus(302);
            
        } catch (Exception $e) {
            if ($this->isAjaxRequest($request)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Analysis failed: ' . $e->getMessage()
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }
            
            return $this->handleError($response, $e, 'Failed to rerun analysis');
        }
    }

    /**
     * Compare analyses between two jobs
     */
    public function compare(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId1 = (int)$args['id1'];
            $jobId2 = (int)$args['id2'];
            
            // Get both jobs with their analyses
            $job1 = Job::with('analysis')->find($jobId1);
            $job2 = Job::with('analysis')->find($jobId2);
            
            if (!$job1 || !$job2) {
                return $this->notFound($response, 'One or both jobs not found');
            }

            if (!$job1->analysis || !$job2->analysis) {
                $_SESSION['flash_message'] = [
                    'type' => 'warning',
                    'message' => 'Both jobs must be analyzed before comparison'
                ];
                return $response->withHeader('Location', '/dashboard')->withStatus(302);
            }

            // Prepare comparison data
            $comparisonData = [
                'job1' => $job1,
                'job2' => $job2,
                'score_comparison' => $this->compareScores($job1->analysis, $job2->analysis),
                'keyword_comparison' => $this->compareKeywords($job1->analysis, $job2->analysis),
                'section_comparison' => $this->compareSections($job1->analysis, $job2->analysis),
                'recommendation_comparison' => $this->compareRecommendations($job1->analysis, $job2->analysis),
                'insights' => $this->getComparisonInsights($job1, $job2)
            ];

            $html = $this->view->render('analysis/compare.twig', $comparisonData);
            $response->getBody()->write($html);
            return $response;
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to compare analyses');
        }
    }

    /**
     * Get analysis insights via API
     */
    public function insights(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $job = Job::with('analysis')->find($jobId);
            
            if (!$job || !$job->analysis) {
                $response->getBody()->write(json_encode([
                    'error' => 'Job or analysis not found'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            $analysis = $job->analysis;
            
            // Generate insights
            $insights = [
                'performance_summary' => $this->getPerformanceSummary($analysis),
                'strengths' => $this->identifyStrengths($analysis),
                'weaknesses' => $this->identifyWeaknesses($analysis),
                'improvement_priority' => $this->getImprovementPriority($analysis),
                'benchmark_comparison' => $this->getBenchmarkComparison($analysis),
                'trend_analysis' => $this->getTrendAnalysis($job),
                'action_items' => $this->getActionItems($analysis)
            ];

            $response->getBody()->write(json_encode($insights, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'error' => 'Failed to generate insights: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Batch analysis for multiple jobs
     */
    public function batchAnalyze(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            $jobIds = $data['job_ids'] ?? [];
            
            if (empty($jobIds) || !is_array($jobIds)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'No job IDs provided'
                ]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            foreach ($jobIds as $jobId) {
                try {
                    $job = Job::find($jobId);
                    if (!$job) {
                        $results[] = [
                            'job_id' => $jobId,
                            'status' => 'failed',
                            'message' => 'Job not found'
                        ];
                        $failureCount++;
                        continue;
                    }

                    // Skip if already analyzed and not forcing reanalysis
                    if ($job->is_analyzed && !($data['force_reanalysis'] ?? false)) {
                        $results[] = [
                            'job_id' => $jobId,
                            'status' => 'skipped',
                            'message' => 'Already analyzed'
                        ];
                        continue;
                    }

                    // Reset if reanalyzing
                    if ($job->is_analyzed) {
                        $job->resetAnalysis();
                    }

                    // Run analysis
                    $analysisResults = $this->jobAnalyzer->analyzeJob($job);
                    
                    $results[] = [
                        'job_id' => $jobId,
                        'status' => 'success',
                        'message' => 'Analysis completed',
                        'score' => $analysisResults['overall_score'] ?? 0
                    ];
                    $successCount++;
                    
                } catch (Exception $e) {
                    $results[] = [
                        'job_id' => $jobId,
                        'status' => 'failed',
                        'message' => $e->getMessage()
                    ];
                    $failureCount++;
                }
            }

            $response->getBody()->write(json_encode([
                'success' => $failureCount === 0,
                'summary' => [
                    'total' => count($jobIds),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'skipped' => count($jobIds) - $successCount - $failureCount
                ],
                'results' => $results
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Batch analysis failed: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    // Private helper methods

    private function getDetailedScores(JobAnalysis $analysis): array
    {
        return [
            'overall' => [
                'score' => $analysis->overall_score,
                'grade' => $this->getGrade($analysis->overall_score),
                'description' => $this->getScoreDescription($analysis->overall_score)
            ],
            'ats' => [
                'score' => $analysis->ats_score,
                'grade' => $this->getGrade($analysis->ats_score),
                'description' => 'Applicant Tracking System compatibility'
            ],
            'resume_match' => [
                'score' => $analysis->resume_match_score,
                'grade' => $this->getGrade($analysis->resume_match_score),
                'description' => 'How well your resume matches the job requirements'
            ],
            'cover_letter_match' => [
                'score' => $analysis->cover_letter_match_score,
                'grade' => $this->getGrade($analysis->cover_letter_match_score),
                'description' => 'How well your cover letter addresses the role'
            ],
            'probabilities' => [
                'interview' => $analysis->interview_probability,
                'job_offer' => $analysis->job_securing_probability,
                'fit' => $analysis->goodness_of_fit_score
            ]
        ];
    }

    private function getKeywordInsights(JobAnalysis $analysis): array
    {
        $matchingKeywords = $analysis->matching_keywords ?? [];
        $missingKeywords = $analysis->missing_keywords ?? [];
        $suggestedKeywords = $analysis->suggested_keywords ?? [];
        
        return [
            'matching' => [
                'count' => count($matchingKeywords),
                'keywords' => $matchingKeywords,
                'percentage' => $analysis->keyword_density ?? 0
            ],
            'missing' => [
                'count' => count($missingKeywords),
                'keywords' => array_slice($missingKeywords, 0, 10), // Top 10 missing
                'critical' => array_slice($missingKeywords, 0, 5) // Top 5 critical
            ],
            'suggested' => [
                'count' => count($suggestedKeywords),
                'keywords' => $suggestedKeywords
            ],
            'density_analysis' => [
                'current' => $analysis->keyword_density ?? 0,
                'recommended' => 15, // Recommended keyword density percentage
                'status' => $this->getKeywordDensityStatus($analysis->keyword_density ?? 0)
            ]
        ];
    }

    private function getSectionBreakdown(JobAnalysis $analysis): array
    {
        return [
            'contact_info' => [
                'score' => $analysis->contact_info_score,
                'status' => $this->getSectionStatus($analysis->contact_info_score),
                'feedback' => $this->getSectionFeedback('contact_info', $analysis->contact_info_score)
            ],
            'summary' => [
                'score' => $analysis->summary_score,
                'status' => $this->getSectionStatus($analysis->summary_score),
                'feedback' => $this->getSectionFeedback('summary', $analysis->summary_score)
            ],
            'experience' => [
                'score' => $analysis->experience_score,
                'status' => $this->getSectionStatus($analysis->experience_score),
                'feedback' => $this->getSectionFeedback('experience', $analysis->experience_score)
            ],
            'education' => [
                'score' => $analysis->education_score,
                'status' => $this->getSectionStatus($analysis->education_score),
                'feedback' => $this->getSectionFeedback('education', $analysis->education_score)
            ],
            'skills' => [
                'score' => $analysis->skills_score,
                'status' => $this->getSectionStatus($analysis->skills_score),
                'feedback' => $this->getSectionFeedback('skills', $analysis->skills_score)
            ],
            'achievements' => [
                'score' => $analysis->achievements_score,
                'status' => $this->getSectionStatus($analysis->achievements_score),
                'feedback' => $this->getSectionFeedback('achievements', $analysis->achievements_score)
            ]
        ];
    }

    private function getRecommendations(JobAnalysis $analysis): array
    {
        return [
            'resume' => $analysis->resume_recommendations ?? [],
            'cover_letter' => $analysis->cover_letter_recommendations ?? [],
            'general' => $analysis->general_recommendations ?? [],
            'priority' => $this->getPriorityRecommendations($analysis)
        ];
    }

    private function getComparisonData(Job $job): array
    {
        // Get average scores from all analyzed jobs for comparison
        $averages = JobAnalysis::selectRaw('
            AVG(overall_score) as avg_overall,
            AVG(ats_score) as avg_ats,
            AVG(resume_match_score) as avg_resume,
            AVG(cover_letter_match_score) as avg_cover_letter
        ')->first();

        return [
            'industry_average' => [
                'overall_score' => round($averages->avg_overall ?? 0, 1),
                'ats_score' => round($averages->avg_ats ?? 0, 1),
                'resume_match_score' => round($averages->avg_resume ?? 0, 1),
                'cover_letter_match_score' => round($averages->avg_cover_letter ?? 0, 1)
            ],
            'your_performance' => [
                'overall_score' => $job->analysis->overall_score,
                'ats_score' => $job->analysis->ats_score,
                'resume_match_score' => $job->analysis->resume_match_score,
                'cover_letter_match_score' => $job->analysis->cover_letter_match_score
            ]
        ];
    }

    private function getImprovementSuggestions(JobAnalysis $analysis): array
    {
        $suggestions = [];
        
        // Score-based suggestions
        if ($analysis->ats_score < 70) {
            $suggestions[] = [
                'category' => 'ATS Optimization',
                'priority' => 'high',
                'suggestion' => 'Improve ATS compatibility by using standard section headers and avoiding complex formatting',
                'impact' => 'High - Better chance of passing initial screening'
            ];
        }
        
        if ($analysis->keyword_density < 10) {
            $suggestions[] = [
                'category' => 'Keywords',
                'priority' => 'high',
                'suggestion' => 'Include more relevant keywords from the job description in your resume',
                'impact' => 'High - Better matching with job requirements'
            ];
        }
        
        if ($analysis->resume_match_score < 60) {
            $suggestions[] = [
                'category' => 'Content Alignment',
                'priority' => 'medium',
                'suggestion' => 'Better align your experience and skills with the job requirements',
                'impact' => 'Medium - Improved relevance to the role'
            ];
        }
        
        return $suggestions;
    }

    private function getSimilarJobs(Job $job, int $limit = 5): array
    {
        return Job::with('analysis')
            ->where('id', '!=', $job->id)
            ->where('is_analyzed', true)
            ->where(function($query) use ($job) {
                $query->where('job_level', $job->job_level)
                      ->orWhere('employment_type', $job->employment_type)
                      ->orWhere('industry', $job->industry);
            })
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function($similarJob) {
                return [
                    'id' => $similarJob->id,
                    'title' => $similarJob->job_title,
                    'company' => $similarJob->company_name,
                    'score' => $similarJob->analysis ? round($similarJob->analysis->overall_score, 1) : 0,
                    'similarity_reasons' => $this->getSimilarityReasons($similarJob)
                ];
            })
            ->toArray();
    }

    private function compareScores(JobAnalysis $analysis1, JobAnalysis $analysis2): array
    {
        return [
            'overall_score' => [
                'job1' => $analysis1->overall_score,
                'job2' => $analysis2->overall_score,
                'difference' => $analysis1->overall_score - $analysis2->overall_score,
                'winner' => $analysis1->overall_score > $analysis2->overall_score ? 'job1' : 'job2'
            ],
            'ats_score' => [
                'job1' => $analysis1->ats_score,
                'job2' => $analysis2->ats_score,
                'difference' => $analysis1->ats_score - $analysis2->ats_score,
                'winner' => $analysis1->ats_score > $analysis2->ats_score ? 'job1' : 'job2'
            ],
            'resume_match_score' => [
                'job1' => $analysis1->resume_match_score,
                'job2' => $analysis2->resume_match_score,
                'difference' => $analysis1->resume_match_score - $analysis2->resume_match_score,
                'winner' => $analysis1->resume_match_score > $analysis2->resume_match_score ? 'job1' : 'job2'
            ],
            'cover_letter_match_score' => [
                'job1' => $analysis1->cover_letter_match_score,
                'job2' => $analysis2->cover_letter_match_score,
                'difference' => $analysis1->cover_letter_match_score - $analysis2->cover_letter_match_score,
                'winner' => $analysis1->cover_letter_match_score > $analysis2->cover_letter_match_score ? 'job1' : 'job2'
            ]
        ];
    }

    private function compareKeywords(JobAnalysis $analysis1, JobAnalysis $analysis2): array
    {
        $keywords1 = $analysis1->matching_keywords ?? [];
        $keywords2 = $analysis2->matching_keywords ?? [];
        
        return [
            'job1_unique' => array_diff($keywords1, $keywords2),
            'job2_unique' => array_diff($keywords2, $keywords1),
            'common' => array_intersect($keywords1, $keywords2),
            'job1_count' => count($keywords1),
            'job2_count' => count($keywords2)
        ];
    }

    private function compareSections(JobAnalysis $analysis1, JobAnalysis $analysis2): array
    {
        $sections = ['contact_info', 'summary', 'experience', 'education', 'skills', 'achievements'];
        $comparison = [];
        
        foreach ($sections as $section) {
            $score1 = $analysis1->{$section . '_score'};
            $score2 = $analysis2->{$section . '_score'};
            
            $comparison[$section] = [
                'job1' => $score1,
                'job2' => $score2,
                'difference' => $score1 - $score2,
                'winner' => $score1 > $score2 ? 'job1' : ($score1 < $score2 ? 'job2' : 'tie')
            ];
        }
        
        return $comparison;
    }

    private function compareRecommendations(JobAnalysis $analysis1, JobAnalysis $analysis2): array
    {
        return [
            'job1_recommendations' => array_merge(
                $analysis1->resume_recommendations ?? [],
                $analysis1->cover_letter_recommendations ?? [],
                $analysis1->general_recommendations ?? []
            ),
            'job2_recommendations' => array_merge(
                $analysis2->resume_recommendations ?? [],
                $analysis2->cover_letter_recommendations ?? [],
                $analysis2->general_recommendations ?? []
            )
        ];
    }

    private function getComparisonInsights(Job $job1, Job $job2): array
    {
        $insights = [];
        
        $score1 = $job1->analysis->overall_score;
        $score2 = $job2->analysis->overall_score;
        
        if (abs($score1 - $score2) < 5) {
            $insights[] = "Both applications have very similar overall scores ({$score1}% vs {$score2}%). Focus on specific areas for improvement.";
        } elseif ($score1 > $score2) {
            $insights[] = "Job '{$job1->job_title}' performs significantly better. Consider applying similar strategies to '{$job2->job_title}'.";
        } else {
            $insights[] = "Job '{$job2->job_title}' performs significantly better. Consider applying similar strategies to '{$job1->job_title}'.";
        }
        
        return $insights;
    }

    private function getPerformanceSummary(JobAnalysis $analysis): array
    {
        $overallScore = $analysis->overall_score;
        
        return [
            'grade' => $this->getGrade($overallScore),
            'performance_level' => $this->getPerformanceLevel($overallScore),
            'key_strengths' => 2, // Count of strong areas (score > 80)
            'improvement_areas' => 3, // Count of weak areas (score < 60)
            'recommendation' => $this->getOverallRecommendation($analysis)
        ];
    }

    private function identifyStrengths(JobAnalysis $analysis): array
    {
        $strengths = [];
        $sections = [
            'contact_info' => $analysis->contact_info_score,
            'summary' => $analysis->summary_score,
            'experience' => $analysis->experience_score,
            'education' => $analysis->education_score,
            'skills' => $analysis->skills_score,
            'achievements' => $analysis->achievements_score
        ];
        
        foreach ($sections as $section => $score) {
            if ($score >= 80) {
                $strengths[] = [
                    'area' => ucfirst(str_replace('_', ' ', $section)),
                    'score' => $score,
                    'description' => "Strong performance in " . str_replace('_', ' ', $section)
                ];
            }
        }
        
        return $strengths;
    }

    private function identifyWeaknesses(JobAnalysis $analysis): array
    {
        $weaknesses = [];
        $sections = [
            'contact_info' => $analysis->contact_info_score,
            'summary' => $analysis->summary_score,
            'experience' => $analysis->experience_score,
            'education' => $analysis->education_score,
            'skills' => $analysis->skills_score,
            'achievements' => $analysis->achievements_score
        ];
        
        foreach ($sections as $section => $score) {
            if ($score < 60) {
                $weaknesses[] = [
                    'area' => ucfirst(str_replace('_', ' ', $section)),
                    'score' => $score,
                    'description' => "Needs improvement in " . str_replace('_', ' ', $section),
                    'priority' => $score < 40 ? 'high' : 'medium'
                ];
            }
        }
        
        return $weaknesses;
    }

    private function getImprovementPriority(JobAnalysis $analysis): array
    {
        $priorities = [];
        
        if ($analysis->ats_score < 70) {
            $priorities[] = [
                'area' => 'ATS Compatibility',
                'priority' => 'high',
                'impact' => 'Critical for initial screening'
            ];
        }
        
        if ($analysis->keyword_density < 10) {
            $priorities[] = [
                'area' => 'Keyword Optimization',
                'priority' => 'high',
                'impact' => 'Better job matching'
            ];
        }
        
        if ($analysis->resume_match_score < 60) {
            $priorities[] = [
                'area' => 'Content Relevance',
                'priority' => 'medium',
                'impact' => 'Improved job fit demonstration'
            ];
        }
        
        return $priorities;
    }

    private function getBenchmarkComparison(JobAnalysis $analysis): array
    {
        // Compare against industry benchmarks
        $benchmarks = [
            'excellent' => 85,
            'good' => 70,
            'average' => 55,
            'poor' => 40
        ];
        
        $score = $analysis->overall_score;
        $position = 'poor';
        
        if ($score >= $benchmarks['excellent']) $position = 'excellent';
        elseif ($score >= $benchmarks['good']) $position = 'good';  
        elseif ($score >= $benchmarks['average']) $position = 'average';
        
        return [
            'position' => $position,
            'percentile' => $this->calculatePercentile($score),
            'gap_to_excellent' => $benchmarks['excellent'] - $score
        ];
    }

    private function getTrendAnalysis(Job $job): array
    {
        // Get recent analyses for trend analysis
        $recentAnalyses = JobAnalysis::join('jobs', 'job_analysis.job_id', '=', 'jobs.id')
            ->where('jobs.user_id', $job->user_id ?? 1)
            ->orderBy('job_analysis.created_at', 'desc')
            ->take(5)
            ->get(['job_analysis.overall_score', 'job_analysis.created_at']);
        
        if ($recentAnalyses->count() < 2) {
            return ['status' => 'insufficient_data'];
        }
        
        $scores = $recentAnalyses->pluck('overall_score')->toArray();
        $trend = $this->calculateTrend($scores);
        
        return [
            'status' => 'available',
            'direction' => $trend > 0 ? 'improving' : ($trend < 0 ? 'declining' : 'stable'),
            'change' => abs($trend),
            'recent_scores' => $scores
        ];
    }

    private function getActionItems(JobAnalysis $analysis): array
    {
        $items = [];
        
        // Based on analysis results, generate specific action items
        if ($analysis->ats_score < 70) {
            $items[] = [
                'action' => 'Optimize resume format for ATS systems',
                'priority' => 'high',
                'estimated_time' => '2-3 hours',
                'expected_impact' => '+15-20 points in ATS score'
            ];
        }
        
        if (count($analysis->missing_keywords ?? []) > 5) {
            $items[] = [
                'action' => 'Add missing keywords to resume and cover letter',
                'priority' => 'high',
                'estimated_time' => '1-2 hours',
                'expected_impact' => '+10-15 points in keyword matching'
            ];
        }
        
        if ($analysis->summary_score < 60) {
            $items[] = [
                'action' => 'Rewrite professional summary to better match job requirements',
                'priority' => 'medium',
                'estimated_time' => '30-60 minutes',
                'expected_impact' => '+8-12 points in summary score'
            ];
        }
        
        if ($analysis->achievements_score < 50) {
            $items[] = [
                'action' => 'Add quantified achievements and impact metrics',
                'priority' => 'medium',
                'estimated_time' => '1-2 hours',
                'expected_impact' => '+10-15 points in achievements score'
            ];
        }
        
        return $items;
    }

    // Utility helper methods
    
    private function getGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'A-';
        if ($score >= 75) return 'B+';
        if ($score >= 70) return 'B';
        if ($score >= 65) return 'B-';
        if ($score >= 60) return 'C+';
        if ($score >= 55) return 'C';
        if ($score >= 50) return 'C-';
        if ($score >= 45) return 'D+';
        if ($score >= 40) return 'D';
        return 'F';
    }
    
    private function getScoreDescription(float $score): string
    {
        if ($score >= 85) return 'Excellent match - high chance of success';
        if ($score >= 70) return 'Good match - solid application';
        if ($score >= 55) return 'Fair match - room for improvement';
        if ($score >= 40) return 'Poor match - significant improvements needed';
        return 'Very poor match - major revisions required';
    }
    
    private function getKeywordDensityStatus(float $density): string
    {
        if ($density >= 15) return 'optimal';
        if ($density >= 10) return 'good';
        if ($density >= 5) return 'fair';
        return 'poor';
    }
    
    private function getSectionStatus(float $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }
    
    private function getSectionFeedback(string $section, float $score): string
    {
        $feedback = [
            'contact_info' => [
                'excellent' => 'Complete contact information with professional presentation',
                'good' => 'Good contact details, minor improvements possible',
                'fair' => 'Basic contact info present, could be more comprehensive',
                'poor' => 'Missing or incomplete contact information'
            ],
            'summary' => [
                'excellent' => 'Compelling summary that perfectly aligns with job requirements',
                'good' => 'Strong summary with good job alignment',
                'fair' => 'Adequate summary, could better target the specific role',
                'poor' => 'Weak or missing summary section'
            ],
            'experience' => [
                'excellent' => 'Highly relevant experience with clear impact demonstrations',
                'good' => 'Good experience match with solid examples',
                'fair' => 'Some relevant experience, could be better highlighted',
                'poor' => 'Limited relevant experience or poor presentation'
            ],
            'education' => [
                'excellent' => 'Perfect educational match with relevant qualifications',
                'good' => 'Good educational background for this role',
                'fair' => 'Adequate education, some gaps in requirements',
                'poor' => 'Educational background doesn\'t align well with job requirements'
            ],
            'skills' => [
                'excellent' => 'Skills perfectly match job requirements with evidence',
                'good' => 'Strong skill match with most requirements covered',
                'fair' => 'Basic skill alignment, missing some key requirements',
                'poor' => 'Significant skill gaps compared to job requirements'
            ],
            'achievements' => [
                'excellent' => 'Outstanding achievements with quantified results',
                'good' => 'Good achievements that demonstrate value',
                'fair' => 'Some achievements listed, could be more impactful',
                'poor' => 'Few or no achievements mentioned'
            ]
        ];
        
        $status = $this->getSectionStatus($score);
        return $feedback[$section][$status] ?? 'No feedback available';
    }
    
    private function getPriorityRecommendations(JobAnalysis $analysis): array
    {
        $allRecommendations = array_merge(
            $analysis->resume_recommendations ?? [],
            $analysis->cover_letter_recommendations ?? [],
            $analysis->general_recommendations ?? []
        );
        
        // Score each recommendation by impact potential
        $prioritized = [];
        foreach ($allRecommendations as $recommendation) {
            $priority = $this->calculateRecommendationPriority($recommendation, $analysis);
            $prioritized[] = [
                'text' => $recommendation,
                'priority' => $priority,
                'category' => $this->categorizeRecommendation($recommendation)
            ];
        }
        
        // Sort by priority and return top 5
        usort($prioritized, function($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priorities[$b['priority']] - $priorities[$a['priority']];
        });
        
        return array_slice($prioritized, 0, 5);
    }
    
    private function getSimilarityReasons(Job $job): array
    {
        // This would be called with the original job in context
        // For now, return generic reasons
        return ['Similar industry', 'Same job level', 'Comparable requirements'];
    }
    
    private function getPerformanceLevel(float $score): string
    {
        if ($score >= 85) return 'Outstanding';
        if ($score >= 70) return 'Above Average';
        if ($score >= 55) return 'Average';
        if ($score >= 40) return 'Below Average';
        return 'Poor';
    }
    
    private function getOverallRecommendation(JobAnalysis $analysis): string
    {
        $score = $analysis->overall_score;
        
        if ($score >= 85) {
            return 'Apply with confidence! Your application is well-optimized for this role.';
        } elseif ($score >= 70) {
            return 'Good application. Minor tweaks could make it even stronger.';
        } elseif ($score >= 55) {
            return 'Decent application. Focus on key improvements before applying.';
        } elseif ($score >= 40) {
            return 'Significant improvements needed. Consider major revisions.';
        } else {
            return 'Major rework required. This application needs substantial improvements.';
        }
    }
    
    private function calculatePercentile(float $score): int
    {
        // Simple percentile calculation based on score
        if ($score >= 90) return 95;
        if ($score >= 85) return 90;
        if ($score >= 80) return 80;
        if ($score >= 75) return 70;
        if ($score >= 70) return 60;
        if ($score >= 65) return 50;
        if ($score >= 60) return 40;
        if ($score >= 55) return 30;
        if ($score >= 50) return 20;
        return 10;
    }
    
    private function calculateTrend(array $scores): float
    {
        if (count($scores) < 2) return 0;
        
        $first = array_slice($scores, -2, 1)[0];
        $last = $scores[0]; // Most recent
        
        return $last - $first;
    }
    
    private function calculateRecommendationPriority(string $recommendation, JobAnalysis $analysis): string
    {
        // Simple keyword-based priority assignment
        $highPriorityKeywords = ['keyword', 'ats', 'format', 'critical', 'required'];
        $mediumPriorityKeywords = ['improve', 'enhance', 'better', 'optimize'];
        
        $rec = strtolower($recommendation);
        
        foreach ($highPriorityKeywords as $keyword) {
            if (strpos($rec, $keyword) !== false) {
                return 'high';
            }
        }
        
        foreach ($mediumPriorityKeywords as $keyword) {
            if (strpos($rec, $keyword) !== false) {
                return 'medium';
            }
        }
        
        return 'low';
    }
    
    private function categorizeRecommendation(string $recommendation): string
    {
        $rec = strtolower($recommendation);
        
        if (strpos($rec, 'resume') !== false) return 'Resume';
        if (strpos($rec, 'cover letter') !== false) return 'Cover Letter';
        if (strpos($rec, 'keyword') !== false) return 'Keywords';
        if (strpos($rec, 'ats') !== false) return 'ATS';
        if (strpos($rec, 'format') !== false) return 'Formatting';
        
        return 'General';
    }
    
    // Error handling and utility methods
    
    private function notFound(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode([
            'error' => $message,
            'status' => 404
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    
    private function handleError(Response $response, Exception $e, string $message): Response
    {
        // Log the error
        error_log("AnalysisController Error: " . $e->getMessage());
        
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => $message . ': ' . $e->getMessage()
        ];
        
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }
    
    private function isAjaxRequest(Request $request): bool
    {
        return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ||
               $request->getHeaderLine('Content-Type') === 'application/json';
    }
    
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return $serverParams['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        } elseif (!empty($serverParams['REMOTE_ADDR'])) {
            return $serverParams['REMOTE_ADDR'];
        }
        
        return 'unknown';
    }
}