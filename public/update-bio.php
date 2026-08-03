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
updateBio($pdo, $userId, $_POST['bio'] ?? '');

header("Location: profile.php?flash=" . urlencode("Bio updated.") . "&flash_type=success");
exit;