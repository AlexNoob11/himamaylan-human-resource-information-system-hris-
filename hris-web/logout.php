<?php
// logout.php
session_start();

// Regenerate session ID first to prevent session fixation
session_regenerate_id(true);

// Unset all session variables
$_SESSION = [];

// Clear session data from memory
if (function_exists('session_unset')) {
    session_unset();
}

// Delete session cookie
$cookieParams = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 86400, // Expire in past (1 day ago)
    $cookieParams['path'],
    $cookieParams['domain'],
    $cookieParams['secure'],
    $cookieParams['httponly']
);

// Destroy the session
session_destroy();

// Prevent caching of the page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login with a success message
$_SESSION['success'] = "You have been successfully logged out.";
header("Location: login.php");
exit();
?>