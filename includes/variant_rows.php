<?php
/**
 * variant_rows.php
 * Table of variant input rows. Every input name ends with [] so PHP
 * receives each column as an ARRAY in $_POST, e.g.
 *     $_POST['variant_label'] = ['8GB', '16GB', '32GB']
 * "+ Add another variant" (assets/app.js) copies the hidden <template> row.
 *
 * Optional: $variantRows — rows to show (e.g. what was posted before a validation error)
 */

if (empty($variantRows)) {
    // Two empty rows by default, so "one product, two variants" is ready to fill in
    $variantRows = [
        ['label' => '', 'sku' => '', 'qty' => '', 'cost' => '', 'price' => '', 'min' => DEFAULT_THRESHOLD],
        ['label' => '', 'sku' => '', 'qty' => '', 'cost' => '', 'price' => '', 'min' => DEFAULT_THRESHOLD],
    ];
}
?>
<div class="variant-rows" data-variant-rows>
    <div class="table-wrap">
        <table class="input-table">
            <thead>
            <tr>
                <th>#</th>
                <th>Variant label *</th>
                <th>SKU</th>
                <th class="num">Opening qty</th>
                <th class="num">Unit cost</th>
                <th class="num">Sell price</th>
                <th class="num">Low at</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($variantRows as $n => $row): ?>
                <tr>
                    <td class="muted row-no"><?= $n + 1 ?></td>
                    <td><input type="text" name="variant_label[]" value="<?= e($row['label']) ?>" placeholder="e.g. 8GB / Black"></td>
                    <td><input type="text" name="variant_sku[]" value="<?= e($row['sku']) ?>" placeholder="Optional"></td>
                    <td><input type="number" name="variant_qty[]" min="0" value="<?= e($row['qty']) ?>" placeholder="0"></td>
                    <td><input type="number" name="variant_cost[]" min="0" step="0.01" value="<?= e($row['cost']) ?>" placeholder="0.00"></td>
                    <td><input type="number" name="variant_price[]" min="0" step="0.01" value="<?= e($row['price']) ?>" placeholder="0.00"></td>
                    <td><input type="number" name="variant_min[]" min="0" value="<?= e($row['min']) ?>"></td>
                    <td><button type="button" class="btn small ghost" data-remove-row title="Remove row">×</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Blank row copied by JavaScript when "+ Add another variant" is clicked -->
    <template>
        <tr>
            <td class="muted row-no"></td>
            <td><input type="text" name="variant_label[]" placeholder="e.g. 16GB / White"></td>
            <td><input type="text" name="variant_sku[]" placeholder="Optional"></td>
            <td><input type="number" name="variant_qty[]" min="0" placeholder="0"></td>
            <td><input type="number" name="variant_cost[]" min="0" step="0.01" placeholder="0.00"></td>
            <td><input type="number" name="variant_price[]" min="0" step="0.01" placeholder="0.00"></td>
            <td><input type="number" name="variant_min[]" min="0" value="<?= DEFAULT_THRESHOLD ?>"></td>
            <td><button type="button" class="btn small ghost" data-remove-row title="Remove row">×</button></td>
        </tr>
    </template>

    <button type="button" class="btn small" data-add-row>+ Add another variant</button>
</div>
