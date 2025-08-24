<?php

/**
 * Class AesDecryption
 *
 * This class is responsible for reversing the transformation applied by
 * the corresponding ``AesEncryption`` class.  It reads encrypted ``.dbfile``
 * files produced by the encryption routine, looks up the appropriate
 * encryption key and IV from an ``AES.KEY`` file, and produces a two‑
 * dimensional array of decrypted values.
 *
 * The decryption logic has been enhanced to support multiple entries in
 * the key file.  If your encryption script appends key metadata for each
 * uploaded file (e.g. storing a JSON array of objects), this class will
 * iterate through those entries and select the one whose ``fileName``
 * matches the original file name encoded in the ``.dbfile`` name.  It
 * remains backwards compatible with a single‑object key file as well.
 */
class AesDecryption
{
    /**
     * Cipher method used for encryption/decryption.  Must match the
     * corresponding value in AesEncryption.
     *
     * @var string
     */
    private $cipher = 'aes-256-cbc';

    /**
     * Length of the initialization vector (IV) for the cipher.
     * Calculated on construction.
     *
     * @var int
     */
    private $ivLength;

    /**
     * Constructor.  Determines the IV length based on the cipher.
     */
    public function __construct()
    {
        $this->ivLength = openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Retrieve the encryption key and IV from a key file.  Supports both
     * a single JSON object and an array of objects.  When the key file
     * contains multiple entries, the original file name is used to locate
     * the correct record.
     *
     * @param string $keyFile         Path to the ``AES.KEY`` file.
     * @param string $originalFileName The original file name (without the
     *                                ``MD5`` suffix) extracted from the
     *                                ``.dbfile`` name.
     * @return array|false            An associative array with ``key`` and
     *                                ``iv`` on success, or ``false`` on
     *                                failure.
     */
    private function getKeyAndIv(string $keyFile, string $originalFileName)
    {
        // Ensure the key file exists and is readable.
        if (!is_readable($keyFile)) {
            return false;
        }

        $json = file_get_contents($keyFile);
        if ($json === false || $json === '') {
            return false;
        }

        $keyData = json_decode($json, true);
        if ($keyData === null) {
            // Invalid JSON
            return false;
        }

        $entry = null;

        // If the decoded JSON is an array of entries, find the one that
        // matches the original file name.  The encryption script stores
        // ``fileName`` as the base name of the uploaded file.
        if (array_keys($keyData) === range(0, count($keyData) - 1)) {
            // Indexed array: iterate to find matching fileName
            foreach ($keyData as $record) {
                if (isset($record['fileName']) && $record['fileName'] === $originalFileName) {
                    $entry = $record;
                    break;
                }
            }
        } else {
            // Single entry: ensure the fileName matches or accept it
            if (isset($keyData['fileName']) && $keyData['fileName'] === $originalFileName) {
                $entry = $keyData;
            }
        }

        // Validate that the entry contains both key and iv
        if (!$entry || !isset($entry['key']) || !isset($entry['iv'])) {
            return false;
        }

        // Decode the IV from base64.  The encryption script saved the IV as
        // base64 so it could be stored as text.
        $iv = base64_decode($entry['iv']);
        if ($iv === false || strlen($iv) !== $this->ivLength) {
            return false;
        }

        return [
            'key' => $entry['key'],
            'iv'  => $iv,
        ];
    }

    /**
     * Decrypt a single data element using AES‑256‑CBC.  The input
     * should be base64‑encoded ciphertext (as produced by
     * ``AesEncryption::encryptData``).  If decryption fails, ``false`` is
     * returned.
     *
     * @param string $data Base64‑encoded ciphertext
     * @param string $key  The AES key
     * @param string $iv   The initialization vector (binary)
     * @return string|false The decrypted plaintext or false on failure
     */
    private function decryptData(string $data, string $key, string $iv)
    {
        $ciphertext = base64_decode($data, true);
        if ($ciphertext === false) {
            return false;
        }
        return openssl_decrypt($ciphertext, $this->cipher, $key, 0, $iv);
    }

    /**
     * Decrypt an entire ``.dbfile`` produced by the encryption script.  The
     * method infers the original file name from the name of the DB file and
     * looks up the corresponding key/IV in the key file.  It returns an
     * array of rows, where each row is an array of decrypted column values.
     * The first column (RID) remains unmodified because it was not
     * encrypted.
     *
     * @param string $dbfilePath Path to the encrypted ``.dbfile``
     * @param string $keyFile    Path to the ``AES.KEY`` file
     * @return array|false       A two‑dimensional array of decrypted values
     *                           on success, or ``false`` on failure.
     */
    public function decryptFile(string $dbfilePath, string $keyFile)
    {
        // Ensure both files exist and are readable
        if (!is_readable($dbfilePath) || !is_readable($keyFile)) {
            return false;
        }

        // Determine the original file name from the DB file name.  The
        // encryption script constructs the DB file name as
        //    <originalFileName>_<md5 suffix>.dbfile
        // We remove the trailing ``.dbfile`` extension and then strip off
        // the last underscore and suffix to recover the original name.  If
        // no underscore exists, we assume the entire name is the original.
        $dbFileName = basename($dbfilePath);
        $nameWithoutExt = (substr($dbFileName, -7) === '.dbfile') ? substr($dbFileName, 0, -7) : $dbFileName;
        $pos = strrpos($nameWithoutExt, '_');
        $originalFileName = ($pos === false) ? $nameWithoutExt : substr($nameWithoutExt, 0, $pos);

        // Look up the key and IV
        $keyAndIv = $this->getKeyAndIv($keyFile, $originalFileName);
        if ($keyAndIv === false) {
            return false;
        }
        $key = $keyAndIv['key'];
        $iv  = $keyAndIv['iv'];

        // Read and decrypt the file line by line
        $handle = fopen($dbfilePath, 'r');
        if ($handle === false) {
            return false;
        }
        $rows = [];
        while (($line = fgets($handle)) !== false) {
            // Trim trailing pipe and newline characters
            $cleanLine = rtrim($line, "|\r\n");
            $encryptedColumns = explode('|', $cleanLine);

            $decryptedRow = [];
            foreach ($encryptedColumns as $index => $encryptedColumn) {
                // The first column is the RID and was not encrypted
                if ($index === 0) {
                    $decryptedRow[] = $encryptedColumn;
                } else {
                    $decryptedRow[] = $this->decryptData($encryptedColumn, $key, $iv);
                }
            }
            $rows[] = $decryptedRow;
        }
        fclose($handle);

        return $rows;
    }
}

?>