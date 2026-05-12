<template>
  <div class="monitor-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Loan Monitoring</div>
        <div class="view-sub">Amortization schedules, period status, balances, and collection tracking</div>
      </div>
      <div class="header-actions">
        <select v-model="filterStatus" class="form-select" @change="loadLoans">
          <option value="">All statuses</option>
          <option value="ACTIVE">Active</option>
          <option value="PENDING">Pending</option>
          <option value="APPROVED">Approved</option>
          <option value="CLOSED">Closed</option>
        </select>
        <button class="btn btn-secondary" @click="loadLoans">Refresh</button>
      </div>
    </header>

    <main class="monitor-body">
      <section class="monitor-kpis">
        <div class="kpi-card">
          <div class="kpi-label">Loans Monitored</div>
          <div class="kpi-value">{{ monitoredLoans.length }}</div>
          <div class="kpi-sub">{{ activeCount }} active</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total Payable</div>
          <div class="kpi-value money">{{ peso(totals.payable) }}</div>
          <div class="kpi-sub">Principal plus interest</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Collected</div>
          <div class="kpi-value money success">{{ peso(totals.collected) }}</div>
          <div class="kpi-sub">{{ totals.paidPeriods }} paid periods</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Overdue Exposure</div>
          <div class="kpi-value money danger">{{ peso(totals.overdue) }}</div>
          <div class="kpi-sub">{{ totals.overduePeriods }} overdue periods</div>
        </div>
      </section>

      <section class="monitor-grid">
        <aside class="loan-list-card">
          <div class="panel-title">Loan Accounts</div>
          <div class="loan-list">
            <div
              v-for="loan in monitoredLoans"
              :key="loan.id"
              :class="['loan-row', selectedLoan?.id === loan.id && 'active']"
              role="button"
              tabindex="0"
              @click="selectLoan(loan)"
              @keydown.enter="selectLoan(loan)"
            >
              <div>
                <div class="row-title loan-member-name">{{ loan.first_name }} {{ loan.last_name }}</div>
                <div class="row-sub mono">{{ loan.loan_no }}</div>
                <div class="row-sub loan-employment">{{ loan.company || 'No company' }} · {{ loan.position || 'No position' }}</div>
                <div class="row-sub">{{ loan.loan_type_label }}</div>
              </div>
              <div class="loan-row-right">
                <span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span>
                <span class="peso">{{ peso(loan.amount) }}</span>
              </div>
            </div>
            <div v-if="!monitoredLoans.length" class="empty-inline">No loans found</div>
          </div>
        </aside>

        <section class="schedule-card">
          <template v-if="selectedLoan">
            <div class="schedule-header">
              <div>
                <div class="panel-title">{{ selectedLoan.loan_no }}</div>
                <div class="row-sub">
                  {{ selectedLoan.first_name }} {{ selectedLoan.last_name }} · {{ selectedLoan.member_no }} · {{ selectedLoan.company || 'No company' }} · {{ selectedLoan.position || 'No position' }} · {{ selectedLoan.loan_type_label }}
                </div>
              </div>
              <router-link :to="{ name: 'loans', query: { member_id: selectedLoan.member_id } }" class="btn btn-secondary btn-sm">
                Open Loan Desk
              </router-link>
            </div>

            <div class="loan-metrics">
              <div class="metric">
                <span>Principal</span>
                <strong>{{ peso(selectedLoan.amount) }}</strong>
              </div>
              <div class="metric">
                <span>Total Interest</span>
                <strong>{{ peso(scheduleTotals.interest) }}</strong>
              </div>
              <div class="metric">
                <span>Outstanding</span>
                <strong>{{ peso(scheduleTotals.outstanding) }}</strong>
              </div>
              <div class="metric">
                <span>Collection Rate</span>
                <strong>{{ scheduleTotals.rate }}%</strong>
              </div>
            </div>

            <div class="period-actions">
              <button class="action-btn primary" @click="recordNextPayment">Record Next Payment</button>
              <button class="action-btn" @click="billNextPeriod">Bill Next Due Period</button>
              <button class="action-btn subtle" @click="flagOldestOverdue">Flag Oldest Overdue</button>
            </div>

            <div class="schedule-table-wrap">
              <table class="monitor-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Amount Due</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="period in schedule" :key="period.period_no">
                    <td class="mono period-no">{{ String(period.period_no).padStart(2, '0') }}</td>
                    <td>{{ formatDate(period.due_date) }}</td>
                    <td class="peso">{{ peso(period.principal) }}</td>
                    <td class="peso">{{ peso(period.interest) }}</td>
                    <td class="peso fw-600">{{ peso(period.amount_due) }}</td>
                    <td class="peso">{{ peso(period.balance) }}</td>
                    <td><span :class="statusBadge(period.status)">{{ period.status }}</span></td>
                    <td>
                      <div class="inline-actions">
                        <button class="mini-btn" @click="collectPeriod(period)">Collect</button>
                        <button class="mini-btn" @click="billPeriod(period)">Bill</button>
                        <button class="mini-btn danger" @click="markOverdue(period)">Overdue</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <div v-else class="empty-state">
            <div class="empty-icon">◈</div>
            <div class="empty-title">Select a loan</div>
            <div class="text-muted">Click a loan account to inspect its amortization and collection status.</div>
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { success, error } = useToast()
const loans = ref([])
const payments = ref([])
const bills = ref([])
const filterStatus = ref('ACTIVE')
const selectedLoan = ref(null)
const statusOverrides = ref({})

function storageKey(loanId) {
  return `crs-monitoring-status-${loanId}`
}

function readOverrides(loanId) {
  return JSON.parse(localStorage.getItem(storageKey(loanId)) || '{}')
}

function writeOverrides(loanId, value) {
  localStorage.setItem(storageKey(loanId), JSON.stringify(value))
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

function defaultStatus(period, loan) {
  if (loan.status === 'CLOSED') return 'PAID'
  if (loan.status === 'PENDING' || loan.status === 'APPROVED') return 'PENDING'
  if (period.period_no <= 4) return 'PAID'
  if (period.period_no === 5) return 'OVERDUE'
  if (period.period_no === 6) return 'PARTIAL'
  return 'PENDING'
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
    total_interest: calc.totalInterest,
    total_payment: calc.totalPayment,
    n_periods: calc.nPeriods,
    first_payment_amt: calc.firstPayment,
    last_payment_amt: calc.lastPayment,
    baseSchedule: addDueDates(calc.schedule, loan.first_due_date, loan.frequency),
  }
}

const monitoredLoans = computed(() => loans.value.map(enrichLoan))
const activeCount = computed(() => monitoredLoans.value.filter(loan => loan.status === 'ACTIVE').length)

const schedule = computed(() => {
  if (!selectedLoan.value) return []
  return selectedLoan.value.baseSchedule.map(period => {
    const paid = paidForPeriod(selectedLoan.value.id, period.period_no)
    const billed = billedForPeriod(selectedLoan.value.id, period.period_no)
    const dueDate = new Date(period.due_date)
    const today = new Date()
    let status = 'PENDING'
    if (paid >= Number(period.amount_due || 0)) status = 'PAID'
    else if (paid > 0) status = 'PARTIAL'
    else if (statusOverrides.value[period.period_no]) status = statusOverrides.value[period.period_no]
    else if (billed) status = 'BILLED'
    else if (dueDate < new Date(today.getFullYear(), today.getMonth(), today.getDate())) status = 'OVERDUE'
    else status = defaultStatus(period, selectedLoan.value)
    return { ...period, paid, billed, status }
  })
})

const scheduleTotals = computed(() => {
  const base = schedule.value.reduce((sum, period) => {
    sum.interest += period.interest
    sum.payable += period.amount_due
    if (period.status === 'PAID') {
      sum.collected += period.amount_due
      sum.paidPeriods += 1
    } else if (period.status === 'PARTIAL') {
      sum.collected += Number(period.paid || 0)
      sum.outstanding += Math.max(0, Number(period.amount_due || 0) - Number(period.paid || 0))
    } else {
      sum.outstanding += period.amount_due
    }
    if (period.status === 'OVERDUE') {
      sum.overdue += period.amount_due
      sum.overduePeriods += 1
    }
    return sum
  }, { interest: 0, payable: 0, collected: 0, outstanding: 0, overdue: 0, paidPeriods: 0, overduePeriods: 0 })

  return {
    interest: +base.interest.toFixed(2),
    payable: +base.payable.toFixed(2),
    collected: +base.collected.toFixed(2),
    outstanding: +base.outstanding.toFixed(2),
    overdue: +base.overdue.toFixed(2),
    paidPeriods: base.paidPeriods,
    overduePeriods: base.overduePeriods,
    rate: base.payable ? Math.round((base.collected / base.payable) * 100) : 0,
  }
})

const totals = computed(() => {
  if (!selectedLoan.value) {
    return monitoredLoans.value.reduce((sum, loan) => {
      const calc = computeSchedule({
        principal: Number(loan.amount || 0),
        termMonths: Number(loan.term_months || 1),
        frequency: loan.frequency || 'monthly',
        annualRate: Number(loan.annual_rate || 0.12),
      })
      sum.payable += calc.totalPayment
      sum.collected += loan.status === 'CLOSED' ? calc.totalPayment : loan.status === 'ACTIVE' ? calc.totalPayment * 0.25 : 0
      sum.overdue += loan.status === 'ACTIVE' ? calc.firstPayment : 0
      sum.paidPeriods += loan.status === 'ACTIVE' ? 4 : loan.status === 'CLOSED' ? calc.nPeriods : 0
      sum.overduePeriods += loan.status === 'ACTIVE' ? 1 : 0
      return sum
    }, { payable: 0, collected: 0, overdue: 0, paidPeriods: 0, overduePeriods: 0 })
  }
  return scheduleTotals.value
})

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

function statusBadge(status) {
  return {
    PAID: 'badge badge-approved',
    BILLED: 'badge badge-active',
    PENDING: 'badge badge-pending',
    OVERDUE: 'badge badge-rejected',
    PARTIAL: 'badge badge-draft',
  }[status] || 'badge badge-draft'
}

function paidForPeriod(loanId, periodNo) {
  return payments.value
    .filter(payment => Number(payment.loan_id) === Number(loanId) && Number(payment.period_no) === Number(periodNo))
    .reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0)
}

function billedForPeriod(loanId, periodNo) {
  const key = `${loanId}-${periodNo}`
  return bills.value
    .filter(bill => bill.status !== 'CANCELLED')
    .flatMap(bill => bill.items || [])
    .some(item => item.schedule_key === key || (Number(item.loan_id) === Number(loanId) && Number(item.period_no) === Number(periodNo)))
}

function selectLoan(loan) {
  selectedLoan.value = loan
  statusOverrides.value = readOverrides(loan.id)
}

function setPeriodStatus(periodNo, status) {
  if (!selectedLoan.value) return
  statusOverrides.value = { ...statusOverrides.value, [periodNo]: status }
  writeOverrides(selectedLoan.value.id, statusOverrides.value)
}

function nextCollectiblePeriod() {
  return schedule.value.find(period => !['PAID', 'BILLED'].includes(period.status))
}

function collectPeriod(period) {
  if (!selectedLoan.value || !period) return
  router.push({ name: 'payments', query: { mode: 'loan', loan_id: selectedLoan.value.id, period_no: period.period_no } })
}

function billPeriod(period) {
  if (!selectedLoan.value || !period) return
  router.push({
    name: 'billing',
    query: {
      open: 'generate',
      company: selectedLoan.value.company || '',
      date_from: period.due_date,
      date_to: period.due_date,
    },
  })
}

function recordNextPayment() {
  const next = nextCollectiblePeriod()
  if (next) collectPeriod(next)
}

function billNextPeriod() {
  const next = schedule.value.find(period => ['PENDING', 'OVERDUE'].includes(period.status))
  if (next) billPeriod(next)
}

async function markOverdue(period) {
  if (!selectedLoan.value || !period) return
  try {
    await api.updateSchedulePeriod({ loan_id: selectedLoan.value.id, period_no: period.period_no, status: 'OVERDUE' })
    setPeriodStatus(period.period_no, 'OVERDUE')
    success(`Period #${period.period_no} flagged overdue.`)
  } catch (err) {
    error(err.message || 'Could not flag period overdue.')
  }
}

function flagOldestOverdue() {
  const next = schedule.value.find(period => period.status === 'PENDING' || period.status === 'PARTIAL')
  if (next) markOverdue(next)
}

async function loadLoans() {
  const params = filterStatus.value ? { status: filterStatus.value } : {}
  const [loanRows, paymentRows, billRows] = await Promise.all([api.getLoans(params), api.getPayments(), api.getBills()])
  loans.value = loanRows
  payments.value = paymentRows
  bills.value = billRows
  const nextSelected = monitoredLoans.value.find(loan => loan.id === selectedLoan.value?.id) || monitoredLoans.value[0]
  if (nextSelected) selectLoan(nextSelected)
}

onMounted(loadLoans)
</script>

<style scoped>
.monitor-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
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
.header-actions .form-select { width:170px; }
.monitor-body {
  flex:1;
  overflow:auto;
  padding:18px 22px 24px;
  display:flex;
  flex-direction:column;
  gap:14px;
  font-family:var(--font-sans);
  color:var(--coop-cream);
}
.monitor-grid,
.loan-list-card,
.schedule-card,
.loan-row,
.schedule-header,
.loan-metrics,
.period-actions,
.monitor-table,
.mini-btn,
.action-btn {
  font-family:var(--font-sans);
  letter-spacing:0;
}
.monitor-kpis {
  display:grid !important;
  grid-template-columns:repeat(4, minmax(160px, 1fr)) !important;
  gap:12px;
}
.kpi-card {
  min-height:108px;
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:15px 18px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
}
.kpi-label {
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:.8px;
  text-transform:uppercase;
}
.kpi-value {
  margin-top:8px;
  color:var(--coop-cream);
  font-size:26px;
  line-height:1.05;
  font-weight:900;
  letter-spacing:0;
}
.kpi-value.money {
  font-family:var(--font-mono);
  font-size:22px;
  letter-spacing:0;
}
.kpi-value.success { color:var(--status-approved); }
.kpi-value.danger { color:var(--coop-red); }
.kpi-sub {
  margin-top:8px;
  color:var(--coop-muted);
  font-size:12px;
  line-height:1.35;
}
.monitor-grid {
  display:grid;
  grid-template-columns:320px minmax(0, 1fr);
  gap:16px;
  min-height:0;
  align-items:start;
}
.loan-list-card, .schedule-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 24px rgba(31,41,55,0.04);
  overflow:hidden;
}
.loan-list-card {
  display:flex;
  flex-direction:column;
  max-height:calc(100vh - 282px);
}
.panel-title {
  color:var(--coop-cream);
  font-size:16px;
  font-weight:900;
  font-family:var(--font-sans);
  letter-spacing:0;
}
.loan-list-card > .panel-title {
  padding:16px;
  border-bottom:1px solid var(--coop-border);
}
.loan-list { overflow:auto; padding:8px; display:flex; flex-direction:column; gap:8px; }
.loan-row {
  width:100%;
  display:grid;
  grid-template-columns:minmax(0, 1fr) auto;
  gap:12px;
  text-align:left;
  padding:13px 14px;
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#fff;
  cursor:pointer;
  transition:all var(--tx);
  outline:none;
}
.loan-row:hover, .loan-row.active {
  background:var(--coop-red-dim);
  border-color:rgba(192,57,43,.28);
}
.loan-row.active {
  box-shadow:inset 4px 0 0 var(--coop-red);
}
.row-title { color:var(--coop-cream); font-weight:900; line-height:1.25; font-family:var(--font-sans); letter-spacing:0; }
.row-sub { color:var(--coop-muted); font-size:12px; line-height:1.35; margin-top:3px; font-family:var(--font-sans); letter-spacing:0; }
.loan-member-name { font-size:14px; }
.loan-employment { max-width:190px; }
.loan-row-right {
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:6px;
  flex-shrink:0;
}
.schedule-card {
  display:flex;
  flex-direction:column;
  min-width:0;
  max-height:calc(100vh - 282px);
}
.schedule-header {
  padding:16px;
  border-bottom:1px solid var(--coop-border);
  display:flex;
  justify-content:space-between;
  gap:14px;
  align-items:center;
}
.loan-metrics {
  display:grid;
  grid-template-columns:repeat(4, minmax(0, 1fr));
  border-bottom:1px solid var(--coop-border);
}
.metric {
  padding:14px 16px;
  border-right:1px solid var(--coop-border);
  display:flex;
  flex-direction:column;
  gap:4px;
}
.metric:last-child { border-right:0; }
.metric span {
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  text-transform:uppercase;
  letter-spacing:0.06em;
  font-family:var(--font-sans);
}
.metric strong {
  color:var(--coop-cream);
  font-size:16px;
  line-height:1.25;
  letter-spacing:0;
  font-family:var(--font-sans);
}
.period-actions {
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  padding:12px 16px;
  border-bottom:1px solid var(--coop-border);
}
.action-btn {
  border:1px solid var(--coop-border);
  background:#fff;
  color:var(--coop-cream);
  border-radius:6px;
  padding:8px 12px;
  font-weight:800;
  font-size:12px;
  cursor:pointer;
}
.action-btn.primary {
  background:var(--coop-red);
  color:#fff;
  border-color:var(--coop-red);
}
.action-btn.subtle {
  color:var(--coop-muted);
  border-color:transparent;
}
.action-btn:hover {
  background:var(--coop-red-dim);
  border-color:rgba(192,57,43,.28);
  color:var(--coop-red);
}
.action-btn.primary:hover {
  background:var(--coop-red-soft);
  color:#fff;
}
.schedule-table-wrap {
  overflow:auto;
  flex:1;
}
.monitor-table {
  width:100%;
  border-collapse:collapse;
  table-layout:auto;
  background:#fff;
}
.monitor-table th {
  position:sticky;
  top:0;
  z-index:1;
  background:#F8FAFC;
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:0.06em;
  font-family:var(--font-sans);
  text-transform:uppercase;
  padding:10px 12px;
  text-align:left;
  border-bottom:1px solid var(--coop-border);
  white-space:nowrap;
}
.monitor-table td {
  padding:10px 12px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  font-size:12px;
  line-height:1.35;
  vertical-align:middle;
  white-space:nowrap;
  font-family:var(--font-sans);
}
.period-no {
  color:var(--coop-muted);
  font-weight:900;
}
.inline-actions {
  display:flex;
  gap:4px;
  flex-wrap:nowrap;
}
.mini-btn {
  border:1px solid var(--coop-border);
  background:#fff;
  color:var(--coop-muted);
  border-radius:4px;
  padding:3px 6px;
  font-size:11px;
  cursor:pointer;
}
.mini-btn:hover {
  color:var(--coop-red);
  border-color:rgba(192,57,43,.35);
  background:var(--coop-red-dim);
}
.mini-btn.danger {
  color:var(--coop-red);
}
.empty-inline {
  padding:24px;
  color:var(--coop-muted);
  text-align:center;
}
@media (max-width: 1180px) {
  .monitor-kpis, .loan-metrics { grid-template-columns:1fr 1fr !important; }
  .monitor-grid { grid-template-columns:1fr; }
  .loan-list-card, .schedule-card { max-height:none; }
}
@media (max-width: 720px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .monitor-kpis, .loan-metrics { grid-template-columns:1fr !important; }
  .monitor-body { padding:14px; }
}
</style>
