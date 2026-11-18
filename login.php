<?php
require __DIR__ . '/auth.php';

$error = null;

// Check submitted credentials against the username and password hash in .env
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    $validUsername = $_ENV['APP_USERNAME'] ?? '';
    $passwordHash  = $_ENV['APP_PASSWORD_HASH'] ?? '';

    if ($validUsername !== '' && $passwordHash !== ''
        && hash_equals($validUsername, (string) $_POST['username'])
        && password_verify((string) $_POST['password'], $passwordHash)) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user'] = $validUsername;
        header('Location: login.php');
        exit;
    }

    $error = 'Invalid username or password';
}

// List .dbfile files from the secure directory for logged-in users
$files = is_logged_in() ? glob(SECURE_DIR . '*.dbfile') : [];
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
        <?php if (!is_logged_in()): ?>
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
                    <?php $name = rawurlencode(basename($file)); ?>
                    <tr>
                        <td><?php echo htmlspecialchars(basename($file)); ?></td>
                        <td>
                            <a href="view.php?file=<?php echo $name; ?>">View</a>
                            |
                            <a href="download.php?file=<?php echo $name; ?>">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <a href="index.php" class="link-button">Upload a file</a>
            <form action="logout.php" method="POST" style="display:inline;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <button type="submit">Log out</button>
            </form>
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
