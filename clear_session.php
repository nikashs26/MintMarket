<?php
/**
 * Clear Session Script
 * Visit this page to clear your session and log out
 */
require_once __DIR__ . '/config.php';

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Session Cleared</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #021024;
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: rgba(67, 142, 219, 0.95);
            border-radius: 12px;
        }
        a {
            color: white;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Session Cleared!</h1>
        <p>You have been logged out. Your session has been cleared.</p>
        <p><a href='index.html'>Return to Homepage</a></p>
    </div>
</body>
</html>";
?>

