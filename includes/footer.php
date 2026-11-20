<?php
/**
 * footer.php
 * Closes the page layout opened in header.php.
 */
?>
</main>
<?php if (empty($authPage)): ?>
<footer class="site-foot">
    <span><?= e(APP_NAME) ?> · Inventory for small PC shops</span>
    <span>Sample product photos: official manufacturer images</span>
</footer>
<?php endif; ?>
<?php if (empty($authPage) && is_logged_in()): ?>
    <?php require __DIR__ . '/chat_widget.php'; ?>
    <script src="assets/chat.js"></script>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
