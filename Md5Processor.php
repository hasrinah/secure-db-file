<?php

require_once __DIR__ . '/UnlReader.php';

class Md5Processor
{
    /**
     * Write an integrity file holding the MD5 checksum of every field, plus a
     * summary line with the column and row counts.
     *
     * MD5 detects accidental corruption, not deliberate tampering. The
     * integrity folder must not be web-accessible, because checksums of
     * short values can be reversed by brute force.
     */
    public function processFile($filePath, $integrityDir)
    {
        $rows = UnlReader::readRows($filePath);
        $columnCount = 0;
        $hashedRows = [];

        foreach ($rows as $row) {
            $columnCount = max($columnCount, count($row));
            $hashedRows[] = implode('|', array_map('md5', $row));
        }

        $rowCount = count($rows);

        $integrityFileName = rtrim($integrityDir, '/') . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '_integrity.file';

        $content = '';
        foreach ($hashedRows as $hashedRow) {
            $content .= $hashedRow . "\n";
        }
        // Add the column and row count to the last line
        $content .= "No of col: $columnCount No of row: $rowCount\n";

        file_put_contents($integrityFileName, $content, LOCK_EX);

        return $integrityFileName;
    }
}
