<?php
require __DIR__ . '/auth.php';
require_login();

// Include the Md5Processor and AesEncryption classes
include('Md5Processor.php');
include('AesEncryption.php');

const MAX_UPLOAD_BYTES   = 20 * 1024 * 1024; // 20 MB
const ALLOWED_EXTENSIONS = ['unl', 'txt'];

// Initialize message variables to avoid undefined warnings
$message = '';
$isError = false;

// Check if the form is submitted
if (isset($_POST['submit'])) {
    $uploadedFile = $_FILES['fileUpload'] ?? null;

    if (!check_csrf()) {
        $message = 'Your session has expired. Please reload the page and try again.';
        $isError = true;
    } elseif (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error uploading file. Please try again.';
        $isError = true;
    } elseif ($uploadedFile['size'] > MAX_UPLOAD_BYTES) {
        $message = 'File is too large (max 20 MB).';
        $isError = true;
    } else {
        // Keep only safe characters in the file name
        $originalFileName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($uploadedFile['name']));
        $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));

        if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
            $message = 'Only .unl and .txt files are accepted.';
            $isError = true;
        } else {
            $targetFilePath = UPLOAD_DIR . $originalFileName;

            // Move the uploaded file to the uploads directory
            if (move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
                try {
                    // Generate the MD5 integrity file, then the encrypted copy
                    $integrityFileName = (new Md5Processor())->processFile($targetFilePath, INTEGRITY_DIR);
                    $encryptedFileName = (new AesEncryption())->encryptFile($targetFilePath, SECURE_DIR);

                    // Build a user-friendly success message
                    $message  = 'File processed successfully!<br>';
                    $message .= 'Integrity file created: ' . htmlspecialchars(basename($integrityFileName)) . '<br>';
                    $message .= 'Encrypted file created: ' . htmlspecialchars(basename($encryptedFileName));
                } finally {
                    // Never keep the plaintext copy once it has been processed
                    unlink($targetFilePath);
                }
            } else {
                $message = 'File upload failed.';
                $isError = true;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Upload File</h2>
        <?php if (!empty($message)): ?>
            <div class="<?php echo $isError ? 'error' : 'message'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <label for="fileUpload">Choose a pipe-delimited .unl or .txt file</label>
            <input type="file" name="fileUpload" id="fileUpload" accept=".unl,.txt" required>
            <button type="submit" name="submit">Upload</button>
        </form>

        <!-- Navigation link to the file list -->
        <a href="login.php" class="link-button">View encrypted files</a>
    </div>
</body>
</html>
