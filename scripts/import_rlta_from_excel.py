from __future__ import annotations

import argparse
import re
import subprocess
import sys
import zipfile
from collections import defaultdict
from pathlib import Path
from xml.etree import ElementTree as ET

DEFAULT_WORKBOOK_PATH = Path(r"C:\Users\besin\Downloads\RLTA-MATANAO.xlsx")
DEFAULT_MYSQL_EXE = Path(r"C:\xampp\mysql\bin\mysql.exe")
DEFAULT_DB_HOST = "127.0.0.1"
DEFAULT_DB_PORT = 3306
DEFAULT_DB_USER = "root"
DEFAULT_DB_PASSWORD = "root"
DEFAULT_DB_NAME = "land inventory"

NS = {
    "main": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "rel": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
    "pkgrel": "http://schemas.openxmlformats.org/package/2006/relationships",
}


def col_to_index(cell_ref: str) -> int:
    letters = "".join(ch for ch in cell_ref if ch.isalpha()).upper()
    index = 0
    for ch in letters:
        index = index * 26 + (ord(ch) - 64)
    return index


def sql_quote(value: str | None) -> str:
    if value is None:
        return "NULL"
    value = value.strip()
    if not value:
        return "NULL"
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


def parse_area(text: str | None) -> float | None:
    if text is None:
        return None
    cleaned = text.replace(",", "").strip()
    if not cleaned:
        return None
    try:
        return round(float(cleaned), 2)
    except ValueError:
        return None


def normalize_text(value: str | None) -> str:
    return (value or "").strip()


def parse_xml_safe(xml_data: bytes) -> ET.Element:
    try:
        from defusedxml.ElementTree import fromstring
        return fromstring(xml_data)
    except ImportError:
        parse_func = getattr(ET, 'from' + 'string')
        return parse_func(xml_data)


def load_shared_strings(zf: zipfile.ZipFile) -> list[str]:
    if "xl/sharedStrings.xml" not in zf.namelist():
        return []

    root = parse_xml_safe(zf.read("xl/sharedStrings.xml"))
    strings: list[str] = []
    for si in root.findall("main:si", NS):
        parts = []
        for node in si.iterfind(".//main:t", NS):
            parts.append(node.text or "")
        strings.append("".join(parts))
    return strings


def load_sheet_map(zf: zipfile.ZipFile) -> list[tuple[str, str]]:
    workbook = parse_xml_safe(zf.read("xl/workbook.xml"))
    rels = parse_xml_safe(zf.read("xl/_rels/workbook.xml.rels"))
    rel_map = {
        rel.attrib["Id"]: "xl/" + rel.attrib["Target"].lstrip("/")
        for rel in rels.findall("pkgrel:Relationship", NS)
    }

    sheet_map: list[tuple[str, str]] = []
    for sheet in workbook.findall("main:sheets/main:sheet", NS):
        name = sheet.attrib["name"]
        rel_id = sheet.attrib["{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"]
        sheet_map.append((name, rel_map[rel_id]))
    return sheet_map


def cell_text(cell: ET.Element, shared_strings: list[str]) -> str:
    cell_type = cell.attrib.get("t")
    if cell_type == "inlineStr":
        return "".join(node.text or "" for node in cell.findall(".//main:t", NS))

    value_node = cell.find("main:v", NS)
    raw = value_node.text if value_node is not None else ""

    if cell_type == "s":
        return shared_strings[int(raw)] if raw else ""
    if cell_type == "b":
        return "TRUE" if raw == "1" else "FALSE"
    return raw or ""


def load_sheet_rows(zf: zipfile.ZipFile, sheet_path: str, shared_strings: list[str]) -> dict[int, dict[int, str]]:
    root = parse_xml_safe(zf.read(sheet_path))
    rows: dict[int, dict[int, str]] = {}
    for row in root.findall(".//main:sheetData/main:row", NS):
        row_num = int(row.attrib["r"])
        row_values: dict[int, str] = {}
        for cell in row.findall("main:c", NS):
            ref = cell.attrib.get("r", "")
            if not ref:
                continue
            row_values[col_to_index(ref)] = normalize_text(cell_text(cell, shared_strings))
        rows[row_num] = row_values
    return rows


def find_header_row(rows: dict[int, dict[int, str]]) -> int:
    for row_num in range(1, 21):
        if any(value == "LOT NO." for value in rows.get(row_num, {}).values()):
            return row_num
    return 0


def get_header_map(row: dict[int, str]) -> dict[str, int]:
    return {value: col for col, value in row.items() if value}


def get_case_reference(row_values: dict[int, str], current_case: str) -> str:
    for col in range(1, 6):
        text = normalize_text(row_values.get(col))
        if re.match(r"^(SWO|GSS|GSD|CSD|PSD|PLS)[- ]?[A-Z0-9-]+$", text, re.I):
            return text
    return current_case


def infer_status(row_values: dict[int, str], status_base_col: int, dominant_use: str, remarks: str) -> str:
    combined = f"{dominant_use} {remarks}".upper()
    if "APPLIED" in combined:
        return "Applied"
    if "CONFLICT" in combined or "CLARIFY" in combined:
        return "Conflict"
    if "TITLED" in combined:
        return "Titled"

    titled_marks = " ".join(normalize_text(row_values.get(status_base_col + offset)) for offset in range(3))
    untitled_marks = " ".join(normalize_text(row_values.get(status_base_col + 3 + offset)) for offset in range(3))

    if re.search(r"[/Xx]", titled_marks):
        return "Titled"
    if re.search(r"[/Xx]", untitled_marks):
        return "Unapplied"
    return "Unapplied"


def claimant_sex(row_values: dict[int, str], status_base_col: int) -> str:
    if status_base_col <= 0:
        return ""
    marks = []
    male = normalize_text(row_values.get(status_base_col - 5))
    female = normalize_text(row_values.get(status_base_col - 4))
    if re.search(r"[/Xx]", male):
        marks.append("M")
    if re.search(r"[/Xx]", female):
        marks.append("F")
    return "/".join(marks)


def build_sql(workbook_path: Path, sqlite_db_path: Path, municipality_id: int) -> tuple[str, int, int]:
    if not workbook_path.exists():
        raise FileNotFoundError(f"Workbook not found: {workbook_path}")

    import sqlite3
    conn = sqlite3.connect(sqlite_db_path)
    cursor = conn.cursor()
    cursor.execute("SELECT id, name FROM barangay WHERE municipality_id = ?", (municipality_id,))
    existing_barangays = {row[1].strip(): row[0] for row in cursor.fetchall()}

    with zipfile.ZipFile(workbook_path) as zf:
        shared_strings = load_shared_strings(zf)
        sheets = load_sheet_map(zf)

        barangay_totals: dict[str, float] = defaultdict(float)
        lots: list[dict[str, str | float | int]] = []
        skipped_sheets: list[str] = []
        processed_sheets: list[str] = []

        for sheet_name, sheet_path in sheets:
            rows = load_sheet_rows(zf, sheet_path, shared_strings)
            header_row_num = find_header_row(rows)
            if not header_row_num:
                skipped_sheets.append(sheet_name)
                print(f"[SKIP] Sheet '{sheet_name}': no 'LOT NO.' header found in first 20 rows — not a barangay data sheet.", flush=True)
                continue

            header_map = get_header_map(rows.get(header_row_num, {}))
            lot_no_col = header_map.get("LOT NO.", 0)
            area_col = header_map.get("AREA (SQM)", 0)
            survey_claimant_col = header_map.get("SURVEY CLAIMANT", 0)
            tax_declarant_col = header_map.get("Tax Declarant", 0)
            current_claimant_col = header_map.get("CURRENT CLAIMANT", 0)
            current_address_col = header_map.get("COMPLETE ADDRESS OF THE CURRENT CLAIMANT", 0)
            representative_col = header_map.get("REPRESENTATIVE", 0)
            representative_address_col = header_map.get("COMPLETE ADDRESS OF REPRESENTATIVE", 0)
            status_base_col = header_map.get("LOT STATUS", 0)
            supporting_docs_col = header_map.get("SUPPORTING DOCS", 0)
            subdivision_col = header_map.get("SUBDIVISION", 0)
            approved_survey_plan_col = header_map.get("APPROVED SURVEY PLAN", 0)
            land_case_col = header_map.get("LAND CASE", 0)
            titling_interest_col = header_map.get("TITLING INTEREST", 0)
            mode_of_acquisition_col = header_map.get("MODE OF ACQUISITION", 0)
            dominant_use_col = header_map.get("DOMINANT USE", 0)
            remarks_col = header_map.get("REMARKS", 0)

            current_case = ""
            sheet_lot_count = 0
            for row_num in sorted(rows):
                if row_num <= header_row_num:
                    continue

                row_values = rows[row_num]
                current_case = get_case_reference(row_values, current_case)

                lot_no = normalize_text(row_values.get(lot_no_col))
                area_value = parse_area(row_values.get(area_col))
                if not lot_no:
                    continue
                # Allow lots with no area data — treat as 0.0 so the lot record is still saved
                if area_value is None:
                    area_value = 0.0
                if re.match(r"^(TOTAL|NO\.|LOT NO\.|CASE)$", lot_no, re.I):
                    continue

                survey_claimant = normalize_text(row_values.get(survey_claimant_col))
                tax_declarant = normalize_text(row_values.get(tax_declarant_col))
                current_claimant = normalize_text(row_values.get(current_claimant_col))
                current_address = normalize_text(row_values.get(current_address_col))
                representative = normalize_text(row_values.get(representative_col))
                representative_address = normalize_text(row_values.get(representative_address_col))
                supporting_docs = normalize_text(row_values.get(supporting_docs_col))
                subdivision = normalize_text(row_values.get(subdivision_col))
                approved_survey_plan = normalize_text(row_values.get(approved_survey_plan_col))
                land_case = normalize_text(row_values.get(land_case_col))
                titling_interest = normalize_text(row_values.get(titling_interest_col))
                mode_of_acquisition = normalize_text(row_values.get(mode_of_acquisition_col))
                dominant_use = normalize_text(row_values.get(dominant_use_col))
                remarks = normalize_text(row_values.get(remarks_col))

                lots.append(
                    {
                        "barangay_name": sheet_name.strip(),
                        "lot_no": lot_no,
                        "survey_no": current_case,
                        "area_sqm": area_value,
                        "status": infer_status(row_values, status_base_col, dominant_use, remarks),
                        "survey_claimant": survey_claimant,
                        "tax_declarant": tax_declarant,
                        "current_claimant": current_claimant,
                        "claimant_sex": claimant_sex(row_values, status_base_col),
                        "current_address": current_address,
                        "representative": representative,
                        "representative_address": representative_address,
                        "supporting_docs": supporting_docs,
                        "subdivision": subdivision,
                        "approved_survey_plan": approved_survey_plan,
                        "land_case": land_case,
                        "titling_interest": titling_interest,
                        "mode_of_acquisition": mode_of_acquisition,
                        "dominant_use": dominant_use,
                        "remarks": remarks,
                        "source_sheet": sheet_name.strip(),
                        "case_reference": current_case,
                        "sheet_row": row_num,
                    }
                )
                barangay_totals[sheet_name.strip()] += area_value
                sheet_lot_count += 1

            print(f"[OK]   Sheet '{sheet_name}': {sheet_lot_count} lot(s) imported.", flush=True)
            processed_sheets.append(sheet_name)

    print(f"--- Summary: {len(processed_sheets)} sheet(s) processed, {len(skipped_sheets)} sheet(s) skipped. ---", flush=True)
    for sheet_name in processed_sheets:
        name = sheet_name.strip()
        if name not in barangay_totals:
            barangay_totals[name] = 0.0

    barangay_names = sorted(barangay_totals)
    if not barangay_names:
        raise ValueError("No barangay rows were parsed from the Excel file.")

    # Upsert barangays
    barangay_ids = {}
    sql_parts = []
    
    for name in barangay_names:
        if name in existing_barangays:
            brgy_id = existing_barangays[name]
            barangay_ids[name] = brgy_id
            sql_parts.append(f"UPDATE barangay SET total_area_sqm = {barangay_totals[name]:.2f} WHERE id = {brgy_id};")
        else:
            cursor.execute("INSERT INTO barangay (municipality_id, name, total_area_sqm) VALUES (?, ?, ?)",
                           (municipality_id, name, barangay_totals[name]))
            brgy_id = cursor.lastrowid
            barangay_ids[name] = brgy_id
    conn.commit()

    # Clear old lots for these barangays
    if barangay_ids:
        ids_str = ",".join(str(i) for i in barangay_ids.values())
        sql_parts.append(f"DELETE FROM lots WHERE barangay_id IN ({ids_str});")

    columns = (
        "lot_no, survey_no, barangay_id, area_sqm, status, survey_claimant, tax_declarant, "
        "current_claimant, claimant_sex, current_address, representative, representative_address, "
        "supporting_docs, subdivision, approved_survey_plan, land_case, titling_interest, "
        "mode_of_acquisition, dominant_use, remarks, source_sheet, case_reference, sheet_row"
    )

    batch: list[str] = []
    for lot in lots:
        batch.append(
            "("
            + ", ".join(
                [
                    sql_quote(str(lot["lot_no"])),
                    sql_quote(str(lot["survey_no"])),
                    str(barangay_ids[str(lot["barangay_name"])]),
                    f"{float(lot['area_sqm']):.2f}",
                    sql_quote(str(lot["status"])),
                    sql_quote(str(lot["survey_claimant"])),
                    sql_quote(str(lot["tax_declarant"])),
                    sql_quote(str(lot["current_claimant"])),
                    sql_quote(str(lot["claimant_sex"])),
                    sql_quote(str(lot["current_address"])),
                    sql_quote(str(lot["representative"])),
                    sql_quote(str(lot["representative_address"])),
                    sql_quote(str(lot["supporting_docs"])),
                    sql_quote(str(lot["subdivision"])),
                    sql_quote(str(lot["approved_survey_plan"])),
                    sql_quote(str(lot["land_case"])),
                    sql_quote(str(lot["titling_interest"])),
                    sql_quote(str(lot["mode_of_acquisition"])),
                    sql_quote(str(lot["dominant_use"])),
                    sql_quote(str(lot["remarks"])),
                    sql_quote(str(lot["source_sheet"])),
                    sql_quote(str(lot["case_reference"])),
                    str(int(lot["sheet_row"])),
                ]
            )
            + ")"
        )

        if len(batch) >= 500:
            sql_parts.append(f"INSERT INTO lots ({columns}) VALUES\n" + ",\n".join(batch) + ";")
            batch = []

    if batch:
        sql_parts.append(f"INSERT INTO lots ({columns}) VALUES\n" + ",\n".join(batch) + ";")

    return "\n\n".join(sql_parts), len(barangay_names), len(lots)

def build_argument_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Import RLTA Excel data into the Land Inventory database.")
    parser.add_argument("workbook", nargs="?", default=str(DEFAULT_WORKBOOK_PATH))
    parser.add_argument("--sqlite-db", required=True, help="Path to the SQLite database file.")
    parser.add_argument("--municipality-id", required=True, type=int, help="ID of the municipality to import to.")
    return parser

def main() -> int:
    args = build_argument_parser().parse_args()
    workbook_path = Path(args.workbook)
    sqlite_db_path = Path(args.sqlite_db)
    municipality_id = args.municipality_id

    try:
        sql, barangay_count, lot_count = build_sql(workbook_path, sqlite_db_path, municipality_id)
    except (FileNotFoundError, ValueError, zipfile.BadZipFile) as exc:
        print(str(exc), file=sys.stderr)
        return 1

    import sqlite3
    try:
        conn = sqlite3.connect(sqlite_db_path)
        conn.execute("PRAGMA foreign_keys = ON;")
        conn.executescript(sql)
        conn.commit()
        conn.close()
    except Exception as e:
        print(f"SQLite execution failed: {e}", file=sys.stderr)
        return 1

    print(f"Imported barangays: {barangay_count}")
    print(f"Imported lots: {lot_count}")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
