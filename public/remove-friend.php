<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: profile.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$friendshipId = (int) ($_POST["friendship_id"] ?? 0);

if ($friendshipId && removeFriendship($pdo, $friendshipId, $userId)) {
    redirectWithFlash('profile.php', "Done.");
}

redirectWithFlash('profile.php', "Could not complete that action.", false);
