<?php
declare(strict_types=1);

$landUseFormAction = $landUseFormAction ?? '';
$landUseFormValues = $landUseFormValues ?? [
    'id' => '',
    'barangay_id' => '',
    'type' => 'Road',
    'area_sqm' => '',
];
$landUseSubmitLabel = $landUseSubmitLabel ?? 'Save Land Use';
$landUseShowBack = $landUseShowBack ?? false;
$landUseTypes = $landUseTypes ?? land_use_types();
?>
<form method="post" action="<?= h($landUseFormAction); ?>">
    <?php if ($landUseFormValues['id'] !== ''): ?>
        <input type="hidden" name="id" value="<?= h((string) $landUseFormValues['id']); ?>">
    <?php endif; ?>
    <div class="form-grid">
        <div>
            <label for="barangay_id">Barangay</label>
            <select id="barangay_id" name="barangay_id" required>
                <option value="">Select barangay</option>
                <?php foreach ($barangays as $barangay): ?>
                    <option value="<?= h((string) $barangay['id']); ?>" <?= (string) $barangay['id'] === (string) $landUseFormValues['barangay_id'] ? 'selected' : ''; ?>>
                        <?= h($barangay['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="type">Land Use Type</label>
            <select id="type" name="type" required>
                <?php foreach ($landUseTypes as $type): ?>
                    <option value="<?= h($type); ?>" <?= $type === $landUseFormValues['type'] ? 'selected' : ''; ?>><?= h($type); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="area_sqm">Area (sqm)</label>
            <input type="number" step="0.01" min="0.01" id="area_sqm" name="area_sqm" value="<?= h((string) $landUseFormValues['area_sqm']); ?>" required>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= h($landUseSubmitLabel); ?></button>
        <?php if ($landUseShowBack): ?>
            <a class="btn btn-secondary" href="<?= h(app_url('/land_use/index.php')); ?>">Back</a>
        <?php endif; ?>
    </div>
</form>
