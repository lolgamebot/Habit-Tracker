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
$name = trim($_POST["name"] ?? "");
$month = $_POST["redirect_month"] ?? date('Y-m');

if ($name !== "") {
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM habits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $nextOrder = (int) $stmt->fetchColumn();

    $insert = $pdo->prepare("INSERT INTO habits (user_id, name, sort_order) VALUES (?, ?, ?)");
    $insert->execute([$userId, $name, $nextOrder]);
}

$month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
header("Location: index.php?month=" . $month);
exit;