<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$name = trim($_POST["name"] ?? "");
$monthInput = $_POST["redirect_month"] ?? date('Y-m');
$month = preg_match('/^\d{4}-\d{2}$/', $monthInput) ? $monthInput : date('Y-m');

if ($name === '') {
    redirectWithFlash("index.php?month=" . $month, "Please enter a habit name.", false);
}

if (strlen($name) > 80) {
    redirectWithFlash("index.php?month=" . $month, "Habit name must be 80 characters or fewer.", false);
}

$stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM habits WHERE user_id = ?");
$stmt->execute([$userId]);
$nextOrder = (int) $stmt->fetchColumn();

$insert = $pdo->prepare("INSERT INTO habits (user_id, name, sort_order) VALUES (?, ?, ?)");
$insert->execute([$userId, $name, $nextOrder]);

redirectWithFlash("index.php?month=" . $month, "Habit added.");
