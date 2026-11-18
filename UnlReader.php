<?php

/**
 * Reads pipe-delimited .unl files: one record per line, with every field
 * followed by "|" (so "1001|Alice|" is the two fields "1001" and "Alice").
 */
class UnlReader
{
    public static function readRows(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return $rows;
        }

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue; // Skip blank lines
            }
            // Drop the record terminator so it does not create an empty extra column
            if (substr($line, -1) === '|') {
                $line = substr($line, 0, -1);
            }
            $rows[] = explode('|', $line);
        }

        fclose($handle);
        return $rows;
    }
}
