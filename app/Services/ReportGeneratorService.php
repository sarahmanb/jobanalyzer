<?php
// app/Services/ReportGeneratorService.php

namespace App\Services;

use App\Models\Job;
use App\Models\JobAnalysis;
use TCPDF;
use Exception;

class ReportGeneratorService
{
    private $twig;
    
    public function __construct($twig = null)
    {
        $this->twig = $twig;
    }

    /**
     * Generate comprehensive PDF analysis report
     */
    public function generateComprehensiveReport(Job $job): string
    {
        if (!$job->analysis) {
            throw new Exception('Job has no analysis data to report');
        }

        $analysis = $job->analysis;
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('JobAnalyzer Pro - AI-Enhanced Resume Analysis');
        $pdf->SetAuthor('JobAnalyzer System');
        $pdf->SetTitle('Comprehensive Job Analysis Report - ' . $job->job_title);
        $pdf->SetSubject('Resume and Job Matching Analysis with ATS Scoring');
        
        // Set header data
        $headerTitle = 'JobAnalyzer Pro - Comprehensive Analysis Report';
        if ($analysis->analysis_type === 'ai_enhanced' || $analysis->analysis_type === 'combined') {
            $headerTitle .= ' (AI-Enhanced)';
        }
        $pdf->SetHeaderData('', 0, $headerTitle, 'Generated on ' . date('F j, Y'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(['helvetica', '', 12]);
        $pdf->setFooterFont(['helvetica', '', 10]);
        
        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // Add a page
        $pdf->AddPage();
        
        // Generate report content
        $html = $this->generateReportHTML($job, $analysis);
        
        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Generate filename
        $filename = 'JobAnalysis_' . $job->id . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        // Save PDF
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }

    /**
     * Generate quick summary report
     */
    public function generateQuickReport(Job $job): string
    {
        if (!$job->analysis) {
            throw new Exception('Job has no analysis data to report');
        }

        $analysis = $job->analysis;
        
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('JobAnalyzer Pro');
        $pdf->SetTitle('Quick Analysis Summary - ' . $job->job_title);
        
        $pdf->SetHeaderData('', 0, 'Quick Analysis Summary', $job->job_title . ' - ' . $job->company_name);
        $pdf->setHeaderFont(['helvetica', '', 11]);
        $pdf->setFooterFont(['helvetica', '', 9]);
        
        $pdf->SetMargins(20, 25, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();
        
        $html = $this->generateQuickReportHTML($job, $analysis);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'QuickSummary_' . $job->id . '_' . date('Y-m-d') . '.pdf';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }

    /**
     * Generate comparison report for multiple jobs
     */
    public function generateComparisonReport(array $jobs): string
    {
        if (empty($jobs)) {
            throw new Exception('No jobs provided for comparison');
        }

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('JobAnalyzer Pro');
        $pdf->SetTitle('Job Comparison Report');
        
        $pdf->SetHeaderData('', 0, 'Job Comparison Report', 'Comparing ' . count($jobs) . ' Job Applications');
        $pdf->setHeaderFont(['helvetica', '', 11]);
        $pdf->setFooterFont(['helvetica', '', 9]);
        
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();
        
        $html = $this->generateComparisonHTML($jobs);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'JobComparison_' . count($jobs) . 'jobs_' . date('Y-m-d') . '.pdf';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }

    /**
     * Generate performance trends report
     */
    public function generateTrendsReport(int $userId = null, int $days = 30): string
    {
        $query = Job::with('analysis')
                    ->where('is_analyzed', true)
                    ->where('created_at', '>=', date('Y-m-d', strtotime("-{$days} days")));
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        $jobs = $query->orderBy('created_at', 'desc')->get();
        
        if ($jobs->isEmpty()) {
            throw new Exception('No analyzed jobs found for the specified period');
        }

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('JobAnalyzer Pro');
        $pdf->SetTitle('Performance Trends Report');
        
        $pdf->SetHeaderData('', 0, 'Performance Trends Report', "Last {$days} days - " . $jobs->count() . ' jobs analyzed');
        $pdf->setHeaderFont(['helvetica', '', 11]);
        $pdf->setFooterFont(['helvetica', '', 9]);
        
        $pdf->SetMargins(20, 25, 20);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->AddPage();
        
        $html = $this->generateTrendsHTML($jobs, $days);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        $filename = 'TrendsReport_' . $days . 'days_' . date('Y-m-d') . '.pdf';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }

    /**
     * Export job data to CSV
     */
    public function exportToCSV(array $jobs): string
    {
        $filename = 'JobAnalysis_Export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        $handle = fopen($filepath, 'w');
        
        // Write CSV header
        fputcsv($handle, [
            'Job ID',
            'Job Title',
            'Company',
            'Location',
            'Employment Type',
            'Job Level',
            'Created Date',
            'Analyzed',
            'Overall Score',
            'ATS Score',
            'Resume Match',
            'Cover Letter Match',
            'Interview Probability',
            'Job Securing Probability',
            'AI Recommendation',
            'Analysis Type',
            'Matching Keywords Count',
            'Missing Keywords Count',
            'Top Recommendation'
        ]);
        
        // Write job data
        foreach ($jobs as $job) {
            $analysis = $job->analysis;
            
            fputcsv($handle, [
                $job->id,
                $job->job_title,
                $job->company_name,
                $job->location,
                $job->employment_type,
                $job->job_level,
                $job->created_at->format('Y-m-d'),
                $job->is_analyzed ? 'Yes' : 'No',
                $analysis ? $analysis->overall_score : '',
                $analysis ? $analysis->ats_score : '',
                $analysis ? $analysis->resume_match_score : '',
                $analysis ? $analysis->cover_letter_match_score : '',
                $analysis ? $analysis->interview_probability : '',
                $analysis ? $analysis->job_securing_probability : '',
                $analysis ? $analysis->recommendation_text : '',
                $analysis ? $analysis->analysis_type : '',
                $analysis ? count($analysis->matching_keywords ?? []) : '',
                $analysis ? count($analysis->missing_keywords ?? []) : '',
                $analysis && !empty($analysis->general_recommendations) ? $analysis->general_recommendations[0] : ''
            ]);
        }
        
        fclose($handle);
        
        return $filepath;
    }

    /**
     * Export detailed analysis to JSON
     */
    public function exportToJSON(array $jobs): string
    {
        $data = [];
        
        foreach ($jobs as $job) {
            $jobData = [
                'job_info' => [
                    'id' => $job->id,
                    'title' => $job->job_title,
                    'company' => $job->company_name,
                    'location' => $job->location,
                    'employment_type' => $job->employment_type,
                    'job_level' => $job->job_level,
                    'created_at' => $job->created_at->toISOString(),
                    'is_analyzed' => $job->is_analyzed
                ]
            ];
            
            if ($job->analysis) {
                $jobData['analysis'] = [
                    'scores' => [
                        'overall' => $job->analysis->overall_score,
                        'ats' => $job->analysis->ats_score,
                        'resume_match' => $job->analysis->resume_match_score,
                        'cover_letter_match' => $job->analysis->cover_letter_match_score,
                        'interview_probability' => $job->analysis->interview_probability,
                        'job_securing_probability' => $job->analysis->job_securing_probability
                    ],
                    'section_scores' => $job->analysis->section_scores,
                    'keyword_analysis' => [
                        'matching' => $job->analysis->matching_keywords,
                        'missing' => $job->analysis->missing_keywords,
                        'suggested' => $job->analysis->suggested_keywords,
                        'density' => $job->analysis->keyword_density
                    ],
                    'recommendations' => [
                        'resume' => $job->analysis->resume_recommendations,
                        'cover_letter' => $job->analysis->cover_letter_recommendations,
                        'general' => $job->analysis->general_recommendations
                    ],
                    'ai_assessment' => [
                        'recommendation' => $job->analysis->ai_recommendation,
                        'confidence_level' => $job->analysis->ai_confidence_level,
                        'analysis_type' => $job->analysis->analysis_type
                    ],
                    'analysis_metadata' => [
                        'duration' => $job->analysis->analysis_duration,
                        'created_at' => $job->analysis->created_at->toISOString()
                    ]
                ];
            }
            
            $data[] = $jobData;
        }
        
        $filename = 'JobAnalysis_Detailed_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $this->getReportsDirectory() . '/' . $filename;
        
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return $filepath;
    }

    /**
     * Generate comprehensive report HTML
     */
    private function generateReportHTML(Job $job, JobAnalysis $analysis): string
    {
        $currentDate = date('F j, Y');
        $overallScore = round($analysis->overall_score);
        $isAIEnhanced = in_array($analysis->analysis_type, ['ai_enhanced', 'combined']);
        
        $scoreColor = $this->getScoreColor($overallScore);
        $scoreCategory = $this->getScoreCategory($overallScore);
        
        $html = '
        <style>
            body { font-family: "Helvetica", sans-serif; line-height: 1.6; color: #333; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #007bff; padding-bottom: 20px; }
            .ai-badge { background: linear-gradient(45deg, #007bff, #0056b3); color: white; padding: 5px 10px; border-radius: 15px; font-size: 10px; }
            .score-section { background-color: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
            .section-title { color: #007bff; font-size: 18px; font-weight: bold; margin: 20px 0 10px 0; border-bottom: 1px solid #dee2e6; padding-bottom: 5px; }
            .metric-grid { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .metric-grid th, .metric-grid td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
            .metric-grid th { background-color: #f8f9fa; font-weight: bold; }
            .recommendations { background-color: #e7f3ff; padding: 15px; margin: 15px 0; border-left: 4px solid #007bff; }
            .improvement-plan { background-color: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; }
            .footer { margin-top: 40px; text-align: center; font-size: 10px; color: #6c757d; border-top: 1px solid #dee2e6; padding-top: 10px; }
        </style>
        
        <div class="header">
            <h1 style="color: #007bff; margin: 0;">Comprehensive Job Analysis Report</h1>';
        
        if ($isAIEnhanced) {
            $html .= '<div style="margin: 10px 0;"><span class="ai-badge">🤖 AI-ENHANCED ANALYSIS</span></div>';
        }
        
        $html .= '
            <p style="margin: 5px 0;"><strong>Job:</strong> ' . htmlspecialchars($job->job_title) . '</p>
            <p style="margin: 5px 0;"><strong>Company:</strong> ' . htmlspecialchars($job->company_name) . '</p>
            <p style="margin: 5px 0;"><strong>Analysis Date:</strong> ' . $currentDate . '</p>
        </div>
        
        <div class="score-section">
            <h2 style="color: ' . $scoreColor . '; margin-bottom: 10px;">Overall Performance Score</h2>
            <div style="font-size: 48px; font-weight: bold; color: ' . $scoreColor . '; margin: 10px 0;">' . $overallScore . '/100</div>
            <div style="width: 200px; height: 20px; background-color: #e9ecef; border-radius: 10px; margin: 15px auto; position: relative;">
                <div style="width: ' . $overallScore . '%; height: 100%; background-color: ' . $scoreColor . '; border-radius: 10px;"></div>
            </div>
            <p style="font-size: 16px; font-weight: bold; color: ' . $scoreColor . '; margin: 10px 0;">' . $scoreCategory . '</p>
        </div>';
        
        // Key Metrics Section
        $html .= '
        <div class="section-title">Key Performance Metrics</div>
        <table class="metric-grid">
            <thead>
                <tr>
                    <th style="width: 30%;">Metric</th>
                    <th style="width: 15%;">Score</th>
                    <th style="width: 55%;">Analysis & Impact</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>ATS Compatibility</strong></td>
                    <td style="text-align: center; font-weight: bold; color: ' . $this->getScoreColor($analysis->ats_score) . ';">' . round($analysis->ats_score) . '%</td>
                    <td>Critical for passing initial screening systems. ' . $this->getATSAnalysis($analysis->ats_score) . '</td>
                </tr>
                <tr>
                    <td><strong>Resume-Job Match</strong></td>
                    <td style="text-align: center; font-weight: bold; color: ' . $this->getScoreColor($analysis->resume_match_score) . ';">' . round($analysis->resume_match_score) . '%</td>
                    <td>How well your resume aligns with job requirements. ' . $this->getMatchAnalysis($analysis->resume_match_score) . '</td>
                </tr>
                <tr>
                    <td><strong>Interview Probability</strong></td>
                    <td style="text-align: center; font-weight: bold; color: ' . $this->getScoreColor($analysis->interview_probability) . ';">' . round($analysis->interview_probability) . '%</td>
                    <td>Predicted likelihood of getting an interview call. ' . $this->getInterviewAnalysis($analysis->interview_probability) . '</td>
                </tr>';
        
        if ($analysis->cover_letter_match_score > 0) {
            $html .= '
                <tr>
                    <td><strong>Cover Letter Match</strong></td>
                    <td style="text-align: center; font-weight: bold; color: ' . $this->getScoreColor($analysis->cover_letter_match_score) . ';">' . round($analysis->cover_letter_match_score) . '%</td>
                    <td>Cover letter alignment with job requirements. ' . $this->getCoverLetterAnalysis($analysis->cover_letter_match_score) . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>';
        
        // Section Analysis
        $html .= '
        <div class="section-title">Resume Section Analysis</div>
        <table class="metric-grid">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Analysis</th>
                </tr>
            </thead>
            <tbody>';
        
        $sectionNames = [
            'contact_info' => 'Contact Information',
            'summary' => 'Professional Summary',
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills & Competencies',
            'achievements' => 'Achievements'
        ];
        
        foreach ($analysis->section_scores as $section => $score) {
            $status = $score >= 80 ? 'Excellent' : ($score >= 60 ? 'Good' : ($score >= 40 ? 'Fair' : 'Needs Work'));
            $statusColor = $this->getScoreColor($score);
            
            $html .= '
                <tr>
                    <td><strong>' . $sectionNames[$section] . '</strong></td>
                    <td style="text-align: center; font-weight: bold;">' . round($score) . '%</td>
                    <td style="color: ' . $statusColor . '; font-weight: bold;">' . $status . '</td>
                    <td>' . $this->getSectionAnalysis($section, $score) . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>';
        
        // Keyword Analysis
        if (!empty($analysis->matching_keywords) || !empty($analysis->missing_keywords)) {
            $html .= '
            <div class="section-title">Keyword Analysis</div>
            <table class="metric-grid">
                <tr>
                    <th style="width: 25%;">Category</th>
                    <th style="width: 25%;">Count</th>
                    <th style="width: 50%;">Details</th>
                </tr>
                <tr>
                    <td><strong>Matching Keywords</strong></td>
                    <td style="text-align: center; color: #28a745; font-weight: bold;">' . count($analysis->matching_keywords ?? []) . '</td>
                    <td>' . implode(', ', array_slice($analysis->matching_keywords ?? [], 0, 10)) . (count($analysis->matching_keywords ?? []) > 10 ? '...' : '') . '</td>
                </tr>
                <tr>
                    <td><strong>Missing Keywords</strong></td>
                    <td style="text-align: center; color: #dc3545; font-weight: bold;">' . count($analysis->missing_keywords ?? []) . '</td>
                    <td>' . implode(', ', array_slice($analysis->missing_keywords ?? [], 0, 10)) . (count($analysis->missing_keywords ?? []) > 10 ? '...' : '') . '</td>
                </tr>
                <tr>
                    <td><strong>Keyword Density</strong></td>
                    <td style="text-align: center; font-weight: bold;">' . round($analysis->keyword_density, 1) . '%</td>
                    <td>' . $this->getKeywordDensityAnalysis($analysis->keyword_density) . '</td>
                </tr>
            </table>';
        }
        
        // Recommendations
        if (!empty($analysis->all_recommendations)) {
            $html .= '
            <div class="section-title">Priority Recommendations</div>
            <div class="recommendations">
                <h4 style="margin-top: 0; color: #007bff;">🎯 Action Items to Improve Your Chances:</h4>
                <ol style="margin-bottom: 0;">';
            
            $priorityRecs = $analysis->priority_recommendations;
            foreach (array_slice($priorityRecs, 0, 5) as $rec) {
                $priorityIcon = $rec['priority'] === 'high' ? '🔴' : ($rec['priority'] === 'medium' ? '🟡' : '🟢');
                $html .= '<li>' . $priorityIcon . ' <strong>' . ucfirst($rec['priority']) . ' Priority:</strong> ' . htmlspecialchars($rec['text']) . '</li>';
            }
            
            $html .= '
                </ol>
            </div>';
        }
        
        // Improvement Plan
        if ($analysis->needsImprovement()) {
            $improvementPlan = $analysis->getImprovementPlan();
            if (!empty($improvementPlan)) {
                $html .= '
                <div class="section-title">3-Step Improvement Plan</div>
                <div class="improvement-plan">
                    <h4 style="margin-top: 0; color: #856404;">📈 Roadmap to Better Results:</h4>';
                
                foreach ($improvementPlan as $step) {
                    $impactIcon = $step['impact'] === 'high' ? '🚀' : ($step['impact'] === 'medium' ? '📊' : '📈');
                    $effortIcon = $step['effort'] === 'low' ? '⚡' : ($step['effort'] === 'medium' ? '⚙️' : '🔧');
                    
                    $html .= '
                    <div style="margin: 15px 0; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 5px;">
                        <h5 style="margin: 0 0 5px 0; color: #856404;">Step ' . $step['step'] . ': ' . $step['title'] . '</h5>
                        <p style="margin: 5px 0;">' . $step['description'] . '</p>
                        <small>' . $impactIcon . ' Impact: ' . ucfirst($step['impact']) . ' | ' . $effortIcon . ' Effort: ' . ucfirst($step['effort']) . '</small>
                    </div>';
                }
                
                $html .= '</div>';
            }
        }
        
        // Footer
        $html .= '
        <div class="footer">
            <p><strong>JobAnalyzer Pro</strong> - AI-Enhanced Resume Analysis & Job Matching</p>
            <p>Helping you optimize your applications for better interview chances</p>
            <p>Report generated on ' . $currentDate . ' | Analysis Type: ' . ucfirst(str_replace('_', ' ', $analysis->analysis_type)) . '</p>
        </div>';
        
        return $html;
    }

    /**
     * Generate quick report HTML
     */
    private function generateQuickReportHTML(Job $job, JobAnalysis $analysis): string
    {
        $html = '<h2 style="color: #007bff;">Quick Analysis Summary</h2>';
        
        $html .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr style="background-color: #f8f9fa;">
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Overall Score</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 18px; color: ' . $this->getScoreColor($analysis->overall_score) . ';">' . round($analysis->overall_score) . '/100</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">ATS Compatibility</td>
                <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' . round($analysis->ats_score) . '%</td>
            