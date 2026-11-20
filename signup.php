<?php
/**
 * signup.php — Create an account
 * Validates the form, hashes the password with password_hash(),
 * saves an optional profile picture and logs the new user in.
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$form   = ['full_name' => '', 'username' => '', 'email' => ''];
$errors = [];

if (is_post()) {
    $form['full_name'] = trim($_POST['full_name'] ?? '');
    $form['username']  = trim($_POST['username'] ?? '');
    $form['email']     = trim($_POST['email'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['confirm_password'] ?? '';

    // ---------- Validation ----------
    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $form['username'])) {
        $errors[] = 'Username must be 3–30 letters, numbers or underscores.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    // Username and email must not belong to someone else already
    if (!$errors) {
        $taken = db_row('SELECT username, email FROM users WHERE username = ? OR email = ?', 'ss', [$form['username'], $form['email']]);
        if ($taken) {
            $errors[] = strcasecmp($taken['username'], $form['username']) === 0
                ? 'That username is already taken.'
                : 'An account with that email already exists.';
        }
    }

    // Optional profile picture (from $_FILES)
    $avatar = '';
    if (!$errors) {
        list($avatar, $uploadError) = upload_image('avatar', 'avatars');
        if ($uploadError !== '') {
            $errors[] = $uploadError;
        }
    }

    // ---------- Save ----------
    if (!$errors) {
        // password_hash() turns "secret123" into something like "$2y$10$Jk1...".
        // Only this hash is stored — the real password is never saved.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        db_run(
            'INSERT INTO users (full_name, username, email, password, avatar) VALUES (?, ?, ?, ?, ?)',
            'sssss',
            [$form['full_name'], $form['username'], $form['email'], $hash, $avatar !== '' ? $avatar : null]
        );

        $newUser = db_row('SELECT * FROM users WHERE id = ?', 'i', [db_insert_id()]);
        log_in_user($newUser);
        flash('Account created. Welcome to ' . APP_NAME . ', ' . $newUser['full_name'] . '!');
        redirect('index.php');
    }
}

$pageTitle = 'Sign up';
$authPage  = true;
include __DIR__ . '/includes/header.php';
?>

<div class="auth-pane">
<div class="auth-card">
    <div class="auth-brand"><span class="brand-mark">PB</span><?= e(APP_NAME) ?></div>
    <h1>Create an account</h1>
    <p class="muted">It only takes a minute.</p>

    <?php if ($flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <!-- enctype="multipart/form-data" is required for file uploads -->
    <form method="post" enctype="multipart/form-data" class="form">
        <div class="avatar-pick">
            <img id="avatar-preview" class="avatar lg" src="assets/img/no-avatar.svg" alt="">
            <label>Profile picture <span class="muted">(optional)</span>
                <input type="file" name="avatar" accept="image/*" data-preview="avatar-preview">
            </label>
        </div>
        <label>Full name
            <input type="text" name="full_name" required value="<?= e($form['full_name']) ?>">
        </label>
        <div class="row">
            <label>Username
                <input type="text" name="username" required value="<?= e($form['username']) ?>">
            </label>
            <label>Email
                <input type="email" name="email" required value="<?= e($form['email']) ?>">
            </label>
        </div>
        <div class="row">
            <label>Password
                <input type="password" name="password" required minlength="6">
            </label>
            <label>Confirm password
                <input type="password" name="confirm_password" required minlength="6">
            </label>
        </div>
        <button class="btn primary block">Sign up</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
