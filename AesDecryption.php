<?php

/**
 * Class AesDecryption
 *
 * Reverses AesEncryption: reads an encrypted ``.dbfile``, looks up that
 * file's key in ``AES.KEY`` and returns a two-dimensional array of
 * decrypted values.
 *
 * Each encrypted value is base64(IV || ciphertext), with a fresh random IV
 * per value, so the IV is read from the value itself.
 */
class AesDecryption
{
    /**
     * Cipher method used for encryption/decryption. Must match AesEncryption.
     *
     * @var string
     */
    private $cipher = 'aes-256-cbc';

    /**
     * Length of the initialization vector (IV) for the cipher.
     *
     * @var int
     */
    private $ivLength;

    public function __construct()
    {
        $this->ivLength = openssl_cipher_iv_length($this->cipher);
    }

    /**
     * Find the key for a file in the key file.
     *
     * @param string $keyFile          Path to the ``AES.KEY`` file.
     * @param string $originalFileName Original file name recorded at encryption time.
     * @return string|null             The raw 32-byte key, or null if not found.
     */
    private function getKey(string $keyFile, string $originalFileName): ?string
    {
        if (!is_readable($keyFile)) {
            return null;
        }

        $keys = json_decode((string) file_get_contents($keyFile), true);
        if (!is_array($keys)) {
            return null;
        }

        foreach ($keys as $record) {
            if (($record['fileName'] ?? null) === $originalFileName && ($record['version'] ?? 1) === 2) {
                $key = base64_decode($record['key'] ?? '', true);
                return ($key !== false && strlen($key) === 32) ? $key : null;
            }
        }

        return null;
    }

    /**
     * Decrypt one base64(IV || ciphertext) value.
     *
     * @return string|false The plaintext, or false on failure.
     */
    private function decryptData(string $data, string $key)
    {
        $raw = base64_decode($data, true);
        if ($raw === false || strlen($raw) <= $this->ivLength) {
            return false;
        }

        $iv = substr($raw, 0, $this->ivLength);
        $ciphertext = substr($raw, $this->ivLength);

        return openssl_decrypt($ciphertext, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Decrypt an entire ``.dbfile``. The original file name is recovered from
     * the DB file name (``<originalFileName>_<md5 suffix>.dbfile``) and used to
     * look up the key. The first column (RID) is returned unchanged.
     *
     * @param string $dbfilePath Path to the encrypted ``.dbfile``
     * @param string $keyFile    Path to the ``AES.KEY`` file
     * @return array|false       Rows of decrypted values, or false on failure.
     */
    public function decryptFile(string $dbfilePath, string $keyFile)
    {
        if (!is_readable($dbfilePath)) {
            return false;
        }

        $dbFileName = basename($dbfilePath);
        $nameWithoutExt = (substr($dbFileName, -7) === '.dbfile') ? substr($dbFileName, 0, -7) : $dbFileName;
        $pos = strrpos($nameWithoutExt, '_');
        $originalFileName = ($pos === false) ? $nameWithoutExt : substr($nameWithoutExt, 0, $pos);

        $key = $this->getKey($keyFile, $originalFileName);
        if ($key === null) {
            return false;
        }

        $handle = fopen($dbfilePath, 'r');
        if ($handle === false) {
            return false;
        }

        $rows = [];
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            $decryptedRow = [];
            foreach (explode('|', $line) as $index => $column) {
                // The first column is the RID and was not encrypted
                $decryptedRow[] = ($index === 0) ? $column : $this->decryptData($column, $key);
            }
            $rows[] = $decryptedRow;
        }
        fclose($handle);

        return $rows;
    }
}
