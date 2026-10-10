<?php
/**
 * movements_table.php
 * Reusable table of stock movements.
 * Expects: $movements (array of rows). Optional: $showPart (bool, default true).
 */

$showPart = $showPart ?? true;
?>
<?php if (empty($movements)): ?>
    <p class="empty">No stock movements yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th><?= $showPart ? 'Part' : 'Variant' ?></th>
                <th>Type</th>
                <th class="num">Change</th>
                <th class="num">After</th>
                <th>Note</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td class="nowrap"><?= e(nice_date($m['created_at'])) ?></td>
                    <?php if ($showPart): ?>
                        <td>
                            <a href="item_view.php?id=<?= (int) $m['item_id'] ?>"><?= e($m['item_name']) ?></a>
                            <span class="muted">· <?= e($m['label']) ?></span>
                        </td>
                    <?php else: ?>
                        <td><?= e($m['label']) ?></td>
                    <?php endif; ?>
                    <td><?= e(movement_label($m['type'])) ?></td>
                    <td class="num <?= $m['qty_change'] > 0 ? 'pos' : 'neg' ?>">
                        <?= $m['qty_change'] > 0 ? '+' . $m['qty_change'] : $m['qty_change'] ?>
                    </td>
                    <td class="num"><?= (int) $m['qty_after'] ?></td>
                    <td class="muted"><?= e(trim($m['supplier'] . ' ' . $m['reason'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
