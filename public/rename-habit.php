<?php
require "../config/db.php";
require "../includes/helpers.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$habitId = (int) ($_POST["habit_id"] ?? 0);
$name = trim($_POST["name"] ?? "");
$month = $_POST["redirect_month"] ?? date('Y-m');

if ($name !== "" && strlen($name) <= 80 && $habitId && habitBelongsToUser($pdo, $habitId, $userId)) {
    $stmt = $pdo->prepare("UPDATE habits SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $habitId, $userId]);
}

$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
header("Location: index.php?month=" . $month);
exit;
