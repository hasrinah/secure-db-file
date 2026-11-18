<?php
// Router for PHP's built-in development server, which ignores .htaccess.
// Usage: php -S localhost:8000 router.php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Block dotfiles (.env, .git, ../) and the data folders
if (preg_match('#(^|/)\.|(^|/)(secure|uploads|integrity)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

return false; // Let the built-in server handle everything else
