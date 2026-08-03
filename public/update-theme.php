<?php
require "../config/db.php";
require "../includes/helpers.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$themeId = (string) ($_POST["theme"] ?? '');

if ($themeId === 'custom') {
    $hex = (string) ($_POST["custom_color"] ?? '');
    $ok = updateCustomTheme($pdo, $userId, $hex);
    if ($ok) {
        $_SESSION['theme'] = 'custom';
        $_SESSION['custom_color'] = '#' . strtolower(ltrim($hex, '#'));
    }
    $msg = $ok ? "Custom color saved." : "Please enter a valid 6-digit hex color.";
} else {
    $ok = updateTheme($pdo, $userId, $themeId);
    if ($ok) {
        $_SESSION['theme'] = $themeId;
    }
    $msg = $ok ? "Theme updated." : "That's not a valid theme.";
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => $ok,
        'message' => $msg,
        'theme' => $_SESSION['theme'],
        'custom_color' => $_SESSION['custom_color'] ?? null,
    ]);
    exit;
}

header("Location: profile.php?flash=" . urlencode($msg) . "&flash_type=" . ($ok ? "success" : "error"));
exit;