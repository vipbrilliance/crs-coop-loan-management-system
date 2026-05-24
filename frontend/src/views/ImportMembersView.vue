<template>
  <div class="import-wrap">
    <header class="view-header">
      <div>
        <div class="view-title">Import Members</div>
        <div class="view-sub">Upload a CSV to add or update member records</div>
      </div>
    </header>

    <main class="import-body">
      <!-- Step 1: Upload -->
      <section class="report-card">
        <div class="card-head report-head">
          <div class="section-kicker">Step 1 — Upload</div>
          <h3>Select Member CSV File</h3>
        </div>

        <div
          class="import-upload-zone"
          :class="{ 'drag-over': isDragging }"
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
        >
          <input
            ref="fileInputRef"
            type="file"
            accept=".csv,text/csv"
            class="import-file-hidden"
            @change="handleFileChange"
          />
          <div class="import-zone-body">
            <div class="import-zone-icon">▤</div>
            <div class="import-zone-label">Drop CSV file here or</div>
            <button type="button" class="btn btn-secondary" @click="fileInputRef.click()">
              Choose CSV File
            </button>
            <div class="import-zone-hint">
              Accepts .csv files up to 5 MB &middot; UTF-8 or UTF-8 BOM &middot; First row must be column headers
            </div>
          </div>
          <div v-if="fileName" class="import-zone-selected">
            Selected: <strong>{{ fileName }}</strong>
          </div>
        </div>

        <div v-if="error" class="import-error-msg">{{ error }}</div>

        <div class="import-template-link">
          Don't have a template?
          <a href="/import-templates/members-template.csv" download class="text-link">
            Download Member CSV template
          </a>
        </div>
      </section>

      <!-- Step 2: Review (shown after file parsed) -->
      <section v-if="previewRows.length > 0" class="report-card">
        <div class="card-head report-head">
          <div class="section-kicker">Step 2 — Review</div>
          <h3>Validation Preview</h3>
          <span class="row-count-badge">
            {{ previewRows.length }} rows,
            {{ validRowCount }} valid,
            {{ previewRows.length - validRowCount }} invalid
          </span>
        </div>

        <div class="table-scroll">
          <table class="data-table import-preview-table">
            <thead>
              <tr>
                <th>Row #</th>
                <th>Status</th>
                <th>member_no</th>
                <th>last_name</th>
                <th>first_name</th>
                <th>contact</th>
                <th>share_capital</th>
                <th>Error</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in previewRows"
                :key="row.rowNum"
                :class="{ 'row-valid': row.valid, 'row-error': !row.valid }"
              >
                <td class="mono">{{ row.rowNum }}</td>
                <td>
                  <span v-if="row.valid" class="badge badge-import-pass">Valid</span>
                  <span v-else class="badge badge-import-fail">Error</span>
                </td>
                <td>{{ row.record.member_no }}</td>
                <td>{{ row.record.last_name }}</td>
                <td>{{ row.record.first_name }}</td>
                <td>{{ row.record.contact }}</td>
                <td class="mono">{{ row.record.share_capital }}</td>
                <td class="text-red">{{ row.error }}</td>
              </tr>
              <tr v-if="previewRows.length === 0">
                <td colspan="8" class="empty-state-cell">No CSV file parsed yet</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Actions (shown after file parsed) -->
      <section v-if="previewRows.length > 0" class="import-actions">
        <!-- Result summary after commit -->
        <div v-if="result" class="import-result-summary">
          <div class="result-icon">&#10003;</div>
          <div class="result-text">
            <strong>Import complete.</strong>
            {{ result.inserted }} inserted, {{ result.updated }} updated, {{ result.skipped }} skipped
            <span v-if="result.skipped > 0"> &mdash; see errors above</span>
          </div>
        </div>

        <div class="commit-actions">
          <button
            v-if="!result"
            class="btn btn-primary"
            @click="commit"
            :disabled="validRowCount === 0 || committing"
          >
            {{ committing ? 'Committing…' : 'Commit Valid Rows' }}
          </button>
          <button class="btn btn-secondary" @click="resetImport">Clear &amp; Start Over</button>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { api } from '../composables/useApi.js'

const fileName = ref('')
const previewRows = ref([])
const committing = ref(false)
const result = ref(null)
const error = ref(null)
const isDragging = ref(false)
const fileInputRef = ref(null)
const fileRef = ref(null)

const validRowCount = computed(() => previewRows.value.filter(r => r.valid).length)

function parseFile(file) {
  const reader = new FileReader()
  reader.onload = (e) => {
    let text = e.target.result
    // BOM strip client-side (D-13, IMPORT-05)
    if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1)
    const lines = text.trim().split('\n').map(l => l.replace(/\r$/, ''))
    if (lines.length < 2) {
      previewRows.value = []
      error.value = 'CSV file must have at least one header row and one data row'
      return
    }
    const headers = lines[0].split(',').map(h => h.trim().replace(/^"|"$/g, ''))
    // Validate required columns present
    const requiredCols = ['member_no', 'last_name', 'first_name']
    const missingCols = requiredCols.filter(c => !headers.includes(c))
    if (missingCols.length) {
      error.value = "Required column '" + missingCols[0] + "' not found in CSV header row"
      previewRows.value = []
      return
    }
    // Parse and validate each data row
    const rows = lines.slice(1).filter(l => l.trim()).map((line, i) => {
      const fields = line.split(',').map(f => f.trim().replace(/^"|"$/g, ''))
      const record = {}
      headers.forEach((h, idx) => { record[h] = fields[idx] || '' })
      let rowError = ''
      if (!record.member_no) rowError = "'member_no' is required and cannot be empty"
      else if (!record.last_name) rowError = "'last_name' is required and cannot be empty"
      else if (!record.first_name) rowError = "'first_name' is required and cannot be empty"
      else if (record.monthly_salary && isNaN(Number(record.monthly_salary))) rowError = "'monthly_salary' must be a number (got: '" + record.monthly_salary + "')"
      else if (record.share_capital && isNaN(Number(record.share_capital))) rowError = "'share_capital' must be a number (got: '" + record.share_capital + "')"
      return { rowNum: i + 2, valid: !rowError, record, error: rowError }
    })
    previewRows.value = rows
    // Pitfall 7: reset file input so re-selecting the same file fires @change
    fileInputRef.value.value = ''
  }
  reader.readAsText(file)
}

function handleFileChange(event) {
  const file = event.target.files[0]
  if (!file) return
  fileName.value = file.name
  fileRef.value = file
  result.value = null
  error.value = null
  parseFile(file)
}

function handleDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (!file) return
  fileName.value = file.name
  fileRef.value = file
  result.value = null
  error.value = null
  parseFile(file)
}

function resetImport() {
  fileName.value = ''
  previewRows.value = []
  result.value = null
  error.value = null
  fileRef.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

async function commit() {
  if (!fileName.value || validRowCount.value === 0) return
  committing.value = true
  try {
    result.value = await api.importCommit('members', fileRef.value)
  } catch (e) {
    error.value = 'Import failed: ' + e.message
  } finally {
    committing.value = false
  }
}
</script>

<style scoped>
.import-wrap {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}

.import-body {
  flex: 1;
  overflow-y: auto;
  padding: 28px 32px;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.import-upload-zone {
  border: 2px dashed var(--coop-border);
  border-radius: 10px;
  padding: 32px 24px;
  text-align: center;
  background: #FAFBFC;
  transition: border-color var(--tx), background var(--tx);
  cursor: pointer;
  margin: 16px 0;
}
.import-upload-zone:hover,
.import-upload-zone.drag-over {
  border-color: var(--coop-red);
  background: var(--coop-red-dim);
}
.import-file-hidden { display: none; }
.import-zone-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.import-zone-icon { font-size: 22px; color: var(--coop-muted); margin-bottom: 12px; }
.import-zone-label { font-size: 14px; color: var(--coop-muted); margin-bottom: 8px; }
.import-zone-hint  { font-size: 11px; color: var(--coop-muted); margin-top: 8px; line-height: 1.5; }
.import-zone-selected {
  margin-top: 12px;
  padding: 8px 12px;
  background: rgba(39,174,96,0.08);
  border: 1px solid rgba(39,174,96,0.25);
  border-radius: 6px;
  font-size: 14px;
  color: var(--status-approved);
}

.import-template-link {
  margin-top: 12px;
  font-size: 13px;
  color: var(--coop-muted);
}
.text-link {
  color: var(--coop-red);
  text-decoration: underline;
  font-weight: 600;
}
.text-link:hover { color: var(--coop-red-soft); }

.import-error-msg {
  margin-top: 8px;
  padding: 10px 14px;
  background: rgba(231,76,60,0.08);
  border: 1px solid rgba(231,76,60,0.25);
  border-radius: 6px;
  font-size: 13px;
  color: var(--status-rejected);
}

.row-count-badge {
  margin-left: 12px;
  font-size: 12px;
  font-weight: 700;
  color: var(--coop-muted);
}

.table-scroll { overflow-x: auto; }

.empty-state-cell {
  text-align: center;
  color: var(--coop-muted);
  padding: 24px;
}

.import-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 20px;
  background: var(--coop-surface);
  border: 1px solid var(--coop-border);
  border-radius: 10px;
}

.import-result-summary {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  background: rgba(39,174,96,0.08);
  border: 1px solid rgba(39,174,96,0.25);
  border-radius: 8px;
  margin-bottom: 12px;
}
.result-icon { font-size: 22px; color: var(--status-approved); }
.result-text { font-size: 14px; color: var(--coop-cream); }
.result-text strong { color: var(--status-approved); }

.commit-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.text-red { color: var(--status-rejected); font-size: 12px; }

/* Report card wrapper reuse */
.report-card {
  background: var(--coop-surface);
  border: 1px solid var(--coop-border);
  border-radius: 10px;
  overflow: hidden;
}
.report-head {
  padding: 18px 24px 14px;
  border-bottom: 1px solid var(--coop-border);
}
.card-head {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.section-kicker {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: var(--coop-red);
  margin-right: 4px;
}
.report-head h3 {
  font-size: 22px;
  font-weight: 800;
  color: #202838;
  margin: 0;
}
</style>
