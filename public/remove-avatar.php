<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
removeAvatar($pdo, $userId);
$_SESSION['avatar'] = null;

redirectWithFlash('profile.php', "Profile picture removed.");
