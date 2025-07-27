<?php
// app/Controllers/JobController.php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Job;
use App\Models\JobAnalysis;
use App\Services\JobAnalyzerService;
use App\Services\PDFParserService;
use App\Validators\JobValidator;
use App\Validators\FileValidator;

class JobController
{
    private $view;
    private $jobAnalyzer;
    private $pdfParser;
    
    public function __construct($view, JobAnalyzerService $jobAnalyzer, PDFParserService $pdfParser)
    {
        $this->view = $view;
        $this->jobAnalyzer = $jobAnalyzer;
        $this->pdfParser = $pdfParser;
    }

    public function index(Request $request, Response $response): Response
    {
        // This is handled by DashboardController
        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function create(Request $request, Response $response): Response
    {
        $templateData = [
            'employment_types' => Job::getEmploymentTypes(),
            'job_levels' => Job::getJobLevels(),
            'errors' => [],
            'old_input' => []
        ];

        $html = $this->view->render('jobs/create.twig', $templateData);
        $response->getBody()->write($html);
        return $response;
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        // Validate input
        $validator = new JobValidator();
        $validation = $validator->validate($data);

        if (!$validation['valid']) {
            $templateData = [
                'employment_types' => Job::getEmploymentTypes(),
                'job_levels' => Job::getJobLevels(),
                'errors' => $validation['errors'],
                'old_input' => $data
            ];

            $html = $this->view->render('jobs/create.twig', $templateData);
            $response->getBody()->write($html);
            return $response->withStatus(400);
        }

        try {
            // Handle file uploads
            $resumePath = null;
            $coverLetterPath = null;

            if (isset($files['resume']) && $files['resume']->getError() === UPLOAD_ERR_OK) {
                $fileValidator = new FileValidator();
                $resumeValidation = $fileValidator->validateResume($files['resume']);
                
                if ($resumeValidation['valid']) {
                    $resumePath = $this->handleFileUpload($files['resume'], 'resumes');
                } else {
                    throw new \Exception('Resume upload failed: ' . implode(', ', $resumeValidation['errors']));
                }
            }

            if (isset($files['cover_letter']) && $files['cover_letter']->getError() === UPLOAD_ERR_OK) {
                $fileValidator = new FileValidator();
                $coverLetterValidation = $fileValidator->validateCoverLetter($files['cover_letter']);
                
                if ($coverLetterValidation['valid']) {
                    $coverLetterPath = $this->handleFileUpload($files['cover_letter'], 'cover_letters');
                } else {
                    throw new \Exception('Cover letter upload failed: ' . implode(', ', $coverLetterValidation['errors']));
                }
            }

            // Parse job description for system fields
            $parsedJobData = $this->jobAnalyzer->parseJobDescription($data['job_description']);

            // Create job record
            $job = Job::create([
                'job_title' => $data['job_title'],
                'company_name' => $data['company_name'] ?? '',
                'job_description' => $data['job_description'],
                'resume_path' => $resumePath,
                'cover_letter_path' => $coverLetterPath,
                'employment_type' => $data['employment_type'] ?? null,
                'job_level' => $data['job_level'] ?? null,
                'location' => $parsedJobData['location'] ?? null,
                'salary_min' => $parsedJobData['salary_min'] ?? null,
                'salary_max' => $parsedJobData['salary_max'] ?? null,
                'experience_required' => $parsedJobData['experience_required'] ?? null,
                'education_required' => $parsedJobData['education_required'] ?? null,
                'industry' => $parsedJobData['industry'] ?? null,
                'hard_skills' => $parsedJobData['hard_skills'] ?? [],
                'soft_skills' => $parsedJobData['soft_skills'] ?? [],
                'languages_required' => $parsedJobData['languages_required'] ?? [],
                'certifications_required' => $parsedJobData['certifications_required'] ?? []
            ]);

            // Trigger analysis if files are uploaded
            if ($resumePath || $coverLetterPath) {
                $this->jobAnalyzer->queueAnalysis($job->id);
            }

            return $response->withHeader('Location', '/jobs/' . $job->id)->withStatus(302);

        } catch (\Exception $e) {
            $templateData = [
                'employment_types' => Job::getEmploymentTypes(),
                'job_levels' => Job::getJobLevels(),
                'errors' => ['general' => $e->getMessage()],
                'old_input' => $data
            ];

            $html = $this->view->render('jobs/create.twig', $templateData);
            $response->getBody()->write($html);
            return $response->withStatus(500);
        }
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $job = Job::with('analysis')->find($jobId);

        if (!$job) {
            return $response->withStatus(404);
        }

        // Get additional insights if analysis exists
        $insights = null;
        if ($job->analysis) {
            $insights = $this->generateJobInsights($job);
        }

        $templateData = [
            'job' => $job,
            'analysis' => $job->analysis,
            'insights' => $insights,
            'employment_types' => Job::getEmploymentTypes(),
            'job_levels' => Job::getJobLevels()
        ];

        $html = $this->view->render('jobs/view.twig', $templateData);
        $response->getBody()->write($html);
        return $response;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $job = Job::find($jobId);

        if (!$job) {
            return $response->withStatus(404);
        }

        $templateData = [
            'job' => $job,
            'employment_types' => Job::getEmploymentTypes(),
            'job_levels' => Job::getJobLevels(),
            'errors' => [],
            'old_input' => []
        ];

        $html = $this->view->render('jobs/edit.twig', $templateData);
        $response->getBody()->write($html);
        return $response;
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $job = Job::find($jobId);

        if (!$job) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        // Validate input
        $validator = new JobValidator();
        $validation = $validator->validate($data, $jobId);

        if (!$validation['valid']) {
            $templateData = [
                'job' => $job,
                'employment_types' => Job::getEmploymentTypes(),
                'job_levels' => Job::getJobLevels(),
                'errors' => $validation['errors'],
                'old_input' => $data
            ];

            $html = $this->view->render('jobs/edit.twig', $templateData);
            $response->getBody()->write($html);
            return $response->withStatus(400);
        }

        try {
            // Handle file uploads
            $resumePath = $job->resume_path;
            $coverLetterPath = $job->cover_letter_path;
            $filesChanged = false;

            if (isset($files['resume']) && $files['resume']->getError() === UPLOAD_ERR_OK) {
                $fileValidator = new FileValidator();
                $resumeValidation = $fileValidator->validateResume($files['resume']);
                
                if ($resumeValidation['valid']) {
                    // Delete old file if exists
                    if ($resumePath && file_exists(public_path($resumePath))) {
                        unlink(public_path($resumePath));
                    }
                    $resumePath = $this->handleFileUpload($files['resume'], 'resumes');
                    $filesChanged = true;
                } else {
                    throw new \Exception('Resume upload failed: ' . implode(', ', $resumeValidation['errors']));
                }
            }

            if (isset($files['cover_letter']) && $files['cover_letter']->getError() === UPLOAD_ERR_OK) {
                $fileValidator = new FileValidator();
                $coverLetterValidation = $fileValidator->validateCoverLetter($files['cover_letter']);
                
                if ($coverLetterValidation['valid']) {
                    // Delete old file if exists
                    if ($coverLetterPath && file_exists(public_path($coverLetterPath))) {
                        unlink(public_path($coverLetterPath));
                    }
                    $coverLetterPath = $this->handleFileUpload($files['cover_letter'], 'cover_letters');
                    $filesChanged = true;
                } else {
                    throw new \Exception('Cover letter upload failed: ' . implode(', ', $coverLetterValidation['errors']));
                }
            }

            // Parse job description for system fields if changed
            $parsedJobData = [];
            if ($data['job_description'] !== $job->job_description) {
                $parsedJobData = $this->jobAnalyzer->parseJobDescription($data['job_description']);
                $filesChanged = true; // Consider job description change as significant
            }

            // Update job record
            $updateData = [
                'job_title' => $data['job_title'],
                'company_name' => $data['company_name'] ?? '',
                'job_description' => $data['job_description'],
                'resume_path' => $resumePath,
                'cover_letter_path' => $coverLetterPath,
                'employment_type' => $data['employment_type'] ?? $job->employment_type,
                'job_level' => $data['job_level'] ?? $job->job_level
            ];

            // Add parsed data if available
            if (!empty($parsedJobData)) {
                $updateData = array_merge($updateData, [
                    'location' => $parsedJobData['location'] ?? $job->location,
                    'salary_min' => $parsedJobData['salary_min'] ?? $job->salary_min,
                    'salary_max' => $parsedJobData['salary_max'] ?? $job->salary_max,
                    'experience_required' => $parsedJobData['experience_required'] ?? $job->experience_required,
                    'education_required' => $parsedJobData['education_required'] ?? $job->education_required,
                    'industry' => $parsedJobData['industry'] ?? $job->industry,
                    'hard_skills' => $parsedJobData['hard_skills'] ?? $job->hard_skills,
                    'soft_skills' => $parsedJobData['soft_skills'] ?? $job->soft_skills,
                    'languages_required' => $parsedJobData['languages_required'] ?? $job->languages_required,
                    'certifications_required' => $parsedJobData['certifications_required'] ?? $job->certifications_required
                ]);
            }

            $job->update($updateData);

            // Re-analyze if significant changes were made
            if ($filesChanged) {
                $job->resetAnalysis();
                $this->jobAnalyzer->queueAnalysis($job->id);
            }

            return $response->withHeader('Location', '/jobs/' . $job->id)->withStatus(302);

        } catch (\Exception $e) {
            $templateData = [
                'job' => $job,
                'employment_types' => Job::getEmploymentTypes(),
                'job_levels' => Job::getJobLevels(),
                'errors' => ['general' => $e->getMessage()],
                'old_input' => $data
            ];

            $html = $this->view->render('jobs/edit.twig', $templateData);
            $response->getBody()->write($html);
            return $response->withStatus(500);
        }
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $job = Job::find($jobId);

        if (!$job) {
            return $response->withStatus(404);
        }

        try {
            // Delete associated files
            if ($job->resume_path && file_exists(public_path($job->resume_path))) {
                unlink(public_path($job->resume_path));
            }
            
            if ($job->cover_letter_path && file_exists(public_path($job->cover_letter_path))) {
                unlink(public_path($job->cover_letter_path));
            }

            // Delete job (cascade will handle analysis and logs)
            $job->delete();

            return $response->withHeader('Location', '/dashboard')->withStatus(302);

        } catch (\Exception $e) {
            // Handle error - could redirect with error message
            return $response->withHeader('Location', '/jobs/' . $jobId . '?error=delete_failed')->withStatus(302);
        }
    }

    public function analyze(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $job = Job::find($jobId);

        if (!$job) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Job not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        try {
            // Trigger immediate analysis
            $analysisResult = $this->jobAnalyzer->analyzeJob($job);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Analysis completed successfully',
                'analysis' => $analysisResult
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Analysis failed: ' . $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function downloadFile(Request $request, Response $response, array $args): Response
    {
        $jobId = (int)$args['id'];
        $fileType = $args['type']; // 'resume' or 'cover_letter'
        
        $job = Job::find($jobId);
        if (!$job) {
            return $response->withStatus(404);
        }

        $filePath = null;
        if ($fileType === 'resume' && $job->resume_path) {
            $filePath = public_path($job->resume_path);
        } elseif ($fileType === 'cover_letter' && $job->cover_letter_path) {
            $filePath = public_path($job->cover_letter_path);
        }

        if (!$filePath || !file_exists($filePath)) {
            return $response->withStatus(404);
        }

        $filename = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $mimeType = mime_content_type($filePath);

        $response->getBody()->write($fileContent);
        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', strlen($fileContent));
    }

    private function handleFileUpload($uploadedFile, $directory): string
    {
        $uploadDir = public_path('uploads/' . $directory);
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        $uploadedFile->moveTo($uploadPath);

        return 'uploads/' . $directory . '/' . $filename;
    }

    private function generateJobInsights($job): array
    {
        $analysis = $job->analysis;
        if (!$analysis) {
            return [];
        }

        return [
            'match_strength' => $analysis->calculateMatchStrength(),
            'keyword_summary' => $analysis->keyword_analysis_summary,
            'spider_chart_data' => $analysis->spider_chart_data,
            'top_recommendations' => array_slice($analysis->getAllRecommendations(), 0, 5),
            'skill_gaps' => $analysis->getSkillGaps(),
            'experience_gaps' => $analysis->getExperienceGaps(),
            'education_gaps' => $analysis->getEducationGaps(),
            'confidence_level' => $analysis->confidence_level_text,
            'interview_odds' => $analysis->interview_odds_text,
            'job_odds' => $analysis->job_odds_text,
            'overall_grade' => $analysis->overall_grade,
            'analysis_quality' => $analysis->getAnalysisQuality(),
            'has_comprehensive_analysis' => $analysis->hasComprehensiveAnalysis()
        ];
    }
}

// Helper functions for file handling
function public_path($path = '')
{
    return __DIR__ . '/../../public/' . ltrim($path, '/');
}

function url($path = '')
{
    return $_ENV['APP_URL'] . '/' . ltrim($path, '/');
}