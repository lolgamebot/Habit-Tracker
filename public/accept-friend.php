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
$requestId = (int) ($_POST["request_id"] ?? 0);

$ok = acceptFriendRequest($pdo, $requestId, $userId);
$msg = $ok ? "Friend request accepted." : "Could not accept that request.";

header("Location: profile.php?flash=" . urlencode($msg) . "&flash_type=" . ($ok ? "success" : "error"));
exit;