<template>
  <div class="restructure-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Loan Restructuring</div>
        <div class="view-sub">Preview new terms, compare schedules, and confirm restructuring records</div>
      </div>
      <div class="header-actions">
        <select v-model="filterStatus" class="form-select" @change="loadLoans">
          <option value="">All loans</option>
          <option value="ACTIVE">Active</option>
          <option value="PENDING">Pending</option>
          <option value="APPROVED">Approved</option>
        </select>
        <button class="btn btn-secondary" @click="loadLoans">Refresh</button>
      </div>
    </header>

    <main class="restructure-body">
      <aside class="loan-panel">
        <div class="panel-title">Eligible Loan Accounts</div>
        <div class="loan-search">
          <input
            v-model.trim="searchTerm"
            class="form-input"
            type="search"
            placeholder="Search loan no, member, or type"
          />
          <button v-if="searchTerm" class="btn btn-secondary clear-search" type="button" @click="searchTerm = ''">Clear</button>
        </div>
        <div class="loan-list">
          <button
            v-for="loan in displayedLoanRows"
            :key="loan.id"
            :class="['loan-row', selectedLoan?.id === loan.id && 'active']"
            @click="selectLoan(loan)"
          >
            <div>
              <div class="row-title loan-member-name">{{ loan.first_name }} {{ loan.last_name }}</div>
              <div class="row-sub mono">{{ loan.loan_no }}</div>
              <div class="row-sub loan-employment">{{ loan.company || 'No company' }} · {{ loan.position || 'No position' }}</div>
              <div class="row-sub">{{ loan.loan_type_label }}</div>
            </div>
            <div class="loan-meta">
              <span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span>
              <span class="peso">{{ peso(loan.amount) }}</span>
            </div>
          </button>
          <div v-if="!displayedLoanRows.length" class="empty-inline">
            {{ searchTerm ? 'No matching active loans' : 'No loans available' }}
          </div>
        </div>
      </aside>

      <section class="workspace">
        <template v-if="selectedLoan">
          <section class="loan-summary">
            <div class="summary-main">
              <div class="eyebrow">Selected loan</div>
              <h1>{{ selectedLoan.loan_no }}</h1>
              <p>{{ selectedLoan.first_name }} {{ selectedLoan.last_name }} · {{ selectedLoan.member_no }} · {{ selectedLoan.company || 'No company' }} · {{ selectedLoan.position || 'No position' }} · {{ selectedLoan.loan_type_label }}</p>
            </div>
            <div class="summary-grid">
              <div>
                <span>Original Amount</span>
                <strong>{{ peso(selectedLoan.amount) }}</strong>
              </div>
              <div>
                <span>Estimated Remaining</span>
                <strong>{{ peso(remainingBalance) }}</strong>
              </div>
              <div>
                <span>Old Payment</span>
                <strong>{{ peso(oldSchedule.firstPayment || 0) }}</strong>
              </div>
              <div>
                <span>Restructuring Count</span>
                <strong>{{ existingRecords.length }}</strong>
              </div>
            </div>
          </section>

          <section class="work-grid">
            <form class="terms-card" @submit.prevent="confirmRestructure">
              <div class="panel-title inline">New Terms</div>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">New Principal</label>
                  <input v-model.number="form.new_amount" class="form-input" type="number" min="0" step="1000" />
                </div>
                <div class="form-group">
                  <label class="form-label">Annual Rate</label>
                  <input v-model.number="form.new_annual_rate" class="form-input" type="number" min="0" step="0.01" />
                </div>
                <div class="form-group">
                  <label class="form-label">New Term</label>
                  <input v-model.number="form.new_term_months" class="form-input" type="number" min="1" />
                </div>
                <div class="form-group">
                  <label class="form-label">Frequency</label>
                  <select v-model="form.frequency" class="form-select">
                    <option value="monthly">Monthly</option>
                    <option value="bimonthly">Bi-Monthly</option>
                    <option value="weekly">Weekly</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">First Due Date</label>
                  <input v-model="form.first_due_date" class="form-input" type="date" />
                </div>
                <div class="form-group">
                  <label class="form-label">Reason</label>
                  <select v-model="form.reason" class="form-select">
                    <option>Member hardship</option>
                    <option>Payroll adjustment</option>
                    <option>Overdue recovery</option>
                    <option>Management approved</option>
                  </select>
                </div>
              </div>
              <textarea v-model="form.notes" class="form-textarea" placeholder="Manager notes"></textarea>
              <button class="btn btn-primary submit-btn" type="submit">Confirm Restructuring</button>
            </form>

            <section class="preview-card">
              <div class="panel-title inline">Side-by-Side Preview</div>
              <div class="compare-grid">
                <div class="compare-item">
                  <span>Old total payable</span>
                  <strong>{{ peso(oldSchedule.totalPayment || 0) }}</strong>
                </div>
                <div class="compare-item highlight">
                  <span>New total payable</span>
                  <strong>{{ peso(newSchedule.totalPayment || 0) }}</strong>
                </div>
                <div class="compare-item">
                  <span>Old first payment</span>
                  <strong>{{ peso(oldSchedule.firstPayment || 0) }}</strong>
                </div>
                <div class="compare-item highlight">
                  <span>New first payment</span>
                  <strong>{{ peso(newSchedule.firstPayment || 0) }}</strong>
                </div>
                <div class="compare-item">
                  <span>Old periods</span>
                  <strong>{{ oldSchedule.nPeriods || 0 }}</strong>
                </div>
                <div class="compare-item highlight">
                  <span>New periods</span>
                  <strong>{{ newSchedule.nPeriods || 0 }}</strong>
                </div>
              </div>

              <div class="impact-box" :class="{ positive: paymentDelta < 0 }">
                <span>Payment impact</span>
                <strong>{{ paymentDelta >= 0 ? '+' : '' }}{{ peso(paymentDelta) }}</strong>
                <small>Difference between old and new first payment</small>
              </div>
            </section>
          </section>

          <section class="schedule-card">
            <div class="panel-title inline">New Amortization Preview</div>
            <table class="schedule-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Due Date</th>
                  <th>Principal</th>
                  <th>Interest</th>
                  <th>Amount Due</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="period in previewRows" :key="period.period">
                  <td class="mono">{{ String(period.period).padStart(2, '0') }}</td>
                  <td>{{ formatPreviewDate(period.period) }}</td>
                  <td class="peso">{{ peso(period.principal) }}</td>
                  <td class="peso">{{ peso(period.interest) }}</td>
                  <td class="peso fw-600">{{ peso(period.payment) }}</td>
                  <td class="peso">{{ peso(period.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="history-card">
            <div class="panel-title inline">Restructuring History</div>
            <div class="history-list">
              <div v-for="record in existingRecords" :key="record.id" class="history-row">
                <div>
                  <div class="row-title">{{ record.restructuring_no }}</div>
                  <div class="row-sub">{{ record.reason }} · {{ formatDate(record.created_at) }}</div>
                </div>
                <strong>{{ peso(record.new_amount) }} · {{ record.new_term_months }} mo</strong>
              </div>
              <div v-if="!existingRecords.length" class="empty-inline">No restructuring records yet</div>
            </div>
          </section>
        </template>

        <div v-else class="empty-state">
          <div class="empty-icon">⟲</div>
          <div class="empty-title">Select a loan</div>
          <div class="text-muted">Choose a loan account to preview restructuring terms.</div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const RECORD_KEY = 'crs-coop-preview-restructurings'

const { success, error } = useToast()
const loans = ref([])
const records = ref([])
const selectedLoan = ref(null)
const filterStatus = ref('ACTIVE')
const searchTerm = ref('')

const form = reactive({
  new_amount: 0,
  new_annual_rate: 0.1,
  new_term_months: 24,
  frequency: 'bimonthly',
  first_due_date: new Date().toISOString().slice(0, 10),
  reason: 'Member hardship',
  notes: '',
})

const loanRows = computed(() => loans.value.map(enrichLoan))
const displayedLoanRows = computed(() => {
  const query = searchTerm.value.toLowerCase()
  if (!query) return loanRows.value

  return loanRows.value.filter(loan => [
    loan.loan_no,
    loan.member_no,
    loan.first_name,
    loan.last_name,
    `${loan.first_name} ${loan.last_name}`,
    loan.loan_type_label,
    loan.status,
  ].some(value => String(value || '').toLowerCase().includes(query)))
})
const oldSchedule = computed(() => selectedLoan.value?.scheduleCalc || { schedule: [] })
const remainingBalance = computed(() => {
  if (!selectedLoan.value) return 0
  const paidPeriods = selectedLoan.value.status === 'ACTIVE' ? 4 : 0
  const balanceIndex = Math.max(0, paidPeriods - 1)
  return oldSchedule.value.schedule?.[balanceIndex]?.balance ?? selectedLoan.value.amount
})

const newSchedule = computed(() => computeSchedule({
  principal: Number(form.new_amount || 0),
  termMonths: Number(form.new_term_months || 1),
  frequency: form.frequency,
  annualRate: Number(form.new_annual_rate || 0),
}))

const paymentDelta = computed(() => +(Number(newSchedule.value.firstPayment || 0) - Number(oldSchedule.value.firstPayment || 0)).toFixed(2))
const previewRows = computed(() => newSchedule.value.schedule.slice(0, 24))
const existingRecords = computed(() => records.value.filter(record => record.loan_id === selectedLoan.value?.id))

function enrichLoan(loan) {
  return {
    ...loan,
    scheduleCalc: computeSchedule({
      principal: Number(loan.amount || 0),
      termMonths: Number(loan.term_months || 1),
      frequency: loan.frequency || 'monthly',
      annualRate: Number(loan.annual_rate || 0.12),
    }),
  }
}

function loadRecords() {
  records.value = JSON.parse(localStorage.getItem(RECORD_KEY) || '[]')
}

function saveRecords() {
  localStorage.setItem(RECORD_KEY, JSON.stringify(records.value))
}

function selectLoan(loan) {
  selectedLoan.value = loan
  form.new_amount = Math.round(remainingBalance.value / 1000) * 1000
  form.new_annual_rate = Number(loan.annual_rate || 0.1)
  form.new_term_months = Math.max(12, Math.ceil(Number(loan.term_months || 12) / 2))
  form.frequency = loan.frequency || 'bimonthly'
  form.first_due_date = new Date().toISOString().slice(0, 10)
}

function confirmRestructure() {
  if (!selectedLoan.value) return error('Select a loan first.')
  if (!form.new_amount || form.new_amount <= 0) return error('Enter a valid new principal.')

  const nextNo = `RST-${new Date().getFullYear()}-${String(records.value.length + 1).padStart(4, '0')}`
  const record = {
    id: Date.now(),
    restructuring_no: nextNo,
    loan_id: selectedLoan.value.id,
    loan_no: selectedLoan.value.loan_no,
    old_amount: selectedLoan.value.amount,
    old_first_payment: oldSchedule.value.firstPayment,
    new_amount: Number(form.new_amount),
    new_annual_rate: Number(form.new_annual_rate),
    new_term_months: Number(form.new_term_months),
    frequency: form.frequency,
    first_due_date: form.first_due_date,
    reason: form.reason,
    notes: form.notes,
    new_first_payment: newSchedule.value.firstPayment,
    new_total_payment: newSchedule.value.totalPayment,
    created_at: new Date().toISOString().slice(0, 10),
  }

  records.value = [record, ...records.value]
  saveRecords()
  success(`${nextNo} confirmed.`)
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

function formatPreviewDate(periodNo) {
  const start = form.first_due_date ? new Date(form.first_due_date) : new Date()
  const dayStep = form.frequency === 'weekly' ? 7 : form.frequency === 'bimonthly' ? 15 : 30
  const date = new Date(start.getTime() + dayStep * (periodNo - 1) * 86400000)
  return formatDate(date)
}

async function loadLoans() {
  const params = filterStatus.value ? { status: filterStatus.value } : {}
  loans.value = await api.getLoans(params)
  const next = loanRows.value.find(loan => loan.id === selectedLoan.value?.id) || loanRows.value[0]
  if (next) selectLoan(next)
}

onMounted(async () => {
  loadRecords()
  await loadLoans()
})
</script>

<style scoped>
.restructure-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
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
.restructure-body {
  flex:1;
  overflow:auto;
  padding:18px 22px 24px;
  display:grid;
  grid-template-columns:320px minmax(0, 1fr);
  gap:14px;
}
.restructure-body,
.loan-panel,
.workspace,
.loan-summary,
.terms-card,
.preview-card,
.schedule-card,
.history-card,
.loan-row,
.summary-grid,
.compare-grid,
.impact-box,
.schedule-table,
.history-row {
  font-family:var(--font-sans);
  letter-spacing:0;
}
.loan-panel, .loan-summary, .terms-card, .preview-card, .schedule-card, .history-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
  overflow:hidden;
}
.loan-panel { max-height:calc(100vh - 190px); }
.panel-title {
  padding:14px 16px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  font-size:16px;
  font-weight:900;
  font-family:var(--font-sans);
  letter-spacing:0;
}
.panel-title.inline { border-bottom:0; padding:0 0 14px; }
.loan-search {
  padding:10px;
  border-bottom:1px solid var(--coop-border);
  display:grid;
  grid-template-columns:minmax(0, 1fr) auto;
  gap:8px;
  background:#F8FAFC;
}
.loan-search .form-input { height:38px; }
.clear-search { height:38px; padding-inline:12px; }
.loan-list { padding:8px; display:flex; flex-direction:column; gap:8px; overflow:auto; }
.loan-row {
  border:1px solid var(--coop-border);
  background:#fff;
  border-radius:8px;
  padding:12px;
  display:grid;
  grid-template-columns:minmax(0, 1fr) auto;
  gap:10px;
  text-align:left;
  cursor:pointer;
}
.loan-row:hover, .loan-row.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.28); }
.loan-row.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.row-title {
  color:var(--coop-cream);
  font-family:var(--font-sans);
  font-weight:900;
  letter-spacing:0;
  line-height:1.25;
}
.row-sub {
  color:var(--coop-muted);
  font-family:var(--font-sans);
  font-size:12px;
  letter-spacing:0;
  line-height:1.35;
  margin-top:3px;
}
.loan-member-name { font-size:14px; }
.loan-employment { max-width:190px; }
.loan-meta { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
.workspace { min-width:0; display:flex; flex-direction:column; gap:14px; }
.loan-summary {
  padding:18px;
  display:grid;
  grid-template-columns:minmax(0, 1fr) 1.1fr;
  gap:18px;
}
.eyebrow {
  color:var(--coop-red);
  font-family:var(--font-sans);
  font-size:11px;
  font-weight:900;
  letter-spacing:.06em;
  text-transform:uppercase;
}
.summary-main h1 {
  color:var(--coop-cream);
  font-family:var(--font-sans);
  font-size:28px;
  font-weight:900;
  letter-spacing:0;
  margin:4px 0 0;
}
.summary-main p {
  color:var(--coop-muted);
  font-family:var(--font-sans);
  letter-spacing:0;
  margin-top:4px;
}
.summary-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:10px;
}
.summary-grid div, .compare-item {
  background:#F8FAFC;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  flex-direction:column;
  gap:4px;
}
.summary-grid span, .compare-item span {
  color:var(--coop-muted);
  font-family:var(--font-sans);
  font-size:10px;
  font-weight:900;
  letter-spacing:.06em;
  text-transform:uppercase;
}
.summary-grid strong, .compare-item strong {
  color:var(--coop-cream);
  font-family:var(--font-sans);
  font-size:16px;
  font-weight:900;
  letter-spacing:0;
}
.work-grid { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:14px; }
.terms-card, .preview-card, .schedule-card, .history-card { padding:16px; }
.form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:12px; }
.submit-btn { width:100%; justify-content:center; margin-top:12px; }
.compare-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.compare-item.highlight { background:var(--coop-red-dim); border-color:rgba(192,57,43,.2); }
.impact-box {
  margin-top:12px;
  border-radius:8px;
  padding:14px;
  background:#fff7f6;
  border:1px solid rgba(192,57,43,.22);
  display:flex;
  flex-direction:column;
  gap:3px;
}
.impact-box.positive { background:rgba(39,174,96,.08); border-color:rgba(39,174,96,.25); }
.impact-box span {
  color:var(--coop-muted);
  font-family:var(--font-sans);
  font-size:10px;
  font-weight:900;
  letter-spacing:.06em;
  text-transform:uppercase;
}
.impact-box strong {
  color:var(--coop-red);
  font-family:var(--font-sans);
  font-size:22px;
  font-weight:900;
  letter-spacing:0;
}
.impact-box.positive strong { color:var(--status-approved); }
.impact-box small { color:var(--coop-muted); font-family:var(--font-sans); }
.schedule-table { width:100%; border-collapse:collapse; }
.schedule-table th {
  background:#F8FAFC;
  color:var(--coop-muted);
  font-family:var(--font-sans);
  font-size:11px;
  font-weight:900;
  letter-spacing:.06em;
  text-transform:uppercase;
  padding:9px 10px;
  text-align:left;
  border-bottom:1px solid var(--coop-border);
}
.schedule-table td {
  padding:9px 10px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  font-family:var(--font-sans);
  font-size:13px;
  letter-spacing:0;
  line-height:1.35;
  white-space:nowrap;
}
.history-list { display:flex; flex-direction:column; gap:8px; }
.history-row {
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
}
.history-row strong {
  color:var(--coop-cream);
  font-family:var(--font-sans);
  font-weight:900;
  letter-spacing:0;
  white-space:nowrap;
}
.empty-inline { padding:22px; text-align:center; color:var(--coop-muted); }
@media (max-width: 1180px) {
  .restructure-body, .loan-summary, .work-grid { grid-template-columns:1fr; }
  .loan-panel { max-height:none; }
}
@media (max-width: 760px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .form-grid, .summary-grid, .compare-grid { grid-template-columns:1fr; }
  .restructure-body { padding:14px; }
}
</style>
