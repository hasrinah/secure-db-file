<?php

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

    // Generate AES key from the original filename using MD5
    private function generateKey($fileName)
    {
        return md5($fileName); // 32-character key for AES
    }

    // Encrypt data using AES-256-CBC and return Base64 encoded ciphertext
    private function encryptData($data, $key, $iv)
    {
        return base64_encode(openssl_encrypt($data, $this->cipher, $key, 0, $iv));
    }

    // Process the file for encryption
    public function encryptFile($filePath, $secureDir)
    {
        // Get the original file name
        $originalFileName = basename($filePath);
        
        // Generate AES key from the original filename
        $key = $this->generateKey($originalFileName);
        
        // Create a random IV for AES encryption
        $iv = openssl_random_pseudo_bytes($this->ivLength);

        // Open the file for reading
        $file = fopen($filePath, 'r');
        $rows = [];
        $columnCount = 0;
        $rowCount = 1; // Start counting rows for RID

        while (($line = fgetcsv($file, 0, '|')) !== FALSE) {
            // Add RID column (sequential numbering: 1, 2, 3, ...)
            array_unshift($line, $rowCount);

            // Encrypt each column except the RID
            for ($i = 1; $i < count($line); $i++) {
                $line[$i] = $this->encryptData($line[$i], $key, $iv);
            }

            $rows[] = $line;
            $columnCount = max($columnCount, count($line));
            $rowCount++; // Increment the RID counter
        }

        fclose($file);

        // Get the row count
        $rowCount = count($rows);

        // Save the AES key, IV, and metadata to AES.KEY
        
        $aesKeyFile = $secureDir . 'AES.KEY';
        $keys = [];

        // If the key file exists, decode its JSON array (handle both old single‑object and array formats).
        if (file_exists($aesKeyFile)) {
            $existing = json_decode(file_get_contents($aesKeyFile), true);
            if ($existing) {
                // If it’s a single object, wrap it into an array.
                $keys = isset($existing[0]) ? $existing : [$existing];
            }
        }

        $keys[] = [
            'fileName' => $originalFileName,
            'key'      => $key,
            'iv'       => base64_encode($iv),
            'columns'  => $columnCount,
            'rows'     => $rowCount
        ];

        file_put_contents($aesKeyFile, json_encode($keys)); // write back the array


        // Export the encrypted data to a new file
        $outputFileName = $secureDir . $originalFileName . '_' . substr(md5($originalFileName), -10) . '.dbfile';
        $outputFile = fopen($outputFileName, 'w');

        foreach ($rows as $encryptedRow) {
            fwrite($outputFile, implode('|', $encryptedRow) . "\n");
        }

        fclose($outputFile);

        return $outputFileName;
    }
}
?>
