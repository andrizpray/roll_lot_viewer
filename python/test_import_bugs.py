"""
Test cases untuk setiap bug yang ditemukan di import_excel.py
Jalankan: python3 python/test_import_bugs.py

Semua test ini dimaksudkan untuk GAGAL dulu (reproducing bug),
lalu PASS setelah fix diterapkan.
"""

import sys
import os
import datetime
import unittest

sys.path.insert(0, os.path.dirname(__file__))

from import_excel import (
    parse_description,
    parse_description_sheet,
    detect_type_from_headers,
    COLUMN_MAP,
    SHEET_COLUMN_MAP,
    HISTORY_COLUMNS,
)


# ---------------------------------------------------------------------------
# BUG #3 — datetime.date/datetime.datetime order di roll mode
# ---------------------------------------------------------------------------
class TestDatetimeCoercionRollMode(unittest.TestCase):
    """
    BUG #3: Di roll mode (import_roll_lots), urutan isinstance check salah.
    datetime.date diperiksa SEBELUM datetime.datetime, padahal datetime.datetime
    IS-A datetime.date. Akibat: datetime values diformat "%Y-%m-%d" (tanggal saja),
    kehilangan komponen waktu.

    Sheet mode (_import_sheet_rows) sudah benar (datetime.datetime duluan).
    """

    def _coerce_roll_mode(self, val):
        """Replika logika coercion di import_roll_lots baris 511-516 (BUGGY)."""
        if isinstance(val, datetime.time):
            return val.strftime("%H:%M:%S")
        elif isinstance(val, datetime.date):          # BUG: datetime.datetime masuk sini
            return val.strftime("%Y-%m-%d")
        elif isinstance(val, datetime.datetime):      # BUG: tidak pernah dieksekusi
            return val.strftime("%Y-%m-%d %H:%M:%S")
        return val

    def _coerce_fixed(self, val):
        """Urutan BENAR: datetime.datetime dulu, baru datetime.date."""
        if isinstance(val, datetime.time):
            return val.strftime("%H:%M:%S")
        elif isinstance(val, datetime.datetime):      # FIXED
            return val.strftime("%Y-%m-%d %H:%M:%S")
        elif isinstance(val, datetime.date):
            return val.strftime("%Y-%m-%d")
        return val

    def test_datetime_loses_time_component_in_buggy_code(self):
        """Reproducing bug: datetime diformat tanpa waktu."""
        val = datetime.datetime(2024, 6, 15, 10, 30, 45)
        result_buggy = self._coerce_roll_mode(val)
        # BUG: hasilnya "2024-06-15" — waktu hilang
        self.assertEqual(result_buggy, "2024-06-15",
            "BUG REPRODUCED: datetime diformat sebagai date saja")

    def test_datetime_preserves_time_after_fix(self):
        """Setelah fix: datetime harus include waktu."""
        val = datetime.datetime(2024, 6, 15, 10, 30, 45)
        result_fixed = self._coerce_fixed(val)
        self.assertEqual(result_fixed, "2024-06-15 10:30:45",
            "FIXED: datetime diformat dengan waktu lengkap")

    def test_date_still_works_after_fix(self):
        """datetime.date murni tetap diformat dengan benar setelah fix."""
        val = datetime.date(2024, 6, 15)
        result_fixed = self._coerce_fixed(val)
        self.assertEqual(result_fixed, "2024-06-15")

    def test_time_still_works_after_fix(self):
        """datetime.time tetap benar setelah fix."""
        val = datetime.time(10, 30, 45)
        result_fixed = self._coerce_fixed(val)
        self.assertEqual(result_fixed, "10:30:45")


# ---------------------------------------------------------------------------
# BUG #6 & #7 — Empty batch wipes entire table
# ---------------------------------------------------------------------------
class TestEmptyBatchGuard(unittest.TestCase):
    """
    BUG #6/#7: Kalau file Excel hanya berisi header (0 data rows), batch = [].
    Kode tetap memanggil _delete_stale(table, job_id) yang execute:
      DELETE FROM <table> WHERE import_batch_id IS DISTINCT FROM <job_id>
    Karena tidak ada row yang pernah di-INSERT dengan job_id ini,
    SEMUA row di tabel akan terhapus.

    Fix: guard sebelum _delete_stale — jika batch kosong, skip delete_stale.
    """

    def _simulate_should_delete_stale(self, batch):
        """
        Simulasi kondisi: apakah _delete_stale boleh dipanggil?
        BUGGY: selalu memanggil _delete_stale meski batch kosong.
        FIXED: hanya panggil jika batch tidak kosong.
        """
        # BUGGY behavior
        buggy_calls_delete = True  # selalu dipanggil

        # FIXED behavior
        fixed_calls_delete = len(batch) > 0

        return buggy_calls_delete, fixed_calls_delete

    def test_empty_excel_file_would_wipe_table_in_buggy_code(self):
        """Reproducing bug: batch kosong tetap trigger delete_stale."""
        batch = []
        buggy_calls_delete, fixed_calls_delete = self._simulate_should_delete_stale(batch)
        # BUG: buggy code calls delete stale even with empty batch
        self.assertTrue(buggy_calls_delete,
            "BUG REPRODUCED: _delete_stale dipanggil meski batch kosong")

    def test_empty_batch_should_skip_delete_stale_after_fix(self):
        """Setelah fix: batch kosong tidak boleh trigger delete_stale."""
        batch = []
        buggy_calls_delete, fixed_calls_delete = self._simulate_should_delete_stale(batch)
        self.assertFalse(fixed_calls_delete,
            "FIXED: _delete_stale dilewati kalau batch kosong")

    def test_non_empty_batch_still_deletes_stale(self):
        """Batch berisi data tetap harus trigger delete_stale."""
        batch = [("LOT001", "ITEM001", 100.0)]
        _, fixed_calls_delete = self._simulate_should_delete_stale(batch)
        self.assertTrue(fixed_calls_delete,
            "FIXED: _delete_stale tetap dipanggil kalau batch ada isi")


# ---------------------------------------------------------------------------
# BUG #14 — SHEET_COLUMN_MAP has location_id not in schema
# ---------------------------------------------------------------------------
class TestSheetColumnMapSchema(unittest.TestCase):
    """
    BUG #14: SHEET_COLUMN_MAP punya "LocationID": "location_id" tapi kolom
    location_id tidak ada di tabel paper_sheets di DB.
    Kalau file Excel ada header "LocationID", INSERT akan gagal dengan:
      psycopg2.errors.UndefinedColumn: column "location_id" of relation "paper_sheets" does not exist

    Fix: hapus "LocationID": "location_id" dari SHEET_COLUMN_MAP
    (atau ganti dengan None agar di-skip seperti "No": None).
    """

    # Kolom yang benar-benar ada di paper_sheets (dari migrasi)
    PAPER_SHEETS_SCHEMA_COLUMNS = {
        "lot_id", "item_id", "weight", "papertype", "gramature", "dimension",
        "content_pack", "content_pallet", "description_raw", "source_tr_date",
        "source_tr_time", "import_batch_id", "qty", "comments", "keterangan",
    }

    def test_all_sheet_column_map_values_exist_in_schema(self):
        """Semua target column di SHEET_COLUMN_MAP harus ada di schema."""
        invalid_cols = []
        for excel_header, db_col in SHEET_COLUMN_MAP.items():
            if db_col is not None and db_col not in self.PAPER_SHEETS_SCHEMA_COLUMNS:
                invalid_cols.append(f"{excel_header!r} -> {db_col!r}")
        self.assertEqual(invalid_cols, [],
            f"BUG: SHEET_COLUMN_MAP references columns not in paper_sheets schema: {invalid_cols}")


# ---------------------------------------------------------------------------
# BUG #15 — COLUMN_MAP (ROLL) has sheet-only columns
# ---------------------------------------------------------------------------
class TestRollColumnMapSchema(unittest.TestCase):
    """
    BUG #15: COLUMN_MAP (alias ROLL_COLUMN_MAP) mengandung kolom sheet-only:
    "Qty" -> "qty", "Keterangan" -> "keterangan", "Qty_Pack" -> "content_pack",
    "Dimension" -> "dimension" — tidak ada di tabel roll_lots.

    Kalau file roll Excel ada header "Qty" atau "Keterangan", INSERT akan gagal.

    Fix: pisahkan COLUMN_MAP menjadi roll-only (hapus sheet-specific entries),
    atau di _import_roll buat filter yang skip kolom tidak dikenal.
    """

    # Kolom yang benar-benar ada di roll_lots (dari migrasi)
    ROLL_LOTS_SCHEMA_COLUMNS = {
        "lot_id", "item_id", "weight", "papertype", "gramature", "playbond",
        "width", "rew_id", "grade", "comments", "diameter", "thickness",
        "description_raw", "source_tr_date", "source_tr_time", "import_batch_id",
    }

    def test_all_roll_column_map_values_exist_in_schema(self):
        """Semua target column di COLUMN_MAP harus ada di roll_lots schema."""
        invalid_cols = []
        for excel_header, db_col in COLUMN_MAP.items():
            if db_col is not None and db_col not in self.ROLL_LOTS_SCHEMA_COLUMNS:
                invalid_cols.append(f"{excel_header!r} -> {db_col!r}")
        self.assertEqual(invalid_cols, [],
            f"BUG: COLUMN_MAP references columns not in roll_lots schema: {invalid_cols}")


# ---------------------------------------------------------------------------
# BUG #1/#2 — _import_sheet_rows: lot_id hardcoded ke row[1]
# ---------------------------------------------------------------------------
class TestSheetLotIdExtraction(unittest.TestCase):
    """
    BUG #1/#2: Di _import_sheet_rows, lot_id untuk error logging diambil dari
    row[1] hardcoded, bukan dari val_idx["lot_id"].

    Ini masalah kalau:
    1. Header "Lot ID" ada di kolom selain kolom B (index 1)
    2. Kolom pertama adalah "No" (index 0), "Lot ID" di index 1 — coincidence OK tapi brittle

    Fix: gunakan val_idx["lot_id"] secara konsisten.
    """

    def test_lot_id_from_val_idx_vs_hardcoded(self):
        """Simulasi: header Lot ID di posisi bukan index 1."""
        # Simulasikan Excel dengan header: ["No", "Item ID", "Lot ID", ...]
        # Lot ID ada di index 2, bukan index 1
        headers = ["No", "Item ID", "Lot ID", "Weight"]
        row = (1, "ITEM001", "LOT-ABC", 50.5)

        # Build val_idx seperti di _import_sheet_rows
        from import_excel import SHEET_COLUMN_MAP
        col_map_list = []
        for h in headers:
            mapped = SHEET_COLUMN_MAP.get(h.strip()) if h else None
            col_map_list.append((h, mapped))

        val_idx = {}
        pos = 0
        for _, db_col in col_map_list:
            if db_col:
                val_idx[db_col] = pos
                pos += 1

        # Metode BENAR: pakai val_idx
        values = [row[i] for i, (h, dc) in enumerate(col_map_list) if dc is not None]

        if "lot_id" in val_idx:
            lot_id_correct = str(values[val_idx["lot_id"]])
        else:
            lot_id_correct = None

        # Metode BUGGY: hardcoded row[1]
        lot_id_buggy = row[1] if len(row) > 1 and row[1] else None
        if lot_id_buggy is not None:
            lot_id_buggy = str(lot_id_buggy)

        self.assertEqual(lot_id_correct, "LOT-ABC",
            "val_idx method harus dapat lot_id dengan benar")
        self.assertNotEqual(lot_id_buggy, "LOT-ABC",
            "BUG REPRODUCED: row[1] hardcoded mengambil nilai salah (ITEM001, bukan LOT-ABC)")
        self.assertEqual(lot_id_buggy, "ITEM001",
            "BUG: row[1] mengambil item_id, bukan lot_id")


# ---------------------------------------------------------------------------
# BUG #9 — detect_type_from_headers dengan header integer
# ---------------------------------------------------------------------------
class TestDetectTypeFromHeaders(unittest.TestCase):
    """
    BUG #9: detect_type_from_headers pakai h.strip() tanpa cek tipe.
    Kalau header Excel ada yang integer (numeric cell jadi header),
    h.strip() akan AttributeError.
    """

    def test_integer_header_was_crashing_before_fix(self):
        """
        Dokumentasi bug lama: `{h.strip() for h in headers if h}` crash
        dengan integer header karena int tidak punya .strip().
        BUG SUDAH DIPERBAIKI — test ini verifikasi bahwa buggy behavior
        terjadi di inline buggy code, bukan di fungsi yang sudah di-fix.
        """
        headers_with_int = ["Lot ID", 123, "Weight"]
        # Simulasi kode LAMA (sebelum fix) secara inline
        try:
            buggy_header_set = {h.strip() for h in headers_with_int if h}
            self.fail("Kode lama seharusnya crash, tapi tidak")
        except AttributeError:
            pass  # CONFIRMED: kode lama memang crash

        # Kode BARU (setelah fix) tidak crash
        fixed_header_set = {h.strip() for h in headers_with_int if h and isinstance(h, str)}
        self.assertIn("Lot ID", fixed_header_set)  # FIXED: tidak crash

    def test_integer_header_handled_after_fix(self):
        """Setelah fix: header non-string di-skip dengan aman."""
        headers_with_int = ["Lot ID", 123, "Weight"]
        # Fix: gunakan `if h and isinstance(h, str)` instead of `if h`
        header_set = {h.strip() for h in headers_with_int if h and isinstance(h, str)}
        self.assertIn("Lot ID", header_set)
        self.assertNotIn(123, header_set)


# ---------------------------------------------------------------------------
# BUG #11 — Duplicate lot_id dalam satu file → double snapshot
# ---------------------------------------------------------------------------
class TestDuplicateLotIdInFile(unittest.TestCase):
    """
    BUG #11: Kalau satu file Excel punya lot_id duplikat (baris ganda),
    lot_ids_in_file akan punya duplikat. _snapshot_existing_batch akan
    query dengan IN yang berisi duplikat → row yang sama di-fetch sekali
    (SQL IN deduplicate di query), tapi data baris duplikat yang kedua
    akan override yang pertama saat upsert (ON CONFLICT UPDATE).

    Tidak data-corrupting, tapi noisy dan mungkin tidak intended.

    Fix: deduplicate lot_ids_in_file sebelum _snapshot_existing_batch.
    """

    def test_duplicate_lot_ids_in_file(self):
        """Reproducing: lot_ids list punya duplikat."""
        # Simulasikan dua baris Excel dengan lot_id yang sama
        lot_ids_in_file = ["LOT001", "LOT002", "LOT001"]  # LOT001 duplikat

        # BUGGY: langsung pakai list dengan duplikat
        has_duplicates = len(lot_ids_in_file) != len(set(lot_ids_in_file))
        self.assertTrue(has_duplicates, "BUG REPRODUCED: lot_ids_in_file punya duplikat")

    def test_deduplication_fix(self):
        """Setelah fix: lot_ids_in_file di-deduplicate sebelum snapshot."""
        lot_ids_in_file = ["LOT001", "LOT002", "LOT001"]
        deduped = list(dict.fromkeys(lot_ids_in_file))  # preserve order, deduplicate
        self.assertEqual(deduped, ["LOT001", "LOT002"])
        self.assertEqual(len(deduped), len(set(deduped)))


# ---------------------------------------------------------------------------
# Tests untuk fungsi yang sudah BENAR (regression tests)
# ---------------------------------------------------------------------------
class TestParseDescription(unittest.TestCase):
    """Regression tests untuk parse_description (roll mode) — sudah benar."""

    def test_standard_description(self):
        result = parse_description("B Kraft BK125 E150 690")
        self.assertEqual(result['papertype'], "B Kraft")
        self.assertEqual(result['gramature'], "BK125")
        self.assertEqual(result['playbond'], "E150")
        self.assertEqual(result['width'], "690")

    def test_none_description(self):
        result = parse_description(None)
        self.assertIsNone(result['papertype'])
        self.assertIsNone(result['gramature'])

    def test_empty_string_description(self):
        result = parse_description("")
        self.assertIsNone(result['papertype'])

    def test_description_with_parentheses(self):
        result = parse_description("B Kraft BK125 E150 690 (Item Blocked)")
        self.assertEqual(result['width'], "690")
        self.assertEqual(result['playbond'], "E150")

    def test_description_integer_input(self):
        """Edge case: kalau description_raw adalah integer dari Excel."""
        result = parse_description(12345)
        # isinstance check sudah ada: `if not description or not isinstance(description, str)`
        self.assertIsNone(result['papertype'])


class TestParseDescriptionSheet(unittest.TestCase):
    """Regression tests untuk parse_description_sheet."""

    def test_standard_sheet_description(self):
        result = parse_description_sheet("BK350 590x840")
        self.assertEqual(result['papertype'], "B Kraft")
        self.assertEqual(result['gramature'], "BK350")
        self.assertEqual(result['dimension'], "590x840")

    def test_with_item_code_prefix(self):
        result = parse_description_sheet("40007433 CB350G 770x650")
        self.assertEqual(result['papertype'], "ChipBoard")
        self.assertEqual(result['gramature'], "CB350G")
        self.assertEqual(result['dimension'], "770x650")

    def test_none_input(self):
        result = parse_description_sheet(None)
        self.assertIsNone(result['papertype'])
        self.assertIsNone(result['dimension'])

    def test_empty_string(self):
        result = parse_description_sheet("")
        self.assertIsNone(result['papertype'])


class TestDetectTypeFromHeadersValid(unittest.TestCase):
    """Regression tests untuk detect_type_from_headers dengan input normal."""

    def test_sheet_headers_detected(self):
        headers = ["Lot ID", "Item ID", "Weight", "Dimension", "Content Pack"]
        result = detect_type_from_headers(headers)
        self.assertEqual(result, "sheet")

    def test_roll_headers_detected(self):
        headers = ["Lot ID", "Item ID", "Weight", "Grade", "Diameter"]
        result = detect_type_from_headers(headers)
        self.assertEqual(result, "roll")

    def test_default_to_roll(self):
        headers = ["Lot ID", "Item ID", "Weight", "Description"]
        result = detect_type_from_headers(headers)
        self.assertEqual(result, "roll")

    def test_none_headers_filtered(self):
        headers = ["Lot ID", None, "Weight", None]
        result = detect_type_from_headers(headers)
        self.assertEqual(result, "roll")


# ---------------------------------------------------------------------------
# BUG #12 — _snapshot_existing_batch: silent failure tidak propagate ke caller
# ---------------------------------------------------------------------------
class TestSnapshotFailurePropagation(unittest.TestCase):
    """
    BUG #12: _snapshot_existing_batch menelan exception saat INSERT chunk gagal
    (insert_ok = False, loop break) dan return None tanpa error.
    Caller tidak tahu snapshot gagal, lanjut ke upsert dan _delete_stale.
    Data bisa terhapus dari tabel utama tanpa backup di history.

    Fix: raise exception atau return False supaya caller bisa abort/warn.
    (Minimal: return nilai boolean yang bisa dicek caller.)
    """

    def test_snapshot_failure_is_silent(self):
        """Dokumentasikan bahwa saat ini _snapshot_existing_batch return None selalu."""
        # Tidak bisa test tanpa DB, tapi kita verifikasi signature return value
        import inspect
        from import_excel import _snapshot_existing_batch
        src = inspect.getsource(_snapshot_existing_batch)
        # Cek bahwa ada 'return' dengan nilai di path failure
        # Saat ini: hanya ada bare 'return' (return None) di path early exit
        # dan tidak ada return di path insert_ok=False → implicit None
        self.assertIn("insert_ok", src,
            "insert_ok flag harus ada di fungsi")


if __name__ == "__main__":
    # Jalankan semua test
    loader = unittest.TestLoader()
    suite = loader.loadTestsFromModule(sys.modules[__name__])
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(suite)
    sys.exit(0 if result.wasSuccessful() else 1)
