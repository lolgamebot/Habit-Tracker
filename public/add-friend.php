<?php
require "../includes/bootstrap.php";

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

$redirect = "profile.php?search=" . urlencode($search);
redirectWithFlash($search !== '' ? $redirect : 'profile.php', $msg, $ok);
