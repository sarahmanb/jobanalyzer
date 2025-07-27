<?php
// app/Models/Job.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Job extends Model
{
    protected $table = 'jobs';
    
    protected $fillable = [
        'user_id',
        'job_title',
        'company_name',
        'job_description',
        'resume_path',
        'cover_letter_path',
        'salary_min',
        'salary_max',
        'location',
        'experience_required',
        'education_required',
        'employment_type',
        'industry',
        'job_level',
        'hard_skills',
        'soft_skills',
        'languages_required',
        'certifications_required',
        'is_analyzed',
        'analysis_completed_at'
    ];

    protected $casts = [
        'hard_skills' => 'array',
        'soft_skills' => 'array',
        'languages_required' => 'array',
        'certifications_required' => 'array',
        'is_analyzed' => 'boolean',
        'analysis_completed_at' => 'datetime',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at',
        'analysis_completed_at'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysis(): HasOne
    {
        return $this->hasOne(JobAnalysis::class);
    }

    public function analysisLogs(): HasMany
    {
        return $this->hasMany(AnalysisLog::class);
    }

    // Scopes
    public function scopeAnalyzed($query)
    {
        return $query->where('is_analyzed', true);
    }

    public function scopeNotAnalyzed($query)
    {
        return $query->where('is_analyzed', false);
    }

    public function scopeByCompany($query, $company)
    {
        return $query->where('company_name', 'LIKE', "%{$company}%");
    }

    public function scopeByTitle($query, $title)
    {
        return $query->where('job_title', 'LIKE', "%{$title}%");
    }

    public function scopeByEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    public function scopeByJobLevel($query, $level)
    {
        return $query->where('job_level', $level);
    }

    // Accessors
    public function getFormattedSalaryAttribute()
    {
        if ($this->salary_min && $this->salary_max) {
            return '$' . number_format($this->salary_min) . ' - $' . number_format($this->salary_max);
        } elseif ($this->salary_min) {
            return '$' . number_format($this->salary_min) . '+';
        } elseif ($this->salary_max) {
            return 'Up to $' . number_format($this->salary_max);
        }
        return 'Not specified';
    }

    public function getResumeFilenameAttribute()
    {
        return $this->resume_path ? basename($this->resume_path) : null;
    }

    public function getCoverLetterFilenameAttribute()
    {
        return $this->cover_letter_path ? basename($this->cover_letter_path) : null;
    }

    public function getTotalSkillsCountAttribute()
    {
        $hardSkills = is_array($this->hard_skills) ? count($this->hard_skills) : 0;
        $softSkills = is_array($this->soft_skills) ? count($this->soft_skills) : 0;
        return $hardSkills + $softSkills;
    }

    public function getAnalysisStatusAttribute()
    {
        if (!$this->is_analyzed) {
            return 'pending';
        }

        if ($this->analysis && $this->analysis->overall_score) {
            if ($this->analysis->overall_score >= 80) {
                return 'excellent';
            } elseif ($this->analysis->overall_score >= 60) {
                return 'good';
            } else {
                return 'needs_improvement';
            }
        }

        return 'analyzed';
    }

    public function getAnalysisStatusColorAttribute()
    {
        switch ($this->analysis_status) {
            case 'excellent':
                return 'success';
            case 'good':
                return 'primary';
            case 'needs_improvement':
                return 'warning';
            case 'pending':
                return 'secondary';
            default:
                return 'info';
        }
    }

    // Mutators
    public function setJobDescriptionAttribute($value)
    {
        $this->attributes['job_description'] = trim($value);
    }

    public function setJobTitleAttribute($value)
    {
        $this->attributes['job_title'] = trim($value);
    }

    public function setCompanyNameAttribute($value)
    {
        $this->attributes['company_name'] = trim($value);
    }

    // Helper Methods
    public function hasResume(): bool
    {
        return !empty($this->resume_path) && file_exists(public_path($this->resume_path));
    }

    public function hasCoverLetter(): bool
    {
        return !empty($this->cover_letter_path) && file_exists(public_path($this->cover_letter_path));
    }

    public function getResumeUrl(): ?string
    {
        return $this->hasResume() ? url($this->resume_path) : null;
    }

    public function getCoverLetterUrl(): ?string
    {
        return $this->hasCoverLetter() ? url($this->cover_letter_path) : null;
    }

    public function markAsAnalyzed(): void
    {
        $this->update([
            'is_analyzed' => true,
            'analysis_completed_at' => now()
        ]);
    }

    public function resetAnalysis(): void
    {
        $this->update([
            'is_analyzed' => false,
            'analysis_completed_at' => null
        ]);
        
        // Delete existing analysis
        if ($this->analysis) {
            $this->analysis->delete();
        }
    }

    public function getSkillsForDisplay(): array
    {
        $skills = [];
        
        if (is_array($this->hard_skills)) {
            foreach ($this->hard_skills as $skill) {
                $skills[] = ['name' => $skill, 'type' => 'hard'];
            }
        }
        
        if (is_array($this->soft_skills)) {
            foreach ($this->soft_skills as $skill) {
                $skills[] = ['name' => $skill, 'type' => 'soft'];
            }
        }
        
        return $skills;
    }

    public function calculateCompletionPercentage(): int
    {
        $fields = [
            'job_title',
            'company_name',
            'job_description',
            'resume_path',
            'cover_letter_path',
            'location',
            'employment_type',
            'job_level'
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $completed++;
            }
        }

        return round(($completed / count($fields)) * 100);
    }

    // Static Methods
    public static function getEmploymentTypes(): array
    {
        return [
            'full-time' => 'Full Time',
            'part-time' => 'Part Time',
            'contract' => 'Contract',
            'internship' => 'Internship',
            'remote' => 'Remote'
        ];
    }

    public static function getJobLevels(): array
    {
        return [
            'entry' => 'Entry Level',
            'mid' => 'Mid Level',
            'senior' => 'Senior Level',
            'executive' => 'Executive'
        ];
    }

    public static function getAnalysisStatuses(): array
    {
        return [
            'pending' => 'Pending Analysis',
            'analyzed' => 'Analyzed',
            'excellent' => 'Excellent Match',
            'good' => 'Good Match',
            'needs_improvement' => 'Needs Improvement'
        ];
    }
}

// Helper functions
function public_path($path = '')
{
    return __DIR__ . '/../../public/' . ltrim($path, '/');
}

function url($path = '')
{
    return $_ENV['APP_URL'] . '/' . ltrim($path, '/');
}

function now()
{
    return new \DateTime();
}