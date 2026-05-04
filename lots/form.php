<?php
declare(strict_types=1);

$lotFormAction = $lotFormAction ?? '';
$lotFormValues = $lotFormValues ?? [
    'id' => '',
    'lot_no' => '',
    'survey_no' => '',
    'barangay_id' => '',
    'area_sqm' => '',
    'status' => 'Unapplied',
];
$lotSubmitLabel = $lotSubmitLabel ?? 'Save Lot';
$lotShowBack = $lotShowBack ?? false;
$lotStatuses = $lotStatuses ?? lot_statuses();
?>
<form method="post" action="<?= h($lotFormAction); ?>">
    <?php if ($lotFormValues['id'] !== ''): ?>
        <input type="hidden" name="id" value="<?= h((string) $lotFormValues['id']); ?>">
    <?php endif; ?>
    <div class="form-grid">
        <div>
            <label for="lot_no">Lot Number</label>
            <input type="text" id="lot_no" name="lot_no" value="<?= h((string) $lotFormValues['lot_no']); ?>" required>
        </div>
        <div>
            <label for="survey_no">Survey Number</label>
            <input type="text" id="survey_no" name="survey_no" value="<?= h((string) $lotFormValues['survey_no']); ?>" required>
        </div>
        <div>
            <label for="barangay_id">Barangay</label>
            <select id="barangay_id" name="barangay_id" required>
                <option value="">Select barangay</option>
                <?php foreach ($barangays as $barangay): ?>
                    <option value="<?= h((string) $barangay['id']); ?>" <?= (string) $barangay['id'] === (string) $lotFormValues['barangay_id'] ? 'selected' : ''; ?>>
                        <?= h($barangay['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="area_sqm">Area (sqm)</label>
            <input type="number" step="0.01" min="0.01" id="area_sqm" name="area_sqm" value="<?= h((string) $lotFormValues['area_sqm']); ?>" required>
        </div>
        <div>
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php foreach ($lotStatuses as $status): ?>
                    <option value="<?= h($status); ?>" <?= $status === $lotFormValues['status'] ? 'selected' : ''; ?>><?= h(get_status_label($status)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= h($lotSubmitLabel); ?></button>
        <?php if ($lotShowBack): ?>
            <a class="btn btn-secondary" href="<?= h(app_url('/lots/index.php')); ?>">Back</a>
        <?php endif; ?>
    </div>
</form>
