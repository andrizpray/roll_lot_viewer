// roll_lot_viewer GAS edition — import xlsx -> search -> export xlsx
// Sheets = DB. Setup once: run setup() from the editor.

const DB_SHEET = 'DB';
const HEADERS = ['lot_id', 'item_id', 'weight', 'papertype', 'gramature', 'playbond',
  'width', 'rew_id', 'grade', 'comments', 'diameter', 'thickness', 'description',
  'source_tr_date', 'source_tr_time', 'imported_at'];

function setup() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (!ss.getSheetByName(DB_SHEET)) ss.insertSheet(DB_SHEET).appendRow(HEADERS);
}

function doGet() {
  return HtmlService.createHtmlOutputFromFile('Index').setTitle('Roll Lot Viewer');
}

// ─── Import: xlsx -> Google Sheet -> copy rows to DB ───
function importXlsx(base64, filename) {
  const blob = Utilities.newBlob(Utilities.base64Decode(base64), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', filename);
  // ponytail: Advanced Drive API v2 (enable Drive API in Services); move to Drive v3 chunked upload if >5MB files
  const converted = Drive.Files.insert({ title: 'tmp_' + Date.now(), parents: [{ id: 'root' }] }, blob, { convert: true });
  const tmp = SpreadsheetApp.openById(converted.id);
  const src = tmp.getSheets()[0];
  const values = src.getDataRange().getValues();
  Drive.Files.remove(converted.id); // trash temp

  if (values.length < 2) throw new Error('File kosong / tidak ada data row');

  const header = values[0].map(h => String(h).toLowerCase().trim());
  const colIdx = HEADERS.slice(0, -1).map(h => header.indexOf(h.toLowerCase()));
  const db = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(DB_SHEET);
  const now = new Date();
  const outRows = values.slice(1)
    .filter(r => r.some(c => c !== ''))
    .map(r => colIdx.map(i => (i >= 0 ? r[i] : '')).concat([now]));

  db.getRange(db.getLastRow() + 1, 1, outRows.length, outRows[0].length).setValues(outRows);
  return { imported: outRows.length };
}

// ─── Search ───
function search(payload) {
  const rows = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(DB_SHEET).getDataRange().getValues();
  const header = rows[0].map(h => String(h));
  const idx = h => header.indexOf(h);
  const data = rows.slice(1);

  let out = data;
  if (payload.mode === 'batch') {
    const wanted = payload.lot_ids.map(s => String(s).toUpperCase().trim()).filter(Boolean);
    out = data.filter(r => wanted.includes(String(r[idx('lot_id')]).toUpperCase()));
  } else {
    out = data.filter(r => {
      const f = payload;
      if (f.item_id && String(r[idx('item_id')]) !== f.item_id) return false;
      if (f.papertype && String(r[idx('papertype')]) !== f.papertype) return false;
      if (f.grade && String(r[idx('grade')]) !== String(f.grade)) return false;
      if (f.width && String(r[idx('width')]) !== String(f.width)) return false;
      if (f.gramature && String(r[idx('gramature')]) !== String(f.gramature)) return false;
      if (f.lot_id && !String(r[idx('lot_id')]).toUpperCase().includes(f.lot_id.toUpperCase())) return false;
      return true;
    });
  }

  // sort: lot_id, width, gramature
  out.sort((a, b) =>
    String(a[idx('lot_id')]).localeCompare(String(b[idx('lot_id')])) ||
    (Number(a[idx('width')]) || 0) - (Number(b[idx('width')]) || 0) ||
    (Number(a[idx('gramature')]) || 0) - (Number(b[idx('gramature')]) || 0));

  return out.map(r => {
    const o = {};
    HEADERS.slice(0, -1).forEach(h => { o[h] = r[idx(h)]; });
    return o;
  });
}

// ─── Export: search results -> xlsx download URL ───
function exportXlsx(payload) {
  const rows = search(payload);
  if (!rows.length) throw new Error('Tidak ada data untuk diexport');

  const cols = HEADERS.slice(0, -1);
  const tmp = SpreadsheetApp.create('export_' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyyMMdd_HHmm'));
  const sheet = tmp.getSheets()[0];
  sheet.getRange(1, 1, 1, cols.length).setValues([cols]).setFontWeight('bold');
  sheet.getRange(2, 1, rows.length, cols.length).setValues(rows.map(r => cols.map(c => r[c])));

  const url = 'https://docs.google.com/spreadsheets/d/' + tmp.getId() + '/export?format=xlsx';
  // ponytail: temp spreadsheet kept (no auto-delete) so the URL stays valid; delete manually or via time-driven trigger if junk accumulates
  return { url: url, count: rows.length };
}
