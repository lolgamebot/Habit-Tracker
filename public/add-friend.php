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
$targetId = (int) ($_POST["target_id"] ?? 0);
$search = (string) ($_POST["search"] ?? '');

[$ok, $msg] = sendFriendRequest($pdo, $userId, $targetId);

$redirect = "profile.php?flash=" . urlencode($msg) . "&flash_type=" . ($ok ? "success" : "error");
if ($search !== '') {
    $redirect .= "&search=" . urlencode($search);
}
header("Location: " . $redirect);
exit;