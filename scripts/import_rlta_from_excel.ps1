param(
    [string]$WorkbookPath = "C:\Users\besin\Downloads\RLTA-MATANAO.xlsx",
    [string]$MySqlExe = "C:\xampp\mysql\bin\mysql.exe",
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3306,
    [string]$Username = "root",
    [string]$Password = "root",
    [string]$Database = "land inventory"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Escape-SqlValue {
    param([AllowNull()][string]$Value)

    if ($null -eq $Value) {
        return "NULL"
    }

    $trimmed = $Value.Trim()
    if ($trimmed -eq "") {
        return "NULL"
    }

    $escaped = $trimmed.Replace("\", "\\").Replace("'", "''")
    return "'" + $escaped + "'"
}

function Parse-AreaValue {
    param([string]$Text)

    if ([string]::IsNullOrWhiteSpace($Text)) {
        return $null
    }

    $normalized = ($Text -replace ",", "").Trim()
    $value = 0.0
    if ([double]::TryParse($normalized, [ref]$value)) {
        return [Math]::Round($value, 2)
    }

    return $null
}

function Get-CellText {
    param($Worksheet, [int]$Row, [int]$Column)

    if ($Column -le 0) {
        return ""
    }

    return ([string]$Worksheet.Cells.Item($Row, $Column).Text).Trim()
}

function Get-HeaderRow {
    param($Worksheet, [int]$MaxRows, [int]$MaxColumns)

    for ($row = 1; $row -le [Math]::Min($MaxRows, 20); $row++) {
        for ($column = 1; $column -le $MaxColumns; $column++) {
            if ((Get-CellText $Worksheet $row $column) -eq "LOT NO.") {
                return $row
            }
        }
    }

    return 0
}

function Get-HeaderMap {
    param($Worksheet, [int]$HeaderRow, [int]$MaxColumns)

    $map = @{}
    for ($column = 1; $column -le $MaxColumns; $column++) {
        $text = Get-CellText $Worksheet $HeaderRow $column
        if ($text -ne "") {
            $map[$text] = $column
        }
    }

    return $map
}

function Get-CaseReference {
    param($Worksheet, [int]$Row, [int]$ColumnsToScan, [string]$CurrentCase)

    for ($column = 1; $column -le [Math]::Min($ColumnsToScan, 5); $column++) {
        $text = Get-CellText $Worksheet $Row $column
        if ($text -match '^(SWO|GSS|GSD|CSD|PSD|PLS)[- ]?[A-Z0-9-]+$') {
            return $text
        }
    }

    return $CurrentCase
}

function Get-ImportStatus {
    param(
        $Worksheet,
        [int]$Row,
        [int]$StatusBaseColumn,
        [string]$DominantUse,
        [string]$Remarks
    )

    $combined = (([string]$DominantUse) + " " + ([string]$Remarks)).ToUpperInvariant()

    if ($combined -match "APPLIED") {
        return "Applied"
    }

    if ($combined -match "CONFLICT|CLARIFY") {
        return "Conflict"
    }

    if ($combined -match "TITLED") {
        return "Titled"
    }

    if ($StatusBaseColumn -gt 0) {
        $titledMarks = @()
        $untitledMarks = @()
        for ($offset = 0; $offset -le 2; $offset++) {
            $titledMarks += Get-CellText $Worksheet $Row ($StatusBaseColumn + $offset)
            $untitledMarks += Get-CellText $Worksheet $Row ($StatusBaseColumn + 3 + $offset)
        }

        if (($titledMarks -join " ") -match '[/Xx]') {
            return "Titled"
        }

        if (($untitledMarks -join " ") -match '[/Xx]') {
            return "Unapplied"
        }
    }

    return "Unapplied"
}

if (-not (Test-Path $WorkbookPath)) {
    throw "Workbook not found: $WorkbookPath"
}

if (-not (Test-Path $MySqlExe)) {
    throw "mysql.exe not found: $MySqlExe"
}

$excel = New-Object -ComObject Excel.Application
$excel.Visible = $false
$excel.DisplayAlerts = $false
$workbook = $excel.Workbooks.Open($WorkbookPath)

$barangayTotals = @{}
$lotStatements = New-Object System.Collections.Generic.List[string]

try {
    foreach ($worksheet in $workbook.Worksheets) {
        if ($worksheet.Name -eq "Sheet3") {
            continue
        }

        $barangayName = $worksheet.Name.Trim()
        $usedRows = $worksheet.UsedRange.Rows.Count
        $usedColumns = $worksheet.UsedRange.Columns.Count
        $headerRow = Get-HeaderRow -Worksheet $worksheet -MaxRows $usedRows -MaxColumns $usedColumns

        if ($headerRow -eq 0) {
            continue
        }

        $headerMap = Get-HeaderMap -Worksheet $worksheet -HeaderRow $headerRow -MaxColumns $usedColumns
        $lotNoColumn = if ($headerMap.ContainsKey("LOT NO.")) { [int]$headerMap["LOT NO."] } else { 0 }
        $surveyClaimantColumn = if ($headerMap.ContainsKey("SURVEY CLAIMANT")) { [int]$headerMap["SURVEY CLAIMANT"] } else { 0 }
        $taxDeclarantColumn = if ($headerMap.ContainsKey("Tax Declarant")) { [int]$headerMap["Tax Declarant"] } else { 0 }
        $areaColumn = if ($headerMap.ContainsKey("AREA (SQM)")) { [int]$headerMap["AREA (SQM)"] } else { 0 }
        $currentClaimantColumn = if ($headerMap.ContainsKey("CURRENT CLAIMANT")) { [int]$headerMap["CURRENT CLAIMANT"] } else { 0 }
        $currentAddressColumn = if ($headerMap.ContainsKey("COMPLETE ADDRESS OF THE CURRENT CLAIMANT")) { [int]$headerMap["COMPLETE ADDRESS OF THE CURRENT CLAIMANT"] } else { 0 }
        $representativeColumn = if ($headerMap.ContainsKey("REPRESENTATIVE")) { [int]$headerMap["REPRESENTATIVE"] } else { 0 }
        $representativeAddressColumn = if ($headerMap.ContainsKey("COMPLETE ADDRESS OF REPRESENTATIVE")) { [int]$headerMap["COMPLETE ADDRESS OF REPRESENTATIVE"] } else { 0 }
        $statusBaseColumn = if ($headerMap.ContainsKey("LOT STATUS")) { [int]$headerMap["LOT STATUS"] } else { 0 }
        $supportingDocsColumn = if ($headerMap.ContainsKey("SUPPORTING DOCS")) { [int]$headerMap["SUPPORTING DOCS"] } else { 0 }
        $subdivisionColumn = if ($headerMap.ContainsKey("SUBDIVISION")) { [int]$headerMap["SUBDIVISION"] } else { 0 }
        $approvedSurveyPlanColumn = if ($headerMap.ContainsKey("APPROVED SURVEY PLAN")) { [int]$headerMap["APPROVED SURVEY PLAN"] } else { 0 }
        $landCaseColumn = if ($headerMap.ContainsKey("LAND CASE")) { [int]$headerMap["LAND CASE"] } else { 0 }
        $titlingInterestColumn = if ($headerMap.ContainsKey("TITLING INTEREST")) { [int]$headerMap["TITLING INTEREST"] } else { 0 }
        $modeOfAcquisitionColumn = if ($headerMap.ContainsKey("MODE OF ACQUISITION")) { [int]$headerMap["MODE OF ACQUISITION"] } else { 0 }
        $dominantUseColumn = if ($headerMap.ContainsKey("DOMINANT USE")) { [int]$headerMap["DOMINANT USE"] } else { 0 }
        $remarksColumn = if ($headerMap.ContainsKey("REMARKS")) { [int]$headerMap["REMARKS"] } else { 0 }

        $currentCaseReference = ""

        for ($row = $headerRow + 1; $row -le $usedRows; $row++) {
            $currentCaseReference = Get-CaseReference -Worksheet $worksheet -Row $row -ColumnsToScan $usedColumns -CurrentCase $currentCaseReference

            $lotNo = Get-CellText $worksheet $row $lotNoColumn
            $areaValue = Parse-AreaValue (Get-CellText $worksheet $row $areaColumn)

            if ([string]::IsNullOrWhiteSpace($lotNo) -or $null -eq $areaValue) {
                continue
            }

            if ($lotNo -match '^(TOTAL|NO\.|LOT NO\.|CASE)$') {
                continue
            }

            $surveyClaimant = Get-CellText $worksheet $row $surveyClaimantColumn
            $taxDeclarant = Get-CellText $worksheet $row $taxDeclarantColumn
            $currentClaimant = Get-CellText $worksheet $row $currentClaimantColumn
            $currentAddress = Get-CellText $worksheet $row $currentAddressColumn
            $representative = Get-CellText $worksheet $row $representativeColumn
            $representativeAddress = Get-CellText $worksheet $row $representativeAddressColumn
            $supportingDocs = Get-CellText $worksheet $row $supportingDocsColumn
            $subdivision = Get-CellText $worksheet $row $subdivisionColumn
            $approvedSurveyPlan = Get-CellText $worksheet $row $approvedSurveyPlanColumn
            $landCase = Get-CellText $worksheet $row $landCaseColumn
            $titlingInterest = Get-CellText $worksheet $row $titlingInterestColumn
            $modeOfAcquisition = Get-CellText $worksheet $row $modeOfAcquisitionColumn
            $dominantUse = Get-CellText $worksheet $row $dominantUseColumn
            $remarks = Get-CellText $worksheet $row $remarksColumn

            $sexMarks = @()
            if ($statusBaseColumn -gt 0) {
                $maleMark = Get-CellText $worksheet $row ($statusBaseColumn - 5)
                $femaleMark = Get-CellText $worksheet $row ($statusBaseColumn - 4)
                if ($maleMark -match '[/Xx]') { $sexMarks += "M" }
                if ($femaleMark -match '[/Xx]') { $sexMarks += "F" }
            }
            $claimantSex = if ($sexMarks.Count -gt 0) { $sexMarks -join "/" } else { "" }

            $status = Get-ImportStatus -Worksheet $worksheet -Row $row -StatusBaseColumn $statusBaseColumn -DominantUse $dominantUse -Remarks $remarks

            if (-not $barangayTotals.ContainsKey($barangayName)) {
                $barangayTotals[$barangayName] = 0.0
            }
            $barangayTotals[$barangayName] += $areaValue

            $lotStatements.Add(
                "INSERT INTO lots (lot_no, survey_no, barangay_id, area_sqm, status, survey_claimant, tax_declarant, current_claimant, claimant_sex, current_address, representative, representative_address, supporting_docs, subdivision, approved_survey_plan, land_case, titling_interest, mode_of_acquisition, dominant_use, remarks, source_sheet, case_reference, sheet_row) " +
                "SELECT " +
                (Escape-SqlValue $lotNo) + ", " +
                (Escape-SqlValue $currentCaseReference) + ", " +
                "b.id, " +
                ([string]::Format([System.Globalization.CultureInfo]::InvariantCulture, "{0:0.00}", $areaValue)) + ", " +
                (Escape-SqlValue $status) + ", " +
                (Escape-SqlValue $surveyClaimant) + ", " +
                (Escape-SqlValue $taxDeclarant) + ", " +
                (Escape-SqlValue $currentClaimant) + ", " +
                (Escape-SqlValue $claimantSex) + ", " +
                (Escape-SqlValue $currentAddress) + ", " +
                (Escape-SqlValue $representative) + ", " +
                (Escape-SqlValue $representativeAddress) + ", " +
                (Escape-SqlValue $supportingDocs) + ", " +
                (Escape-SqlValue $subdivision) + ", " +
                (Escape-SqlValue $approvedSurveyPlan) + ", " +
                (Escape-SqlValue $landCase) + ", " +
                (Escape-SqlValue $titlingInterest) + ", " +
                (Escape-SqlValue $modeOfAcquisition) + ", " +
                (Escape-SqlValue $dominantUse) + ", " +
                (Escape-SqlValue $remarks) + ", " +
                (Escape-SqlValue $barangayName) + ", " +
                (Escape-SqlValue $currentCaseReference) + ", " +
                $row +
                " FROM barangay b WHERE b.name = " + (Escape-SqlValue $barangayName) + ";"
            ) | Out-Null
        }
    }
}
finally {
    $workbook.Close($false)
    $excel.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($workbook) | Out-Null
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($excel) | Out-Null
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}

$dbNameSql = "``$Database``"

$sqlStatements = New-Object System.Collections.Generic.List[string]
$sqlStatements.Add("CREATE DATABASE IF NOT EXISTS $dbNameSql;") | Out-Null
$sqlStatements.Add("USE $dbNameSql;") | Out-Null
$sqlStatements.Add("DROP TABLE IF EXISTS land_use;") | Out-Null
$sqlStatements.Add("DROP TABLE IF EXISTS lots;") | Out-Null
$sqlStatements.Add("DROP TABLE IF EXISTS barangay;") | Out-Null
$sqlStatements.Add(@"
CREATE TABLE barangay (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    total_area_sqm DOUBLE NOT NULL DEFAULT 0
);
"@) | Out-Null
$sqlStatements.Add(@"
CREATE TABLE lots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lot_no VARCHAR(100) NOT NULL,
    survey_no VARCHAR(100) NULL,
    barangay_id INT NOT NULL,
    area_sqm DOUBLE NOT NULL DEFAULT 0,
    status ENUM('Unapplied', 'Applied', 'Titled', 'Conflict') NOT NULL DEFAULT 'Unapplied',
    survey_claimant VARCHAR(255) NULL,
    tax_declarant VARCHAR(255) NULL,
    current_claimant VARCHAR(255) NULL,
    claimant_sex VARCHAR(20) NULL,
    current_address VARCHAR(255) NULL,
    representative VARCHAR(255) NULL,
    representative_address VARCHAR(255) NULL,
    supporting_docs VARCHAR(255) NULL,
    subdivision VARCHAR(50) NULL,
    approved_survey_plan VARCHAR(255) NULL,
    land_case VARCHAR(50) NULL,
    titling_interest VARCHAR(255) NULL,
    mode_of_acquisition VARCHAR(255) NULL,
    dominant_use VARCHAR(100) NULL,
    remarks TEXT NULL,
    source_sheet VARCHAR(100) NULL,
    case_reference VARCHAR(100) NULL,
    sheet_row INT NULL,
    CONSTRAINT fk_lots_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangay (id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
"@) | Out-Null
$sqlStatements.Add(@"
CREATE TABLE land_use (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_id INT NOT NULL,
    type ENUM('Road', 'Alley', 'Irrigation', 'Canal', 'Church', 'School', 'Plaza') NOT NULL,
    area_sqm DOUBLE NOT NULL DEFAULT 0,
    CONSTRAINT fk_land_use_barangay
        FOREIGN KEY (barangay_id) REFERENCES barangay (id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
"@) | Out-Null

foreach ($barangayName in ($barangayTotals.Keys | Sort-Object)) {
    $totalArea = [Math]::Round([double]$barangayTotals[$barangayName], 2)
    $sqlStatements.Add(
        "INSERT INTO barangay (name, total_area_sqm) VALUES (" +
        (Escape-SqlValue $barangayName) + ", " +
        ([string]::Format([System.Globalization.CultureInfo]::InvariantCulture, "{0:0.00}", $totalArea)) +
        ");"
    ) | Out-Null
}

foreach ($statement in $lotStatements) {
    $sqlStatements.Add($statement) | Out-Null
}

$tempSqlFile = Join-Path $env:TEMP "land_inventory_rlta_import.sql"
[System.IO.File]::WriteAllLines($tempSqlFile, $sqlStatements)

Get-Content $tempSqlFile | & $MySqlExe -h $DbHost -P $Port -u $Username "-p$Password"

Write-Output ("Imported barangays: " + $barangayTotals.Count)
Write-Output ("Imported lots: " + $lotStatements.Count)
Write-Output ("SQL file: " + $tempSqlFile)
