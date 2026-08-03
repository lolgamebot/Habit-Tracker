<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
updateBio($pdo, $userId, $_POST['bio'] ?? '');

redirectWithFlash('profile.php', "Bio updated.");
