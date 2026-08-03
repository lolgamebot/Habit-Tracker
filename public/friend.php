<?php
require "../config/db.php";
require "../includes/helpers.php";

requireLogin();
$userId = $_SESSION["user_id"];

$friendId = (int) ($_GET["id"] ?? 0);

if (getFriendshipStatus($pdo, $userId, $friendId) !== 'friends') {
    header("Location: profile.php");
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, avatar, bio, created_at FROM accounts WHERE id = ?");
$stmt->execute([$friendId]);
$friend = $stmt->fetch();

if (!$friend) {
    header("Location: profile.php");
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
$stmt->execute([$friendId]);
$totalHabits = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs hl JOIN habits h ON h.id = hl.habit_id WHERE h.user_id = ? AND hl.completed = 1");
$stmt->execute([$friendId]);
$totalCheckins = (int) $stmt->fetchColumn();

$grid = getMonthGrid($pdo, $friendId, date('Y-m'));
$thisMonthChecked = array_sum(array_column($grid['rowStats'], 'checked'));
$thisMonthPossible = $totalHabits * $grid['daysInMonth'];
$thisMonthPercent = $thisMonthPossible > 0 ? round($thisMonthChecked / $thisMonthPossible * 100, 1) : 0.0;

$stmt = $pdo->prepare("SELECT avatar, theme FROM accounts WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();
$myAvatar = $me['avatar'];
$myTheme = $me['theme'] ?? 'rose';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($friend['username']) ?> - Habit Tracker</title>
    <link rel="stylesheet" href="style.css">
    <?php renderThemeStyle(); ?>
</head>
<body>
<?php renderNav($_SESSION["username"], getPendingFriendRequestCount($pdo, $userId), $myAvatar); ?>

<main class="page profile-page">
    <a href="profile.php" class="muted" style="text-decoration:none;">&larr; Back to profile</a>

    <div class="profile-header">
        <?= renderAvatar($friend['avatar'], $friend['username'], 'xl') ?>
        <div>
            <h1><?= e($friend['username']) ?></h1>
            <p class="muted">Member since <?= date('F Y', strtotime($friend['created_at'])) ?></p>
        </div>
    </div>

    <?php if (!empty($friend['bio'])): ?>
        <p class="profile-bio"><?= nl2br(e($friend['bio'])) ?></p>
    <?php endif; ?>

    <div class="profile-stats">
        <div class="profile-stat"><span class="profile-stat-num"><?= $totalHabits ?></span><span class="profile-stat-label">Habits tracked</span></div>
        <div class="profile-stat"><span class="profile-stat-num"><?= $totalCheckins ?></span><span class="profile-stat-label">Total check-ins</span></div>
        <div class="profile-stat"><span class="profile-stat-num"><?= number_format($thisMonthPercent, 0) ?>%</span><span class="profile-stat-label">This month</span></div>
    </div>

    <p class="muted" style="text-align:center; margin-top:1.5rem;">You can see overall stats, but not the day-by-day details of <?= e($friend['username']) ?>'s habits.</p>
</main>
</body>
</html>