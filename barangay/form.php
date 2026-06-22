<?php
declare(strict_types=1);

$barangayFormAction = $barangayFormAction ?? '';
$barangayFormValues = $barangayFormValues ?? [
    'id' => '',
    'municipality_id' => '',
    'name' => '',
    'total_area_sqm' => '',
];
$barangaySubmitLabel = $barangaySubmitLabel ?? 'Save Barangay';
$barangayShowBack = $barangayShowBack ?? false;
?>
<form method="post" action="<?= h($barangayFormAction); ?>">
    <?php if ($barangayFormValues['id'] !== ''): ?>
        <input type="hidden" name="id" value="<?= h((string) $barangayFormValues['id']); ?>">
    <?php endif; ?>
    <div class="form-grid">
        <div>
            <label for="municipality_id">Municipality</label>
            <select id="municipality_id" name="municipality_id" required>
                <option value="">-- Select Municipality --</option>
                <?php foreach (fetch_municipalities($mysqli) as $mun): ?>
                    <option value="<?= h((string)$mun['id']) ?>" <?= (string)$mun['id'] === (string)$barangayFormValues['municipality_id'] ? 'selected' : '' ?>>
                        <?= h($mun['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="name">Barangay Name</label>
            <input type="text" id="name" name="name" value="<?= h((string) $barangayFormValues['name']); ?>" required>
        </div>
        <div>
            <label for="total_area_sqm">Total Area (sqm)</label>
            <input type="number" step="0.01" min="0.01" id="total_area_sqm" name="total_area_sqm" value="<?= h((string) $barangayFormValues['total_area_sqm']); ?>" required>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= h($barangaySubmitLabel); ?></button>
        <?php if ($barangayShowBack): ?>
            <a class="btn btn-secondary" href="<?= h(app_url('/barangay/index.php')); ?>">Back</a>
        <?php endif; ?>
    </div>
</form>
