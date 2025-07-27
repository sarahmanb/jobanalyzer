<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'username',
        'email',
        'password_hash'
    ];

    protected $hidden = [
        'password_hash'
    ];
    
    protected $dates = [
        'created_at',
        'updated_at'
    ];

    // Relationships
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNotNull('email');
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->username ?: $this->email;
    }

    public function getInitialsAttribute(): string
    {
        $name = $this->display_name;
        $words = explode(' ', $name);
        
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        
        return strtoupper(substr($name, 0, 2));
    }

    public function getTotalJobsAttribute(): int
    {
        return $this->jobs()->count();
    }

    public function getAnalyzedJobsAttribute(): int
    {
        return $this->jobs()->analyzed()->count();
    }

    public function getAverageScoreAttribute(): float
    {
        $jobs = $this->jobs()
                    ->analyzed()
                    ->with('analysis')
                    ->get();

        if ($jobs->isEmpty()) {
            return 0;
        }

        $totalScore = $jobs->sum(function($job) {
            return $job->analysis ? $job->analysis->overall_score : 0;
        });

        return round($totalScore / $jobs->count(), 1);
    }

    public function getBestScoreAttribute(): float
    {
        $bestJob = $this->jobs()
                       ->analyzed()
                       ->with('analysis')
                       ->get()
                       ->sortByDesc(function($job) {
                           return $job->analysis ? $job->analysis->overall_score : 0;
                       })
                       ->first();

        return $bestJob && $bestJob->analysis ? $bestJob->analysis->overall_score : 0;
    }

    public function getJoinedAttribute(): string
    {
        return $this->created_at->format('M Y');
    }

    // Helper Methods
    public function hasJobs(): bool
    {
        return $this->jobs()->exists();
    }

    public function hasAnalyzedJobs(): bool
    {
        return $this->jobs()->analyzed()->exists();
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $this->save();
    }

    public function getStats(): array
    {
        $jobs = $this->jobs()->with('analysis')->get();
        $analyzedJobs = $jobs->where('is_analyzed', true);

        $stats = [
            'total_jobs' => $jobs->count(),
            'analyzed_jobs' => $analyzedJobs->count(),
            'pending_jobs' => $jobs->count() - $analyzedJobs->count(),
            'average_score' => 0,
            'best_score' => 0,
            'jobs_with_high_score' => 0,
            'recent_activity' => $jobs->sortByDesc('created_at')->take(5)
        ];

        if ($analyzedJobs->isNotEmpty()) {
            $scores = $analyzedJobs->map(function($job) {
                return $job->analysis ? $job->analysis->overall_score : 0;
            })->filter();

            $stats['average_score'] = round($scores->avg(), 1);
            $stats['best_score'] = round($scores->max(), 1);
            $stats['jobs_with_high_score'] = $scores->filter(function($score) {
                return $score >= 80;
            })->count();
        }

        return $stats;
    }

    public function getRecentJobs(int $limit = 10)
    {
        return $this->jobs()
                   ->with('analysis')
                   ->orderBy('created_at', 'desc')
                   ->take($limit)
                   ->get();
    }

    public function getTopPerformingJobs(int $limit = 5)
    {
        return $this->jobs()
                   ->analyzed()
                   ->with('analysis')
                   ->get()
                   ->sortByDesc(function($job) {
                       return $job->analysis ? $job->analysis->overall_score : 0;
                   })
                   ->take($limit);
    }

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }

    public function setSetting(string $key, $value): void
    {
        $this->settings()->updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }

    // Static Methods
    public static function createUser(string $username, string $email, string $password): self
    {
        return static::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT)
        ]);
    }

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    public static function findByUsername(string $username): ?self
    {
        return static::where('username', $username)->first();
    }

    public static function authenticate(string $identifier, string $password): ?self
    {
        // Try to find by email first, then username
        $user = static::findByEmail($identifier) ?: static::findByUsername($identifier);

        if ($user && $user->verifyPassword($password)) {
            return $user;
        }

        return null;
    }
}

// UserSetting Model (for user preferences)
class UserSetting extends Model
{
    protected $table = 'user_settings';
    
    protected $fillable = [
        'user_id',
        'setting_key',
        'setting_value'
    ];

    protected $casts = [
        'setting_value' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getDefaults(): array
    {
        return [
            'notifications_enabled' => true,
            'auto_analyze' => false,
            'analysis_type_preference' => 'combined',
            'dashboard_layout' => 'grid',
            'items_per_page' => 10,
            'email_reports' => false,
            'theme' => 'light'
        ];
    }
}