<?php
// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

// XSS escaping helper
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF protection helpers
function getCsrfToken() {
    initSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function renderCsrfInput() {
    echo '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
}

function verifyCsrfToken() {
    initSecureSession();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            die("CSRF token validation failed. Please refresh the page and try again.");
        }
    }
}

// CSRF check for the JSON AJAX endpoint (toggle.php)
function verifyCsrfFromJson($token) {
    initSecureSession();
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or expired security token. Please refresh the page.']);
        exit;
    }
}

function requireLogin() {
    initSecureSession();
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function renderNav($username) {
    echo '
    <nav class="topbar">
        <a href="index.php" class="brand">Habit&nbsp;Tracker</a>
        <div class="topbar-nav">
            <span class="topbar-user">' . e($username) . '</span>
            <a href="logout.php" class="link-button">Log out</a>
        </div>
    </nav>
    ';
}

// ---- Habit grid logic (shared between index.php and toggle.php) ----

function getMonthGrid($pdo, $userId, $yearMonth) {
    [$year, $month] = array_map('intval', explode('-', $yearMonth));
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

    $stmt = $pdo->prepare("SELECT id, name FROM habits WHERE user_id = ? ORDER BY sort_order, id");
    $stmt->execute([$userId]);
    $habits = $stmt->fetchAll();
    $habitIds = array_column($habits, 'id');

    $logs = [];
    if ($habitIds) {
        $placeholders = implode(',', array_fill(0, count($habitIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT habit_id, log_date, completed FROM habit_logs
             WHERE habit_id IN ($placeholders) AND log_date LIKE ?"
        );
        $stmt->execute([...$habitIds, $yearMonth . '-%']);
        foreach ($stmt->fetchAll() as $row) {
            $day = (int) substr($row['log_date'], 8, 2);
            $logs[(int) $row['habit_id']][$day] = (bool) $row['completed'];
        }
    }

    $rowStats = [];
    foreach ($habits as $habit) {
        $checked = 0;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if (!empty($logs[$habit['id']][$d])) {
                $checked++;
            }
        }
        $rowStats[$habit['id']] = [
            'checked' => $checked,
            'total'   => $daysInMonth,
            'percent' => $daysInMonth > 0 ? round($checked / $daysInMonth * 100, 1) : 0.0,
        ];
    }

    $totalHabits = count($habits);
    $columnStats = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $done = 0;
        foreach ($habits as $habit) {
            if (!empty($logs[$habit['id']][$d])) {
                $done++;
            }
        }
        $columnStats[$d] = [
            'completed' => $done,
            'percent'   => $totalHabits > 0 ? round($done / $totalHabits * 100, 1) : 0.0,
        ];
    }

    return [
        'year' => $year, 'month' => $month, 'daysInMonth' => $daysInMonth,
        'habits' => $habits, 'logs' => $logs,
        'rowStats' => $rowStats, 'columnStats' => $columnStats,
    ];
}

function habitBelongsToUser($pdo, $habitId, $userId) {
    $stmt = $pdo->prepare("SELECT 1 FROM habits WHERE id = ? AND user_id = ?");
    $stmt->execute([$habitId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function toggleHabitLog($pdo, $habitId, $date) {
    $stmt = $pdo->prepare("SELECT completed FROM habit_logs WHERE habit_id = ? AND log_date = ?");
    $stmt->execute([$habitId, $date]);
    $current = $stmt->fetchColumn();

    $newValue = $current === false ? 1 : (((int) $current) === 1 ? 0 : 1);

    $stmt = $pdo->prepare(
        "INSERT INTO habit_logs (habit_id, log_date, completed) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE completed = VALUES(completed)"
    );
    $stmt->execute([$habitId, $date, $newValue]);

    return (bool) $newValue;
}