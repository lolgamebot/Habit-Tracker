<?php
require "../includes/bootstrap.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["password_confirm"] ?? "";

    if (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
        $check->execute([$username]);

        if ($check->fetch()) {
            $error = "That username is already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO accounts (username, password) VALUES (?, ?)");
            $insert->execute([$username, $hash]);
            $userId = $pdo->lastInsertId();

            // Seed a few starter habit slots so the dashboard isn't empty on first login
            $seed = $pdo->prepare("INSERT INTO habits (user_id, name, sort_order) VALUES (?, ?, ?)");
            foreach (["Habit 1", "Habit 2", "Habit 3"] as $i => $name) {
                $seed->execute([$userId, $name, $i]);
            }

            session_regenerate_id(true);
            $_SESSION["user_id"] = $userId;
            $_SESSION["username"] = $username;
            $_SESSION["avatar"] = null;
            $_SESSION["theme"] = 'rose';

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account - Habit Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <a href="login.php" class="brand" style="margin-bottom:1.25rem; display:inline-flex;"><span class="brand-dot" aria-hidden="true"></span>Habit&nbsp;Tracker</a>
        <h1>Create your account</h1>
        <p class="muted">Start tracking habits in under a minute.</p>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="register.php" class="stacked-form">
            <?php renderCsrfInput(); ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required minlength="3" autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8">

            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">

            <button type="submit" class="btn btn-primary">Create account</button>
        </form>

        <p class="muted">Already have an account? <a href="login.php">Log in</a>.</p>
    </div>
</body>
</html>