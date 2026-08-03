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
removeAvatar($pdo, $userId);
$_SESSION['avatar'] = null;

header("Location: profile.php?flash=" . urlencode("Profile picture removed.") . "&flash_type=success");
exit;