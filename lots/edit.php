<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Enforce login and permissions (Editor or Admin to modify)
require_role(['Admin', 'Editor']);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Invalid lot selected.');
    redirect(app_url('/lots/index.php'));
}

$barangays = fetch_barangays($mysqli);
$lotStatuses = lot_statuses();

// Fetch current values
$stmt = $mysqli->prepare('
    SELECT 
        id, lot_no, survey_no, barangay_id, area_sqm, status,
        survey_claimant, tax_declarant, current_claimant, claimant_sex, current_address,
        representative, representative_address, supporting_docs, subdivision, approved_survey_plan,
        land_case, titling_interest, mode_of_acquisition, dominant_use, remarks,
        source_sheet, case_reference, sheet_row
    FROM lots 
    WHERE id = ?
');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$lot = $result->fetch_assoc();
$stmt->close();

if (!$lot) {
    set_flash('error', 'Lot record not found.');
    redirect(app_url('/lots/index.php'));
}

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

    if ($lotNo === '' || $surveyNo === '' || $barangayId <= 0 || $area <= 0 || !in_array($status, $lotStatuses, true)) {
        set_flash('error', 'Please provide valid lot information.');
        redirect(app_url('/lots/edit.php?id=' . $id));
    }

    // Handle File Upload: Supporting Documents (Keep current if no new file is uploaded)
    $supportingDocsPath = $lot['supporting_docs'];
    if (isset($_FILES['supporting_docs_file']) && $_FILES['supporting_docs_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['supporting_docs_file']['tmp_name'];
        $fileName = $_FILES['supporting_docs_file']['name'];
        
        $newFileName = time() . '_doc_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
        $destPath = __DIR__ . '/../uploads/supporting_docs/' . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Delete old file if exists
            if ($supportingDocsPath && is_file(__DIR__ . '/../' . $supportingDocsPath)) {
                @unlink(__DIR__ . '/../' . $supportingDocsPath);
            }
            $supportingDocsPath = 'uploads/supporting_docs/' . $newFileName;
        }
    }

    // Handle File Upload: Approved Survey Plan (Keep current if no new file is uploaded)
    $approvedSurveyPlanPath = $lot['approved_survey_plan'];
    if (isset($_FILES['approved_survey_plan_file']) && $_FILES['approved_survey_plan_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['approved_survey_plan_file']['tmp_name'];
        $fileName = $_FILES['approved_survey_plan_file']['name'];
        
        $newFileName = time() . '_plan_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', $fileName);
        $destPath = __DIR__ . '/../uploads/approved_survey_plans/' . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            // Delete old file if exists
            if ($approvedSurveyPlanPath && is_file(__DIR__ . '/../' . $approvedSurveyPlanPath)) {
                @unlink(__DIR__ . '/../' . $approvedSurveyPlanPath);
            }
            $approvedSurveyPlanPath = 'uploads/approved_survey_plans/' . $newFileName;
        }
    }

    // Update statement for all columns
    $stmt = $mysqli->prepare('
        UPDATE lots SET 
            lot_no = ?, survey_no = ?, barangay_id = ?, area_sqm = ?, status = ?,
            survey_claimant = ?, tax_declarant = ?, current_claimant = ?, claimant_sex = ?, current_address = ?,
            representative = ?, representative_address = ?, supporting_docs = ?, subdivision = ?, approved_survey_plan = ?,
            land_case = ?, titling_interest = ?, mode_of_acquisition = ?, dominant_use = ?, remarks = ?,
            case_reference = ?, sheet_row = ?
        WHERE id = ?
    ');

    if ($stmt) {
        $stmt->bind_param(
            'ssidssssssssssssssssiii',
            $lotNo, $surveyNo, $barangayId, $area, $status,
            $surveyClaimant, $taxDeclarant, $currentClaimant, $claimantSex, $currentAddress,
            $representative, $representativeAddress, $supportingDocsPath, $subdivision, $approvedSurveyPlanPath,
            $landCase, $titlingInterest, $modeOfAcquisition, $dominantUse, $remarks,
            $caseReference, $sheetRow, $id
        );
        
        if ($stmt->execute()) {
            log_action($mysqli, 'Edit Lot', "Updated details for lot: ID: $id, Lot No: $lotNo, Survey No: $surveyNo.");
            set_flash('success', 'Lot updated successfully.');
        } else {
            set_flash('error', 'Failed to update lot: ' . $mysqli->error);
        }
        $stmt->close();
    } else {
        set_flash('error', 'Database preparation failed.');
    }

    redirect(app_url('/lots/index.php'));
}

$pageTitle = 'Edit Lot';
$pageDescription = 'Update lot details, claimants, GAD markers, and supporting documents.';
$lotFormAction = app_url('/lots/edit.php?id=' . $id);
$lotFormValues = $lot;
$lotSubmitLabel = 'Update Lot';
$lotShowBack = true;

require_once __DIR__ . '/../includes/header.php';
?>
<section class="panel">
    <h2 class="panel-title">Edit Lot Record</h2>
    <?php require __DIR__ . '/form.php'; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
