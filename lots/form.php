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
    'survey_claimant' => '',
    'tax_declarant' => '',
    'current_claimant' => '',
    'claimant_sex' => '',
    'current_address' => '',
    'representative' => '',
    'representative_address' => '',
    'supporting_docs' => '',
    'subdivision' => '',
    'approved_survey_plan' => '',
    'land_case' => '',
    'titling_interest' => '',
    'mode_of_acquisition' => '',
    'dominant_use' => '',
    'remarks' => '',
    'source_sheet' => '',
    'case_reference' => '',
    'sheet_row' => '',
];
$lotSubmitLabel = $lotSubmitLabel ?? 'Save Lot';
$lotShowBack = $lotShowBack ?? false;
$lotStatuses = $lotStatuses ?? lot_statuses();
$barangays = $barangays ?? [];
?>
<form method="post" action="<?= h($lotFormAction); ?>" enctype="multipart/form-data" id="lotForm">
    <?php if ($lotFormValues['id'] !== ''): ?>
        <input type="hidden" name="id" value="<?= h((string) $lotFormValues['id']); ?>">
    <?php endif; ?>

    <!-- JS Tab Switcher Header -->
    <div class="tabs-header" style="display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 1px solid var(--panel-border); padding-bottom: 12px; flex-wrap: wrap;">
        <button type="button" id="btn-tab-basic" class="btn btn-primary" onclick="switchLotTab('basic')" style="padding: 7px 12px; min-height: 34px; font-size: 0.8rem; flex: 1; min-width: 80px;">Basic Info</button>
        <button type="button" id="btn-tab-claimants" class="btn btn-secondary" onclick="switchLotTab('claimants')" style="padding: 7px 12px; min-height: 34px; font-size: 0.8rem; flex: 1; min-width: 80px;">Claimants & GAD</button>
        <button type="button" id="btn-tab-legal" class="btn btn-secondary" onclick="switchLotTab('legal')" style="padding: 7px 12px; min-height: 34px; font-size: 0.8rem; flex: 1; min-width: 80px;">Legal & Files</button>
    </div>

    <!-- TAB 1: BASIC INFO -->
    <div id="pane-tab-basic" class="tab-pane-content">
        <div class="form-grid">
            <div>
                <label for="lot_no">Lot Number <span style="color:var(--danger)">*</span></label>
                <input type="text" id="lot_no" name="lot_no" value="<?= h((string) $lotFormValues['lot_no']); ?>" required placeholder="e.g. Lot-001" oninput="hideDuplicateWarning()">
                <div id="duplicateWarning" class="inline-warning hidden">
                    ⚠️ A lot with this number already exists in the selected barangay. Please verify to avoid duplicates.
                </div>
            </div>
            <div>
                <label for="survey_no">Survey Number <span style="color:var(--danger)">*</span></label>
                <input type="text" id="survey_no" name="survey_no" value="<?= h((string) $lotFormValues['survey_no']); ?>" required placeholder="e.g. SRV-1001" oninput="triggerSurveyAutofill(this.value)">
            </div>
            <div>
                <label for="barangay_id">Barangay <span style="color:var(--danger)">*</span></label>
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
                <label for="area_sqm">Area (sqm) <span style="color:var(--danger)">*</span></label>
                <input type="number" step="0.01" min="0.01" id="area_sqm" name="area_sqm" value="<?= h((string) $lotFormValues['area_sqm']); ?>" required placeholder="0.00">
            </div>
            <div>
                <label for="status">Status <span style="color:var(--danger)">*</span></label>
                <select id="status" name="status" required>
                    <?php foreach ($lotStatuses as $status): ?>
                        <option value="<?= h($status); ?>" <?= $status === $lotFormValues['status'] ? 'selected' : ''; ?>><?= h(get_status_label($status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="dominant_use">Dominant Use</label>
                <input type="text" id="dominant_use" name="dominant_use" value="<?= h((string) $lotFormValues['dominant_use']); ?>" placeholder="e.g. Residential, Agricultural">
            </div>
            <div>
                <label for="subdivision">Subdivision</label>
                <input type="text" id="subdivision" name="subdivision" value="<?= h((string) $lotFormValues['subdivision']); ?>" placeholder="Subdivision name if any">
            </div>
            <div>
                <label for="sheet_row">Sheet Row</label>
                <input type="number" id="sheet_row" name="sheet_row" value="<?= h((string) $lotFormValues['sheet_row']); ?>" placeholder="Excel row ref (optional)">
            </div>
        </div>
    </div>

    <!-- TAB 2: CLAIMANTS & GAD -->
    <div id="pane-tab-claimants" class="tab-pane-content" style="display: none;">
        <div class="form-grid">
            <div>
                <label for="current_claimant">Current Claimant</label>
                <input type="text" id="current_claimant" name="current_claimant" value="<?= h((string) $lotFormValues['current_claimant']); ?>" placeholder="Full name of claimant">
            </div>
            <div>
                <label for="claimant_sex">Sex / Gender <span style="font-size:0.78rem; color: var(--primary); font-weight:500;">(GAD)</span></label>
                <select id="claimant_sex" name="claimant_sex">
                    <option value="">Select gender</option>
                    <option value="M" <?= $lotFormValues['claimant_sex'] === 'M' ? 'selected' : ''; ?>>Male (M)</option>
                    <option value="F" <?= $lotFormValues['claimant_sex'] === 'F' ? 'selected' : ''; ?>>Female (F)</option>
                    <option value="M/F" <?= $lotFormValues['claimant_sex'] === 'M/F' ? 'selected' : ''; ?>>Co-owners / Both (M/F)</option>
                </select>
            </div>
            <div>
                <label for="current_address">Current Claimant Address</label>
                <input type="text" id="current_address" name="current_address" value="<?= h((string) $lotFormValues['current_address']); ?>" placeholder="Complete address of current claimant">
            </div>
            <div>
                <label for="survey_claimant">Original Survey Claimant</label>
                <input type="text" id="survey_claimant" name="survey_claimant" value="<?= h((string) $lotFormValues['survey_claimant']); ?>" placeholder="Survey Claimant name">
            </div>
            <div>
                <label for="tax_declarant">Tax Declarant</label>
                <input type="text" id="tax_declarant" name="tax_declarant" value="<?= h((string) $lotFormValues['tax_declarant']); ?>" placeholder="Tax declarant name">
            </div>
            <div>
                <label for="representative">Representative</label>
                <input type="text" id="representative" name="representative" value="<?= h((string) $lotFormValues['representative']); ?>" placeholder="Representative if applicable">
            </div>
            <div>
                <label for="representative_address">Representative Address</label>
                <input type="text" id="representative_address" name="representative_address" value="<?= h((string) $lotFormValues['representative_address']); ?>" placeholder="Representative's complete address">
            </div>
        </div>
    </div>

    <!-- TAB 3: LEGAL & FILES -->
    <div id="pane-tab-legal" class="tab-pane-content" style="display: none;">
        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
            <div>
                <label for="mode_of_acquisition">Mode of Acquisition</label>
                <input type="text" id="mode_of_acquisition" name="mode_of_acquisition" value="<?= h((string) $lotFormValues['mode_of_acquisition']); ?>" placeholder="e.g. Free Patent, Sales Patent, Homestead">
            </div>
            <div>
                <label for="titling_interest">Titling Interest</label>
                <input type="text" id="titling_interest" name="titling_interest" value="<?= h((string) $lotFormValues['titling_interest']); ?>" placeholder="Interest detail">
            </div>
            <div>
                <label for="land_case">Land Case Status</label>
                <input type="text" id="land_case" name="land_case" value="<?= h((string) $lotFormValues['land_case']); ?>" placeholder="e.g. Protest Filed, Dismissed, Disputed">
            </div>
            <div>
                <label for="case_reference">Case Reference</label>
                <input type="text" id="case_reference" name="case_reference" value="<?= h((string) $lotFormValues['case_reference']); ?>" placeholder="e.g. CENRO Case No. 2026-44">
            </div>
            
            <div>
                <label for="remarks">Legal / Operational Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" style="width: 100%; background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 10px; color: #fff; padding: 12px; font-family: inherit; font-size: 1rem; resize: vertical;" placeholder="Enter comments or notes regarding this lot..."><?= h((string) $lotFormValues['remarks']); ?></textarea>
            </div>

            <!-- Uploads -->
            <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); padding: 15px; border-radius: 12px;">
                <label for="supporting_docs_file" style="margin-bottom: 4px;">Supporting Document (PDF/Image)</label>
                <input type="file" id="supporting_docs_file" name="supporting_docs_file" accept=".pdf,image/*" style="font-size: 0.88rem; padding: 6px;">
                <?php if (!empty($lotFormValues['supporting_docs'])): ?>
                    <div style="margin-top: 10px; font-size: 0.85rem;">
                        📄 Current: <a href="<?= h(app_url('/' . $lotFormValues['supporting_docs'])); ?>" target="_blank" style="color: var(--primary); text-decoration: underline;"><?= h(basename($lotFormValues['supporting_docs'])); ?></a>
                    </div>
                <?php endif; ?>
            </div>

            <div style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); padding: 15px; border-radius: 12px;">
                <label for="approved_survey_plan_file" style="margin-bottom: 4px;">Approved Survey Plan (PDF/Image)</label>
                <input type="file" id="approved_survey_plan_file" name="approved_survey_plan_file" accept=".pdf,image/*" style="font-size: 0.88rem; padding: 6px;">
                <?php if (!empty($lotFormValues['approved_survey_plan'])): ?>
                    <div style="margin-top: 10px; font-size: 0.85rem;">
                        🗺️ Current: <a href="<?= h(app_url('/' . $lotFormValues['approved_survey_plan'])); ?>" target="_blank" style="color: var(--primary); text-decoration: underline;"><?= h(basename($lotFormValues['approved_survey_plan'])); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Actions Footer -->
    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= h($lotSubmitLabel); ?></button>
        <?php if ($lotShowBack): ?>
            <a class="btn btn-secondary" href="<?= h(app_url('/lots/index.php')); ?>">Back</a>
        <?php endif; ?>
    </div>
</form>

<script>
function switchLotTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane-content').forEach(pane => {
        pane.style.display = 'none';
    });
    
    // De-activate all tab buttons
    document.querySelectorAll('.tabs-header .btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
    });

    // Show selected tab pane
    document.getElementById('pane-tab-' + tabId).style.display = 'block';

    // Activate selected button
    const targetBtn = document.getElementById('btn-tab-' + tabId);
    targetBtn.classList.remove('btn-secondary');
    targetBtn.classList.add('btn-primary');
}

// Auto-fill logic based on survey numbers cached client-side
let surveyDataCache = {};

// We can populate this cache dynamically when this page loads
async function initSurveyCache() {
    try {
        const response = await fetch('<?= h(app_url("/lots/index.php?action=survey_api")); ?>');
        if (response.ok) {
            surveyDataCache = await response.json();
        }
    } catch(e) {
        console.log('Unable to initialize survey cache');
    }
}

function triggerSurveyAutofill(val) {
    if (!val) return;
    const cleanVal = val.trim();
    if (surveyDataCache[cleanVal]) {
        const matched = surveyDataCache[cleanVal];
        // Autofill barangay and subdivision
        const brgySelect = document.getElementById('barangay_id');
        if (matched.barangay_id && !brgySelect.value) {
            brgySelect.value = matched.barangay_id;
        }
        const subdivInput = document.getElementById('subdivision');
        if (matched.subdivision && !subdivInput.value) {
            subdivInput.value = matched.subdivision;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initSurveyCache();
    
    // Form check: Duplicate lot verification
    const form = document.getElementById('lotForm');
    form.addEventListener('submit', async function(e) {
        const lotNo = document.getElementById('lot_no').value.trim();
        const brgyId = document.getElementById('barangay_id').value;
        const currentLotId = "<?= h((string)$lotFormValues['id']); ?>";
        
        if (!lotNo || !brgyId) return;

        // Perform validation check
        try {
            e.preventDefault(); // Pause submission
            const response = await fetch(`<?= h(app_url("/lots/index.php")); ?>?action=check_duplicate&lot_no=${encodeURIComponent(lotNo)}&barangay_id=${brgyId}&current_id=${currentLotId}`);
            if (response.ok) {
                const data = await response.json();
                if (data.exists) {
                    document.getElementById('duplicateWarning').classList.remove('hidden');
                    // Scroll to the warning
                    document.getElementById('duplicateWarning').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            form.submit(); // Continue standard submit
        } catch(err) {
            // If API fails, submit normally
            form.submit();
        }
    });
});

function hideDuplicateWarning() {
    const w = document.getElementById('duplicateWarning');
    if (w) w.classList.add('hidden');
}
</script>
