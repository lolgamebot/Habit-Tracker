<?php
/**
 * Shared helper functions for Habit Tracker.
 *
 * NOTE: Security headers + the hardened session init are handled by
 * bootstrap.php. Pages should require bootstrap.php (which loads this file),
 * not this file directly.
 */

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

// Redirect to a page with a flash message attached (used by the action endpoints).
function redirectWithFlash($location, $message, $isSuccess = true) {
    $type = $isSuccess ? 'success' : 'error';
    header("Location: " . $location . "?flash=" . urlencode($message) . "&flash_type=" . $type);
    exit;
}

// Loads the current user's theme/avatar/dark-mode settings into the session
// (idempotent), so pages can render the right theme without repeated queries.
function loadUserSession($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT avatar, theme, custom_color, custom_text_color, dark_mode FROM accounts WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $_SESSION['avatar'] = $row['avatar'];
    $_SESSION['theme'] = $row['theme'] ?? 'rose';
    $_SESSION['custom_color'] = $row['custom_color'] ?? null;
    $_SESSION['custom_text_color'] = $row['custom_text_color'] ?? null;
    $_SESSION['dark_mode'] = (bool)($row['dark_mode'] ?? false);
}

function renderNav($username, $pendingRequests = 0, $avatar = null, $darkMode = null) {
    initSecureSession();
    if ($darkMode === null) {
        $darkMode = !empty($_SESSION['dark_mode']);
    }
    $badge = $pendingRequests > 0 ? '<span class="nav-badge">' . (int) $pendingRequests . '</span>' : '';
    $currentPath = e($_SERVER['REQUEST_URI'] ?? 'index.php');
    $checked = $darkMode ? 'checked' : '';
    echo '
    <nav class="topbar">
        <a href="index.php" class="brand"><span class="brand-dot" aria-hidden="true"></span>Habit&nbsp;Tracker</a>
        <div class="topbar-nav">
            <a href="recap.php" class="link-button">Year Recap</a>
            <form action="toggle-dark-mode.php" method="post" class="inline-form" id="dark-mode-form">
                ' . renderCsrfInput() . '
                <input type="hidden" name="redirect" value="' . $currentPath . '">
                <label class="dark-toggle" title="Toggle dark mode">
                    <input type="checkbox" id="dark-mode-checkbox" name="dark_mode" ' . $checked . '>
                    <span class="dark-toggle-slider">
                        <span class="dark-toggle-icon sun" aria-hidden="true">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        </span>
                        <span class="dark-toggle-icon moon" aria-hidden="true">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        </span>
                    </span>
                </label>
            </form>
            <a href="profile.php" class="profile-link">
                ' . renderAvatar($avatar, $username, 'sm') . '
                Profile' . $badge . '
            </a>
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

// ---- Friends logic ----

function searchUsersByUsername($pdo, $query, $excludeUserId, $limit = 10) {
    $query = trim($query);
    if (strlen($query) < 2) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT id, username, avatar FROM accounts WHERE username LIKE ? AND id != ? ORDER BY username LIMIT ?");
    $stmt->bindValue(1, '%' . $query . '%', PDO::PARAM_STR);
    $stmt->bindValue(2, $excludeUserId, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Returns: 'none' | 'pending_sent' | 'pending_received' | 'friends'
function getFriendshipStatus($pdo, $userA, $userB) {
    $stmt = $pdo->prepare("
        SELECT requester_id, status FROM friendships
        WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$userA, $userB, $userB, $userA]);
    $row = $stmt->fetch();
    if (!$row) {
        return 'none';
    }
    if ($row['status'] === 'accepted') {
        return 'friends';
    }
    return ((int) $row['requester_id'] === (int) $userA) ? 'pending_sent' : 'pending_received';
}

// Sends a request, or if the other person already requested us, auto-accepts (mutual request = instant friends).
function sendFriendRequest($pdo, $fromUserId, $toUserId) {
    if ($fromUserId === $toUserId) {
        return [false, "You can't add yourself as a friend."];
    }

    $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ?");
    $stmt->execute([$toUserId]);
    if (!$stmt->fetch()) {
        return [false, "That user doesn't exist."];
    }

    $status = getFriendshipStatus($pdo, $fromUserId, $toUserId);

    if ($status === 'friends') {
        return [false, "You're already friends."];
    }
    if ($status === 'pending_sent') {
        return [false, "Friend request already sent."];
    }
    if ($status === 'pending_received') {
        $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE requester_id = ? AND addressee_id = ?");
        $stmt->execute([$toUserId, $fromUserId]);
        return [true, "You're now friends!"];
    }

    $stmt = $pdo->prepare("INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$fromUserId, $toUserId]);
    return [true, "Friend request sent."];
}

// Only the addressee (the person who received the request) can accept it.
function acceptFriendRequest($pdo, $requestId, $currentUserId) {
    $stmt = $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE id = ? AND addressee_id = ? AND status = 'pending'");
    $stmt->execute([$requestId, $currentUserId]);
    return $stmt->rowCount() > 0;
}

// Deletes a friendship row. Works for declining an incoming request, cancelling
// an outgoing one, or unfriending an accepted friend - all are just "remove this row",
// as long as the current user is one of the two people in it.
function removeFriendship($pdo, $friendshipId, $currentUserId) {
    $stmt = $pdo->prepare("DELETE FROM friendships WHERE id = ? AND (requester_id = ? OR addressee_id = ?)");
    $stmt->execute([$friendshipId, $currentUserId, $currentUserId]);
    return $stmt->rowCount() > 0;
}

function getFriends($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT f.id AS friendship_id, a.id AS user_id, a.username, a.avatar
        FROM friendships f
        JOIN accounts a ON a.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
        WHERE (f.requester_id = ? OR f.addressee_id = ?) AND f.status = 'accepted'
        ORDER BY a.username
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function getIncomingRequests($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT f.id AS friendship_id, a.id AS user_id, a.username, a.avatar
        FROM friendships f
        JOIN accounts a ON a.id = f.requester_id
        WHERE f.addressee_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getOutgoingRequests($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT f.id AS friendship_id, a.id AS user_id, a.username, a.avatar
        FROM friendships f
        JOIN accounts a ON a.id = f.addressee_id
        WHERE f.requester_id = ? AND f.status = 'pending'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getPendingFriendRequestCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friendships WHERE addressee_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

// ---- Avatar + bio ----

// Renders either the user's uploaded photo or a colored circle with their initial.
// Used everywhere a person shows up: nav, profile page, friend lists, search results.
function renderAvatar($avatar, $username, $size = 'md') {
    $sizeClass = 'avatar-' . $size;
    $initial = e(strtoupper(substr($username, 0, 1)));
    if ($avatar) {
        $src = 'uploads/avatars/' . rawurlencode($avatar);
        return '<img src="' . e($src) . '" alt="' . $initial . '" class="avatar-img ' . $sizeClass . '">';
    }
    return '<span class="avatar-initial ' . $sizeClass . '">' . $initial . '</span>';
}

// Validates, crops-to-square, resizes, and re-encodes the upload as a fresh JPEG -
// this strips anything embedded in the original file and guarantees consistent output,
// rather than trusting the uploaded bytes or file extension.
function uploadAvatar($file, $userId, $pdo) {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [false, "Please choose an image to upload."];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, "Upload failed. Please try again."];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return [false, "Image must be smaller than 2MB."];
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return [false, "That file doesn't look like a valid image."];
    }

    $creators = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif',
        'image/webp' => 'imagecreatefromwebp',
    ];
    $mime = $info['mime'];
    if (!isset($creators[$mime]) || !function_exists($creators[$mime])) {
        return [false, "Please upload a JPG, PNG, GIF, or WEBP image."];
    }

    $srcImage = @$creators[$mime]($file['tmp_name']);
    if (!$srcImage) {
        return [false, "Could not process that image."];
    }

    $srcWidth = imagesx($srcImage);
    $srcHeight = imagesy($srcImage);
    $side = min($srcWidth, $srcHeight);
    $cropX = (int) (($srcWidth - $side) / 2);
    $cropY = (int) (($srcHeight - $side) / 2);

    $targetSize = 256;
    $dest = imagecreatetruecolor($targetSize, $targetSize);
    imagecopyresampled($dest, $srcImage, 0, 0, $cropX, $cropY, $targetSize, $targetSize, $side, $side);

    $uploadDir = __DIR__ . '/../public/uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.jpg';
    imagejpeg($dest, $uploadDir . $filename, 85);
    imagedestroy($srcImage);
    imagedestroy($dest);

    $stmt = $pdo->prepare("SELECT avatar FROM accounts WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && is_file($uploadDir . $old)) {
        @unlink($uploadDir . $old);
    }

    $stmt = $pdo->prepare("UPDATE accounts SET avatar = ? WHERE id = ?");
    $stmt->execute([$filename, $userId]);

    return [true, $filename];
}

function removeAvatar($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT avatar FROM accounts WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old) {
        $path = __DIR__ . '/../public/uploads/avatars/' . $old;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $stmt = $pdo->prepare("UPDATE accounts SET avatar = NULL WHERE id = ?");
    $stmt->execute([$userId]);
}

function updateBio($pdo, $userId, $bio) {
    $bio = trim($bio);
    if (strlen($bio) > 280) {
        $bio = substr($bio, 0, 280);
    }
    $stmt = $pdo->prepare("UPDATE accounts SET bio = ? WHERE id = ?");
    $stmt->execute([$bio === '' ? null : $bio, $userId]);
    return $bio;
}

// ---- Color themes ----

// A curated set rather than a raw color picker, so every combination stays legible -
// each preset supplies the same handful of CSS variables the whole app is built on.
function getThemeList() {
    return [
        'rose'     => ['name' => 'Rose',     'accent' => '#c17b73', 'accentDark' => '#a35a52', 'accentSoft' => '#e8cfc9', 'cream' => '#faf7f3'],
        'ocean'    => ['name' => 'Ocean',    'accent' => '#5b9bd5', 'accentDark' => '#3a72a8', 'accentSoft' => '#cfe4f5', 'cream' => '#f2f7fb'],
        'forest'   => ['name' => 'Forest',   'accent' => '#6fa876', 'accentDark' => '#4c8354', 'accentSoft' => '#d6ecd9', 'cream' => '#f3f9f3'],
        'sunset'   => ['name' => 'Sunset',   'accent' => '#dd9152', 'accentDark' => '#b96f30', 'accentSoft' => '#f6ddc0', 'cream' => '#fbf6ef'],
        'grape'    => ['name' => 'Grape',    'accent' => '#9a72c2', 'accentDark' => '#7a52a3', 'accentSoft' => '#e6d7f2', 'cream' => '#f7f4fa'],
        'slate'    => ['name' => 'Slate',    'accent' => '#7186a0', 'accentDark' => '#52657d', 'accentSoft' => '#dbe2ea', 'cream' => '#f5f6f8'],
        'blurple'  => ['name' => 'Blurple',  'accent' => '#5865f2', 'accentDark' => '#3c47c4', 'accentSoft' => '#dce0fc', 'cream' => '#f6f7fe'],
        'emerald'  => ['name' => 'Emerald',  'accent' => '#2ecc71', 'accentDark' => '#1f9d55', 'accentSoft' => '#d1f4e0', 'cream' => '#f2faf5'],
        'crimson'  => ['name' => 'Crimson',  'accent' => '#ed4245', 'accentDark' => '#c02a2c', 'accentSoft' => '#fcdad9', 'cream' => '#fef5f5'],
        'fuchsia'  => ['name' => 'Fuchsia',  'accent' => '#eb459e', 'accentDark' => '#be2275', 'accentSoft' => '#fad9ec', 'cream' => '#fef5fa'],
    ];
}

// ---- Small HSL color-math helpers, used to derive a full theme (accent /
// accent-dark / accent-soft / cream) from a single custom hex the user picks. ----

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function rgbToHex($r, $g, $b) {
    return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($r))), max(0, min(255, (int) round($g))), max(0, min(255, (int) round($b))));
}

function rgbToHsl($r, $g, $b) {
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r, $g, $b); $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    if ($max === $min) {
        $h = $s = 0;
    } else {
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        switch ($max) {
            case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0); break;
            case $g: $h = ($b - $r) / $d + 2; break;
            default: $h = ($r - $g) / $d + 4;
        }
        $h /= 6;
    }
    return [$h * 360, $s * 100, $l * 100];
}

function hslToRgb($h, $s, $l) {
    $h /= 360; $s /= 100; $l /= 100;
    if ($s == 0) {
        $r = $g = $b = $l;
    } else {
        $hue2rgb = function ($p, $q, $t) {
            if ($t < 0) $t += 1;
            if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $r = $hue2rgb($p, $q, $h + 1 / 3);
        $g = $hue2rgb($p, $q, $h);
        $b = $hue2rgb($p, $q, $h - 1 / 3);
    }
    return [$r * 255, $g * 255, $b * 255];
}

// Takes one hex color the user picked and derives a full, legible theme from
// it server-side - clamping saturation/lightness so even a very dark or very
// pale pick still produces readable buttons, badges, and table headers.
function deriveThemeFromHex($hex) {
    if (!preg_match('/^#?[0-9a-f]{6}$/i', $hex)) {
        return null;
    }
    $hex = '#' . strtolower(ltrim($hex, '#'));
    [$r, $g, $b] = hexToRgb($hex);
    [$h, $s, $l] = rgbToHsl($r, $g, $b);

    $accentSat = max($s, 35);
    $accentL = max(35, min(65, $l));

    [$ar, $ag, $ab] = hslToRgb($h, $accentSat, $accentL);
    [$dr, $dg, $db] = hslToRgb($h, $accentSat, max($accentL - 18, 20));
    [$sr, $sg, $sb] = hslToRgb($h, max(min($s, 60), 20), min($accentL + 32, 92));
    [$cr, $cg, $cb] = hslToRgb($h, min($s, 15), 97);

    return [
        'name'       => 'Custom',
        'accent'     => rgbToHex($ar, $ag, $ab),
        'accentDark' => rgbToHex($dr, $dg, $db),
        'accentSoft' => rgbToHex($sr, $sg, $sb),
        'cream'      => rgbToHex($cr, $cg, $cb),
    ];
}

function getTheme($themeId, $customColor = null) {
    if ($themeId === 'custom' && $customColor) {
        $derived = deriveThemeFromHex($customColor);
        if ($derived) {
            return $derived;
        }
    }
    $themes = getThemeList();
    return $themes[$themeId] ?? $themes['rose'];
}

// Outputs a tiny inline <style> overriding the CSS variables for this user's
// theme (and, if enabled, dark mode's neutral palette) - computed server-side
// so the page is never briefly the wrong color.
function renderThemeStyle($themeId = null, $customColor = null, $darkMode = null, $customTextColor = null) {
    initSecureSession();
    if ($themeId === null) {
        $themeId = $_SESSION['theme'] ?? 'rose';
    }
    if ($customColor === null && array_key_exists('custom_color', $_SESSION)) {
        $customColor = $_SESSION['custom_color'];
    }
    if ($customTextColor === null && array_key_exists('custom_text_color', $_SESSION)) {
        $customTextColor = $_SESSION['custom_text_color'];
    }
    if ($darkMode === null) {
        $darkMode = !empty($_SESSION['dark_mode']);
    }

    $t = getTheme($themeId, $customColor);
    $css = ':root{--accent:' . e($t['accent']) . ';--accent-dark:' . e($t['accentDark']) . ';--accent-soft:' . e($t['accentSoft']) . ';--cream:' . e($t['cream']) . ';--accent-text:#ffffff;}';
    
    if ($customTextColor) {
        $css .= ':root{--ink:' . e($customTextColor) . ';}';
    }

    echo '<style id="user-theme-style">' . $css . '</style>';
    echo '<script>'
        . '(function(){'
        . 'var dark = ' . ($darkMode ? 'true' : 'false') . ';'
        . 'var el = document.documentElement;'
        . 'el.classList.toggle("dark-mode", dark);'
        . 'function applyBody(){ if (document.body) { document.body.classList.toggle("dark-mode", dark); } }'
        . 'applyBody();'
        . 'if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", applyBody); }'
        . '})();'
        . '</script>';
}

function updateTheme($pdo, $userId, $themeId) {
    $themes = getThemeList();
    if (!isset($themes[$themeId])) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE accounts SET theme = ? WHERE id = ?");
    $stmt->execute([$themeId, $userId]);
    return true;
}

function updateCustomTheme($pdo, $userId, $hex, $textColor = null) {
    $hex = trim($hex);
    if (!preg_match('/^#?[0-9a-f]{6}$/i', $hex)) {
        return false;
    }
    $hex = '#' . strtolower(ltrim($hex, '#'));

    $cleanTextColor = null;
    if ($textColor && preg_match('/^#?[0-9a-f]{6}$/i', trim($textColor))) {
        $cleanTextColor = '#' . strtolower(ltrim(trim($textColor), '#'));
    }

    $stmt = $pdo->prepare("UPDATE accounts SET theme = 'custom', custom_color = ?, custom_text_color = ? WHERE id = ?");
    $stmt->execute([$hex, $cleanTextColor, $userId]);
    return true;
}

function toggleDarkMode($pdo, $userId, $enabled) {
    $stmt = $pdo->prepare("UPDATE accounts SET dark_mode = ? WHERE id = ?");
    $stmt->execute([$enabled ? 1 : 0, $userId]);
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

    // Best habit, ranked by completion PERCENTAGE over the days elapsed this
    // year - not raw count, so someone tracking one habit isn't penalized
    // against someone tracking ten.
    $stmt = $pdo->prepare("
        SELECT h.name, COUNT(*) AS cnt
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.completed = 1 AND hl.log_date BETWEEN ? AND ?
        GROUP BY h.id, h.name
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
$habitCounts = $stmt->fetchAll();

    $bestHabitName = null;
    $bestHabitPercent = 0.0;
    $bestHabitCount = 0;
    foreach ($habitCounts as $row) {
        $pct = $daysElapsed > 0 ? ($row['cnt'] / $daysElapsed) * 100 : 0;
        if ($pct > $bestHabitPercent) {
            $bestHabitPercent = $pct;
            $bestHabitName = $row['name'];
            $bestHabitCount = (int) $row['cnt'];
        }
    }
    $bestHabitPercent = round(min($bestHabitPercent, 100), 1);

    // Busiest month, ranked by completion PERCENTAGE of that month's possible
    // check-ins (habits x days) - a month just isn't automatically "busier"
    // because more habits happened to be active in it.
    $stmt = $pdo->prepare("
        SELECT MONTH(hl.log_date) AS mo, COUNT(*) AS cnt
        FROM habit_logs hl
        JOIN habits h ON h.id = hl.habit_id
        WHERE h.user_id = ? AND hl.completed = 1 AND hl.log_date BETWEEN ? AND ?
        GROUP BY mo
    ");
    $stmt->execute([$userId, $yearStart, $yearEnd]);
    $monthCounts = $stmt->fetchAll();

$busiestMonthName = null;
    $busiestMonthPercent = 0.0;
    $busiestMonthCount = 0;
    $currentMonthNum = (int) $today->format('n');
    foreach ($monthCounts as $row) {
        $mo = (int) $row['mo'];
        if ($isCurrentYear && $mo > $currentMonthNum) {
            continue;
        }
        $daysInThatMonth = ($isCurrentYear && $mo === $currentMonthNum)
            ? (int) $today->format('j')
            : (int) date('t', mktime(0, 0, 0, $mo, 1, $year));

        $denominator = $totalHabits * $daysInThatMonth;
        $pct = $denominator > 0 ? ($row['cnt'] / $denominator) * 100 : 0;
        if ($pct > $busiestMonthPercent) {
            $busiestMonthPercent = $pct;
            $busiestMonthName = date('F', mktime(0, 0, 0, $mo, 1));
            $busiestMonthCount = (int) $row['cnt'];
        }
    }
    $busiestMonthPercent = round(min($busiestMonthPercent, 100), 1);

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
        'year'                => (int) $year,
        'totalCheckins'       => $totalCheckins,
        'totalHabits'         => $totalHabits,
        'bestHabit'           => $bestHabitName,
        'bestHabitPercent'    => $bestHabitPercent,
        'bestHabitCount'      => $bestHabitCount,
        'busiestMonth'        => $busiestMonthName,
        'busiestMonthPercent' => $busiestMonthPercent,
        'busiestMonthCount'   => $busiestMonthCount,
        'longestStreak'       => $longestStreak,
        'perfectDays'         => $perfectDays,
        'completionRate'      => $completionRate,
        'activeDays'          => count($activeDates),
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