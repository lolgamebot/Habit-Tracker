<?php
require "../includes/bootstrap.php";

requireLogin();

$userId = $_SESSION["user_id"];
loadUserSession($pdo, $userId);

$flash = $_GET['flash'] ?? null;
$flashType = ($_GET['flash_type'] ?? 'success') === 'error' ? 'error' : 'success';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$grid = getMonthGrid($pdo, $userId, $month);

$monthTs   = mktime(0, 0, 0, $grid['month'], 1, $grid['year']);
$monthName = date('F Y', $monthTs);
$prevMonth = date('Y-m', strtotime('-1 month', $monthTs));
$nextMonth = date('Y-m', strtotime('+1 month', $monthTs));

$weekdayLetters = ['Sun' => 'S', 'Mon' => 'M', 'Tue' => 'T', 'Wed' => 'W', 'Thu' => 'T', 'Fri' => 'F', 'Sat' => 'S'];
$theme = getTheme($_SESSION['theme'] ?? 'rose');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($monthName) ?> - Habit Tracker</title>
    <link rel="stylesheet" href="style.css">
<?php renderThemeStyle(); ?>
</head>
<body>
<?php renderNav($_SESSION["username"], getPendingFriendRequestCount($pdo, $userId), $_SESSION['avatar'] ?? null); ?>

<main class="page">
    <?php if ($flash): ?>
        <p class="alert <?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($flash) ?></p>
    <?php endif; ?>

    <div class="dashboard-header">
        <div class="month-nav">
            <a href="index.php?month=<?= $prevMonth ?>" class="btn btn-ghost">&larr;</a>
            <h1><?= e($monthName) ?></h1>
            <a href="index.php?month=<?= $nextMonth ?>" class="btn btn-ghost">&rarr;</a>
        </div>

        <form action="add-habit.php" method="post" class="add-habit-form">
            <?php renderCsrfInput(); ?>
            <input type="hidden" name="redirect_month" value="<?= e($month) ?>">
            <input type="text" name="name" placeholder="New habit, e.g. Read 10 pages" required maxlength="80">
            <button type="submit" class="btn btn-primary">Add habit</button>
        </form>
    </div>

    <div class="grid-scroll">
    <table class="habit-grid" id="habit-grid">
        <thead>
            <tr>
                <th class="col-habit" rowspan="2">Habit</th>
                <?php for ($d = 1; $d <= $grid['daysInMonth']; $d++):
                    $wd = date('D', mktime(0, 0, 0, $grid['month'], $d, $grid['year'])); ?>
                    <th class="col-day <?= in_array($wd, ['Sat', 'Sun'], true) ? 'weekend' : '' ?>">
                        <?= $weekdayLetters[$wd] ?>
                    </th>
                <?php endfor; ?>
                <th class="col-stat">Checked</th>
                <th class="col-stat">Total</th>
                <th class="col-stat">%</th>
            </tr>
            <tr>
                <?php for ($d = 1; $d <= $grid['daysInMonth']; $d++): ?>
                    <th class="col-day-num"><?= $d ?></th>
                <?php endfor; ?>
                <th colspan="3"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($grid['habits'])): ?>
            <tr><td colspan="<?= $grid['daysInMonth'] + 4 ?>" class="empty-row">No habits yet — add your first one above.</td></tr>
        <?php endif; ?>
        <?php foreach ($grid['habits'] as $habit):
            $stats = $grid['rowStats'][$habit['id']]; ?>
            <tr data-habit-id="<?= $habit['id'] ?>">
                <td class="col-habit">
                    <div class="habit-cell-inner">
                    <span class="habit-name"><?= e($habit['name']) ?></span>
                    <form action="delete-habit.php" method="post" class="inline-form">
                        <?php renderCsrfInput(); ?>
                        <input type="hidden" name="habit_id" value="<?= $habit['id'] ?>">
                        <input type="hidden" name="redirect_month" value="<?= e($month) ?>">
                        <button type="submit" class="link-button danger" onclick="return confirm('Delete this habit and all its history?')">&times;</button>
                    </form>
                    </div>
                </td>
                <?php for ($d = 1; $d <= $grid['daysInMonth']; $d++):
                    $checked = !empty($grid['logs'][$habit['id']][$d]);
                    $date = sprintf('%04d-%02d-%02d', $grid['year'], $grid['month'], $d); ?>
                    <td class="col-day">
                        <input type="checkbox" class="habit-checkbox"
                               data-habit-id="<?= $habit['id'] ?>" data-date="<?= $date ?>"
                               <?= $checked ? 'checked' : '' ?>>
                    </td>
                <?php endfor; ?>
                <td class="col-stat" data-role="checked"><?= $stats['checked'] ?></td>
                <td class="col-stat" data-role="total"><?= $stats['total'] ?></td>
                <td class="col-stat" data-role="percent"><?= number_format($stats['percent'], 1) ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td class="col-habit">Tasks completed</td>
                <?php for ($d = 1; $d <= $grid['daysInMonth']; $d++): ?>
                    <td class="col-day" data-role="day-completed" data-date-index="<?= $d ?>"><?= $grid['columnStats'][$d]['completed'] ?></td>
                <?php endfor; ?>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td class="col-habit">%</td>
                <?php for ($d = 1; $d <= $grid['daysInMonth']; $d++): ?>
                    <td class="col-day" data-role="day-percent" data-date-index="<?= $d ?>"><?= number_format($grid['columnStats'][$d]['percent'], 0) ?>%</td>
                <?php endfor; ?>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
    </div>

    <div class="charts">
        <div class="chart-card">
            <h2>Tasks completed per day</h2>
            <canvas id="chart-completed" height="120"></canvas>
        </div>
        <div class="chart-card">
            <h2>% of habits completed per day</h2>
            <canvas id="chart-percent" height="120"></canvas>
        </div>
    </div>
</main>

<script>
    window.HABIT_TRACKER = {
        csrfToken: <?= json_encode(getCsrfToken()) ?>,
        daysInMonth: <?= json_encode($grid['daysInMonth']) ?>,
        columnStats: <?= json_encode(array_values($grid['columnStats'])) ?>,
        totalHabits: <?= json_encode(count($grid['habits'])) ?>,
        accentColor: <?= json_encode($theme['accentDark']) ?>
    };
</script>
<script src="chart.min.js"></script>
<script src="app.js"></script>
</body>
</html>