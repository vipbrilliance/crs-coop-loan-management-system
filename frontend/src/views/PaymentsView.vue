<template>
  <div class="payments-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Collections & Payments</div>
        <div class="view-sub">Record O.R. payments, update period status, and track balances</div>
      </div>
      <div class="header-actions">
        <select v-model="filterStatus" class="form-select" @change="loadLoans">
          <option value="">All loans</option>
          <option value="ACTIVE">Active</option>
          <option value="PENDING">Pending</option>
          <option value="APPROVED">Approved</option>
          <option value="CLOSED">Closed</option>
        </select>
        <button class="btn btn-secondary" @click="downloadImportGuide">Loan Excel Guide</button>
        <label class="btn btn-secondary import-btn">
          Import Loan Payments
          <input type="file" accept=".csv,.tsv,.txt,.xls,.xlsx" @change="handleImportFile" />
        </label>
        <button class="btn btn-secondary" @click="loadAll">Refresh</button>
      </div>
    </header>

    <main class="payments-body">
      <section class="payment-kpis">
        <div class="pay-kpi">
          <div class="kpi-label">Today's Collection</div>
          <div class="kpi-value success">{{ peso(todayTotal) }}</div>
          <div class="kpi-sub">{{ todayPayments.length }} posted payment(s)</div>
        </div>
        <div class="pay-kpi">
          <div class="kpi-label">Total Posted</div>
          <div class="kpi-value">{{ peso(totalPosted) }}</div>
          <div class="kpi-sub">{{ payments.length }} preview records</div>
        </div>
        <div class="pay-kpi">
          <div class="kpi-label">Selected Loan Balance</div>
          <div class="kpi-value danger">{{ peso(selectedBalance) }}</div>
          <div class="kpi-sub">{{ selectedLoan?.loan_no || 'No loan selected' }}</div>
        </div>
      </section>

      <section class="payment-grid">
        <aside class="loan-panel">
          <div class="panel-title">Collect From Loan</div>
          <div class="loan-list">
            <button
              v-for="loan in loanRows"
              :key="loan.id"
              :class="['loan-pick', selectedLoan?.id === loan.id && 'active']"
              @click="selectLoan(loan)"
            >
              <div>
                <div class="row-title">{{ loan.loan_no }}</div>
                <div class="row-sub">{{ loan.first_name }} {{ loan.last_name }} · {{ loan.loan_type_label }}</div>
              </div>
              <div class="loan-meta">
                <span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span>
                <span class="peso">{{ peso(loan.amount) }}</span>
              </div>
            </button>
          </div>
        </aside>

        <section class="posting-panel">
          <div class="panel-title">Payment Posting</div>
          <template v-if="selectedLoan">
            <div class="selected-strip">
              <div>
                <strong>{{ selectedLoan.loan_no }}</strong>
                <span>{{ selectedLoan.first_name }} {{ selectedLoan.last_name }} · {{ selectedLoan.member_no }}</span>
              </div>
              <span class="balance-pill">{{ peso(selectedBalance) }} balance</span>
            </div>

            <div class="due-strip" v-if="nextPeriod">
              <div>
                <span class="due-label">Next due period</span>
                <strong>#{{ nextPeriod.period_no }}</strong>
              </div>
              <div>
                <span class="due-label">Due date</span>
                <strong>{{ formatDate(nextPeriod.due_date) }}</strong>
              </div>
              <div>
                <span class="due-label">Amount due</span>
                <strong>{{ peso(nextPeriod.amount_due) }}</strong>
              </div>
              <div>
                <span class="due-label">Breakdown</span>
                <strong>Principal {{ peso(nextPeriod.principal) }} · Interest {{ peso(nextPeriod.interest) }}</strong>
              </div>
            </div>

            <div class="posting-layout">
              <form class="payment-form" @submit.prevent="postPayment">
                <div class="form-group">
                  <label class="form-label">Period</label>
                  <select v-model.number="form.period_no" class="form-select" @change="syncAmount">
                    <option v-for="period in unpaidPeriods" :key="period.period_no" :value="period.period_no">
                      #{{ period.period_no }} · {{ formatDate(period.due_date) }} · {{ peso(period.amount_due) }}
                    </option>
                  </select>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">O.R. Number</label>
                    <input v-model="form.or_number" class="form-input" required />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input v-model="form.payment_date" type="date" class="form-input" required />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label class="form-label">Amount Paid</label>
                    <input v-model.number="form.amount_paid" type="number" class="form-input" min="0" step="0.01" required />
                  </div>
                  <div class="form-group">
                    <label class="form-label">Method</label>
                    <select v-model="form.method" class="form-select">
                      <option>Cash</option>
                      <option>Payroll Deduction</option>
                      <option>Bank Transfer</option>
                      <option>GCash</option>
                    </select>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Remarks</label>
                  <textarea v-model="form.remarks" class="form-textarea" placeholder="Optional notes"></textarea>
                </div>

                <button class="btn btn-primary submit-btn" type="submit">Post Payment</button>
              </form>
            </div>

            <div class="schedule-card">
              <div class="panel-title small">Period Status</div>
              <table class="payments-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Due</th>
                    <th>Amount Due</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="period in schedule" :key="period.period_no">
                    <td class="mono">{{ String(period.period_no).padStart(2, '0') }}</td>
                    <td>{{ formatDate(period.due_date) }}</td>
                    <td class="peso">{{ peso(period.amount_due) }}</td>
                    <td class="peso text-green">{{ peso(period.paid) }}</td>
                    <td class="peso">{{ peso(period.period_balance) }}</td>
                    <td><span :class="statusBadge(period.status)">{{ period.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <div v-else class="empty-state">
            <div class="empty-icon">₱</div>
            <div class="empty-title">Select a loan</div>
            <div class="text-muted">Choose a loan account from the left to post a collection.</div>
          </div>
        </section>

        <aside class="history-panel">
          <div class="panel-title">Payment History</div>
          <div class="history-list">
            <div v-for="payment in visiblePayments" :key="payment.id" class="history-row">
              <div>
                <div class="row-title">{{ payment.or_number }}</div>
                <div class="row-sub">Period #{{ payment.period_no }} · {{ payment.method || payment.payment_type || 'Payment' }} · {{ formatDate(payment.payment_date) }}</div>
              </div>
              <strong>{{ peso(payment.amount_paid) }}</strong>
            </div>
            <div v-if="!visiblePayments.length" class="empty-inline">No payments posted yet</div>
          </div>
        </aside>
      </section>

      <div v-if="importOpen" class="modal-overlay" @click.self="importOpen = false">
        <section class="modal-card import-modal">
          <div class="modal-header">
            <div class="modal-title">Import Payments</div>
            <button class="modal-close" @click="importOpen = false">×</button>
          </div>
          <div class="modal-body">
            <p class="text-muted">Review loan payment rows detected from the spreadsheet.</p>
            <table class="payments-table import-table">
              <thead><tr><th>Target</th><th>Member/Loan</th><th>OR #</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-for="row in importRows" :key="row._id">
                  <td>{{ row.target }}</td>
                  <td>{{ row.loan_no || row.member_no }}</td>
                  <td>{{ row.or_number }}</td>
                  <td>{{ row.payment_date }}</td>
                  <td class="peso">{{ peso(row.amount_paid) }}</td>
                  <td><span :class="row.error ? 'badge badge-rejected' : 'badge badge-approved'">{{ row.error || 'Ready' }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary" @click="importOpen = false">Cancel</button>
            <button class="btn btn-primary" :disabled="!validImportRows.length" @click="commitImport">Import {{ validImportRows.length }} Rows</button>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const route = useRoute()
const { success, error } = useToast()
const loans = ref([])
const payments = ref([])
const selectedLoan = ref(null)
const importOpen = ref(false)
const importRows = ref([])
const filterStatus = ref('ACTIVE')

const form = reactive({
  period_no: null,
  or_number: '',
  payment_date: new Date().toISOString().slice(0, 10),
  amount_paid: 0,
  method: 'Cash',
  remarks: '',
})

const loanRows = computed(() => loans.value.map(enrichLoan))
const selectedPayments = computed(() => payments.value.filter(payment => payment.loan_id === selectedLoan.value?.id))
const visiblePayments = computed(() => selectedLoan.value ? selectedPayments.value : payments.value)

const todayPayments = computed(() => {
  const today = new Date().toISOString().slice(0, 10)
  return payments.value.filter(payment => payment.payment_date === today)
})
const todayTotal = computed(() => todayPayments.value.reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0))
const totalPosted = computed(() => payments.value.reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0))
const validImportRows = computed(() => importRows.value.filter(row => !row.error))

const schedule = computed(() => {
  if (!selectedLoan.value) return []
  const paidByPeriod = selectedPayments.value.reduce((map, payment) => {
    map[payment.period_no] = (map[payment.period_no] || 0) + Number(payment.amount_paid || 0)
    return map
  }, {})

  return selectedLoan.value.baseSchedule.map(period => {
    const paid = +(paidByPeriod[period.period_no] || 0).toFixed(2)
    const periodBalance = Math.max(0, +(period.amount_due - paid).toFixed(2))
    const status = periodBalance === 0 ? 'PAID' : paid > 0 ? 'PARTIAL' : period.period_no <= 5 ? 'OVERDUE' : 'PENDING'
    return { ...period, paid, period_balance: periodBalance, status }
  })
})

const unpaidPeriods = computed(() => schedule.value.filter(period => period.status !== 'PAID'))
const nextPeriod = computed(() => unpaidPeriods.value[0])
const selectedBalance = computed(() => schedule.value.reduce((sum, period) => sum + period.period_balance, 0))


async function loadPayments() {
  payments.value = await api.getPayments()
}

function addDueDates(items, firstDueDate, frequency) {
  const start = firstDueDate ? new Date(firstDueDate) : new Date()
  const dayStep = frequency === 'weekly' ? 7 : frequency === 'bimonthly' ? 15 : 30
  return items.map((item, index) => {
    const due = new Date(start.getTime() + dayStep * index * 86400000)
    return {
      period_no: item.period,
      due_date: due.toISOString().slice(0, 10),
      principal: item.principal,
      interest: item.interest,
      amount_due: item.payment,
      balance: item.balance,
    }
  })
}

function enrichLoan(loan) {
  const calc = computeSchedule({
    principal: Number(loan.amount || 0),
    termMonths: Number(loan.term_months || 1),
    frequency: loan.frequency || 'monthly',
    annualRate: Number(loan.annual_rate || 0.12),
  })
  return {
    ...loan,
    total_payment: calc.totalPayment,
    n_periods: calc.nPeriods,
    baseSchedule: addDueDates(calc.schedule, loan.first_due_date, loan.frequency),
  }
}

function selectLoan(loan) {
  selectedLoan.value = loan
  syncFormToNext()
}

function syncFormToNext() {
  const period = nextPeriod.value
  form.period_no = period?.period_no ?? null
  form.amount_paid = period?.period_balance ?? period?.amount_due ?? 0
  form.or_number = `OR-${new Date().getFullYear()}-${String(payments.value.length + 1).padStart(4, '0')}`
  form.payment_date = new Date().toISOString().slice(0, 10)
  form.method = 'Cash'
  form.remarks = ''
}

function syncAmount() {
  const period = schedule.value.find(item => item.period_no === form.period_no)
  form.amount_paid = period?.period_balance ?? period?.amount_due ?? 0
}

async function postPayment() {
  if (!selectedLoan.value) return error('Select a loan first.')
  if (!form.period_no) return error('Select a period first.')
  if (!form.amount_paid || form.amount_paid <= 0) return error('Enter a valid payment amount.')

  try {
    const payment = await api.createPayment({
      loan_id: selectedLoan.value.id,
      loan_no: selectedLoan.value.loan_no,
      period_no: form.period_no,
      or_number: form.or_number,
      payment_date: form.payment_date,
      amount_paid: Number(form.amount_paid),
      method: form.method,
      payment_type: form.method,
      remarks: form.remarks,
    })
    payments.value = [payment, ...payments.value]
    success(`Payment ${payment.or_number} posted.`)
    await loadPayments()
    await loadLoans()
    syncFormToNext()
  } catch (err) {
    error(err.message || 'Could not post payment.')
  }
}

function parseDelimited(text) {
  const delimiter = text.includes('\t') ? '\t' : ','
  const lines = text.split(/\r?\n/).filter(line => line.trim())
  const headers = (lines.shift() || '').split(delimiter).map(h => h.trim().toLowerCase())
  return lines.map((line, index) => {
    const values = line.split(delimiter).map(v => v.trim())
    const row = { _id: index + 1 }
    headers.forEach((header, i) => { row[header] = values[i] || '' })
    return row
  })
}

function validateImportRow(row) {
  const target = 'loan'
  const amount = Number(row.amount_paid || row.amount || 0)
  const normalized = {
    ...row,
    target,
    amount_paid: amount,
    payment_date: row.payment_date || new Date().toISOString().slice(0, 10),
    or_number: row.or_number || row.or || '',
    method: row.method || 'Payroll Deduction',
  }
  if (!amount || amount <= 0) normalized.error = 'Invalid amount'
  else if (!normalized.or_number) normalized.error = 'Missing OR'
  else if (!loanRows.value.find(loan => loan.loan_no === normalized.loan_no)) normalized.error = 'Loan not found'
  return normalized
}

function handleImportFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    importRows.value = parseDelimited(String(reader.result || '')).map(row => validateImportRow(row))
    importOpen.value = true
  }
  reader.onerror = () => error('Could not read import file.')
  reader.readAsText(file)
}

async function commitImport() {
  let imported = 0
  for (const row of validImportRows.value) {
    const loan = loanRows.value.find(item => item.loan_no === row.loan_no)
    await api.createPayment({
      loan_id: loan.id, loan_no: loan.loan_no, period_no: Number(row.period_no || 1), or_number: row.or_number,
      payment_date: row.payment_date, amount_paid: row.amount_paid, method: row.method, payment_type: row.source || 'import', remarks: row.remarks || 'Imported payment',
    })
    imported++
  }
  await loadPayments()
  importOpen.value = false
  success(`${imported} payment row(s) imported.`)
}

function downloadImportGuide() {
  const rows = [
    ['loan_no','period_no','or_number','payment_date','amount_paid','method','source','company','remarks'],
    ['LN-2026-0001','2','OR-2026-0005','2026-05-30','1129.17','Payroll Deduction','company','CRS Holdings Corporation','May payroll remittance'],
  ]
  const csv = rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'crs-loan-payments-import-guide.csv'
  a.click()
  URL.revokeObjectURL(url)
}

async function loadAll() {
  await loadPayments()
  await loadLoans()
}

function statusBadge(status) {
  return {
    PAID: 'badge badge-approved',
    PENDING: 'badge badge-pending',
    OVERDUE: 'badge badge-rejected',
    PARTIAL: 'badge badge-draft',
  }[status] || 'badge badge-draft'
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

async function loadLoans() {
  const params = filterStatus.value ? { status: filterStatus.value } : {}
  loans.value = await api.getLoans(params)
  const routeLoan = route.query.loan_id ? loanRows.value.find(loan => Number(loan.id) === Number(route.query.loan_id)) : null
  const next = routeLoan || loanRows.value.find(loan => loan.id === selectedLoan.value?.id) || loanRows.value[0]
  if (next) selectLoan(next)
}

function applyRoutePrefill() {
  if (route.query.period_no && selectedLoan.value) {
    form.period_no = Number(route.query.period_no)
    syncAmount()
  }
}

onMounted(async () => {
  await loadAll()
  applyRoutePrefill()
})
</script>

<style scoped>
.payments-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header {
  padding:20px 28px;
  border-bottom:1px solid var(--coop-border);
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fff;
}
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:10px; align-items:center; }
.header-actions .form-select { width:160px; }
.import-btn input { display:none; }
.payments-body {
  flex:1;
  overflow:auto;
  padding:18px 22px 24px;
  display:flex;
  flex-direction:column;
  gap:14px;
}
.payment-kpis {
  display:grid;
  grid-template-columns:repeat(3, minmax(180px, 1fr));
  gap:12px;
}
.pay-kpi {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:16px 18px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
}
.kpi-label { color:var(--coop-muted); font-size:11px; font-weight:900; letter-spacing:.8px; text-transform:uppercase; }
.kpi-value { margin-top:8px; color:var(--coop-cream); font-family:var(--font-mono); font-size:24px; font-weight:900; }
.kpi-value.success { color:var(--status-approved); }
.kpi-value.danger { color:var(--coop-red); }
.kpi-sub { color:var(--coop-muted); font-size:12px; margin-top:6px; }

.import-modal { width:min(980px, 92vw); }
.import-table { margin-top:14px; }
.payment-grid {
  display:grid;
  grid-template-columns:320px minmax(0, 1fr);
  gap:14px;
  align-items:start;
}
.loan-panel, .posting-panel, .history-panel {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
  overflow:hidden;
  min-width:0;
}
.loan-panel { grid-row:1 / span 2; }
.history-panel { grid-column:2; }
.panel-title {
  padding:14px 16px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  font-size:16px;
  font-weight:900;
}
.panel-title.small { font-size:14px; }
.panel-search { padding:10px 12px; border-bottom:1px solid var(--coop-border); background:#F8FAFC; }
.loan-list, .history-list { padding:8px; display:flex; flex-direction:column; gap:8px; max-height:calc(100vh - 330px); overflow:auto; }
.loan-pick {
  border:1px solid var(--coop-border);
  background:#fff;
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  text-align:left;
  cursor:pointer;
}
.loan-pick:hover, .loan-pick.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.28); }
.loan-pick.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.row-title { color:var(--coop-cream); font-weight:900; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.loan-meta { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.selected-strip {
  margin:14px 16px 0;
  padding:12px;
  border:1px solid rgba(192,57,43,.18);
  border-radius:8px;
  background:var(--coop-red-dim);
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
}
.selected-strip div { display:flex; flex-direction:column; gap:2px; }
.selected-strip span { color:var(--coop-muted); font-size:12px; }
.balance-pill {
  padding:5px 8px;
  border-radius:999px;
  background:#fff;
  color:var(--coop-red) !important;
  font-weight:900;
  white-space:nowrap;
}
.due-strip {
  margin:12px 16px 0;
  display:grid;
  grid-template-columns:120px 150px 160px minmax(0, 1fr);
  gap:10px;
  padding:12px;
  background:#F8FAFC;
  border:1px solid var(--coop-border);
  border-radius:8px;
}
.due-strip > div {
  min-width:0;
  display:flex;
  flex-direction:column;
  gap:3px;
}
.due-label {
  color:var(--coop-muted);
  font-size:10px;
  font-weight:900;
  letter-spacing:.6px;
  text-transform:uppercase;
}
.due-strip strong {
  color:var(--coop-cream);
  font-size:13px;
  white-space:normal;
}
.posting-layout {
  display:block;
  padding:14px 16px;
}
.payment-form { display:flex; flex-direction:column; gap:12px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.submit-btn { justify-content:center; }
.schedule-card { padding:0 16px 16px; }
.payments-table { width:100%; border-collapse:collapse; }
.payments-table th {
  background:#F8FAFC;
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:.5px;
  text-transform:uppercase;
  padding:9px 10px;
  text-align:left;
  border-bottom:1px solid var(--coop-border);
}
.payments-table td {
  padding:9px 10px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  white-space:nowrap;
}
.history-row {
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
}
.history-row strong { color:var(--coop-cream); font-family:var(--font-mono); white-space:nowrap; }
.empty-inline { padding:22px; text-align:center; color:var(--coop-muted); }
@media (max-width: 1240px) {
  .payment-grid { grid-template-columns:1fr; }
  .loan-panel, .history-panel { grid-column:auto; grid-row:auto; }
  .loan-list, .history-list { max-height:none; }
}
@media (max-width: 760px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .payment-kpis, .form-row, .due-strip { grid-template-columns:1fr; }
  .payments-body { padding:14px; }
}
</style>
