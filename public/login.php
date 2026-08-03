<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields!";
    } else {
        $find = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $find->execute([$username]);
        $account = $find->fetch();

        if ($account && password_verify($password, $account["password"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $account["id"];
            $_SESSION["username"] = $account["username"];
            $_SESSION["avatar"] = $account["avatar"];
            $_SESSION["theme"] = $account["theme"] ?? 'rose';
            $_SESSION["custom_color"] = $account["custom_color"] ?? null;
            $_SESSION["dark_mode"] = (bool)($account["dark_mode"] ?? false);

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - Habit Tracker</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <a href="login.php" class="brand" style="margin-bottom:1.25rem; display:inline-flex;"><span class="brand-dot" aria-hidden="true"></span>Habit&nbsp;Tracker</a>
        <h1>Welcome back</h1>
        <p class="muted">Log in to keep your streak going.</p>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" class="stacked-form">
            <?php renderCsrfInput(); ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-primary">Log in</button>
        </form>

        <p class="muted">No account yet? <a href="register.php">Create one</a>.</p>
    </div>
</body>
</html>