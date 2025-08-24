<?php
// Include the Decrypt class
include('AesDecryption.php');

// Load .env file manually (no dotenv library needed)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        list($name, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
    }
}

// Get credentials from .env
$validUsername = $_ENV['USERNAME'] ?? '';
$validPassword = $_ENV['PASSWORD'] ?? '';

$error = null;

// Check if the form is submitted
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if credentials are correct
    if ($username === $validUsername && $password === $validPassword) {
        // List .dbfile files from the secure directory
        $files = glob('secure/*.dbfile');
    } else {
        $error = 'Invalid username or password';
    }
} else {
    $files = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login and File Decrypt</title>
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
    <!-- Custom style overrides -->
    <link rel="stylesheet" href="style.css">
    <!-- jQuery and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <div class="container">
        <?php if (!isset($_POST['username']) || !isset($_POST['password']) || $error): ?>
            <h2>Login</h2>
            <form action="" method="POST">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
                <button type="submit">Login</button>
            </form>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        <?php else: ?>
            <h2>Available Files</h2>
            <table id="fileTable" class="display">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(basename($file)); ?></td>
                        <td>
                            <a href="view.php?file=<?php echo urlencode($file); ?>">View</a>
                            |
                            <?php
                            // Prepare a safe download URL and suggested filename
                            $downloadUrl  = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
                            $downloadName = htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8');
                            ?>
                            <a href="<?php echo $downloadUrl; ?>" download="<?php echo $downloadName; ?>">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

            </table>
        <?php endif; ?>
    </div>

    <script>
    $(document).ready(function() {
        $('#fileTable').DataTable({
            "searching": false,
            "paging": true
        });
    });
    </script>
</body>
</html>
