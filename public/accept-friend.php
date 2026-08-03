<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$requestId = (int) ($_POST["request_id"] ?? 0);

if ($requestId && acceptFriendRequest($pdo, $requestId, $userId)) {
    redirectWithFlash('profile.php', "Friend request accepted.");
}

redirectWithFlash('profile.php', "Could not accept that request.", false);
