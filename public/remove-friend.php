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
$friendshipId = (int) ($_POST["friendship_id"] ?? 0);

$ok = removeFriendship($pdo, $friendshipId, $userId);
$msg = $ok ? "Done." : "Could not complete that action.";

header("Location: profile.php?flash=" . urlencode($msg) . "&flash_type=" . ($ok ? "success" : "error"));
exit;