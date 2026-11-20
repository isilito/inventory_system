<?php
/**
 * login.php — Log in
 * Checks the username/email and password against the users table.
 * Passwords are compared with password_verify(), never as plain text.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in? Go straight to the dashboard
if (is_logged_in()) {
    redirect('index.php');
}

$login  = '';
$errors = [];

if (is_post()) {
    // Values typed into the form arrive in $_POST, keyed by each input's name=""
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $errors[] = 'Enter your username (or email) and password.';
    } else {
        // Find the account by username OR email
        $user = db_row('SELECT * FROM users WHERE username = ? OR email = ?', 'ss', [$login, $login]);

        // password_verify() hashes the typed password the same way and compares
        if ($user && password_verify($password, $user['password'])) {
            log_in_user($user);
            flash('Welcome back, ' . $user['full_name'] . '!');
            redirect('index.php');
        } else {
            // Same message for "no such user" and "wrong password" (don't reveal which)
            $errors[] = 'Incorrect username or password.';
        }
    }
}

$pageTitle = 'Log in';
$authPage  = true;
include __DIR__ . '/includes/header.php';
?>

<div class="auth-pane">
<div class="auth-card">
    <div class="auth-brand"><span class="brand-mark">PB</span><?= e(APP_NAME) ?></div>
    <h1>Log in</h1>
    <p class="muted">Manage your shop's parts, stock and restocks.</p>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <label>Username or email
            <input type="text" name="login" required autofocus value="<?= e($login) ?>">
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="btn primary block">Log in</button>
    </form>

    <p class="auth-switch">No account yet? <a href="signup.php">Sign up</a></p>
    <p class="muted small auth-hint">Demo account: <b>admin</b> / <b>admin123</b></p>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
