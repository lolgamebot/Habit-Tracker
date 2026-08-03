<?php
require "../config/db.php";
require "../includes/helpers.php";

requireLogin();
$userId = $_SESSION["user_id"];

$availableYears = getAvailableYears($pdo, $userId);
$currentYear = (int) date('Y');
if (empty($availableYears)) {
    $availableYears = [$currentYear];
}

$year = (int) ($_GET['year'] ?? $currentYear);
if (!in_array($year, $availableYears, true)) {
    $year = $availableYears[0];
}

$recap = getYearRecap($pdo, $userId, $year);
$hasData = $recap['totalCheckins'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $year ?> Recap - Habit Tracker</title>
<link rel="stylesheet" href="recap.css">
<?php renderThemeStyle(); ?>
</head>
<body class="recap-body">

<a href="index.php" class="recap-exit" aria-label="Back to dashboard">&times;</a>

<?php if (count($availableYears) > 1): ?>
<form method="get" class="recap-year-picker">
    <select name="year" onchange="this.form.submit()">
        <?php foreach ($availableYears as $y): ?>
            <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php endif; ?>

<?php if (!$hasData): ?>

<div class="recap-slide recap-slide-empty active">
    <div class="recap-content">
        <p class="recap-eyebrow">Nothing here yet</p>
        <h1 class="recap-huge">No data for <?= $year ?></h1>
        <p class="recap-label">Check off a few habits and come back — your recap builds itself.</p>
        <a href="index.php" class="recap-btn">Back to dashboard</a>
    </div>
</div>

<?php else: ?>

<div class="recap-progress" id="recap-progress"></div>

<div class="recap-slide recap-slide-1 active">
    <div class="recap-content">
        <p class="recap-eyebrow">Your</p>
        <h1 class="recap-huge"><?= $year ?></h1>
        <p class="recap-eyebrow">in habits</p>
    </div>
</div>

<div class="recap-slide recap-slide-2">
    <div class="recap-content">
        <p class="recap-eyebrow">You checked off</p>
        <h1 class="recap-number" data-count="<?= $recap['totalCheckins'] ?>">0</h1>
        <p class="recap-label">habits in <?= $year ?></p>
    </div>
</div>

<div class="recap-slide recap-slide-3">
    <div class="recap-content">
        <p class="recap-eyebrow">Your longest streak</p>
        <h1 class="recap-number" data-count="<?= $recap['longestStreak'] ?>">0</h1>
        <p class="recap-label"><?= $recap['longestStreak'] === 1 ? 'day' : 'days' ?> in a row with something checked off</p>
    </div>
</div>

<?php if ($recap['bestHabit']): ?>
<div class="recap-slide recap-slide-4">
    <div class="recap-content">
        <p class="recap-eyebrow">Your most consistent habit was</p>
        <h1 class="recap-huge recap-huge-text"><?= e($recap['bestHabit']) ?></h1>
        <p class="recap-label">checked off <?= $recap['bestHabitCount'] ?> times</p>
    </div>
</div>
<?php endif; ?>

<?php if ($recap['busiestMonth']): ?>
<div class="recap-slide recap-slide-5">
    <div class="recap-content">
        <p class="recap-eyebrow">Your busiest month was</p>
        <h1 class="recap-huge recap-huge-text"><?= e($recap['busiestMonth']) ?></h1>
        <p class="recap-label"><?= $recap['busiestMonthCount'] ?> habits checked off</p>
    </div>
</div>
<?php endif; ?>

<div class="recap-slide recap-slide-6">
    <div class="recap-content">
        <p class="recap-eyebrow">You had</p>
        <h1 class="recap-number" data-count="<?= $recap['perfectDays'] ?>">0</h1>
        <p class="recap-label">perfect <?= $recap['perfectDays'] === 1 ? 'day' : 'days' ?> — every habit checked off</p>
    </div>
</div>

<div class="recap-slide recap-slide-7">
    <div class="recap-content">
        <p class="recap-eyebrow">Overall, you kept</p>
        <h1 class="recap-number" data-count="<?= $recap['completionRate'] ?>" data-suffix="%">0%</h1>
        <p class="recap-label">of your commitments this year</p>
    </div>
</div>

<div class="recap-slide recap-slide-8">
    <div class="recap-content">
        <p class="recap-eyebrow">That's a wrap on</p>
        <h1 class="recap-huge"><?= $year ?></h1>
        <p class="recap-label">Here's to an even better <?= $year + 1 ?></p>
        <a href="index.php" class="recap-btn">Back to dashboard</a>
    </div>
</div>

<button class="recap-nav recap-prev" aria-label="Previous">&larr;</button>
<button class="recap-nav recap-next" aria-label="Next">&rarr;</button>

<?php endif; ?>

<script src="recap.js"></script>
</body>
</html>