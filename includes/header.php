<?php
/**
 * header.php
 * Shared page header and navigation.
 * Pages set $pageTitle before including this file.
 */

$currentPage = basename($_SERVER['PHP_SELF']);

// Detail pages highlight their parent menu entry
$parentPage = [
    'item_view.php'   => 'items.php',
    'item_form.php'   => 'items.php',
];
$activePage = $parentPage[$currentPage] ?? $currentPage;

$menu = [
    'index.php'      => 'Dashboard',
    'items.php'      => 'Items',
    'categories.php' => 'Categories',
    'stock.php'      => 'Stock',
    'restocks.php'   => 'Restocks',
    'valuation.php'  => 'Valuation',
];

$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($pageTitle ?? 'Inventory') . ' · ' . APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php"><?= e(APP_NAME) ?></a>
        <nav>
            <?php foreach ($menu as $file => $label): ?>
                <a href="<?= $file ?>" class="<?= $file === $activePage ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="container">
<?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
