<?php
// Include the Md5Processor and AesEncryption classes
include('Md5Processor.php');
include('AesEncryption.php');

// Define the upload directory and the secure directory
$uploadDir = 'uploads/';
$secureDir = 'secure/';

// Initialize message variable to avoid undefined warnings
$message = '';


// Check if the form is submitted
if (isset($_POST['submit'])) {
    // Initialize message variable
    $message = '';

    // Get the uploaded file details
    $uploadedFile = $_FILES['fileUpload'];

    // Check for file upload errors
    if ($uploadedFile['error'] === UPLOAD_ERR_OK) {
        $originalFileName = basename($uploadedFile['name']);
        $targetFilePath = $uploadDir . $originalFileName;

        // Move the uploaded file to the uploads directory
        if (move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
            // Create an instance of the Md5Processor class
            $md5Processor = new Md5Processor();
            // Process the file for MD5 and integrity generation
            $integrityFileName = $md5Processor->processFile($targetFilePath, 'integrity/');

            // Create an instance of the AesEncryption class
            $aesEncryption = new AesEncryption();

            // Process the file for encryption and export the encrypted data
            $encryptedFileName = $aesEncryption->encryptFile($targetFilePath, $secureDir);

            // Build a user-friendly success message
            $message  = 'File processed successfully!<br>';
            $message .= 'Integrity file created: ' . htmlspecialchars($integrityFileName) . '<br>';
            $message .= 'Encrypted file created: ' . htmlspecialchars($encryptedFileName);

        } else {
            $message = 'File upload failed.';
        }
    } else {
        $message = 'Error uploading file. Please try again.';
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
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <label for="fileUpload">Choose a file</label>
            <input type="file" name="fileUpload" id="fileUpload" required>
            <button type="submit" name="submit">Upload</button>
        </form>

        <!-- Navigation link to the login page -->
        <a href="login.php" class="link-button">Go to Login</a>
    </div>
</body>
</html>
