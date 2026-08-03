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
[$ok, $result] = uploadAvatar($_FILES['avatar'] ?? null, $userId, $pdo);

if ($ok) {
    $_SESSION['avatar'] = $result;
}

$msg = $ok ? "Profile picture updated." : $result;
header("Location: profile.php?flash=" . urlencode($msg) . "&flash_type=" . ($ok ? "success" : "error"));
exit;