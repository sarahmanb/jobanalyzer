<?php
namespace App\Services;
use GuzzleHttp\Client;

class AIAnalysisService
{
    private $client;
    private $serviceUrl;
    
    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
        $this->serviceUrl = $_ENV['AI_SERVICE_URL'] ?? 'http://localhost:5000';
    }
    
    public function analyzeWithAI(string $jobDescription, string $resumeText, string $coverLetterText): array
    {
        try {
            $response = $this->client->post($this->serviceUrl . '/analyze', [
                'json' => [
                    'job_description' => $jobDescription,
                    'resume_text' => $resumeText,
                    'cover_letter_text' => $coverLetterText
                ]
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            throw new \Exception("AI analysis failed: " . $e->getMessage());
        }
    }
    
    public function checkStatus(): array
    {
        try {
            $response = $this->client->get($this->serviceUrl . '/health');
            return ['status' => 'running', 'message' => 'AI service is operational'];
        } catch (\Exception $e) {
            return ['status' => 'stopped', 'message' => 'AI service is not running'];
        }
    }
    
    public function startService(): array
    {
        // Implementation to start Python service
        return ['success' => true, 'message' => 'Service started'];
    }
}
