<?php
/**
 * profile.php — My profile
 * Change your name, email and profile picture, or your password.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user   = current_user();
$errors = [];

if (is_post()) {
    $action = $_POST['action'] ?? '';

    // ---------- Update details + picture ----------
    if ($action === 'details') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } elseif (db_row('SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, $user['id']])) {
            $errors[] = 'Another account already uses that email.';
        }

        $avatar = $user['avatar'];
        if (!$errors) {
            list($newAvatar, $uploadError) = upload_image('avatar', 'avatars');
            if ($uploadError !== '') {
                $errors[] = $uploadError;
            } elseif ($newAvatar !== '') {
                delete_image('avatars', $user['avatar']);   // remove the old file from disk
                $avatar = $newAvatar;
            } elseif (isset($_POST['remove_avatar'])) {
                delete_image('avatars', $user['avatar']);
                $avatar = null;
            }
        }

        if (!$errors) {
            db_run('UPDATE users SET full_name = ?, email = ?, avatar = ? WHERE id = ?', 'sssi', [$fullName, $email, $avatar, $user['id']]);
            $_SESSION['user_name'] = $fullName;
            flash('Profile updated.');
            redirect('profile.php');
        }
    }

    // ---------- Change password ----------
    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // The hash is not part of current_user(), so read it separately
        $hash = db_value('SELECT password FROM users WHERE id = ?', 'i', [$user['id']]);

        if (!password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'The new password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'The new passwords do not match.';
        } else {
            db_run('UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('Password changed.');
            redirect('profile.php');
        }
    }
}

$stats = db_row(
    "SELECT COUNT(*) AS moves, COALESCE(SUM(type = 'restock'), 0) AS restocks FROM stock_movements WHERE user_id = ?",
    'i',
    [$user['id']]
);

$pageTitle = 'My profile';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>My profile</h1>
</div>

<?php if ($errors): ?>
    <div class="flash flash-error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid-side">
    <section class="card">
        <h2>Account details</h2>
        <form method="post" enctype="multipart/form-data" class="form">
            <input type="hidden" name="action" value="details">
            <div class="avatar-pick">
                <img id="avatar-preview" class="avatar xl" src="<?= e(image_url('avatars', $user['avatar'])) ?>" alt="">
                <div>
                    <label>Profile picture
                        <input type="file" name="avatar" accept="image/*" data-preview="avatar-preview">
                    </label>
                    <?php if ($user['avatar']): ?>
                        <label class="check"><input type="checkbox" name="remove_avatar" value="1"> Remove current picture</label>
                    <?php endif; ?>
                </div>
            </div>
            <label>Full name
                <input type="text" name="full_name" required value="<?= e($user['full_name']) ?>">
            </label>
            <div class="row">
                <label>Username
                    <input type="text" value="<?= e($user['username']) ?>" disabled>
                </label>
                <label>Email
                    <input type="email" name="email" required value="<?= e($user['email']) ?>">
                </label>
            </div>
            <div class="form-actions"><button class="btn primary">Save profile</button></div>
        </form>
        <p class="muted small">Member since <?= e(nice_date($user['created_at'])) ?> · <?= (int) $stats['moves'] ?> stock movements recorded · <?= (int) $stats['restocks'] ?> restocks</p>
    </section>

    <section class="card">
        <h2>Change password</h2>
        <form method="post" class="form">
            <input type="hidden" name="action" value="password">
            <label>Current password
                <input type="password" name="current_password" required>
            </label>
            <label>New password
                <input type="password" name="new_password" required minlength="6">
            </label>
            <label>Confirm new password
                <input type="password" name="confirm_password" required minlength="6">
            </label>
            <div class="form-actions"><button class="btn">Change password</button></div>
        </form>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
