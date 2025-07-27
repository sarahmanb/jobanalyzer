<?php
// app/Services/PDFParserService.php

namespace App\Services;

use Spatie\PdfToText\Pdf;
use Smalot\PdfParser\Parser;
use Exception;

class PDFParserService
{
    private $errors = [];
    private $pdfParser;
    
    public function __construct()
    {
        $this->pdfParser = new Parser();
    }

    /**
     * Extract text from PDF using multiple methods for best results
     */
    public function extractText(string $filePath): string
    {
        $this->errors = [];
        $extractedText = '';

        // Method 1: Try Spatie PDF-to-Text (requires pdftotext binary)
        try {
            $text = Pdf::getText($filePath);
            if (!empty(trim($text)) && !$this->hasExtractionIssues($text)) {
                return $this->cleanText($text);
            }
        } catch (Exception $e) {
            $this->errors[] = 'Spatie extraction failed: ' . $e->getMessage();
        }

        // Method 2: Try Smalot PDF Parser
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $text = $pdf->getText();
            if (!empty(trim($text)) && !$this->hasExtractionIssues($text)) {
                return $this->cleanText($text);
            }
            $extractedText = $text; // Keep as fallback
        } catch (Exception $e) {
            $this->errors[] = 'Smalot extraction failed: ' . $e->getMessage();
        }

        // Method 3: Try manual page-by-page extraction
        try {
            $text = $this->extractPageByPage($filePath);
            if (!empty(trim($text))) {
                return $this->cleanText($text);
            }
        } catch (Exception $e) {
            $this->errors[] = 'Page-by-page extraction failed: ' . $e->getMessage();
        }

        // Return whatever we got, even if poor quality
        if (!empty(trim($extractedText))) {
            return $this->cleanText($extractedText);
        }

        throw new Exception('Failed to extract text from PDF: ' . implode('; ', $this->errors));
    }

    /**
     * Extract detailed information from PDF
     */
    public function extractDetailedInfo(string $filePath): array
    {
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $details = $pdf->getDetails();
            $pages = $pdf->getPages();
            
            $allText = '';
            $pageContents = [];
            
            foreach ($pages as $pageNum => $page) {
                $pageText = $page->getText();
                $allText .= $pageText . "\n\n";
                $pageContents[] = [
                    'page' => $pageNum + 1,
                    'content' => $this->cleanText($pageText),
                    'word_count' => str_word_count($pageText),
                    'char_count' => strlen($pageText)
                ];
            }

            return [
                'success' => true,
                'text' => $this->cleanText($allText),
                'pages' => $pageContents,
                'metadata' => [
                    'title' => $details['Title'] ?? 'N/A',
                    'author' => $details['Author'] ?? 'N/A',
                    'creator' => $details['Creator'] ?? 'N/A',
                    'producer' => $details['Producer'] ?? 'N/A',
                    'creation_date' => $details['CreationDate'] ?? 'N/A',
                    'total_pages' => count($pages),
                    'total_words' => str_word_count($allText),
                    'total_chars' => strlen($allText)
                ],
                'quality_score' => $this->assessExtractionQuality($allText),
                'issues' => $this->detectIssues($allText)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'text' => '',
                'pages' => [],
                'metadata' => [],
                'quality_score' => 0,
                'issues' => ['Failed to parse PDF']
            ];
        }
    }

    /**
     * Validate PDF file before processing
     */
    public function validatePDF(string $filePath): array
    {
        $validation = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'file_info' => []
        ];

        // Check file existence
        if (!file_exists($filePath)) {
            $validation['errors'][] = 'File does not exist';
            return $validation;
        }

        // Check file size
        $fileSize = filesize($filePath);
        $validation['file_info']['size'] = $fileSize;
        $validation['file_info']['size_mb'] = round($fileSize / 1024 / 1024, 2);

        if ($fileSize === 0) {
            $validation['errors'][] = 'File is empty';
            return $validation;
        }

        if ($fileSize > 50 * 1024 * 1024) { // 50MB limit
            $validation['warnings'][] = 'File is very large (>50MB), processing may be slow';
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $validation['file_info']['mime_type'] = $mimeType;

        if ($mimeType !== 'application/pdf') {
            $validation['errors'][] = 'File is not a valid PDF (detected: ' . $mimeType . ')';
            return $validation;
        }

        // Try to open with parser to check integrity
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $pages = $pdf->getPages();
            
            $validation['file_info']['pages'] = count($pages);
            
            if (count($pages) === 0) {
                $validation['warnings'][] = 'PDF appears to have no readable pages';
            }

            // Test text extraction on first page
            if (count($pages) > 0) {
                $firstPage = reset($pages);
                $testText = $firstPage->getText();
                
                if (empty(trim($testText))) {
                    $validation['warnings'][] = 'PDF may be image-based or protected';
                } elseif ($this->hasExtractionIssues($testText)) {
                    $validation['warnings'][] = 'PDF may have text extraction issues';
                }
            }

            $validation['valid'] = true;

        } catch (Exception $e) {
            $validation['errors'][] = 'Cannot parse PDF: ' . $e->getMessage();
        }

        return $validation;
    }

    /**
     * Extract text page by page for better error handling
     */
    private function extractPageByPage(string $filePath): string
    {
        $pdf = $this->pdfParser->parseFile($filePath);
        $pages = $pdf->getPages();
        $allText = '';

        foreach ($pages as $pageNum => $page) {
            try {
                $pageText = $page->getText();
                $allText .= $pageText . "\n\n";
            } catch (Exception $e) {
                // Skip problematic pages but continue
                $this->errors[] = "Page {$pageNum} extraction failed: " . $e->getMessage();
                continue;
            }
        }

        return $allText;
    }

    /**
     * Clean and normalize extracted text
     */
    private function cleanText(string $text): string
    {
        // Remove multiple whitespaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove problematic characters
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
        
        // Remove extra spaces around punctuation
        $text = preg_replace('/\s*([.,;:!?])\s*/', '$1 ', $text);
        
        // Clean up common PDF artifacts
        $text = str_replace(['�', '�', ''], '', $text);
        
        // Normalize quotes
        $text = str_replace(['"', '"', ''', '''], ['"', '"', "'", "'"], $text);
        
        return trim($text);
    }

    /**
     * Check if extracted text has common issues
     */
    private function hasExtractionIssues(string $text): bool
    {
        // Check for garbled text indicators
        $issues = [
            strlen($text) < 50,  // Too short
            substr_count($text, '�') > 5,  // Encoding issues
            preg_match('/[^\x20-\x7E\s]/', $text),  // Non-printable characters
            str_word_count($text) < 10  // Too few words
        ];

        return array_sum($issues) >= 2;
    }

    /**
     * Assess the quality of extracted text
     */
    private function assessExtractionQuality(string $text): int
    {
        $score = 100;
        
        // Penalize for length issues
        $wordCount = str_word_count($text);
        if ($wordCount < 50) {
            $score -= 40;
        } elseif ($wordCount < 100) {
            $score -= 20;
        }

        // Penalize for encoding issues
        $badChars = substr_count($text, '�');
        $score -= min(30, $badChars * 2);

        // Penalize for poor readability
        if (preg_match_all('/[A-Z][a-z]+/', $text) < $wordCount * 0.3) {
            $score -= 15;
        }

        // Penalize for lack of structure
        if (!preg_match('/\b(experience|education|skills|summary|objective)\b/i', $text)) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * Detect specific issues with extracted text
     */
    private function detectIssues(string $text): array
    {
        $issues = [];

        if (strlen($text) < 100) {
            $issues[] = 'Text too short - may be image-based PDF';
        }

        if (substr_count($text, '�') > 0) {
            $issues[] = 'Character encoding problems detected';
        }

        if (str_word_count($text) < 50) {
            $issues[] = 'Very few readable words found';
        }

        if (!preg_match('/[a-zA-Z]{3,}/', $text)) {
            $issues[] = 'No meaningful words detected';
        }

        if (preg_match('/(.)\1{10,}/', $text)) {
            $issues[] = 'Repetitive character patterns detected';
        }

        return $issues;
    }

    /**
     * Get extraction statistics
     */
    public function getExtractionStats(string $text): array
    {
        return [
            'total_chars' => strlen($text),
            'word_count' => str_word_count($text),
            'line_count' => substr_count($text, "\n"),
            'sentence_count' => preg_match_all('/[.!?]+/', $text),
            'avg_word_length' => $this->getAverageWordLength($text),
            'readability_score' => $this->calculateReadabilityScore($text),
            'has_email' => preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text) ? true : false,
            'has_phone' => preg_match('/(\+?\d{1,3}[-.\s]?)?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,9}/', $text) ? true : false,
            'has_urls' => preg_match('/https?:\/\/[^\s]+/', $text) ? true : false
        ];
    }

    /**
     * Calculate average word length
     */
    private function getAverageWordLength(string $text): float
    {
        $words = str_word_count($text, 1);
        if (empty($words)) return 0;

        $totalLength = array_sum(array_map('strlen', $words));
        return round($totalLength / count($words), 1);
    }

    /**
     * Simple readability score calculation
     */
    private function calculateReadabilityScore(string $text): float
    {
        $words = str_word_count($text);
        $sentences = preg_match_all('/[.!?]+/', $text);
        
        if ($sentences === 0 || $words === 0) return 0;

        // Simple Flesch Reading Ease approximation
        $avgWordsPerSentence = $words / $sentences;
        $avgSyllablesPerWord = $this->estimateSyllables($text) / $words;
        
        $score = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);
        
        return max(0, min(100, round($score, 1)));
    }

    /**
     * Estimate syllable count
     */
    private function estimateSyllables(string $text): int
    {
        $words = str_word_count($text, 1);
        $totalSyllables = 0;

        foreach ($words as $word) {
            $word = strtolower($word);
            $syllables = preg_match_all('/[aeiouy]+/', $word);
            
            // Adjust for silent e
            if (substr($word, -1) === 'e' && $syllables > 1) {
                $syllables--;
            }
            
            $totalSyllables += max(1, $syllables);
        }

        return $totalSyllables;
    }

    /**
     * Get errors from last extraction attempt
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if PDF contains images (heuristic)
     */
    public function hasImages(string $filePath): bool
    {
        try {
            $content = file_get_contents($filePath);
            // Look for image-related PDF objects
            return strpos($content, '/Image') !== false || 
                   strpos($content, '/DCTDecode') !== false ||
                   strpos($content, '/JPXDecode') !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Estimate if PDF is primarily image-based
     */
    public function isImageBased(string $filePath): bool
    {
        try {
            $textInfo = $this->extractDetailedInfo($filePath);
            
            if (!$textInfo['success']) return true;
            
            $wordCount = str_word_count($textInfo['text']);
            $pageCount = count($textInfo['pages']);
            
            // If less than 50 words per page on average, likely image-based
            return ($wordCount / max(1, $pageCount)) < 50;
            
        } catch (Exception $e) {
            return true;
        }
    }
}