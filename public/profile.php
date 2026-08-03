<?php
require "../includes/bootstrap.php";

requireLogin();
$userId = $_SESSION["user_id"];
$username = $_SESSION["username"];
loadUserSession($pdo, $userId);

$flash = $_GET['flash'] ?? null;
$flashType = ($_GET['flash_type'] ?? 'success') === 'error' ? 'error' : 'success';

$searchQuery = trim($_GET['search'] ?? '');
$searchResults = [];
if ($searchQuery !== '') {
    foreach (searchUsersByUsername($pdo, $searchQuery, $userId) as $result) {
        $result['status'] = getFriendshipStatus($pdo, $userId, $result['id']);
        $searchResults[] = $result;
    }
}

$incoming = getIncomingRequests($pdo, $userId);
$outgoing = getOutgoingRequests($pdo, $userId);
$friends = getFriends($pdo, $userId);

$stmt = $pdo->prepare("SELECT avatar, bio, theme, custom_color, dark_mode, created_at FROM accounts WHERE id = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();
$_SESSION['theme'] = $account['theme'] ?? 'rose';
$_SESSION['custom_color'] = $account['custom_color'] ?? null;
$_SESSION['dark_mode'] = (bool)($account['dark_mode'] ?? false);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
$stmt->execute([$userId]);
$totalHabits = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM habit_logs hl JOIN habits h ON h.id = hl.habit_id WHERE h.user_id = ? AND hl.completed = 1");
$stmt->execute([$userId]);
$totalCheckins = (int) $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Habit Tracker</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="cropper.min.css">
    <?php renderThemeStyle($account['theme'] ?? 'rose', $account['custom_color'] ?? null, (bool)($account['dark_mode'] ?? false)); ?>
</head>
<body>
<?php renderNav($username, getPendingFriendRequestCount($pdo, $userId), $account['avatar'], (bool)($account['dark_mode'] ?? false)); ?>

<main class="page profile-page">

    <?php if ($flash): ?>
        <p class="alert <?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>"><?= e($flash) ?></p>
    <?php endif; ?>

    <div class="profile-header">
        <div class="avatar-edit">
            <?= renderAvatar($account['avatar'], $username, 'xl') ?>
            <label for="avatar-input" class="avatar-edit-btn" title="Change photo">✎</label>
        </div>
        <div>
            <h1><?= e($username) ?></h1>
            <p class="muted">Member since <?= date('F Y', strtotime($account['created_at'])) ?></p>
        </div>
    </div>

    <input type="file" id="avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
    <?php if ($account['avatar']): ?>
        <form action="remove-avatar.php" method="post" class="inline-form">
            <?php renderCsrfInput(); ?>
            <button type="submit" class="link-button" style="font-size:0.82rem;">Remove photo</button>
        </form>
    <?php endif; ?>

    <div class="crop-modal" id="crop-modal">
        <div class="crop-modal-card">
            <h3>Adjust your photo</h3>
            <div class="crop-container">
                <img id="cropper-image" src="" alt="">
            </div>
            <div class="crop-zoom-row">
                <span aria-hidden="true">&#128247;</span>
                <input type="range" id="crop-zoom" min="0" max="100" value="0">
            </div>
            <div class="crop-actions">
                <button type="button" class="btn btn-ghost" id="crop-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="crop-save">Save photo</button>
            </div>
        </div>
    </div>

    <script>window.CSRF_TOKEN = <?= json_encode(getCsrfToken()) ?>;</script>
    <script src="cropper.min.js"></script>
    <script src="avatar-crop.js"></script>

    <section class="profile-section">
        <h2>About me</h2>
        <form action="update-bio.php" method="post" class="bio-form">
            <?php renderCsrfInput(); ?>
            <textarea name="bio" maxlength="280" placeholder="Say a little about yourself and what you're working on..."><?= e($account['bio'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-primary btn-sm">Save bio</button>
        </form>
    </section>

    <div class="profile-stats">
        <div class="profile-stat"><span class="profile-stat-num"><?= $totalHabits ?></span><span class="profile-stat-label">Habits tracked</span></div>
        <div class="profile-stat"><span class="profile-stat-num"><?= $totalCheckins ?></span><span class="profile-stat-label">Total check-ins</span></div>
        <div class="profile-stat"><span class="profile-stat-num"><?= count($friends) ?></span><span class="profile-stat-label">Friends</span></div>
    </div>

    <section class="profile-section theme-section-card">
        <h2>App Theme & Accent Color</h2>
        <p class="muted" style="margin-top:-0.3rem; margin-bottom:1rem;">Select a theme preset or pick your custom accent color below.</p>

        <div class="theme-swatch-container">
        <?php foreach (getThemeList() as $themeId => $t): ?>
            <form action="update-theme.php" method="post" class="inline-form">
                <?php renderCsrfInput(); ?>
                <input type="hidden" name="theme" value="<?= e($themeId) ?>">
                <button
                    type="submit"
                    class="theme-swatch <?= (($account['theme'] ?? 'rose') === $themeId) ? 'active' : '' ?>"
                    style="background: <?= e($t['accent']) ?>;"
                    title="<?= e($t['name']) ?>"
                    aria-label="<?= e($t['name']) ?> theme"
                ></button>
            </form>
        <?php endforeach; ?>

            <!-- Custom Swatch Circle -->
            <form action="update-theme.php" method="post" class="inline-form">
                <?php renderCsrfInput(); ?>
                <input type="hidden" name="theme" value="custom">
                <input type="hidden" name="custom_color" value="<?= e($account['custom_color'] ?? '#5865f2') ?>">
                <button
                    type="submit"
                    class="theme-swatch custom-swatch <?= (($account['theme'] ?? 'rose') === 'custom') ? 'active' : '' ?>"
                    title="Custom Color"
                    aria-label="Custom Color theme"
                >
                    <span class="custom-swatch-inner"></span>
                </button>
            </form>
        </div>

        <!-- Custom Color Controls Panel -->
        <div class="custom-color-controls">
            <h3 style="font-size:0.95rem; margin:0 0 0.75rem;">Custom Accent Color</h3>
            <form action="update-theme.php" method="post" id="custom-theme-form">
                <?php renderCsrfInput(); ?>
                <input type="hidden" name="theme" value="custom">

                <div class="custom-color-row">
                    <div class="color-input-wrapper">
                        <input type="color" id="native-color-picker" value="<?= e($account['custom_color'] ?? '#5865f2') ?>" title="Choose custom color">
                        <input type="text" id="color-hex" name="custom_color" class="custom-form-hex" value="<?= e($account['custom_color'] ?? '#5865f2') ?>" maxlength="7" placeholder="#5865F2" required>
                    </div>

                    <button type="button" class="btn btn-ghost" id="custom-theme-btn" style="width:auto; padding:0 0.8rem;" title="Open visual canvas picker">🎨 Canvas</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:0;">Save custom theme</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Visual Canvas Spectrum Modal -->
    <div class="crop-modal" id="color-modal">
        <div class="color-modal-card">
            <h3 style="margin:0 0 0.5rem; font-size:1.1rem;">Visual Color Spectrum</h3>
            <p class="muted" style="margin-top:0; font-size:0.82rem;">Drag the cursor or hue slider to tune your exact shade.</p>

            <div class="color-picker-canvas-wrap">
                <canvas id="color-sv" width="300" height="180"></canvas>
                <div id="color-sv-cursor" class="color-sv-cursor"></div>
            </div>

            <input type="range" id="color-hue" class="color-hue-slider" min="0" max="360" value="220">

            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <div id="color-preview" style="width:28px; height:28px; border-radius:50%; border:1px solid var(--line);"></div>
                </div>
                <div class="crop-actions" style="margin-top:0;">
                    <button type="button" class="btn btn-ghost" id="color-cancel">Done</button>
                </div>
            </div>
        </div>
    </div>

    <section class="profile-section">
        <h2>Find friends</h2>
        <form method="get" class="friend-search-form">
            <input type="text" name="search" placeholder="Search by username" value="<?= e($searchQuery) ?>" minlength="2">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <?php if ($searchQuery !== ''): ?>
            <?php if (empty($searchResults)): ?>
                <p class="muted">No users found matching "<?= e($searchQuery) ?>".</p>
            <?php else: ?>
                <ul class="friend-list">
                <?php foreach ($searchResults as $r): ?>
                    <li class="friend-row">
                        <?= renderAvatar($r['avatar'], $r['username'], 'md') ?>
                        <span class="friend-name"><?= e($r['username']) ?></span>
                        <?php if ($r['status'] === 'friends'): ?>
                            <span class="friend-status">Friends</span>
                        <?php elseif ($r['status'] === 'pending_sent'): ?>
                            <span class="friend-status">Request sent</span>
                        <?php elseif ($r['status'] === 'pending_received'): ?>
                            <form action="add-friend.php" method="post" class="inline-form">
                                <?php renderCsrfInput(); ?>
                                <input type="hidden" name="target_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="search" value="<?= e($searchQuery) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Accept request</button>
                            </form>
                        <?php else: ?>
                            <form action="add-friend.php" method="post" class="inline-form">
                                <?php renderCsrfInput(); ?>
                                <input type="hidden" name="target_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="search" value="<?= e($searchQuery) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Add friend</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if (!empty($incoming)): ?>
    <section class="profile-section">
        <h2>Friend requests <span class="section-count"><?= count($incoming) ?></span></h2>
        <ul class="friend-list">
        <?php foreach ($incoming as $req): ?>
            <li class="friend-row">
                <?= renderAvatar($req['avatar'], $req['username'], 'md') ?>
                <span class="friend-name"><?= e($req['username']) ?></span>
                <form action="accept-friend.php" method="post" class="inline-form">
                    <?php renderCsrfInput(); ?>
                    <input type="hidden" name="request_id" value="<?= $req['friendship_id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Accept</button>
                </form>
                <form action="remove-friend.php" method="post" class="inline-form">
                    <?php renderCsrfInput(); ?>
                    <input type="hidden" name="friendship_id" value="<?= $req['friendship_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm">Decline</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($outgoing)): ?>
    <section class="profile-section">
        <h2>Sent requests</h2>
        <ul class="friend-list">
        <?php foreach ($outgoing as $req): ?>
            <li class="friend-row">
                <?= renderAvatar($req['avatar'], $req['username'], 'md') ?>
                <span class="friend-name"><?= e($req['username']) ?></span>
                <span class="friend-status">Pending</span>
                <form action="remove-friend.php" method="post" class="inline-form">
                    <?php renderCsrfInput(); ?>
                    <input type="hidden" name="friendship_id" value="<?= $req['friendship_id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm">Cancel</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="profile-section">
        <h2>Friends <span class="section-count"><?= count($friends) ?></span></h2>
        <?php if (empty($friends)): ?>
            <p class="muted">No friends yet — search for a username above to send a request.</p>
        <?php else: ?>
            <ul class="friend-list">
            <?php foreach ($friends as $f): ?>
                <li class="friend-row">
                    <a href="friend.php?id=<?= $f['user_id'] ?>"><?= renderAvatar($f['avatar'], $f['username'], 'md') ?></a>
                    <a href="friend.php?id=<?= $f['user_id'] ?>" class="friend-name friend-name-link"><?= e($f['username']) ?></a>
                    <form action="remove-friend.php" method="post" class="inline-form">
                        <?php renderCsrfInput(); ?>
                        <input type="hidden" name="friendship_id" value="<?= $f['friendship_id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Remove this friend?')">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</main>
<script>window.CURRENT_CUSTOM_COLOR = <?= json_encode($account['custom_color'] ?? '#5865f2') ?>;</script>
<script src="color-picker.js"></script>
<script src="app.js"></script>
</body>
</html>