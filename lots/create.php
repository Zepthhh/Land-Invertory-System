<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login and permissions (Editor or Admin to modify)
require_role(['Admin', 'Editor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lotNo = trim($_POST['lot_no'] ?? '');
    $surveyNo = trim($_POST['survey_no'] ?? '');
    $barangayId = (int) ($_POST['barangay_id'] ?? 0);
    $area = (float) ($_POST['area_sqm'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    // Extended fields
    $surveyClaimant = trim($_POST['survey_claimant'] ?? '');
    $taxDeclarant = trim($_POST['tax_declarant'] ?? '');
    $currentClaimant = trim($_POST['current_claimant'] ?? '');
    $claimantSex = $_POST['claimant_sex'] ?? null;
    $currentAddress = trim($_POST['current_address'] ?? '');
    $representative = trim($_POST['representative'] ?? '');
    $representativeAddress = trim($_POST['representative_address'] ?? '');
    $subdivision = trim($_POST['subdivision'] ?? '');
    $landCase = trim($_POST['land_case'] ?? '');
    $titlingInterest = trim($_POST['titling_interest'] ?? '');
    $modeOfAcquisition = trim($_POST['mode_of_acquisition'] ?? '');
    $dominantUse = trim($_POST['dominant_use'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $caseReference = trim($_POST['case_reference'] ?? '');
    $sheetRow = ($_POST['sheet_row'] !== '') ? (int)$_POST['sheet_row'] : null;
    
    $allowedStatuses = lot_statuses();

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $allowedStatuses, true)) {
        set_flash('error', 'Please complete all required lot fields with valid values.');
        redirect(app_url('/lots/create.php'));
    }

    // Handle File Upload: Supporting Documents
    $supportingDocsPath = null;
    if (isset($_FILES['supporting_docs_file']) && $_FILES['supporting_docs_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['supporting_docs_file']['tmp_name'];
        $fileName = $_FILES['supporting_docs_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $newFileName = time() . '_doc_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
        $destPath = __DIR__ . '/../uploads/supporting_docs/' . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $supportingDocsPath = 'uploads/supporting_docs/' . $newFileName;
        }
    }

    // Handle File Upload: Approved Survey Plan
    $approvedSurveyPlanPath = null;
    if (isset($_FILES['approved_survey_plan_file']) && $_FILES['approved_survey_plan_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['approved_survey_plan_file']['tmp_name'];
        $fileName = $_FILES['approved_survey_plan_file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $newFileName = time() . '_plan_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
        $destPath = __DIR__ . '/../uploads/approved_survey_plans/' . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $approvedSurveyPlanPath = 'uploads/approved_survey_plans/' . $newFileName;
        }
    }

    // Insert statement with all columns
    $stmt = $mysqli->prepare('
        INSERT INTO lots (
            lot_no, survey_no, barangay_id, area_sqm, status, 
            survey_claimant, tax_declarant, current_claimant, claimant_sex, current_address, 
            representative, representative_address, supporting_docs, subdivision, approved_survey_plan, 
            land_case, titling_interest, mode_of_acquisition, dominant_use, remarks, 
            source_sheet, case_reference, sheet_row
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    if ($stmt) {
        $sourceSheet = 'Web Interface';
        $stmt->bind_param(
            'ssidssssssssssssssssssi',
            $lotNo, $surveyNo, $barangayId, $area, $status,
            $surveyClaimant, $taxDeclarant, $currentClaimant, $claimantSex, $currentAddress,
            $representative, $representativeAddress, $supportingDocsPath, $subdivision, $approvedSurveyPlanPath,
            $landCase, $titlingInterest, $modeOfAcquisition, $dominantUse, $remarks,
            $sourceSheet, $caseReference, $sheetRow
        );
        
        if ($stmt->execute()) {
            // Log this action to audit logs
            log_action($mysqli, 'Create Lot', "Added new lot: Lot No: $lotNo, Survey No: $surveyNo, Area: $area sqm.");
            set_flash('success', 'Lot added successfully.');
        } else {
            set_flash('error', 'Failed to save lot record: ' . $mysqli->error);
        }
        $stmt->close();
    } else {
        set_flash('error', 'Database preparation failed.');
    }

    redirect(app_url('/lots/index.php'));
}

$pageTitle = 'Add Lot';
$pageDescription = 'Create a lot record tied to an existing barangay.';
$barangays = fetch_barangays($mysqli);
$lotStatuses = lot_statuses();
$lotFormAction = app_url('/lots/create.php');
$lotFormValues = [
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
$lotSubmitLabel = 'Save Lot';
$lotShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">New Lot Record</h2>
    <?php if (!$barangays): ?>
        <div class="empty-state">Add a barangay first before creating lot records.</div>
    <?php else: ?>
        <?php require __DIR__ . '/form.php'; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
