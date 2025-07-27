<?php
// app/Controllers/DashboardController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Job;
use App\Models\JobAnalysis;

class DashboardController
{
    private $view;
    
    public function __construct($view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        
        // Get filter parameters
        $search = $queryParams['search'] ?? '';
        $filterBy = $queryParams['filter_by'] ?? 'all';
        $sortBy = $queryParams['sort_by'] ?? 'created_at';
        $sortOrder = $queryParams['sort_order'] ?? 'desc';
        $perPage = min((int)($queryParams['per_page'] ?? 10), 50);
        $page = max((int)($queryParams['page'] ?? 1), 1);

        // Build query
        $query = Job::with('analysis');

        // Apply search
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('job_title', 'LIKE', "%{$search}%")
                  ->orWhere('company_name', 'LIKE', "%{$search}%")
                  ->orWhere('location', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        switch ($filterBy) {
            case 'analyzed':
                $query->analyzed();
                break;
            case 'pending':
                $query->notAnalyzed();
                break;
            case 'excellent':
                $query->analyzed()->whereHas('analysis', function($q) {
                    $q->where('overall_score', '>=', 85);
                });
                break;
            case 'good':
                $query->analyzed()->whereHas('analysis', function($q) {
                    $q->whereBetween('overall_score', [70, 84]);
                });
                break;
            case 'needs_improvement':
                $query->analyzed()->whereHas('analysis', function($q) {
                    $q->where('overall_score', '<', 70);
                });
                break;
        }

        // Apply sorting
        if ($sortBy === 'score') {
            $query->leftJoin('job_analysis', 'jobs.id', '=', 'job_analysis.job_id')
                  ->orderBy('job_analysis.overall_score', $sortOrder)
                  ->select('jobs.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Get paginated results
        $offset = ($page - 1) * $perPage;
        $jobs = $query->skip($offset)->take($perPage)->get();
        $totalJobs = Job::count();

        // Calculate pagination
        $totalPages = ceil($totalJobs / $perPage);
        $hasNextPage = $page < $totalPages;
        $hasPrevPage = $page > 1;

        // Get dashboard statistics
        $stats = $this->getDashboardStats();

        // Prepare template data
        $templateData = [
            'jobs' => $jobs,
            'stats' => $stats,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total_jobs' => $totalJobs,
                'has_next' => $hasNextPage,
                'has_prev' => $hasPrevPage,
                'start' => $offset + 1,
                'end' => min($offset + $perPage, $totalJobs)
            ],
            'filters' => [
                'search' => $search,
                'filter_by' => $filterBy,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
                'per_page' => $perPage
            ],
            'filter_options' => [
                'all' => 'All Jobs',
                'analyzed' => 'Analyzed',
                'pending' => 'Pending Analysis',
                'excellent' => 'Excellent Match (85%+)',
                'good' => 'Good Match (70-84%)',
                'needs_improvement' => 'Needs Improvement (<70%)'
            ],
            'sort_options' => [
                'created_at' => 'Date Added',
                'job_title' => 'Job Title',
                'company_name' => 'Company',
                'score' => 'Analysis Score'
            ]
        ];

        $html = $this->view->render('dashboard/index.twig', $templateData);
        $response->getBody()->write($html);
        return $response;
    }

    public function stats(Request $request, Response $response): Response
    {
        $stats = $this->getDashboardStats();
        
        $response->getBody()->write(json_encode($stats));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getDashboardStats(): array
    {
        // Basic counts
        $totalJobs = Job::count();
        $analyzedJobs = Job::analyzed()->count();
        $pendingJobs = Job::notAnalyzed()->count();
        
        // Jobs with files
        $jobsWithResume = Job::whereNotNull('resume_path')->count();
        $jobsWithCoverLetter = Job::whereNotNull('cover_letter_path')->count();
        $jobsWithBoth = Job::whereNotNull('resume_path')
                           ->whereNotNull('cover_letter_path')
                           ->count();

        // Analysis statistics
        $analysisStats = JobAnalysis::selectRaw('
            AVG(overall_score) as avg_overall_score,
            AVG(ats_score) as avg_ats_score,
            AVG(resume_match_score) as avg_resume_score,
            AVG(cover_letter_match_score) as avg_cover_letter_score,
            AVG(interview_probability) as avg_interview_probability,
            MAX(overall_score) as best_score,
            MIN(overall_score) as worst_score
        ')->first();

        // Score distribution
        $scoreDistribution = [
            'excellent' => JobAnalysis::where('overall_score', '>=', 85)->count(),
            'good' => JobAnalysis::whereBetween('overall_score', [70, 84])->count(),
            'fair' => JobAnalysis::whereBetween('overall_score', [55, 69])->count(),
            'poor' => JobAnalysis::where('overall_score', '<', 55)->count()
        ];

        // Recent activity
        $recentJobs = Job::with('analysis')
                         ->orderBy('created_at', 'desc')
                         ->take(5)
                         ->get();

        // Top performing jobs
        $topJobs = Job::with('analysis')
                      ->analyzed()
                      ->join('job_analysis', 'jobs.id', '=', 'job_analysis.job_id')
                      ->orderBy('job_analysis.overall_score', 'desc')
                      ->select('jobs.*')
                      ->take(5)
                      ->get();

        // Employment type distribution
        $employmentTypes = Job::selectRaw('employment_type, COUNT(*) as count')
                              ->groupBy('employment_type')
                              ->pluck('count', 'employment_type')
                              ->toArray();

        // Monthly job additions (last 6 months)
        $monthlyData = Job::selectRaw('
            DATE_FORMAT(created_at, "%Y-%m") as month,
            COUNT(*) as jobs_added,
            SUM(CASE WHEN is_analyzed = 1 THEN 1 ELSE 0 END) as jobs_analyzed
        ')
        ->where('created_at', '>=', date('Y-m-d', strtotime('-6 months')))
        ->groupBy('month')
        ->orderBy('month')
        ->get();

        return [
            'totals' => [
                'total_jobs' => $totalJobs,
                'analyzed_jobs' => $analyzedJobs,
                'pending_jobs' => $pendingJobs,
                'jobs_with_resume' => $jobsWithResume,
                'jobs_with_cover_letter' => $jobsWithCoverLetter,
                'jobs_with_both' => $jobsWithBoth
            ],
            'averages' => [
                'overall_score' => round($analysisStats->avg_overall_score ?? 0, 1),
                'ats_score' => round($analysisStats->avg_ats_score ?? 0, 1),
                'resume_score' => round($analysisStats->avg_resume_score ?? 0, 1),
                'cover_letter_score' => round($analysisStats->avg_cover_letter_score ?? 0, 1),
                'interview_probability' => round($analysisStats->avg_interview_probability ?? 0, 1)
            ],
            'extremes' => [
                'best_score' => round($analysisStats->best_score ?? 0, 1),
                'worst_score' => round($analysisStats->worst_score ?? 0, 1)
            ],
            'score_distribution' => $scoreDistribution,
            'employment_types' => $employmentTypes,
            'recent_jobs' => $recentJobs->map(function($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'created_at' => $job->created_at->format('M j, Y'),
                    'is_analyzed' => $job->is_analyzed,
                    'score' => $job->analysis ? round($job->analysis->overall_score, 1) : null
                ];
            }),
            'top_jobs' => $topJobs->map(function($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'score' => $job->analysis ? round($job->analysis->overall_score, 1) : 0,
                    'recommendation' => $job->analysis ? $job->analysis->recommendation_text : 'N/A'
                ];
            }),
            'monthly_data' => $monthlyData->map(function($data) {
                return [
                    'month' => date('M Y', strtotime($data->month . '-01')),
                    'jobs_added' => $data->jobs_added,
                    'jobs_analyzed' => $data->jobs_analyzed
                ];
            }),
            'completion_rate' => $totalJobs > 0 ? round(($analyzedJobs / $totalJobs) * 100, 1) : 0,
            'files_completion_rate' => $totalJobs > 0 ? round(($jobsWithBoth / $totalJobs) * 100, 1) : 0
        ];
    }

    public function export(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $format = $queryParams['format'] ?? 'csv';

        // Get all jobs with analysis
        $jobs = Job::with('analysis')->get();

        if ($format === 'csv') {
            return $this->exportCSV($response, $jobs);
        } elseif ($format === 'json') {
            return $this->exportJSON($response, $jobs);
        }

        // Default to CSV
        return $this->exportCSV($response, $jobs);
    }

    private function exportCSV(Response $response, $jobs): Response
    {
        $csv = "ID,Job Title,Company,Location,Employment Type,Job Level,Created Date,Analyzed,Overall Score,ATS Score,Resume Score,Cover Letter Score,AI Recommendation\n";

        foreach ($jobs as $job) {
            $analysis = $job->analysis;
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $job->id,
                $this->escapeCsv($job->job_title),
                $this->escapeCsv($job->company_name),
                $this->escapeCsv($job->location),
                $job->employment_type,
                $job->job_level,
                $job->created_at->format('Y-m-d'),
                $job->is_analyzed ? 'Yes' : 'No',
                $analysis ? $analysis->overall_score : '',
                $analysis ? $analysis->ats_score : '',
                $analysis ? $analysis->resume_match_score : '',
                $analysis ? $analysis->cover_letter_match_score : '',
                $analysis ? $analysis->ai_recommendation : ''
            );
        }

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="jobs_export_' . date('Y-m-d') . '.csv"');
    }

    private function exportJSON(Response $response, $jobs): Response
    {
        $data = $jobs->map(function($job) {
            return [
                'id' => $job->id,
                'job_title' => $job->job_title,
                'company_name' => $job->company_name,
                'location' => $job->location,
                'employment_type' => $job->employment_type,
                'job_level' => $job->job_level,
                'created_at' => $job->created_at->toISOString(),
                'is_analyzed' => $job->is_analyzed,
                'analysis' => $job->analysis ? [
                    'overall_score' => $job->analysis->overall_score,
                    'ats_score' => $job->analysis->ats_score,
                    'resume_match_score' => $job->analysis->resume_match_score,
                    'cover_letter_match_score' => $job->analysis->cover_letter_match_score,
                    'ai_recommendation' => $job->analysis->ai_recommendation
                ] : null
            ];
        });

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="jobs_export_' . date('Y-m-d') . '.json"');
    }

    private function escapeCsv($value): string
    {
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}