<?php
/**
 * CLI / Web Utility Script to Ingest PDF Manuals into MySQL Document Chunks
 * Usage (CLI): php ingest_pdf.php path/to/document.pdf
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function extract_pdf_text_basic(string $pdfPath): array {
    $pages = [];

    // Method 1: Check if pdftotext CLI is available on the Linux server
    $cmd = "pdftotext " . escapeshellarg($pdfPath) . " -";
    @exec($cmd, $output, $returnVar);

    if ($returnVar === 0 && !empty($output)) {
        $fullText = implode("\n", $output);
        $pageSplits = preg_split('/\f/', $fullText); // Formfeed character is page boundary
        foreach ($pageSplits as $idx => $pText) {
            if (trim($pText)) {
                $pages[$idx + 1] = trim($pText);
            }
        }
        if (!empty($pages)) return $pages;
    }

    // Method 2: Fallback basic regex text stream parser for uncompressed PDF text
    $content = file_get_contents($pdfPath);
    if ($content) {
        preg_match_all('/BT[\s\S]*?ET/s', $content, $matches);
        $extractedText = "";
        foreach ($matches[0] as $btBlock) {
            preg_match_all('/\((.*?)\)\s*Tj/s', $btBlock, $textMatches);
            foreach ($textMatches[1] as $tStr) {
                $extractedText .= $tStr . " ";
            }
            $extractedText .= "\n";
        }
        if (trim($extractedText)) {
            $pages[1] = trim($extractedText);
        }
    }

    return $pages;
}

function ingest_pdf_to_mysql(string $pdfPath): int {
    if (!file_exists($pdfPath)) {
        echo "Error: File '{$pdfPath}' not found.\n";
        return 0;
    }

    $filename = basename($pdfPath);
    $pdo = get_db_connection();

    // 1. Insert/Update Document entry
    $stmt = $pdo->prepare("INSERT INTO documents (filename, file_size) VALUES (?, ?) ON DUPLICATE KEY UPDATE file_size = VALUES(file_size)");
    $stmt->execute([$filename, filesize($pdfPath)]);
    $docId = $pdo->lastInsertId() ?: 1;

    // 2. Extract pages
    $pages = extract_pdf_text_basic($pdfPath);
    if (empty($pages)) {
        echo "Warning: Could not extract plain text from {$filename}. Ensure pdftotext (poppler-utils) is installed.\n";
        return 0;
    }

    // 3. Clear existing chunks for this file
    $delStmt = $pdo->prepare("DELETE FROM document_chunks WHERE filename = ?");
    $delStmt->execute([$filename]);

    // 4. Chunk text into ~800 character blocks and save to DB
    $chunkCount = 0;
    $insertStmt = $pdo->prepare("INSERT INTO document_chunks (document_id, filename, page_number, chunk_index, content) VALUES (?, ?, ?, ?, ?)");

    foreach ($pages as $pageNum => $pageText) {
        $words = explode(' ', $pageText);
        $chunks = array_chunk($words, 150); // ~150 words per chunk

        foreach ($chunks as $chunkIdx => $chunkWords) {
            $chunkContent = implode(' ', $chunkWords);
            if (strlen(trim($chunkContent)) > 20) {
                $insertStmt->execute([$docId, $filename, $pageNum, $chunkIdx, $chunkContent]);
                $chunkCount++;
            }
        }
    }

    echo "Successfully ingested '{$filename}' -> {$chunkCount} text chunks stored in MySQL.\n";
    return $chunkCount;
}

// If executed via CLI:
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    ingest_pdf_to_mysql($argv[1]);
}
