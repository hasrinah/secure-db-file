<?php

require_once __DIR__ . '/UnlReader.php';

class AesEncryption
{
    // AES encryption parameters
    private $cipher = 'aes-256-cbc';
    private $ivLength;

    public function __construct()
    {
        // Set the IV length for AES-256-CBC
        $this->ivLength = openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Encrypt one value with its own random IV.
     * Output is base64(IV || ciphertext), so equal values never look the same.
     */
    private function encryptData(string $data, string $key): string
    {
        $iv = random_bytes($this->ivLength);
        $ciphertext = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    // Process the file for encryption
    public function encryptFile($filePath, $secureDir)
    {
        $secureDir = rtrim($secureDir, '/') . '/';
        $originalFileName = basename($filePath);

        // Random 256-bit key for each file (cannot be guessed from the file name)
        $key = random_bytes(32);

        $rows = [];
        $columnCount = 0;
        $rowId = 1; // Sequential RID: 1, 2, 3, ...

        foreach (UnlReader::readRows($filePath) as $fields) {
            // Encrypt every column; the RID stays in clear text
            $encryptedRow = array_map(fn ($field) => $this->encryptData($field, $key), $fields);
            array_unshift($encryptedRow, $rowId++);

            $rows[] = $encryptedRow;
            $columnCount = max($columnCount, count($encryptedRow));
        }

        $this->saveKey($secureDir . 'AES.KEY', [
            'fileName' => $originalFileName,
            'key'      => base64_encode($key),
            'columns'  => $columnCount,
            'rows'     => count($rows),
            'version'  => 2,
        ]);

        // Export the encrypted data to a new file
        $outputFileName = $secureDir . $originalFileName . '_' . substr(md5($originalFileName), -10) . '.dbfile';

        $content = '';
        foreach ($rows as $encryptedRow) {
            $content .= implode('|', $encryptedRow) . "\n";
        }
        file_put_contents($outputFileName, $content, LOCK_EX);

        return $outputFileName;
    }

    /**
     * Store key metadata in AES.KEY as a JSON array. An older entry for the
     * same file name is replaced, so re-uploading a file never leaves a
     * stale key that would be picked up first during decryption.
     */
    private function saveKey(string $keyFile, array $entry): void
    {
        $keys = [];
        if (is_readable($keyFile)) {
            $existing = json_decode((string) file_get_contents($keyFile), true);
            if (is_array($existing) && array_is_list($existing)) {
                $keys = $existing;
            }
        }

        $keys = array_values(array_filter(
            $keys,
            fn ($record) => ($record['fileName'] ?? null) !== $entry['fileName']
        ));
        $keys[] = $entry;

        file_put_contents($keyFile, json_encode($keys, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($keyFile, 0600);
    }
}
