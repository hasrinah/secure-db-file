<?php

class Md5Processor
{
    // Method to generate MD5 hashes for a file's rows and columns
    public function processFile($filePath, $integrityDir)
    {
        // Read the file and process
        $file = fopen($filePath, 'r');
        $rows = [];
        $columnCount = 0;

        while (($line = fgetcsv($file, 1000, '|')) !== FALSE) {
            // Process the row
            $rows[] = $line;
            // Count the number of columns (assuming consistent column length)
            $columnCount = max($columnCount, count($line));
        }

        fclose($file);

        // Get the row count
        $rowCount = count($rows);

        // Generate MD5 hash for each row
        $hashedRows = [];
        foreach ($rows as $row) {
            $hashedRow = [];
            foreach ($row as $field) {
                $hashedRow[] = md5($field);
            }
            $hashedRows[] = implode('|', $hashedRow);
        }

        // Write the new file in the integrity folder
        $integrityFileName = $integrityDir . pathinfo($filePath, PATHINFO_FILENAME) . '_integrity.file';
        $outputFile = fopen($integrityFileName, 'w');

        foreach ($hashedRows as $hashedRow) {
            fwrite($outputFile, $hashedRow . "\n");
        }

        // Add the column and row count to the last line
        $summaryLine = "No of col: $columnCount No of row: $rowCount\n";
        fwrite($outputFile, $summaryLine);

        fclose($outputFile);

        return $integrityFileName;
    }
}
?>
