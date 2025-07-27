<?php
namespace App\Validators;

class FileValidator
{
    public function validateResume($uploadedFile): array
    {
        return $this->validateFile($uploadedFile, ['pdf', 'doc', 'docx']);
    }
    
    public function validateCoverLetter($uploadedFile): array
    {
        return $this->validateFile($uploadedFile, ['pdf', 'doc', 'docx']);
    }
    
    private function validateFile($uploadedFile, array $allowedTypes): array
    {
        $errors = [];

        if ($uploadedFile->getSize() > 5 * 1024 * 1024) { // example 5MB max
            $errors[] = 'File is too large.';
        }

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $allowedTypes)) {
            $errors[] = 'Unsupported file type.';
        }

        return $errors;
    }
}
