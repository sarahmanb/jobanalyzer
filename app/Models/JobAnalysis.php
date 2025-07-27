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
    public function getOverallGradeAttribute(): string
    {
        if ($this->overall_score >= 90) return 'A+';
        if ($this->overall_score >= 85) return 'A';
        if ($this->overall_score >= 80) return 'A-';
        if ($this->overall_score >= 75) return 'B+';
        if ($this->overall_score >= 70) return 'B';
        if ($this->overall_score >= 65) return 'B-';
        if ($this->overall_score >= 60) return 'C+';
        if ($this->overall_score >= 55) return 'C';
        if ($this->overall_score >= 50) return 'C-';
        return 'D';
    }

    public function getRecommendationBadgeColorAttribute(): string
    {
        switch ($this->ai_recommendation) {
            case 'excellent_match':
                return 'success';
            case 'good_match':
                return 'primary';
            case 'fair_match':
                return 'warning';
            case 'poor_match':
                return 'danger';
            case 'not_recommended':
                return 'dark';
            default:
                return 'secondary';
        }
    }

    public function getRecommendationTextAttribute(): string
    {
        switch ($this->ai_recommendation) {
            case 'excellent_match':
                return 'Excellent Match - Apply Immediately';
            case 'good_match':
                return 'Good Match - Strong Candidate';
            case 'fair_match':
                return 'Fair Match - Consider After Improvements';
            case 'poor_match':
                return 'Poor Match - Significant Gaps';
            case 'not_recommended':
                return 'Not Recommended - Major Misalignment';
            default:
                return 'Analysis Pending';
        }
    }

    public function getSectionScoresAttribute(): array
    {
        return [
            'Contact Info' => $this->contact_info_score,
            'Summary' => $this->summary_score,
            'Experience' => $this->experience_score,
            'Education' => $this->education_score,
            'Skills' => $this->skills_score,
            'Achievements' => $this->achievements_score
        ];
    }

    public function getSpiderChartDataAttribute(): array
    {
        return [
            'labels' => ['Contact', 'Summary', 'Experience', 'Education', 'Skills', 'Achievements'],
            'data' => [
                $this->contact_info_score,
                $this->summary_score,
                $this->experience_score,
                $this->education_score,
                $this->skills_score,
                $this->achievements_score
            ]
        ];
    }

    public function getKeywordAnalysisSummaryAttribute(): array
    {
        $matching = is_array($this->matching_keywords) ? count($this->matching_keywords) : 0;
        $missing = is_array($this->missing_keywords) ? count($this->missing_keywords) : 0;
        $suggested = is_array($this->suggested_keywords) ? count($this->suggested_keywords) : 0;

        return [
            'matching_count' => $matching,
            'missing_count' => $missing,
            'suggested_count' => $suggested,
            'total_analyzed' => $matching + $missing,
            'match_percentage' => $matching + $missing > 0 ? round(($matching / ($matching + $missing)) * 100, 1) : 0
        ];
    }

    public function getInterviewOddsTextAttribute(): string
    {
        $probability = $this->interview_probability;
        
        if ($probability >= 80) return 'Very High';
        if ($probability >= 60) return 'High';
        if ($probability >= 40) return 'Moderate';
        if ($probability >= 20) return 'Low';
        return 'Very Low';
    }

    public function getJobOddsTextAttribute(): string
    {
        $probability = $this->job_securing_probability;
        
        if ($probability >= 80) return 'Excellent';
        if ($probability >= 60) return 'Good';
        if ($probability >= 40) return 'Fair';
        if ($probability >= 20) return 'Poor';
        return 'Very Poor';
    }

    public function getConfidenceLevelTextAttribute(): string
    {
        $confidence = $this->ai_confidence_level;
        
        if ($confidence >= 90) return 'Very High Confidence';
        if ($confidence >= 75) return 'High Confidence';
        if ($confidence >= 60) return 'Moderate Confidence';
        if ($confidence >= 40) return 'Low Confidence';
        return 'Very Low Confidence';
    }

    // Helper Methods
    public function getAllRecommendations(): array
    {
        $recommendations = [];
        
        if (is_array($this->resume_recommendations)) {
            foreach ($this->resume_recommendations as $rec) {
                $recommendations[] = ['type' => 'resume', 'text' => $rec];
            }
        }
        
        if (is_array($this->cover_letter_recommendations)) {
            foreach ($this->cover_letter_recommendations as $rec) {
                $recommendations[] = ['type' => 'cover_letter', 'text' => $rec];
            }
        }
        
        if (is_array($this->general_recommendations)) {
            foreach ($this->general_recommendations as $rec) {
                $recommendations[] = ['type' => 'general', 'text' => $rec];
            }
        }
        
        return $recommendations;
    }

    public function getTopMatchingKeywords(int $limit = 10): array
    {
        if (!is_array($this->matching_keywords)) {
            return [];
        }
        
        return array_slice($this->matching_keywords, 0, $limit);
    }

    public function getTopMissingKeywords(int $limit = 10): array
    {
        if (!is_array($this->missing_keywords)) {
            return [];
        }
        
        return array_slice($this->missing_keywords, 0, $limit);
    }

    public function getTopSuggestedKeywords(int $limit = 10): array
    {
        if (!is_array($this->suggested_keywords)) {
            return [];
        }
        
        return array_slice($this->suggested_keywords, 0, $limit);
    }

    public function getScoreColor(float $score): string
    {
        if ($score >= 80) return 'success';
        if ($score >= 60) return 'primary';
        if ($score >= 40) return 'warning';
        return 'danger';
    }

    public function getAnalysisQuality(): string
    {
        $avgScore = ($this->overall_score + $this->ats_score + $this->resume_match_score) / 3;
        
        if ($avgScore >= 85) return 'excellent';
        if ($avgScore >= 70) return 'good';
        if ($avgScore >= 55) return 'fair';
        return 'poor';
    }

    public function hasComprehensiveAnalysis(): bool
    {
        return !empty($this->matching_keywords) && 
               !empty($this->skill_match_analysis) && 
               $this->analysis_type === 'ai_enhanced';
    }

    public function getSkillGaps(): array
    {
        if (!is_array($this->experience_gap_analysis)) {
            return [];
        }

        return $this->experience_gap_analysis['skill_gaps'] ?? [];
    }

    public function getExperienceGaps(): array
    {
        if (!is_array($this->experience_gap_analysis)) {
            return [];
        }

        return $this->experience_gap_analysis['experience_gaps'] ?? [];
    }

    public function getEducationGaps(): array
    {
        if (!is_array($this->education_match_analysis)) {
            return [];
        }

        return $this->education_match_analysis['education_gaps'] ?? [];
    }

    // Static Methods
    public static function getRecommendationTypes(): array
    {
        return [
            'excellent_match' => 'Excellent Match',
            'good_match' => 'Good Match',
            'fair_match' => 'Fair Match',
            'poor_match' => 'Poor Match',
            'not_recommended' => 'Not Recommended'
        ];
    }

    public static function getAnalysisTypes(): array
    {
        return [
            'basic' => 'Basic Analysis',
            'ai_enhanced' => 'AI Enhanced',
            'combined' => 'Combined Analysis'
        ];
    }

    public function calculateMatchStrength(): array
    {
        $strengths = [];
        $weaknesses = [];
        
        $scores = $this->section_scores;
        
        foreach ($scores as $section => $score) {
            if ($score >= 75) {
                $strengths[] = $section;
            } elseif ($score < 50) {
                $weaknesses[] = $section;
            }
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'strength_count' => count($strengths),
            'weakness_count' => count($weaknesses)
        ];
    }
}