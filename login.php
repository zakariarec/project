<?php
require 'config.php';

if (!empty($_SESSION['user_id']) && currentUser() !== null) {
    header('Location: index.php');
    exit;
}

$tab    = 'login';
$error  = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $userObj = new User();

    if ($action === 'login') {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $result   = $userObj->login($email, $password);

        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $u = $result['user'];
            session_regenerate_id(true);
            $_SESSION['user_id']  = $u['id'];
            $_SESSION['username'] = $u['username'];
            $_SESSION['role']     = $u['role'];
            $_SESSION['last_activity'] = time();
            $userObj->writeLog($u);
            setcookie('last_login', date('Y-m-d H:i:s'), [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            header('Location: index.php');
            exit;
        }
    }

    if ($action === 'register') {
        $tab    = 'register';
        $result = $userObj->register(
            trim($_POST['username'] ?? ''),
            trim($_POST['email']    ?? ''),
            trim($_POST['password'] ?? '')
        );

        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $success = 'Account made, You can sign in now';
            $tab     = 'login';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<main class="auth-wrap">
    <section class="auth-card">
        <h2 class="auth-title">Project</h2>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= h($error) ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p class="alert alert-success"><?= h($success) ?></p>
        <?php endif; ?>

        <nav class="tabs" aria-label="Authentication">
            <button class="tab-btn <?= $tab === 'login' ? 'active' : '' ?>" id="tab-login" type="button">Sign In</button>
            <button class="tab-btn <?= $tab === 'register' ? 'active' : '' ?>" id="tab-register" type="button">Register</button>
        </nav>

        <section id="form-login" class="<?= $tab !== 'login' ? 'is-hidden' : '' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label class="form-label" for="l-email">Email Address</label>
                    <input class="form-control" type="email" id="l-email" name="email"
                           placeholder="you@example.com" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label" for="l-pass">Password</label>
                    <input class="form-control" type="password" id="l-pass" name="password"
                           placeholder="Enter your password" required minlength="6" autocomplete="current-password">
                </div>

                <button class="btn btn-primary w-full" type="submit">Sign In</button>

                <p class="text-muted text-center mt-1 auth-demo">
                    I know you said no sensitive info on client side but you need to check admin role <br> admin@blog.com / admin123
                </p>
            </form>
        </section>

        <section id="form-register" class="<?= $tab !== 'register' ? 'is-hidden' : '' ?>">
            <form method="POST">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label class="form-label" for="r-user">Username</label>
                    <input class="form-control" type="text" id="r-user" name="username"
                           placeholder="Choose a username" required minlength="3" maxlength="30"
                           pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores">
                </div>

                <div class="form-group">
                    <label class="form-label" for="r-email">Email Address</label>
                    <input class="form-control" type="email" id="r-email" name="email"
                           placeholder="you@example.com" required autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label" for="r-pass">Password</label>
                    <input class="form-control" type="password" id="r-pass" name="password"
                           placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
                </div>

                <button class="btn btn-primary w-full" type="submit">Create Account</button>
            </form>
        </section>

    </section>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// toggle seemlessly between the sign-in and registration panels
$(function () {
    $('#tab-login').on('click', function () {
        $(this).addClass('active');
        $('#tab-register').removeClass('active');
        $('#form-login').fadeIn(200);
        $('#form-register').hide();
    });

    $('#tab-register').on('click', function () {
        $(this).addClass('active');
        $('#tab-login').removeClass('active');
        $('#form-register').fadeIn(200);
        $('#form-login').hide();
    });
});
</script>

</body>
</html>
