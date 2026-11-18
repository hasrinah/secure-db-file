<?php
/**
 * Shared bootstrap for every page: loads .env, starts a hardened session
 * and provides the login, CSRF and file-lookup helpers.
 */

// Load .env manually (KEY=VALUE per line, no dotenv library needed)
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue; // Skip comments and malformed lines
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = trim($value, "\"'");
    }
}

// Session cookie cannot be read by JavaScript or sent cross-site
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

const SECURE_DIR    = __DIR__ . '/secure/';
const UPLOAD_DIR    = __DIR__ . '/uploads/';
const INTEGRITY_DIR = __DIR__ . '/integrity/';
const KEY_FILE      = SECURE_DIR . 'AES.KEY';

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

// Send visitors who are not logged in to the login page
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

/**
 * Turn a user-supplied file name into a path inside the secure directory.
 * Returns null for anything that is not an existing .dbfile, which blocks
 * path traversal such as "../.env".
 */
function resolve_dbfile(string $name): ?string
{
    $name = basename($name);
    if (!preg_match('/^[A-Za-z0-9._-]+\.dbfile$/', $name)) {
        return null;
    }
    $path = SECURE_DIR . $name;
    return is_file($path) ? $path : null;
}
