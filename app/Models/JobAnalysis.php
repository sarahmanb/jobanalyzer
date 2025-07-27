<?php
// app/Models/JobAnalysis.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobAnalysis extends Model
{
    protected $table = 'job_analysis';
    
    protected $fillable = [
        'job_id',
        'overall_score',
        'ats_score',
        'resume_match_score',
        'cover_letter_match_score',
        'interview_probability',
        'job_securing_probability',
        'goodness_of_fit_score',
        'ai_recommendation',
        'ai_confidence_level',
        'matching_keywords',
        'missing_keywords',
        'suggested_keywords',
        'keyword_density',
        'contact_info_score',
        'summary_score',
        'experience_score',
        'education_score',
        'skills_score',
        'achievements_score',
        'skill_match_analysis',
        'experience_gap_analysis',
        'education_match_analysis',
        'resume_recommendations',
        'cover_letter_recommendations',
        'general_recommendations',
        'analysis_type',
        'analysis_duration'
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'ats_score' => 'decimal:2',
        'resume_match_score' => 'decimal:2',
        'cover_letter_match_score' => 'decimal:2',
        'interview_probability' => 'decimal:2',
        'job_securing_probability' => 'decimal:2',
        'goodness_of_fit_score' => 'decimal:2',
        'ai_confidence_level' => 'decimal:2',
        'keyword_density' => 'decimal:2',
        'contact_info_score' => 'decimal:2',
        'summary_score' => 'decimal:2',
        'experience_score' => 'decimal:2',
        'education_score' => 'decimal:2',
        'skills_score' => 'decimal:2',
        'achievements_score' => 'decimal:2',
        'analysis_duration' => 'decimal:2',
        'matching_keywords' => 'array',
        'missing_keywords' => 'array',
        'suggested_keywords' => 'array',
        'skill_match_analysis' => 'array',
        'experience_gap_analysis' => 'array',
        'education_match_analysis' => 'array',
        'resume_recommendations' => 'array',
        'cover_letter_recommendations' => 'array',
        'general_recommendations' => 'array'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    // Accessors
    public function getOverallScoreColorAttribute(): string
    {
        $score = $this->overall_score;
        if ($score >= 85) return 'success';
        if ($score >= 70) return 'primary';
        if ($score >= 55) return 'warning';
        return 'danger';
    }

    public function getATSScoreColorAttribute(): string
    {
        $score = $this->ats_score;
        if ($score >= 80) return 'success';
        if ($score >= 60) return 'warning';
        return 'danger';
    }

    public function getInterviewProbabilityColorAttribute(): string
    {
        $probability = $this->interview_probability;
        if ($probability >= 70) return 'success';
        if ($probability >= 50) return 'primary';
        if ($probability >= 30) return 'warning';
        return 'danger';
    }

    public function getRecommendationTextAttribute(): string
    {
        switch ($this->ai_recommendation) {
            case 'excellent_match':
                return 'Excellent Match - Apply immediately!';
            case 'good_match':
                return 'Good Match - Strong candidate';
            case 'fair_match':
                return 'Fair Match - Consider improvements';
            case 'poor_match':
                return 'Poor Match - Significant gaps';
            case 'not_recommended':
                return 'Not Recommended - Major misalignment';
            default:
                return 'Analysis Complete';
        }
    }

    public function getRecommendationColorAttribute(): string
    {
        switch ($this->ai_recommendation) {
            case 'excellent_match':
                return 'success';
            case 'good_match':
                return 'primary';
            case 'fair_match':
                return 'info';
            case 'poor_match':
                return 'warning';
            case 'not_recommended':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    public function getKeywordMatchPercentageAttribute(): float
    {
        $matching = count($this->matching_keywords ?? []);
        $missing = count($this->missing_keywords ?? []);
        $total = $matching + $missing;
        
        return $total > 0 ? round(($matching / $total) * 100, 1) : 0;
    }

    public function getTotalKeywordsAttribute(): int
    {
        return count($this->matching_keywords ?? []) + count($this->missing_keywords ?? []);
    }

    public function getMatchingKeywordsCountAttribute(): int
    {
        return count($this->matching_keywords ?? []);
    }

    public function getMissingKeywordsCountAttribute(): int
    {
        return count($this->missing_keywords ?? []);
    }

    public function getSectionScoresAttribute(): array
    {
        return [
            'contact_info' => $this->contact_info_score,
            'summary' => $this->summary_score,
            'experience' => $this->experience_score,
            'education' => $this->education_score,
            'skills' => $this->skills_score,
            'achievements' => $this->achievements_score
        ];
    }

    public function getAverageSectionScoreAttribute(): float
    {
        $scores = $this->section_scores;
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0;
    }

    public function getWeakestSectionAttribute(): string
    {
        $scores = $this->section_scores;
        $minScore = min($scores);
        return array_search($minScore, $scores);
    }

    public function getStrongestSectionAttribute(): string
    {
        $scores = $this->section_scores;
        $maxScore = max($scores);
        return array_search($maxScore, $scores);
    }

    public function getAllRecommendationsAttribute(): array
    {
        $all = [];
        
        if (is_array($this->resume_recommendations)) {
            foreach ($this->resume_recommendations as $rec) {
                $all[] = ['type' => 'resume', 'text' => $rec];
            }
        }
        
        if (is_array($this->cover_letter_recommendations)) {
            foreach ($this->cover_letter_recommendations as $rec) {
                $all[] = ['type' => 'cover_letter', 'text' => $rec];
            }
        }
        
        if (is_array($this->general_recommendations)) {
            foreach ($this->general_recommendations as $rec) {
                $all[] = ['type' => 'general', 'text' => $rec];
            }
        }
        
        return $all;
    }

    public function getPriorityRecommendationsAttribute(): array
    {
        $recommendations = [];
        
        // High priority: Low ATS score
        if ($this->ats_score < 60) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'ats',
                'text' => 'Critical: Improve ATS compatibility to increase visibility'
            ];
        }
        
        // High priority: Low interview probability
        if ($this->interview_probability < 40) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'interview',
                'text' => 'Focus on improving resume-job match to boost interview chances'
            ];
        }
        
        // Medium priority: Missing keywords
        if (count($this->missing_keywords ?? []) > 5) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'keywords',
                'text' => 'Add more relevant keywords from job description'
            ];
        }
        
        // Low priority: Section improvements
        $weakestSection = $this->weakest_section;
        if ($this->section_scores[$weakestSection] < 70) {
            $recommendations[] = [
                'priority' => 'low',
                'type' => 'section',
                'text' => 'Strengthen your ' . str_replace('_', ' ', $weakestSection) . ' section'
            ];
        }
        
        return array_slice($recommendations, 0, 5);
    }

    // Helper Methods
    public function isExcellentMatch(): bool
    {
        return $this->overall_score >= 85 && $this->ats_score >= 80;
    }

    public function isGoodMatch(): bool
    {
        return $this->overall_score >= 70 && $this->ats_score >= 60;
    }

    public function needsImprovement(): bool
    {
        return $this->overall_score < 60 || $this->ats_score < 50;
    }

    public function hasHighInterviewChance(): bool
    {
        return $this->interview_probability >= 70;
    }

    public function getSpiderChartData(): array
    {
        return [
            'labels' => ['ATS Score', 'Resume Match', 'Cover Letter', 'Experience', 'Education', 'Skills'],
            'data' => [
                $this->ats_score,
                $this->resume_match_score,
                $this->cover_letter_match_score,
                $this->experience_score,
                $this->education_score,
                $this->skills_score
            ]
        ];
    }

    public function getImprovementPlan(): array
    {
        $plan = [];
        
        // Step 1: Fix critical ATS issues
        if ($this->ats_score < 60) {
            $plan[] = [
                'step' => 1,
                'title' => 'Fix ATS Compatibility Issues',
                'description' => 'Address formatting and parsing issues that prevent ATS from reading your resume',
                'impact' => 'high',
                'effort' => 'medium'
            ];
        }
        
        // Step 2: Add missing keywords
        if (count($this->missing_keywords ?? []) > 3) {
            $plan[] = [
                'step' => 2,
                'title' => 'Add Missing Keywords',
                'description' => 'Include relevant keywords from job description: ' . implode(', ', array_slice($this->missing_keywords ?? [], 0, 5)),
                'impact' => 'high',
                'effort' => 'low'
            ];
        }
        
        // Step 3: Improve weakest section
        $weakest = $this->weakest_section;
        if ($this->section_scores[$weakest] < 70) {
            $plan[] = [
                'step' => 3,
                'title' => 'Strengthen ' . ucwords(str_replace('_', ' ', $weakest)) . ' Section',
                'description' => 'This section scored only ' . $this->section_scores[$weakest] . '% and needs improvement',
                'impact' => 'medium',
                'effort' => 'medium'
            ];
        }
        
        return $plan;
    }

    // Static Methods
    public static function getAverageScoresByType(): array
    {
        return static::selectRaw('
            analysis_type,
            AVG(overall_score) as avg_overall,
            AVG(ats_score) as avg_ats,
            AVG(resume_match_score) as avg_resume,
            AVG(interview_probability) as avg_interview,
            COUNT(*) as count
        ')
        ->groupBy('analysis_type')
        ->get()
        ->keyBy('analysis_type')
        ->toArray();
    }

    public static function getScoreDistribution(): array
    {
        return [
            'excellent' => static::where('overall_score', '>=', 85)->count(),
            'good' => static::whereBetween('overall_score', [70, 84])->count(),
            'fair' => static::whereBetween('overall_score', [55, 69])->count(),
            'poor' => static::where('overall_score', '<', 55)->count()
        ];
    }

    public static function getTopPerformingJobs(int $limit = 10)
    {
        return static::with('job')
                    ->orderBy('overall_score', 'desc')
                    ->take($limit)
                    ->get();
    }
}