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
        <a href="index.php" class="brand"><span class="brand-dot" aria-hidden="true"></span>Habit&nbsp;Tracker</a>
        <div class="topbar-nav">
            <a href="recap.php" class="link-button">Year Recap</a>
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

// ---- Year recap logic ----

function getAvailableYears($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT YEAR(hl.log_date) AS yr
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ?
        ORDER BY yr DESC
    ");
    $stmt->execute([$userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'yr'));
}

function getYearRecap($pdo, $userId, $year) {
    $yearStart = "$year-01-01";
    $yearEnd = "$year-12-31";

    $today = new DateTime();
    $isCurrentYear = ((int) $year === (int) $today->format('Y'));
    $daysElapsed = $isCurrentYear
        ? (int) $today->format('z') + 1
        : (int) (new DateTime("$year-12-31"))->format('z') + 1;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalHabits = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.completed = 1 AND hl.log_date BETWEEN ? AND ?
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
    $totalCheckins = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT h.name, COUNT(*) AS cnt
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.completed = 1 AND hl.log_date BETWEEN ? AND ?
        GROUP BY h.id, h.name
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
    $bestHabit = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT MONTH(hl.log_date) AS mo, COUNT(*) AS cnt
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.completed = 1 AND hl.log_date BETWEEN ? AND ?
        GROUP BY mo
        ORDER BY cnt DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
    $busiestMonth = $stmt->fetch();

    // One row per day that had ANY logged activity, with how many were completed that day.
    $stmt = $pdo->prepare("
        SELECT hl.log_date AS d, SUM(hl.completed) AS checkedCount
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.log_date BETWEEN ? AND ?
        GROUP BY hl.log_date
        ORDER BY hl.log_date ASC
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
    $dayRows = $stmt->fetchAll();

    $activeDates = [];
    $perfectDays = 0;
    foreach ($dayRows as $row) {
        if ((int) $row['checkedCount'] > 0) {
            $activeDates[] = $row['d'];
        }
        if ($totalHabits > 0 && (int) $row['checkedCount'] === $totalHabits) {
            $perfectDays++;
        }
    }

    // Longest run of consecutive days with at least one habit checked off.
    $longestStreak = 0;
    $currentStreak = 0;
    $prevDate = null;
    foreach ($activeDates as $dateStr) {
        $date = new DateTime($dateStr);
        $currentStreak = ($prevDate !== null && (int) $prevDate->diff($date)->days === 1)
            ? $currentStreak + 1
            : 1;
        $longestStreak = max($longestStreak, $currentStreak);
        $prevDate = $date;
    }

    $completionRate = ($totalHabits > 0 && $daysElapsed > 0)
        ? round($totalCheckins / ($totalHabits * $daysElapsed) * 100, 1)
        : 0.0;

    return [
        'year'             => (int) $year,
        'totalCheckins'    => $totalCheckins,
        'totalHabits'      => $totalHabits,
        'bestHabit'        => $bestHabit['name'] ?? null,
        'bestHabitCount'   => $bestHabit ? (int) $bestHabit['cnt'] : 0,
        'busiestMonth'     => $busiestMonth ? date('F', mktime(0, 0, 0, (int) $busiestMonth['mo'], 1)) : null,
        'busiestMonthCount'=> $busiestMonth ? (int) $busiestMonth['cnt'] : 0,
        'longestStreak'    => $longestStreak,
        'perfectDays'      => $perfectDays,
        'completionRate'   => $completionRate,
        'activeDays'       => count($activeDates),
    ];
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