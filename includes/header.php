<?php
/**
 * header.php
 * Shared page header and navigation.
 * Pages set $pageTitle before including this file.
 * Login / signup pages set $authPage = true: no menu, and they print the
 * flash message themselves inside their split layout.
 */

$authPage = $authPage ?? false;

// Which page is open? $_SERVER['PHP_SELF'] is the path of the running script
$currentPage = basename($_SERVER['PHP_SELF']);

// Detail pages highlight their parent menu entry
$parentPage = [
    'item_view.php' => 'items.php',
    'item_form.php' => 'items.php',
];
$activePage = $parentPage[$currentPage] ?? $currentPage;

$menu = [
    'index.php'      => 'Dashboard',
    'items.php'      => 'Products',
    'categories.php' => 'Categories',
    'stock.php'      => 'Stock',
    'restocks.php'   => 'Restocks',
    'valuation.php'  => 'Valuation',
];

$user  = $authPage ? null : current_user();
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Inventory') . ' · ' . APP_NAME) ?></title>
    <link rel="preload" href="assets/fonts/geist-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="theme-color" content="#f8f5ef">
    <!-- Lets the CSS know JavaScript is on (the mobile menu needs it) -->
    <script>document.documentElement.className += ' js';</script>
</head>
<body class="<?= $authPage ? 'auth-body' : '' ?>">
<?php if (!$authPage): ?>
<header class="topbar" data-topbar>
    <div class="topbar-inner">
        <a class="brand" href="index.php"><span class="brand-mark">PB</span><?= e(APP_NAME) ?></a>
        <!-- Small screens: this button opens and closes the menu below -->
        <button type="button" class="nav-toggle" data-nav-toggle aria-controls="site-menu" aria-expanded="false" aria-label="Open menu">
            <svg class="nav-toggle-open" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <svg class="nav-toggle-close" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <div class="topbar-menu" id="site-menu">
            <nav>
                <?php foreach ($menu as $file => $label): ?>
                    <a href="<?= $file ?>" class="<?= $file === $activePage ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($user): ?>
                <div class="user-menu">
                    <a class="user-chip <?= $currentPage === 'profile.php' ? 'active' : '' ?>" href="profile.php" title="My profile">
                        <img class="avatar sm" src="<?= e(image_url('avatars', $user['avatar'])) ?>" alt="">
                        <span><?= e($user['username']) ?></span>
                    </a>
                    <a class="btn small ghost" href="logout.php">Log out</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
<?php else: ?>
<main class="auth-split">
<?php endif; ?>
