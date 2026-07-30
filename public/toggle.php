<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$userId = $_SESSION["user_id"];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

verifyCsrfFromJson($input['csrf_token'] ?? null);

$habitId = (int) ($input['habit_id'] ?? 0);
$date = (string) ($input['date'] ?? '');

if (!$habitId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(422);
    echo json_encode(['error' => 'Missing or invalid habit_id/date.']);
    exit;
}

if (!habitBelongsToUser($pdo, $habitId, $userId)) {
    http_response_code(403);
    echo json_encode(['error' => 'That habit does not belong to you.']);
    exit;
}

$completed = toggleHabitLog($pdo, $habitId, $date);

$month = substr($date, 0, 7);
$grid = getMonthGrid($pdo, $userId, $month);
$day = (int) substr($date, 8, 2);

echo json_encode([
    'ok' => true,
    'completed' => $completed,
    'rowStats' => $grid['rowStats'][$habitId] ?? null,
    'dayStats' => $grid['columnStats'][$day] ?? null,
    'day' => $day,
]);