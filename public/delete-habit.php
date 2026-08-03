<?php
require "../includes/bootstrap.php";

requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit;
}

verifyCsrfToken();

$userId = $_SESSION["user_id"];
$habitId = (int) ($_POST["habit_id"] ?? 0);
$monthInput = $_POST["redirect_month"] ?? date('Y-m');
$month = preg_match('/^\d{4}-\d{2}$/', $monthInput) ? $monthInput : date('Y-m');

if ($habitId && habitBelongsToUser($pdo, $habitId, $userId)) {
    // Clean up its logs first, since there's no foreign key cascade
    $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = ?");
    $stmt->execute([$habitId]);

    $stmt = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
    $stmt->execute([$habitId, $userId]);

    redirectWithFlash("index.php?month=" . $month, "Habit deleted.");
}

header("Location: index.php?month=" . $month);
exit;
