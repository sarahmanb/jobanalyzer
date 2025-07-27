<?php
namespace App\Validators;

class JobValidator
{
    public function validate(array $data, ?int $jobId = null): array
    {
        $errors = [];
        
        if (empty($data['job_title'])) {
            $errors['job_title'] = 'Job title is required';
        }
        
        if (empty($data['job_description'])) {
            $errors['job_description'] = 'Job description is required';
        } elseif (strlen($data['job_description']) < 50) {
            $errors['job_description'] = 'Job description must be at least 50 characters';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
