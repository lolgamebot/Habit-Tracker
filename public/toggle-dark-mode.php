<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];

if (isset($_POST['dark_mode'])) {
    $newValue = filter_var($_POST['dark_mode'], FILTER_VALIDATE_BOOLEAN);
} else {
    $newValue = empty($_SESSION['dark_mode']);
}

toggleDarkMode($pdo, $userId, $newValue);
$_SESSION['dark_mode'] = $newValue;

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'dark_mode' => $newValue]);
    exit;
}

$redirect = (string) ($_POST['redirect'] ?? 'index.php');
// Only allow a relative in-app path back - never let this become an open redirect.
if ($redirect === '' || !preg_match('#^[a-zA-Z0-9_\-./?=&]*$#', $redirect) || str_starts_with($redirect, '//')) {
    $redirect = 'index.php';
}

header("Location: " . $redirect);
exit;