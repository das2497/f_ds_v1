/**
 * Products & Variants — Sheet → App reverse sync.
 *
 * SETUP (one time):
 *   1. Open the Google Sheet the app syncs Products/Variants into.
 *   2. Extensions → Apps Script. Delete any starter code and paste this
 *      whole file in.
 *   3. Project Settings (gear icon) → Script Properties → add two:
 *        WEBHOOK_URL   = https://your-app-domain.com/api/sheet-sync/webhook
 *        WEBHOOK_TOKEN = the exact value of GOOGLE_SHEETS_SYNC_TOKEN in the app's .env
 *   4. Back in the editor, select the `createTrigger` function from the
 *      function dropdown and click Run once. Approve the permissions
 *      prompt (it needs permission to make external requests on edit).
 *   5. Done — editing Title/Description/Price/Active on the "Products"
 *      tab, or Cost Price/Price/Stock Status/Active on the "Variants"
 *      tab, now pushes that change back into the app. Any other column
 *      is read-only; edits to it are ignored and get overwritten on the
 *      app's next push.
 *
 * Only ONE person needs to run step 4, once, for the whole sheet.
 */

const SYNCED_SHEETS = ['Products', 'Variants'];

function createTrigger() {
  // Avoid stacking duplicate triggers if this is ever run more than once.
  ScriptApp.getProjectTriggers()
    .filter((trigger) => trigger.getHandlerFunction() === 'onEditHandler')
    .forEach((trigger) => ScriptApp.deleteTrigger(trigger));

  ScriptApp.newTrigger('onEditHandler')
    .forSpreadsheet(SpreadsheetApp.getActiveSpreadsheet())
    .onEdit()
    .create();
}

/**
 * Installable onEdit trigger (not the simple `onEdit(e)` trigger) — only
 * an installable trigger is allowed to call UrlFetchApp.
 */
function onEditHandler(e) {
  const range = e.range;
  const sheet = range.getSheet();
  const sheetName = sheet.getName();

  if (SYNCED_SHEETS.indexOf(sheetName) === -1) {
    return;
  }

  const row = range.getRow();

  if (row === 1) {
    return; // header row
  }

  const lastColumn = sheet.getLastColumn();
  const headers = sheet.getRange(1, 1, 1, lastColumn).getValues()[0];
  const rowValues = sheet.getRange(row, 1, 1, lastColumn).getValues()[0];

  const statusColumn = headers.indexOf('Sync Status') + 1; // 0 if missing
  const idValue = rowValues[headers.indexOf('ID')];

  if (idValue === '' || idValue === null) {
    writeStatus(sheet, row, statusColumn, 'No ID — add new products/variants from the app, not a blank row here.');
    return;
  }

  const values = {};
  headers.forEach((header, i) => {
    if (header) {
      values[header] = rowValues[i];
    }
  });

  const props = PropertiesService.getScriptProperties();
  const webhookUrl = props.getProperty('WEBHOOK_URL');
  const webhookToken = props.getProperty('WEBHOOK_TOKEN');

  if (!webhookUrl || !webhookToken) {
    writeStatus(sheet, row, statusColumn, 'Script not configured — set WEBHOOK_URL/WEBHOOK_TOKEN in Script Properties.');
    return;
  }

  const payload = {
    sheet: sheetName,
    id: idValue,
    values: values,
  };

  try {
    const response = UrlFetchApp.fetch(webhookUrl, {
      method: 'post',
      contentType: 'application/json',
      headers: { Authorization: 'Bearer ' + webhookToken },
      payload: JSON.stringify(payload),
      muteHttpExceptions: true,
    });

    const code = response.getResponseCode();
    const body = JSON.parse(response.getContentText() || '{}');

    writeStatus(sheet, row, statusColumn, code === 200 ? '' : 'Error: ' + (body.error || ('HTTP ' + code)));
  } catch (err) {
    writeStatus(sheet, row, statusColumn, 'Error: ' + err.message);
  }
}

function writeStatus(sheet, row, statusColumn, message) {
  if (statusColumn > 0) {
    sheet.getRange(row, statusColumn).setValue(message);
  }
}
