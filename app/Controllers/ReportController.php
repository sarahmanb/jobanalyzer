<?php
// app/Controllers/ReportController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Job;
use App\Models\JobAnalysis;
use App\Models\AnalysisLog;
use App\Services\ReportGeneratorService;
use Exception;
use DateTime;

class ReportController
{
    private $reportGenerator;
    private $view;
    
    public function __construct(ReportGeneratorService $reportGenerator = null, $view = null)
    {
        $this->reportGenerator = $reportGenerator ?: new ReportGeneratorService($view);
        $this->view = $view;
    }

    /**
     * Generate PDF report for a specific job analysis
     */
    public function generatePDF(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $job = Job::with('analysis')->find($jobId);
            
            if (!$job) {
                return $this->notFound($response, 'Job not found');
            }

            if (!$job->analysis) {
                return $this->badRequest($response, 'Job has not been analyzed yet');
            }

            // Get query parameters for report customization
            $queryParams = $request->getQueryParams();
            $reportType = $queryParams['type'] ?? 'standard'; // standard, detailed, summary
            $includeCharts = filter_var($queryParams['charts'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            $includeRecommendations = filter_var($queryParams['recommendations'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            
            // Generate PDF
            $pdfContent = $this->reportGenerator->generatePDFReport($job, [
                'type' => $reportType,
                'include_charts' => $includeCharts,
                'include_recommendations' => $includeRecommendations,
                'show_benchmarks' => true,
                'show_trends' => true
            ]);

            // Set response headers
            $filename = $this->generateFilename($job, 'pdf');
            
            $response->getBody()->write($pdfContent);
            return $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->withHeader('Content-Length', strlen($pdfContent));
                
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate PDF report');
        }
    }

    /**
     * Generate detailed analysis report with comprehensive insights
     */
    public function generateDetailed(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $job = Job::with(['analysis', 'analysisLogs'])->find($jobId);
            
            if (!$job || !$job->analysis) {
                return $this->notFound($response, 'Job or analysis not found');
            }

            $queryParams = $request->getQueryParams();
            $format = $queryParams['format'] ?? 'html'; // html, pdf, docx
            
            // Prepare comprehensive report data
            $reportData = [
                'job' => $job,
                'analysis' => $job->analysis,
                'executive_summary' => $this->generateExecutiveSummary($job),
                'detailed_breakdown' => $this->generateDetailedBreakdown($job->analysis),
                'improvement_roadmap' => $this->generateImprovementRoadmap($job->analysis),
                'benchmark_analysis' => $this->generateBenchmarkAnalysis($job->analysis),
                'keyword_analysis' => $this->generateKeywordAnalysis($job->analysis),
                'section_analysis' => $this->generateSectionAnalysis($job->analysis),
                'recommendations' => $this->generateRecommendations($job->analysis),
                'action_plan' => $this->generateActionPlan($job->analysis),
                'appendices' => $this->generateAppendices($job),
                'generated_at' => new DateTime(),
                'report_metadata' => [
                    'version' => '2.0',
                    'type' => 'detailed',
                    'analysis_date' => $job->analysis->created_at,
                    'job_id' => $jobId
                ]
            ];

            // Generate report based on format
            switch ($format) {
                case 'pdf':
                    $content = $this->reportGenerator->generateDetailedPDFReport($reportData);
                    $filename = $this->generateFilename($job, 'pdf', 'detailed');
                    
                    $response->getBody()->write($content);
                    return $response
                        ->withHeader('Content-Type', 'application/pdf')
                        ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                        
                case 'docx':
                    $content = $this->reportGenerator->generateWordReport($reportData);
                    $filename = $this->generateFilename($job, 'docx', 'detailed');
                    
                    $response->getBody()->write($content);
                    return $response
                        ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                        ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                        
                case 'html':
                default:
                    $html = $this->reportGenerator->generateHTMLReport($reportData);
                    $response->getBody()->write($html);
                    return $response->withHeader('Content-Type', 'text/html');
            }
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate detailed report');
        }
    }

    /**
     * Export analysis data in various formats
     */
    public function exportData(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $format = $args['format'] ?? 'json'; // json, csv, xlsx, xml
            
            $job = Job::with('analysis')->find($jobId);
            
            if (!$job || !$job->analysis) {
                return $this->notFound($response, 'Job or analysis not found');
            }

            $queryParams = $request->getQueryParams();
            $includeRawData = filter_var($queryParams['raw'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            $includeMetadata = filter_var($queryParams['metadata'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            
            // Prepare export data
            $exportData = $this->prepareExportData($job, $includeRawData, $includeMetadata);
            
            switch (strtolower($format)) {
                case 'csv':
                    return $this->exportCSV($response, $exportData, $job);
                    
                case 'xlsx':
                    return $this->exportExcel($response, $exportData, $job);
                    
                case 'xml':
                    return $this->exportXML($response, $exportData, $job);
                    
                case 'json':
                default:
                    return $this->exportJSON($response, $exportData, $job);
            }
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to export data');
        }
    }

    /**
     * Generate bulk reports for multiple jobs
     */
    public function generateBulk(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $request->getParsedBody();
            $jobIds = $data['job_ids'] ?? [];
            $reportType = $data['report_type'] ?? 'summary'; // summary, detailed, comparison
            $format = $data['format'] ?? 'pdf'; // pdf, xlsx, zip
            
            if (empty($jobIds) || !is_array($jobIds)) {
                return $this->badRequest($response, 'No job IDs provided');
            }

            // Validate jobs exist and are analyzed
            $jobs = Job::with('analysis')->whereIn('id', $jobIds)->get();
            $validJobs = $jobs->filter(function($job) {
                return $job->analysis !== null;
            });

            if ($validJobs->isEmpty()) {
                return $this->badRequest($response, 'No valid analyzed jobs found');
            }

            // Generate bulk report based on type
            switch ($reportType) {
                case 'comparison':
                    return $this->generateComparisonReport($response, $validJobs, $format);
                    
                case 'detailed':
                    return $this->generateBulkDetailedReport($response, $validJobs, $format);
                    
                case 'summary':
                default:
                    return $this->generateBulkSummaryReport($response, $validJobs, $format);
            }
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate bulk reports');
        }
    }

    /**
     * Generate portfolio report showing all user's job applications
     */
    public function generatePortfolio(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $userId = $queryParams['user_id'] ?? 1; // Default user
            $format = $queryParams['format'] ?? 'pdf';
            $timeframe = $queryParams['timeframe'] ?? 'all'; // all, last_30_days, last_90_days, last_year
            
            // Get jobs based on timeframe
            $jobs = $this->getJobsByTimeframe($userId, $timeframe);
            
            if ($jobs->isEmpty()) {
                return $this->badRequest($response, 'No jobs found for the specified timeframe');
            }

            // Prepare portfolio data
            $portfolioData = [
                'user_id' => $userId,
                'timeframe' => $timeframe,
                'jobs' => $jobs,
                'summary_statistics' => $this->generatePortfolioStatistics($jobs),
                'performance_trends' => $this->generatePerformanceTrends($jobs),
                'skill_analysis' => $this->generateSkillAnalysis($jobs),
                'industry_breakdown' => $this->generateIndustryBreakdown($jobs),
                'success_patterns' => $this->identifySuccessPatterns($jobs),
                'improvement_areas' => $this->identifyImprovementAreas($jobs),
                'generated_at' => new DateTime()
            ];

            // Generate portfolio report
            switch ($format) {
                case 'pdf':
                    $content = $this->reportGenerator->generatePortfolioPDF($portfolioData);
                    $filename = "job_portfolio_" . date('Y-m-d') . ".pdf";
                    
                    $response->getBody()->write($content);
                    return $response
                        ->withHeader('Content-Type', 'application/pdf')
                        ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                        
                case 'xlsx':
                    $content = $this->reportGenerator->generatePortfolioExcel($portfolioData);
                    $filename = "job_portfolio_" . date('Y-m-d') . ".xlsx";
                    
                    $response->getBody()->write($content);
                    return $response
                        ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                        ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                        
                default:
                    return $this->badRequest($response, 'Unsupported format');
            }
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate portfolio report');
        }
    }

    /**
     * Generate analytics dashboard data for reporting
     */
    public function generateAnalytics(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $userId = $queryParams['user_id'] ?? 1;
            $startDate = $queryParams['start_date'] ?? date('Y-m-d', strtotime('-6 months'));
            $endDate = $queryParams['end_date'] ?? date('Y-m-d');
            $format = $queryParams['format'] ?? 'json';
            
            // Generate comprehensive analytics
            $analytics = [
                'time_period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'overview' => $this->generateAnalyticsOverview($userId, $startDate, $endDate),
                'performance_metrics' => $this->generatePerformanceMetrics($userId, $startDate, $endDate),
                'trend_analysis' => $this->generateTrendAnalysis($userId, $startDate, $endDate),
                'comparative_analysis' => $this->generateComparativeAnalysis($userId, $startDate, $endDate),
                'success_factors' => $this->identifySuccessFactors($userId, $startDate, $endDate),
                'recommendations' => $this->generateAnalyticsRecommendations($userId, $startDate, $endDate),
                'charts_data' => $this->generateChartsData($userId, $startDate, $endDate),
                'generated_at' => new DateTime()
            ];

            switch ($format) {
                case 'pdf':
                    $content = $this->reportGenerator->generateAnalyticsPDF($analytics);
                    $filename = "analytics_report_" . date('Y-m-d') . ".pdf";
                    
                    $response->getBody()->write($content);
                    return $response
                        ->withHeader('Content-Type', 'application/pdf')
                        ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                        
                case 'json':
                default:
                    $response->getBody()->write(json_encode($analytics, JSON_PRETTY_PRINT));
                    return $response->withHeader('Content-Type', 'application/json');
            }
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate analytics');
        }
    }

    /**
     * Preview report before generation
     */
    public function previewReport(Request $request, Response $response, array $args): Response
    {
        try {
            $jobId = (int)$args['id'];
            $job = Job::with('analysis')->find($jobId);
            
            if (!$job || !$job->analysis) {
                return $this->notFound($response, 'Job or analysis not found');
            }

            $queryParams = $request->getQueryParams();
            $reportType = $queryParams['type'] ?? 'standard';
            
            // Generate preview data (lighter version)
            $previewData = [
                'job' => [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'created_at' => $job->created_at->format('M j, Y')
                ],
                'analysis_summary' => [
                    'overall_score' => $job->analysis->overall_score,
                    'ats_score' => $job->analysis->ats_score,
                    'resume_match_score' => $job->analysis->resume_match_score,
                    'cover_letter_match_score' => $job->analysis->cover_letter_match_score,
                    'recommendation' => $job->analysis->ai_recommendation
                ],
                'sections_preview' => $this->generateSectionsPreview($job->analysis),
                'recommendations_count' => $this->countRecommendations($job->analysis),
                'report_metadata' => [
                    'type' => $reportType,
                    'estimated_pages' => $this->estimatePages($job->analysis, $reportType),
                    'estimated_size' => $this->estimateFileSize($job->analysis, $reportType)
                ]
            ];

            $response->getBody()->write(json_encode($previewData, JSON_PRETTY_PRINT));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            return $this->handleError($response, $e, 'Failed to generate report preview');
        }
    }

    // Private helper methods for report generation

    private function generateExecutiveSummary(Job $job): array
    {
        $analysis = $job->analysis;
        
        return [
            'overall_assessment' => $this->getOverallAssessment($analysis->overall_score),
            'key_strengths' => $this->identifyKeyStrengths($analysis),
            'primary_concerns' => $this->identifyPrimaryConcerns($analysis),
            'recommendation' => $this->getExecutiveRecommendation($analysis),
            'success_probability' => [
                'interview' => $analysis->interview_probability,
                'job_offer' => $analysis->job_securing_probability
            ],
            'next_steps' => $this->generateNextSteps($analysis)
        ];
    }

    private function generateDetailedBreakdown(JobAnalysis $analysis): array
    {
        return [
            'scoring_methodology' => [
                'overall_calculation' => 'Weighted average of all components',
                'weights' => [
                    'ats_compatibility' => '25%',
                    'keyword_matching' => '30%',
                    'content_quality' => '25%',
                    'structure_organization' => '20%'
                ]
            ],
            'section_scores' => [
                'contact_information' => [
                    'score' => $analysis->contact_info_score,
                    'analysis' => $this->analyzeSectionPerformance('contact_info', $analysis->contact_info_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->contact_info_score)
                ],
                'professional_summary' => [
                    'score' => $analysis->summary_score,
                    'analysis' => $this->analyzeSectionPerformance('summary', $analysis->summary_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->summary_score)
                ],
                'work_experience' => [
                    'score' => $analysis->experience_score,
                    'analysis' => $this->analyzeSectionPerformance('experience', $analysis->experience_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->experience_score)
                ],
                'education' => [
                    'score' => $analysis->education_score,
                    'analysis' => $this->analyzeSectionPerformance('education', $analysis->education_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->education_score)
                ],
                'skills' => [
                    'score' => $analysis->skills_score,
                    'analysis' => $this->analyzeSectionPerformance('skills', $analysis->skills_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->skills_score)
                ],
                'achievements' => [
                    'score' => $analysis->achievements_score,
                    'analysis' => $this->analyzeSectionPerformance('achievements', $analysis->achievements_score),
                    'improvement_potential' => $this->calculateImprovementPotential($analysis->achievements_score)
                ]
            ],
            'technical_analysis' => [
                'ats_compatibility' => $this->generateATSAnalysis($analysis),
                'keyword_optimization' => $this->generateKeywordOptimization($analysis),
                'content_structure' => $this->generateContentStructureAnalysis($analysis)
            ]
        ];
    }

    private function generateImprovementRoadmap(JobAnalysis $analysis): array
    {
        $roadmap = [];
        
        // Phase 1: Critical Issues (0-2 weeks)
        $criticalIssues = [];
        if ($analysis->ats_score < 60) {
            $criticalIssues[] = [
                'issue' => 'ATS Compatibility',
                'priority' => 'Critical',
                'timeframe' => '1-2 days',
                'effort' => 'Low',
                'impact' => 'High',
                'actions' => [
                    'Use standard section headers',
                    'Remove complex formatting',
                    'Ensure consistent font usage',
                    'Check file format compatibility'
                ]
            ];
        }
        
        if (count($analysis->missing_keywords ?? []) > 10) {
            $criticalIssues[] = [
                'issue' => 'Keyword Gap',
                'priority' => 'Critical',
                'timeframe' => '2-3 days',
                'effort' => 'Medium',
                'impact' => 'High',
                'actions' => [
                    'Add top 5 missing keywords',
                    'Integrate keywords naturally',
                    'Update skills section',
                    'Revise job descriptions'
                ]
            ];
        }
        
        $roadmap['phase_1_critical'] = $criticalIssues;
        
        // Phase 2: Important Improvements (2-4 weeks)
        $importantImprovements = [];
        if ($analysis->summary_score < 70) {
            $importantImprovements[] = [
                'issue' => 'Professional Summary',
                'priority' => 'Important',
                'timeframe' => '1 week',
                'effort' => 'Medium',
                'impact' => 'Medium',
                'actions' => [
                    'Rewrite to match job requirements',
                    'Include key achievements',
                    'Quantify impact where possible',
                    'Align with job description language'
                ]
            ];
        }
        
        $roadmap['phase_2_important'] = $importantImprovements;
        
        // Phase 3: Optimization (4+ weeks)
        $optimizations = [];
        if ($analysis->achievements_score < 80) {
            $optimizations[] = [
                'issue' => 'Achievement Highlighting',
                'priority' => 'Optimization',
                'timeframe' => '2-3 weeks',
                'effort' => 'High',
                'impact' => 'Medium',
                'actions' => [
                    'Identify quantifiable achievements',
                    'Use STAR method for descriptions',
                    'Add metrics and percentages',
                    'Highlight leadership examples'
                ]
            ];
        }
        
        $roadmap['phase_3_optimization'] = $optimizations;
        
        return $roadmap;
    }

    private function generateBenchmarkAnalysis(JobAnalysis $analysis): array
    {
        // Get industry benchmarks
        $industryAverages = JobAnalysis::selectRaw('
            AVG(overall_score) as avg_overall,
            AVG(ats_score) as avg_ats,
            AVG(resume_match_score) as avg_resume,
            AVG(cover_letter_match_score) as avg_cover_letter,
            STDDEV(overall_score) as std_overall
        ')->first();
        
        return [
            'your_performance' => [
                'overall_score' => $analysis->overall_score,
                'percentile' => $this->calculatePercentile($analysis->overall_score),
                'grade' => $this->getGrade($analysis->overall_score)
            ],
            'industry_comparison' => [
                'industry_average' => round($industryAverages->avg_overall ?? 0, 1),
                'your_advantage' => round($analysis->overall_score - ($industryAverages->avg_overall ?? 0), 1),
                'performance_category' => $this->getPerformanceCategory($analysis->overall_score, $industryAverages->avg_overall ?? 0)
            ],
            'detailed_comparison' => [
                'ats_score' => [
                    'your_score' => $analysis->ats_score,
                    'industry_avg' => round($industryAverages->avg_ats ?? 0, 1),
                    'gap' => round($analysis->ats_score - ($industryAverages->avg_ats ?? 0), 1)
                ],
                'resume_match' => [
                    'your_score' => $analysis->resume_match_score,
                    'industry_avg' => round($industryAverages->avg_resume ?? 0, 1),
                    'gap' => round($analysis->resume_match_score - ($industryAverages->avg_resume ?? 0), 1)
                ],
                'cover_letter_match' => [
                    'your_score' => $analysis->cover_letter_match_score,
                    'industry_avg' => round($industryAverages->avg_cover_letter ?? 0, 1),
                    'gap' => round($analysis->cover_letter_match_score - ($industryAverages->avg_cover_letter ?? 0), 1)
                ]
            ]
        ];
    }

    private function generateKeywordAnalysis(JobAnalysis $analysis): array
    {
        $matchingKeywords = $analysis->matching_keywords ?? [];
        $missingKeywords = $analysis->missing_keywords ?? [];
        $suggestedKeywords = $analysis->suggested_keywords ?? [];
        
        return [
            'summary' => [
                'total_relevant_keywords' => count($matchingKeywords) + count($missingKeywords),
                'keywords_found' => count($matchingKeywords),
                'keywords_missing' => count($missingKeywords),
                'optimization_score' => $analysis->keyword_density ?? 0
            ],
            'keyword_categories' => [
                'technical_skills' => $this->categorizeKeywords($matchingKeywords, 'technical'),
                'soft_skills' => $this->categorizeKeywords($matchingKeywords, 'soft'),
                'industry_terms' => $this->categorizeKeywords($matchingKeywords, 'industry'),
                'tools_technologies' => $this->categorizeKeywords($matchingKeywords, 'tools')
            ],
            'missing_opportunities' => [
                'high_priority' => array_slice($missingKeywords, 0, 5),
                'medium_priority' => array_slice($missingKeywords, 5, 5),
                'suggested_additions' => $suggestedKeywords
            ],
            'implementation_guide' => [
                'where_to_add' => [
                    'professional_summary' => 'Include 3-4 key terms',
                    'skills_section' => 'Add missing technical skills',
                    'experience_descriptions' => 'Naturally integrate keywords',
                    'achievements' => 'Use industry-specific terminology'
                ],
                'density_recommendations' => [
                    'current_density' => $analysis->keyword_density ?? 0,
                    'target_density' => '12-15%',
                    'keywords_to_add' => max(0, ceil((15 - ($analysis->keyword_density ?? 0)) * 0.5))
                ]
            ]
        ];
    }

    private function generateSectionAnalysis(JobAnalysis $analysis): array
    {
        $sections = [
            'contact_info' => $analysis->contact_info_score,
            'summary' => $analysis->summary_score,
            'experience' => $analysis->experience_score,
            'education' => $analysis->education_score,
            'skills' => $analysis->skills_score,
            'achievements' => $analysis->achievements_score
        ];
        
        $sectionAnalysis = [];
        
        foreach ($sections as $section => $score) {
            $sectionAnalysis[$section] = [
                'current_score' => $score,
                'grade' => $this->getGrade($score),
                'status' => $this->getSectionStatus($score),
                'improvement_potential' => 100 - $score,
                'priority_level' => $this->getPriorityLevel($score),
                'specific_recommendations' => $this->getSectionSpecificRecommendations($section, $score),
                'best_practices' => $this->getSectionBestPractices($section),
                'common_mistakes' => $this->getSectionCommonMistakes($section)
            ];
        }
        
        return $sectionAnalysis;
    }

    private function generateRecommendations(JobAnalysis $analysis): array
    {
        return [
            'immediate_actions' => [
                'description' => 'Critical changes needed within 1-2 days',
                'items' => $this->getImmediateActions($analysis)
            ],
            'short_term_improvements' => [
                'description' => 'Important improvements over 1-2 weeks',
                'items' => $this->getShortTermImprovements($analysis)
            ],
            'long_term_optimization' => [
                'description' => 'Strategic improvements for ongoing success',
                'items' => $this->getLongTermOptimizations($analysis)
            ],
            'categorized_recommendations' => [
                'resume' => $analysis->resume_recommendations ?? [],
                'cover_letter' => $analysis->cover_letter_recommendations ?? [],
                'general' => $analysis->general_recommendations ?? []
            ]
        ];
    }

    private function generateActionPlan(JobAnalysis $analysis): array
    {
        $actionPlan = [];
        
        // Week 1: Critical fixes
        $week1 = [];
        if ($analysis->ats_score < 70) {
            $week1[] = [
                'task' => 'Fix ATS compatibility issues',
                'description' => 'Update formatting and structure for better ATS parsing',
                'time_required' => '2-3 hours',
                'difficulty' => 'Easy',
                'impact' => 'High',
                'resources' => ['ATS-friendly template', 'Formatting guidelines']
            ];
        }
        
        if (count($analysis->missing_keywords ?? []) > 5) {
            $week1[] = [
                'task' => 'Add critical missing keywords',
                'description' => 'Integrate top 5 missing keywords naturally throughout resume',
                'time_required' => '1-2 hours',
                'difficulty' => 'Medium',
                'impact' => 'High',
                'resources' => ['Job description analysis', 'Keyword integration guide']
            ];
        }
        
        $actionPlan['week_1'] = $week1;
        
        // Week 2-3: Content improvements
        $week2_3 = [];
        if ($analysis->summary_score < 70) {
            $week2_3[] = [
                'task' => 'Rewrite professional summary',
                'description' => 'Create compelling summary that aligns with job requirements',
                'time_required' => '2-4 hours',
                'difficulty' => 'Medium',
                'impact' => 'Medium',
                'resources' => ['Summary templates', 'Industry examples']
            ];
        }
        
        $actionPlan['weeks_2-3'] = $week2_3;
        
        // Week 4+: Advanced optimization
        $week4_plus = [];
        if ($analysis->achievements_score < 80) {
            $week4_plus[] = [
                'task' => 'Enhance achievement descriptions',
                'description' => 'Add quantified results and impact metrics to experience',
                'time_required' => '4-6 hours',
                'difficulty' => 'Hard',
                'impact' => 'Medium',
                'resources' => ['STAR method guide', 'Achievement examples', 'Metrics calculator']
            ];
        }
        
        $actionPlan['weeks_4_plus'] = $week4_plus;
        
        return $actionPlan;
    }

    private function generateAppendices(Job $job): array
    {
        return [
            'raw_analysis_data' => [
                'scores' => [
                    'overall' => $job->analysis->overall_score,
                    'ats' => $job->analysis->ats_score,
                    'resume_match' => $job->analysis->resume_match_score,
                    'cover_letter_match' => $job->analysis->cover_letter_match_score,
                    'sections' => [
                        'contact_info' => $job->analysis->contact_info_score,
                        'summary' => $job->analysis->summary_score,
                        'experience' => $job->analysis->experience_score,
                        'education' => $job->analysis->education_score,
                        'skills' => $job->analysis->skills_score,
                        'achievements' => $job->analysis->achievements_score
                    ]
                ],
                'keywords' => [
                    'matching' => $job->analysis->matching_keywords ?? [],
                    'missing' => $job->analysis->missing_keywords ?? [],
                    'suggested' => $job->analysis->suggested_keywords ?? [],
                    'density' => $job->analysis->keyword_density ?? 0
                ]
            ],
            'job_details' => [
                'title' => $job->job_title,
                'company' => $job->company_name,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'job_level' => $job->job_level,
                'industry' => $job->industry
            ],
            'analysis_metadata' => [
                'analysis_type' => $job->analysis->analysis_type,
                'analysis_duration' => $job->analysis->analysis_duration,
                'confidence_level' => $job->analysis->ai_confidence_level,
                'created_at' => $job->analysis->created_at->format('Y-m-d H:i:s')
            ],
            'glossary' => $this->generateGlossary(),
            'resources' => $this->generateResourcesList()
        ];
    }

    // Export helper methods

    private function prepareExportData(Job $job, bool $includeRawData, bool $includeMetadata): array
    {
        $data = [
            'job_information' => [
                'id' => $job->id,
                'title' => $job->job_title,
                'company' => $job->company_name,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'job_level' => $job->job_level,
                'created_at' => $job->created_at->toISOString()
            ],
            'analysis_results' => [
                'overall_score' => $job->analysis->overall_score,
                'ats_score' => $job->analysis->ats_score,
                'resume_match_score' => $job->analysis->resume_match_score,
                'cover_letter_match_score' => $job->analysis->cover_letter_match_score,
                'interview_probability' => $job->analysis->interview_probability,
                'job_securing_probability' => $job->analysis->job_securing_probability,
                'ai_recommendation' => $job->analysis->ai_recommendation
            ],
            'section_scores' => [
                'contact_info' => $job->analysis->contact_info_score,
                'summary' => $job->analysis->summary_score,
                'experience' => $job->analysis->experience_score,
                'education' => $job->analysis->education_score,
                'skills' => $job->analysis->skills_score,
                'achievements' => $job->analysis->achievements_score
            ],
            'keyword_analysis' => [
                'matching_keywords' => $job->analysis->matching_keywords ?? [],
                'missing_keywords' => $job->analysis->missing_keywords ?? [],
                'suggested_keywords' => $job->analysis->suggested_keywords ?? [],
                'keyword_density' => $job->analysis->keyword_density ?? 0
            ],
            'recommendations' => [
                'resume' => $job->analysis->resume_recommendations ?? [],
                'cover_letter' => $job->analysis->cover_letter_recommendations ?? [],
                'general' => $job->analysis->general_recommendations ?? []
            ]
        ];

        if ($includeRawData) {
            $data['raw_data'] = [
                'skill_match_analysis' => $job->analysis->skill_match_analysis ?? [],
                'experience_gap_analysis' => $job->analysis->experience_gap_analysis ?? [],
                'education_match_analysis' => $job->analysis->education_match_analysis ?? []
            ];
        }

        if ($includeMetadata) {
            $data['metadata'] = [
                'analysis_type' => $job->analysis->analysis_type,
                'analysis_duration' => $job->analysis->analysis_duration,
                'ai_confidence_level' => $job->analysis->ai_confidence_level,
                'analysis_date' => $job->analysis->created_at->toISOString(),
                'export_date' => (new DateTime())->format('c'),
                'version' => '2.0'
            ];
        }

        return $data;
    }

    private function exportJSON(Response $response, array $data, Job $job): Response
    {
        $filename = $this->generateFilename($job, 'json');
        
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportCSV(Response $response, array $data, Job $job): Response
    {
        $filename = $this->generateFilename($job, 'csv');
        
        // Flatten data for CSV format
        $csvData = [];
        $csvData[] = ['Field', 'Value'];
        
        // Job information
        foreach ($data['job_information'] as $key => $value) {
            $csvData[] = ['job_' . $key, $value];
        }
        
        // Analysis results
        foreach ($data['analysis_results'] as $key => $value) {
            $csvData[] = ['analysis_' . $key, $value];
        }
        
        // Section scores
        foreach ($data['section_scores'] as $key => $value) {
            $csvData[] = ['section_' . $key, $value];
        }
        
        // Keywords (as comma-separated lists)
        $csvData[] = ['matching_keywords', implode(', ', $data['keyword_analysis']['matching_keywords'])];
        $csvData[] = ['missing_keywords', implode(', ', $data['keyword_analysis']['missing_keywords'])];
        $csvData[] = ['suggested_keywords', implode(', ', $data['keyword_analysis']['suggested_keywords'])];
        $csvData[] = ['keyword_density', $data['keyword_analysis']['keyword_density']];
        
        // Recommendations
        $csvData[] = ['resume_recommendations', implode('; ', $data['recommendations']['resume'])];
        $csvData[] = ['cover_letter_recommendations', implode('; ', $data['recommendations']['cover_letter'])];
        $csvData[] = ['general_recommendations', implode('; ', $data['recommendations']['general'])];
        
        // Generate CSV content
        $csv = '';
        foreach ($csvData as $row) {
            $csv .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportExcel(Response $response, array $data, Job $job): Response
    {
        $filename = $this->generateFilename($job, 'xlsx');
        
        // This would require PhpSpreadsheet implementation
        // For now, return a simplified version
        $content = $this->reportGenerator->generateExcelReport($data);
        
        $response->getBody()->write($content);
        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportXML(Response $response, array $data, Job $job): Response
    {
        $filename = $this->generateFilename($job, 'xml');
        
        $xml = new \SimpleXMLElement('<job_analysis/>');
        $this->arrayToXml($data, $xml);
        
        $response->getBody()->write($xml->asXML());
        return $response
            ->withHeader('Content-Type', 'application/xml')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // Bulk report methods

    private function generateComparisonReport(Response $response, $jobs, string $format): Response
    {
        $comparisonData = [
            'jobs' => $jobs->map(function($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'overall_score' => $job->analysis->overall_score,
                    'ats_score' => $job->analysis->ats_score,
                    'resume_match_score' => $job->analysis->resume_match_score,
                    'cover_letter_match_score' => $job->analysis->cover_letter_match_score,
                    'recommendation' => $job->analysis->ai_recommendation
                ];
            }),
            'comparison_analysis' => $this->generateJobComparison($jobs),
            'insights' => $this->generateComparisonInsights($jobs)
        ];

        switch ($format) {
            case 'pdf':
                $content = $this->reportGenerator->generateComparisonPDF($comparisonData);
                $filename = "job_comparison_" . date('Y-m-d') . ".pdf";
                
                $response->getBody()->write($content);
                return $response
                    ->withHeader('Content-Type', 'application/pdf')
                    ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            case 'xlsx':
                $content = $this->reportGenerator->generateComparisonExcel($comparisonData);
                $filename = "job_comparison_" . date('Y-m-d') . ".xlsx";
                
                $response->getBody()->write($content);
                return $response
                    ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                    ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            default:
                return $this->badRequest($response, 'Unsupported format for comparison report');
        }
    }

    private function generateBulkSummaryReport(Response $response, $jobs, string $format): Response
    {
        $summaryData = [
            'overview' => [
                'total_jobs' => $jobs->count(),
                'average_score' => $jobs->avg('analysis.overall_score'),
                'best_score' => $jobs->max('analysis.overall_score'),
                'worst_score' => $jobs->min('analysis.overall_score')
            ],
            'jobs_summary' => $jobs->map(function($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'score' => $job->analysis->overall_score,
                    'grade' => $this->getGrade($job->analysis->overall_score),
                    'recommendation' => $job->analysis->ai_recommendation,
                    'top_strength' => $this->getTopStrength($job->analysis),
                    'main_weakness' => $this->getMainWeakness($job->analysis)
                ];
            }),
            'recommendations' => $this->generateBulkRecommendations($jobs)
        ];

        switch ($format) {
            case 'pdf':
                $content = $this->reportGenerator->generateBulkSummaryPDF($summaryData);
                $filename = "jobs_summary_" . date('Y-m-d') . ".pdf";
                
                $response->getBody()->write($content);
                return $response
                    ->withHeader('Content-Type', 'application/pdf')
                    ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
                    
            default:
                return $this->badRequest($response, 'Unsupported format for summary report');
        }
    }

    private function generateBulkDetailedReport(Response $response, $jobs, string $format): Response
    {
        if ($format === 'zip') {
            // Generate individual detailed reports and zip them
            $zipContent = $this->reportGenerator->generateBulkZipReport($jobs);
            $filename = "detailed_reports_" . date('Y-m-d') . ".zip";
            
            $response->getBody()->write($zipContent);
            return $response
                ->withHeader('Content-Type', 'application/zip')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        }
        
        return $this->badRequest($response, 'Bulk detailed reports only available in ZIP format');
    }

    // Analytics and portfolio helper methods

    private function getJobsByTimeframe(int $userId, string $timeframe)
    {
        $query = Job::with('analysis')->where('user_id', $userId)->where('is_analyzed', true);
        
        switch ($timeframe) {
            case 'last_30_days':
                $query->where('created_at', '>=', date('Y-m-d', strtotime('-30 days')));
                break;
            case 'last_90_days':
                $query->where('created_at', '>=', date('Y-m-d', strtotime('-90 days')));
                break;
            case 'last_year':
                $query->where('created_at', '>=', date('Y-m-d', strtotime('-1 year')));
                break;
            case 'all':
            default:
                // No additional filter
                break;
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    private function generatePortfolioStatistics($jobs): array
    {
        $totalJobs = $jobs->count();
        if ($totalJobs === 0) return [];
        
        $analyses = $jobs->pluck('analysis');
        
        return [
            'total_applications' => $totalJobs,
            'average_score' => round($analyses->avg('overall_score'), 1),
            'best_score' => $analyses->max('overall_score'),
            'worst_score' => $analyses->min('overall_score'),
            'score_distribution' => [
                'excellent' => $analyses->where('overall_score', '>=', 85)->count(),
                'good' => $analyses->whereBetween('overall_score', [70, 84])->count(),
                'fair' => $analyses->whereBetween('overall_score', [55, 69])->count(),
                'poor' => $analyses->where('overall_score', '<', 55)->count()
            ],
            'improvement_trend' => $this->calculateImprovementTrend($analyses),
            'top_performing_jobs' => $jobs->sortByDesc('analysis.overall_score')->take(3)->values(),
            'areas_for_improvement' => $this->identifyCommonWeaknesses($analyses)
        ];
    }

    // Utility helper methods

    private function generateFilename(Job $job, string $extension, string $type = 'report'): string
    {
        $jobTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $job->job_title);
        $company = preg_replace('/[^a-zA-Z0-9_-]/', '_', $job->company_name);
        $date = date('Y-m-d');
        
        return "{$type}_{$jobTitle}_{$company}_{$date}.{$extension}";
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $subNode = $xml->addChild($key);
                $this->arrayToXml($value, $subNode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

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
        return 'F';
    }

    private function getSectionStatus(float $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    private function generateGlossary(): array
    {
        return [
            'ATS' => 'Applicant Tracking System - Software used by employers to filter and rank resumes',
            'Keyword Density' => 'Percentage of relevant keywords found in your resume compared to job requirements',
            'Match Score' => 'How well your application aligns with the specific job requirements',
            'Overall Score' => 'Comprehensive assessment combining all analysis factors',
            'Interview Probability' => 'Estimated likelihood of receiving an interview invitation',
            'Job Securing Probability' => 'Estimated likelihood of receiving a job offer'
        ];
    }

    private function generateResourcesList(): array
    {
        return [
            'Resume Templates' => 'ATS-friendly resume templates and formats',
            'Keyword Research Tools' => 'Tools for identifying relevant keywords',
            'Industry Guides' => 'Specific guidance for different industries',
            'Interview Preparation' => 'Resources for preparing for interviews',
            'Cover Letter Examples' => 'Sample cover letters for various roles'
        ];
    }

    // Error handling methods

    private function notFound(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }

    private function badRequest(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    private function handleError(Response $response, Exception $e, string $message): Response
    {
        error_log("ReportController Error: " . $e->getMessage());
        
        $response->getBody()->write(json_encode([
            'error' => $message,
            'details' => $_ENV['APP_DEBUG'] === 'true' ? $e->getMessage() : 'An error occurred'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }

    // Placeholder methods for complex analysis (to be implemented based on specific needs)
    
    private function getOverallAssessment(float $score): string
    {
        if ($score >= 85) return 'Outstanding application with excellent job fit';
        if ($score >= 70) return 'Strong application with good potential';
        if ($score >= 55) return 'Solid application with room for improvement';
        if ($score >= 40) return 'Weak application requiring significant improvements';
        return 'Poor application needing major revisions';
    }

    private function identifyKeyStrengths(JobAnalysis $analysis): array
    {
        $strengths = [];
        $sections = [
            'ATS Compatibility' => $analysis->ats_score,
            'Keyword Optimization' => $analysis->keyword_density ?? 0,
            'Resume Content' => $analysis->resume_match_score,
            'Cover Letter' => $analysis->cover_letter_match_score
        ];
        
        foreach ($sections as $section => $score) {
            if ($score >= 80) {
                $strengths[] = $section;
            }
        }
        
        return array_slice($strengths, 0, 3);
    }

    private function identifyPrimaryConcerns(JobAnalysis $analysis): array
    {
        $concerns = [];
        $sections = [
            'ATS Compatibility' => $analysis->ats_score,
            'Keyword Gap' => 100 - ($analysis->keyword_density ?? 0),
            'Content Alignment' => 100 - $analysis->resume_match_score,
            'Cover Letter Quality' => 100 - $analysis->cover_letter_match_score
        ];
        
        foreach ($sections as $section => $gap) {
            if ($gap >= 30) {
                $concerns[] = $section;
            }
        }
        
        return array_slice($concerns, 0, 3);
    }

    private function getExecutiveRecommendation(JobAnalysis $analysis): string
    {
        $score = $analysis->overall_score;
        
        if ($score >= 85) {
            return 'Proceed with application - excellent fit with high success probability';
        } elseif ($score >= 70) {
            return 'Good candidate - minor optimizations recommended before applying';
        } elseif ($score >= 55) {
            return 'Moderate fit - targeted improvements needed to increase success rate';
        } else {
            return 'Significant improvements required - recommend major revisions before applying';
        }
    }

    private function generateNextSteps(JobAnalysis $analysis): array
    {
        $steps = [];
        
        if ($analysis->ats_score < 70) {
            $steps[] = 'Optimize resume format for ATS compatibility';
        }
        
        if (count($analysis->missing_keywords ?? []) > 5) {
            $steps[] = 'Integrate missing keywords throughout application';
        }
        
        if ($analysis->summary_score < 60) {
            $steps[] = 'Rewrite professional summary to better match role';
        }
        
        if ($analysis->overall_score >= 70) {
            $steps[] = 'Apply with confidence';
        } else {
            $steps[] = 'Complete improvements before submitting application';
        }
        
        return $steps;
    }

    // Additional placeholder methods would be implemented here based on specific requirements
    private function analyzeSectionPerformance(string $section, float $score): string { return "Analysis for {$section}"; }
    private function calculateImprovementPotential(float $score): float { return 100 - $score; }
    private function generateATSAnalysis(JobAnalysis $analysis): array { return []; }
    private function generateKeywordOptimization(JobAnalysis $analysis): array { return []; }
    private function generateContentStructureAnalysis(JobAnalysis $analysis): array { return []; }
    private function calculatePercentile(float $score): int { return min(95, max(5, intval($score))); }
    private function getPerformanceCategory(float $score, float $average): string { return $score > $average ? 'above_average' : 'below_average'; }
    private function categorizeKeywords(array $keywords, string $category): array { return array_slice($keywords, 0, 5); }
    private function getPriorityLevel(float $score): string { return $score < 60 ? 'high' : ($score < 80 ? 'medium' : 'low'); }
    private function getSectionSpecificRecommendations(string $section, float $score): array { return []; }
    private function getSectionBestPractices(string $section): array { return []; }
    private function getSectionCommonMistakes(string $section): array { return []; }
    private function getImmediateActions(JobAnalysis $analysis): array { return []; }
    private function getShortTermImprovements(JobAnalysis $analysis): array { return []; }
    private function getLongTermOptimizations(JobAnalysis $analysis): array { return []; }
    private function generateJobComparison($jobs): array { return []; }
    private function generateComparisonInsights($jobs): array { return []; }
    private function generateBulkRecommendations($jobs): array { return []; }
    private function getTopStrength(JobAnalysis $analysis): string { return 'Strong performance'; }
    private function getMainWeakness(JobAnalysis $analysis): string { return 'Area for improvement'; }
    private function generatePerformanceTrends($jobs): array { return []; }
    private function generateSkillAnalysis($jobs): array { return []; }
    private function generateIndustryBreakdown($jobs): array { return []; }
    private function identifySuccessPatterns($jobs): array { return []; }
    private function identifyImprovementAreas($jobs): array { return []; }
    private function generateAnalyticsOverview(int $userId, string $startDate, string $endDate): array { return []; }
    private function generatePerformanceMetrics(int $userId, string $startDate, string $endDate): array { return []; }
    private function generateTrendAnalysis(int $userId, string $startDate, string $endDate): array { return []; }
    private function generateComparativeAnalysis(int $userId, string $startDate, string $endDate): array { return []; }
    private function identifySuccessFactors(int $userId, string $startDate, string $endDate): array { return []; }
    private function generateAnalyticsRecommendations(int $userId, string $startDate, string $endDate): array { return []; }
    private function generateChartsData(int $userId, string $startDate, string $endDate): array { return []; }
    private function generateSectionsPreview(JobAnalysis $analysis): array { return []; }
    private function countRecommendations(JobAnalysis $analysis): int { return 0; }
    private function estimatePages(JobAnalysis $analysis, string $type): int { return $type === 'detailed' ? 8 : 4; }
    private function estimateFileSize(JobAnalysis $analysis, string $type): string { return $type === 'detailed' ? '2.5 MB' : '1.2 MB'; }
    private function calculateImprovementTrend($analyses): string { return 'improving'; }
    private function identifyCommonWeaknesses($analyses): array { return []; }
}