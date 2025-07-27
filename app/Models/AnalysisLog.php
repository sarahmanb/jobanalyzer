<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AnalysisLog extends Model
{
    protected $table = 'analysis_logs';
    protected $fillable = ['job_id', 'analysis_id', 'log_level', 'message', 'additional_data'];
    protected $casts = ['additional_data' => 'array'];
    
    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
