<?php
// app/Models/AnalysisLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisLog extends Model
{
    protected $table = 'analysis_logs';
    
    protected $fillable = [
        'job_id',
        'analysis_id',
        'log_level',
        'message',
        'additional_data'
    ];

    protected $casts = [
        'additional_data' => 'array'
    ];
    
    protected $dates = [
        'created_at'
    ];

    // Disable updated_at since logs are immutable
    public $timestamps = ['created_at'];
    const UPDATED_AT = null;

    // Relationships
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(JobAnalysis::class, 'analysis_id');
    }

    // Scopes
    public function scopeInfo($query)
    {
        return $query->where('log_level', 'info');
    }

    public function scopeWarning($query)
    {
        return $query->where('log_level', 'warning');
    }

    public function scopeError($query)
    {
        return $query->where('log_level', 'error');
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeForJob($query, int $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    // Accessors
    public function getLogLevelColorAttribute(): string
    {
        switch ($this->log_level) {
            case 'info':
                return 'primary';
            case 'warning':
                return 'warning';
            case 'error':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    public function getLogLevelIconAttribute(): string
    {
        switch ($this->log_level) {
            case 'info':
                return 'fas fa-info-circle';
            case 'warning':
                return 'fas fa-exclamation-triangle';
            case 'error':
                return 'fas fa-times-circle';
            default:
                return 'fas fa-circle';
        }
    }

    public function getFormattedMessageAttribute(): string
    {
        $message = $this->message;
        
        // Add context from additional_data if available
        if (!empty($this->additional_data)) {
            $context = [];
            foreach ($this->additional_data as $key => $value) {
                if (is_scalar($value)) {
                    $context[] = "{$key}: {$value}";
                }
            }
            
            if (!empty($context)) {
                $message .= ' [' . implode(', ', $context) . ']';
            }
        }
        
        return $message;
    }

    public function getRelativeTimeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    // Static Methods
    public static function logInfo(int $jobId, string $message, array $data = [], int $analysisId = null): self
    {
        return static::create([
            'job_id' => $jobId,
            'analysis_id' => $analysisId,
            'log_level' => 'info',
            'message' => $message,
            'additional_data' => $data
        ]);
    }

    public static function logWarning(int $jobId, string $message, array $data = [], int $analysisId = null): self
    {
        return static::create([
            'job_id' => $jobId,
            'analysis_id' => $analysisId,
            'log_level' => 'warning',
            'message' => $message,
            'additional_data' => $data
        ]);
    }

    public static function logError(int $jobId, string $message, array $data = [], int $analysisId = null): self
    {
        return static::create([
            'job_id' => $jobId,
            'analysis_id' => $analysisId,
            'log_level' => 'error',
            'message' => $message,
            'additional_data' => $data
        ]);
    }

    public static function getLogSummary(int $jobId): array
    {
        $logs = static::forJob($jobId)->get();
        
        return [
            'total' => $logs->count(),
            'info' => $logs->where('log_level', 'info')->count(),
            'warning' => $logs->where('log_level', 'warning')->count(),
            'error' => $logs->where('log_level', 'error')->count(),
            'latest' => $logs->first()
        ];
    }

    public static function getRecentActivity(int $days = 7, int $limit = 50)
    {
        return static::with(['job', 'analysis'])
                    ->recent($days)
                    ->orderBy('created_at', 'desc')
                    ->take($limit)
                    ->get();
    }

    public static function getErrorSummary(int $days = 30): array
    {
        $errors = static::error()
                       ->recent($days)
                       ->get()
                       ->groupBy('message');

        $summary = [];
        foreach ($errors as $message => $logs) {
            $summary[] = [
                'message' => $message,
                'count' => $logs->count(),
                'latest_occurrence' => $logs->first()->created_at,
                'affected_jobs' => $logs->pluck('job_id')->unique()->count()
            ];
        }

        // Sort by frequency
        usort($summary, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $summary;
    }

    public static function cleanOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        return static::where('created_at', '<', $cutoffDate)->delete();
    }

    // Helper Methods
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Add computed attributes
        $array['log_level_color'] = $this->log_level_color;
        $array['log_level_icon'] = $this->log_level_icon;
        $array['formatted_message'] = $this->formatted_message;
        $array['relative_time'] = $this->relative_time;
        
        return $array;
    }
}

// Helper function for current timestamp
function now()
{
    return new \DateTime();
}