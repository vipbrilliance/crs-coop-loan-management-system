<template>
  <div class="billing-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Billing</div>
        <div class="view-sub">Generate payroll deduction bills, issue them to companies, and track remittances</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="loadAll">Refresh</button>
        <button class="btn btn-primary" @click="openCreate">Generate Bill</button>
      </div>
    </header>

    <main class="billing-body">
      <section class="billing-kpis">
        <div class="bill-kpi">
          <div class="kpi-label">Open Bills</div>
          <div class="kpi-value">{{ openBills.length }}</div>
          <div class="kpi-sub">Draft, issued, and partial</div>
        </div>
        <div class="bill-kpi">
          <div class="kpi-label">Total Billed</div>
          <div class="kpi-value money">{{ peso(totalBilled) }}</div>
          <div class="kpi-sub">Current filter result</div>
        </div>
        <div class="bill-kpi">
          <div class="kpi-label">Remitted</div>
          <div class="kpi-value money success">{{ peso(totalRemitted) }}</div>
          <div class="kpi-sub">Posted company remittances</div>
        </div>
        <div class="bill-kpi">
          <div class="kpi-label">Outstanding</div>
          <div class="kpi-value money danger">{{ peso(totalOutstanding) }}</div>
          <div class="kpi-sub">Remaining payroll balance</div>
        </div>
      </section>

      <section class="filter-card">
        <div class="form-group">
          <label class="form-label">Company</label>
          <select v-model="filters.company_id" class="form-select" @change="loadBills">
            <option value="">All companies</option>
            <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select v-model="filters.status" class="form-select" @change="loadBills">
            <option value="">All statuses</option>
            <option>DRAFT</option>
            <option>ISSUED</option>
            <option>PARTIAL</option>
            <option>SETTLED</option>
            <option>CANCELLED</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">From</label>
          <input v-model="filters.date_from" type="date" class="form-input" @change="loadBills" />
        </div>
        <div class="form-group">
          <label class="form-label">To</label>
          <input v-model="filters.date_to" type="date" class="form-input" @change="loadBills" />
        </div>
        <button class="btn btn-secondary" @click="resetFilters">Reset</button>
      </section>

      <section class="billing-grid">
        <aside class="bill-list-card">
          <div class="panel-title">Billing Cycles</div>
          <div class="bill-list">
            <button
              v-for="bill in bills"
              :key="bill.id"
              :class="['bill-row', selected?.id === bill.id && 'active']"
              @click="selectBill(bill)"
            >
              <div class="bill-row-main">
                <div class="row-title">{{ bill.bill_no }}</div>
                <div class="row-sub">{{ bill.company_name }} · {{ periodLabel(bill) }}</div>
              </div>
              <div class="bill-row-right">
                <span :class="statusBadge(bill.status)">{{ bill.status }}</span>
                <span class="peso">{{ peso(bill.total_amount) }}</span>
              </div>
            </button>
            <div v-if="!loading && !bills.length" class="empty-inline">No bills found</div>
            <div v-if="loading" class="empty-inline"><div class="spinner"></div></div>
          </div>
        </aside>

        <section class="bill-detail-card">
          <template v-if="selected">
            <div class="detail-header">
              <div>
                <div class="panel-title">{{ selected.bill_no }}</div>
                <div class="row-sub">{{ selected.company_name }} · {{ periodLabel(selected) }}</div>
              </div>
              <div class="detail-actions">
                <button class="btn btn-secondary btn-sm" @click="printBill">Print Bill</button>
                <button v-if="selected.status === 'DRAFT'" class="btn btn-primary btn-sm" @click="issueSelected">Issue</button>
                <button v-if="canReceiveRemittance" class="btn btn-secondary btn-sm" @click="openRemittance">Upload Remittance</button>
                <button v-if="canReceiveRemittance" class="btn btn-secondary btn-sm" @click="settleSelected">Mark Settled</button>
                <button v-if="['DRAFT','ISSUED'].includes(selected.status)" class="btn btn-ghost btn-sm danger-text" @click="cancelSelected">Cancel</button>
              </div>
            </div>

            <div class="bill-summary">
              <div class="metric">
                <span>Status</span>
                <strong><span :class="statusBadge(selected.status)">{{ selected.status }}</span></strong>
              </div>
              <div class="metric">
                <span>Line Items</span>
                <strong>{{ selected.item_count || selected.items?.length || 0 }}</strong>
              </div>
              <div class="metric">
                <span>Total Billed</span>
                <strong>{{ peso(selected.total_amount) }}</strong>
              </div>
              <div class="metric">
                <span>Balance</span>
                <strong :class="Number(selected.balance) > 0 ? 'danger-text' : 'success-text'">{{ peso(selected.balance) }}</strong>
              </div>
            </div>

            <div class="detail-body-grid">
              <div class="line-items-card">
                <div class="section-title">Bill Line Items</div>
                <div class="table-wrap">
                  <table class="billing-table">
                    <thead>
                      <tr>
                        <th>Member</th>
                        <th>Loan</th>
                        <th>Period</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in selected.items" :key="item.id || item.schedule_key">
                        <td>
                          <strong>{{ item.member_name }}</strong>
                          <span>{{ item.member_no }}</span>
                        </td>
                        <td class="mono">{{ item.loan_no }}</td>
                        <td class="mono">#{{ item.period_no }}</td>
                        <td>{{ formatDate(item.due_date) }}</td>
                        <td class="peso fw-600">{{ peso(item.amount_due) }}</td>
                        <td><span :class="item.status === 'PAID' ? 'badge badge-approved' : 'badge badge-pending'">{{ item.status }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <aside class="remittance-card">
                <div class="section-title">Remittance History</div>
                <div class="remittance-list">
                  <div v-for="remit in selected.remittances" :key="remit.id" class="remit-row">
                    <div>
                      <strong>{{ peso(remit.amount) }}</strong>
                      <span>{{ formatDate(remit.remittance_date) }} · O.R. {{ remit.or_number || '-' }}</span>
                      <span v-if="remit.file_name">{{ remit.file_name }}</span>
                    </div>
                  </div>
                  <div v-if="!selected.remittances?.length" class="empty-inline compact">No remittances yet</div>
                </div>
                <div v-if="selected.notes" class="notes-box">{{ selected.notes }}</div>
              </aside>
            </div>
          </template>

          <div v-else class="empty-state">
            <div class="empty-icon">▤</div>
            <div class="empty-title">Select a bill</div>
            <div class="text-muted">Generate or pick a billing cycle to inspect its line items.</div>
          </div>
        </section>
      </section>
    </main>

    <div v-if="createOpen" class="modal-overlay" @click.self="createOpen = false">
      <form class="modal" @submit.prevent="createBill">
        <div class="modal-header">
          <div class="modal-title">Generate Bill</div>
          <button type="button" class="btn btn-ghost btn-sm" @click="createOpen = false">Close</button>
        </div>
        <div class="modal-body form-stack">
          <div class="form-group">
            <label class="form-label">Company</label>
            <select v-model.number="createForm.company_id" class="form-select" required>
              <option disabled value="">Select company</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Billing Period Start</label>
              <input v-model="createForm.billing_period_start" type="date" class="form-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">Billing Period End</label>
              <input v-model="createForm.billing_period_end" type="date" class="form-input" required />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea v-model="createForm.notes" class="form-textarea" placeholder="Optional payroll notes"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="createOpen = false">Cancel</button>
          <button class="btn btn-primary" type="submit" :disabled="actioning">Generate</button>
        </div>
      </form>
    </div>

    <div v-if="remitOpen" class="modal-overlay" @click.self="remitOpen = false">
      <form class="modal" @submit.prevent="submitRemittance">
        <div class="modal-header">
          <div class="modal-title">Upload Remittance</div>
          <button type="button" class="btn btn-ghost btn-sm" @click="remitOpen = false">Close</button>
        </div>
        <div class="modal-body form-stack">
          <div class="selected-strip">
            <strong>{{ selected.bill_no }}</strong>
            <span>{{ peso(selected.balance) }} remaining</span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">O.R. Number</label>
              <input v-model="remitForm.or_number" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Remittance Date</label>
              <input v-model="remitForm.remittance_date" type="date" class="form-input" required />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Amount</label>
            <input v-model.number="remitForm.amount" type="number" min="0" step="0.01" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">Attachment Name</label>
            <input v-model="remitForm.file_name" class="form-input" placeholder="Optional file reference" />
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea v-model="remitForm.notes" class="form-textarea"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="remitOpen = false">Cancel</button>
          <button class="btn btn-primary" type="submit" :disabled="actioning">Post Remittance</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../composables/useApi'
import { peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const route = useRoute()
const { success, error } = useToast()
const companies = ref([])
const bills = ref([])
const selected = ref(null)
const loading = ref(false)
const actioning = ref(false)
const createOpen = ref(false)
const remitOpen = ref(false)

const filters = reactive({ company_id: '', status: '', date_from: '', date_to: '' })
const createForm = reactive({ company_id: '', billing_period_start: '', billing_period_end: '', notes: '' })
const remitForm = reactive({ or_number: '', remittance_date: today(), amount: 0, file_name: '', notes: '' })

const openBills = computed(() => bills.value.filter(b => ['DRAFT', 'ISSUED', 'PARTIAL'].includes(b.status)))
const totalBilled = computed(() => bills.value.reduce((sum, bill) => sum + Number(bill.total_amount || 0), 0))
const totalRemitted = computed(() => bills.value.reduce((sum, bill) => sum + Number(bill.amount_remitted || 0), 0))
const totalOutstanding = computed(() => bills.value.reduce((sum, bill) => sum + Number(bill.balance || 0), 0))
const canReceiveRemittance = computed(() => selected.value && ['ISSUED', 'PARTIAL'].includes(selected.value.status))

function today() {
  return new Date().toISOString().slice(0, 10)
}

function periodLabel(bill) {
  return `${formatDate(bill.billing_period_start)} to ${formatDate(bill.billing_period_end)}`
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

function statusBadge(status) {
  return {
    DRAFT: 'badge badge-draft',
    ISSUED: 'badge badge-active',
    PARTIAL: 'badge badge-pending',
    SETTLED: 'badge badge-approved',
    CANCELLED: 'badge badge-rejected',
  }[status] || 'badge badge-draft'
}

function requestParams() {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null))
}

async function loadCompanies() {
  companies.value = await api.getCompanies()
}

async function loadBills() {
  loading.value = true
  try {
    bills.value = await api.getBills(requestParams())
    if (selected.value) {
      const fresh = bills.value.find(b => b.id === selected.value.id)
      selected.value = fresh ? await api.getBill(fresh.id) : null
    } else if (bills.value[0]) {
      await selectBill(bills.value[0])
    }
  } catch (err) {
    error(err.message || 'Could not load bills.')
  } finally {
    loading.value = false
  }
}

async function loadAll() {
  await loadCompanies()
  applyRoutePrefill()
  await loadBills()
}

async function selectBill(bill) {
  selected.value = await api.getBill(bill.id)
}

function resetFilters() {
  Object.assign(filters, { company_id: '', status: '', date_from: '', date_to: '' })
  loadBills()
}

function companyIdByName(name) {
  if (!name) return ''
  return companies.value.find(company => company.name === name)?.id || ''
}

function applyRoutePrefill() {
  const companyId = route.query.company_id || companyIdByName(route.query.company)
  if (companyId) filters.company_id = companyId
  if (route.query.date_from) filters.date_from = route.query.date_from
  if (route.query.date_to) filters.date_to = route.query.date_to
  if (route.query.open === 'generate') {
    const now = new Date()
    const start = route.query.date_from || new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
    const end = route.query.date_to || new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
    Object.assign(createForm, { company_id: companyId || companies.value[0]?.id || '', billing_period_start: start, billing_period_end: end, notes: 'Generated from Loan Monitoring handoff.' })
    createOpen.value = true
  }
}

function openCreate() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
  Object.assign(createForm, { company_id: filters.company_id || companies.value[0]?.id || '', billing_period_start: filters.date_from || start, billing_period_end: filters.date_to || end, notes: '' })
  createOpen.value = true
}

async function createBill() {
  actioning.value = true
  try {
    const bill = await api.createBill({ ...createForm })
    createOpen.value = false
    success(`${bill.bill_no} generated with ${bill.items.length} line item(s).`)
    await loadBills()
    await selectBill(bill)
  } catch (err) {
    error(err.message || 'Could not generate bill.')
  } finally {
    actioning.value = false
  }
}

async function issueSelected() {
  actioning.value = true
  try {
    selected.value = await api.issueBill(selected.value.id)
    success(`${selected.value.bill_no} issued to company.`)
    await loadBills()
  } catch (err) {
    error(err.message || 'Could not issue bill.')
  } finally {
    actioning.value = false
  }
}

function openRemittance() {
  Object.assign(remitForm, {
    or_number: `OR-${new Date().getFullYear()}-${String((selected.value.remittances?.length || 0) + 1).padStart(4, '0')}`,
    remittance_date: today(),
    amount: selected.value.balance,
    file_name: '',
    notes: '',
  })
  remitOpen.value = true
}

async function submitRemittance() {
  actioning.value = true
  try {
    selected.value = await api.remitBill(selected.value.id, { ...remitForm })
    remitOpen.value = false
    success(selected.value.status === 'SETTLED' ? 'Bill fully settled.' : 'Remittance posted.')
    await loadBills()
  } catch (err) {
    error(err.message || 'Could not post remittance.')
  } finally {
    actioning.value = false
  }
}

async function settleSelected() {
  if (!window.confirm(`Mark ${selected.value.bill_no} as fully settled?`)) return
  actioning.value = true
  try {
    selected.value = await api.settleBill(selected.value.id)
    success(`${selected.value.bill_no} settled.`)
    await loadBills()
  } catch (err) {
    error(err.message || 'Could not settle bill.')
  } finally {
    actioning.value = false
  }
}

async function cancelSelected() {
  if (!window.confirm(`Cancel ${selected.value.bill_no}?`)) return
  actioning.value = true
  try {
    selected.value = await api.cancelBill(selected.value.id)
    success(`${selected.value.bill_no} cancelled.`)
    await loadBills()
  } catch (err) {
    error(err.message || 'Could not cancel bill.')
  } finally {
    actioning.value = false
  }
}

function printBill() {
  const bill = selected.value
  const rows = (bill.items || []).map(item => `
    <tr>
      <td>${item.member_name}<br><small>${item.member_no}</small></td>
      <td>${item.loan_no}</td>
      <td>${item.period_no}</td>
      <td>${formatDate(item.due_date)}</td>
      <td style="text-align:right">${peso(item.amount_due)}</td>
    </tr>
  `).join('')
  const doc = window.open('', '_blank', 'width=900,height=700')
  doc.document.write(`
    <html><head><title>${bill.bill_no}</title>
    <style>
      body{font-family:Arial,sans-serif;padding:32px;color:#111} h1,h2{text-align:center;margin:0} h2{margin-top:8px}
      .meta{display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;margin:28px 0 18px;font-size:13px}
      table{width:100%;border-collapse:collapse;font-size:12px} th,td{border:1px solid #333;padding:7px} th{background:#f0f0f0;text-align:left}
      .total{margin-top:18px;text-align:right;font-size:15px;font-weight:700}.sig{display:grid;grid-template-columns:1fr 1fr;gap:60px;margin-top:56px}.line{border-top:1px solid #111;padding-top:6px;text-align:center;font-size:12px}
    </style></head><body>
      <h1>CRS HOLDINGS CORP.</h1><h2>Payroll Deduction Billing Statement</h2>
      <div class="meta"><div><strong>Bill No:</strong> ${bill.bill_no}</div><div><strong>Status:</strong> ${bill.status}</div><div><strong>Company:</strong> ${bill.company_name}</div><div><strong>Period:</strong> ${periodLabel(bill)}</div></div>
      <table><thead><tr><th>Member</th><th>Loan</th><th>Period</th><th>Due Date</th><th>Amount</th></tr></thead><tbody>${rows}</tbody></table>
      <div class="total">Total Deduction: ${peso(bill.total_amount)}</div>
      <div class="sig"><div class="line">Prepared by Cooperative</div><div class="line">Received by HR / Payroll</div></div>
    </body></html>
  `)
  doc.document.close()
  doc.focus()
  doc.print()
}

onMounted(loadAll)
</script>

<style scoped>
.billing-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { padding:20px 28px; border-bottom:1px solid var(--coop-border); display:flex; justify-content:space-between; align-items:center; background:#fff; }
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:10px; align-items:center; }
.billing-body { flex:1; overflow:auto; padding:18px 22px 24px; display:flex; flex-direction:column; gap:14px; }
.billing-kpis { display:grid; grid-template-columns:repeat(4, minmax(160px, 1fr)); gap:12px; }
.bill-kpi, .filter-card, .bill-list-card, .bill-detail-card, .line-items-card, .remittance-card { background:#fff; border:1px solid var(--coop-border); border-radius:8px; box-shadow:0 8px 22px rgba(31,41,55,.04); }
.bill-kpi { min-height:108px; padding:16px 18px; }
.kpi-label { color:var(--coop-muted); font-size:11px; font-weight:900; letter-spacing:.8px; text-transform:uppercase; }
.kpi-value { margin-top:8px; color:var(--coop-cream); font-size:28px; line-height:1; font-weight:900; }
.kpi-value.money { font-family:var(--font-mono); font-size:23px; }
.kpi-value.success, .success-text { color:var(--status-approved); }
.kpi-value.danger, .danger-text { color:var(--coop-red); }
.kpi-sub { color:var(--coop-muted); font-size:12px; margin-top:8px; }
.filter-card { padding:14px; display:grid; grid-template-columns:2fr 1.2fr 1fr 1fr auto; gap:12px; align-items:end; }
.billing-grid { display:grid; grid-template-columns:340px minmax(0, 1fr); gap:14px; align-items:start; }
.bill-list-card { overflow:hidden; max-height:calc(100vh - 330px); display:flex; flex-direction:column; }
.panel-title { color:var(--coop-cream); font-size:16px; font-weight:900; }
.bill-list-card > .panel-title { padding:16px; border-bottom:1px solid var(--coop-border); }
.bill-list { overflow:auto; padding:8px; display:flex; flex-direction:column; gap:8px; }
.bill-row { width:100%; border:1px solid var(--coop-border); background:#fff; border-radius:8px; padding:12px; display:flex; justify-content:space-between; gap:12px; text-align:left; cursor:pointer; transition:all var(--tx); }
.bill-row:hover, .bill-row.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.28); }
.bill-row.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.bill-row-main { min-width:0; }
.row-title { color:var(--coop-cream); font-weight:900; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.bill-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.bill-detail-card { min-width:0; overflow:hidden; }
.detail-header { padding:16px; border-bottom:1px solid var(--coop-border); display:flex; justify-content:space-between; gap:14px; align-items:center; }
.detail-actions { display:flex; flex-wrap:wrap; gap:7px; justify-content:flex-end; }
.bill-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); border-bottom:1px solid var(--coop-border); }
.metric { padding:14px 16px; border-right:1px solid var(--coop-border); display:flex; flex-direction:column; gap:5px; }
.metric:last-child { border-right:0; }
.metric span { color:var(--coop-muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; }
.metric strong { color:var(--coop-cream); font-size:16px; }
.detail-body-grid { display:grid; grid-template-columns:minmax(0, 1fr) 280px; gap:14px; padding:14px; }
.line-items-card, .remittance-card { overflow:hidden; box-shadow:none; }
.section-title { padding:13px 14px; border-bottom:1px solid var(--coop-border); color:var(--coop-cream); font-size:13px; font-weight:900; text-transform:uppercase; letter-spacing:.5px; }
.table-wrap { overflow:auto; max-height:calc(100vh - 490px); }
.billing-table { width:100%; border-collapse:collapse; }
.billing-table th { background:#F8FAFC; color:var(--coop-muted); font-size:11px; font-weight:900; letter-spacing:.5px; text-transform:uppercase; padding:9px 10px; text-align:left; border-bottom:1px solid var(--coop-border); white-space:nowrap; }
.billing-table td { padding:10px; border-bottom:1px solid var(--coop-border); color:var(--coop-cream); font-size:13px; vertical-align:middle; white-space:nowrap; }
.billing-table td:first-child { white-space:normal; }
.billing-table td:first-child span { display:block; color:var(--coop-muted); font-size:11px; }
.remittance-list { padding:10px; display:flex; flex-direction:column; gap:8px; }
.remit-row { border:1px solid var(--coop-border); border-radius:8px; padding:10px; }
.remit-row strong, .remit-row span { display:block; }
.remit-row span { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.notes-box { margin:0 10px 10px; padding:10px; border-radius:8px; background:#F8FAFC; color:var(--coop-muted); font-size:12px; }
.empty-inline { padding:24px; color:var(--coop-muted); text-align:center; display:flex; justify-content:center; }
.empty-inline.compact { padding:16px; }
.form-stack { display:flex; flex-direction:column; gap:14px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.selected-strip { padding:12px; border:1px solid rgba(192,57,43,.18); border-radius:8px; background:var(--coop-red-dim); display:flex; justify-content:space-between; gap:12px; }
.selected-strip span { color:var(--coop-red); font-weight:900; }
@media (max-width: 1180px) { .billing-kpis, .filter-card, .billing-grid, .detail-body-grid { grid-template-columns:1fr; } .bill-list-card { max-height:none; } .bill-summary { grid-template-columns:1fr 1fr; } }
@media (max-width: 760px) { .view-header, .detail-header { flex-direction:column; align-items:flex-start; } .billing-body { padding:14px; } .billing-kpis, .bill-summary, .form-row { grid-template-columns:1fr; } }
</style>
