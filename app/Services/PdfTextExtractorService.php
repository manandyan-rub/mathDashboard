<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractorService
{
    public function extract(string $path): string
    {
        if (!class_exists(Parser::class)) {
            throw new RuntimeException('PDF parser is not installed.');
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        return trim((string) $pdf->getText());
    }
}
