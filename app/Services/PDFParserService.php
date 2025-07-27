<?php
namespace App\Services;
use Smalot\PdfParser\Parser;

class PDFParserService
{
    public function extractText(string $filePath): string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($filePath);
            return $pdf->getText();
        } catch (\Exception $e) {
            throw new \Exception("Failed to extract PDF text: " . $e->getMessage());
        }
    }
}
