<template>
  <div class="collection-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Collection Summary</div>
        <div class="view-sub">Expected vs collected by loan type and status</div>
      </div>
      <div class="header-actions">
        <select v-model="filters.status" class="form-select" @change="load">
          <option value="">All statuses</option>
          <option value="PENDING">Pending</option>
          <option value="APPROVED">Approved</option>
          <option value="ACTIVE">Active</option>
          <option value="CLOSED">Closed</option>
        </select>
        <input type="date" v-model="filters.from" class="form-input" style="width:160px; min-height:44px; border-radius:9px;" @change="load">
        <input type="date" v-model="filters.to" class="form-input" style="width:160px; min-height:44px; border-radius:9px;" @change="load">
        <a :href="api.getReportCsvUrl('collection', { from: filters.from, to: filters.to })" target="_blank" class="btn btn-secondary" style="min-height:44px; border-radius:9px; text-decoration:none;">Download CSV</a>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="collection-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
      <section class="summary-strip">
        <div>
          <div class="section-kicker">Collections Summary</div>
          <h2>Collection performance at a glance</h2>
          <p>{{ summaryText }}</p>
        </div>
        <div class="health-meter">
          <div class="health-score">{{ collectionRate }}</div>
          <span>Collection rate</span>
        </div>
      </section>

      <section class="stats-row">
        <div class="stat-card">
          <div class="stat-label">Expected</div>
          <div class="stat-value">{{ peso(totals.expected) }}</div>
          <div class="stat-sub">{{ rows.length }} loan group rows</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Collected</div>
          <div class="stat-value text-green">{{ peso(totals.collected) }}</div>
          <div class="stat-sub">{{ collectionRate }} collection rate</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Overdue</div>
          <div class="stat-value text-red">{{ peso(totals.overdue) }}</div>
          <div class="stat-sub">From overdue schedule rows</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Pending Balance</div>
          <div class="stat-value">{{ peso(totals.pending) }}</div>
          <div class="stat-sub">Remaining collectible</div>
        </div>
      </section>

      <section class="report-card">
        <div class="card-head report-head">
          <div>
            <div class="section-kicker">Breakdown</div>
            <h3>Loan Type Breakdown</h3>
          </div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Loan Type</th>
              <th>Status</th>
              <th>Loans</th>
              <th>Expected</th>
              <th>Collected</th>
              <th>Overdue</th>
              <th>Rate</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="`${row.type}-${row.status}`">
              <td class="fw-600">{{ row.type }}</td>
              <td><span :class="`badge badge-${row.status.toLowerCase()}`">{{ row.status }}</span></td>
              <td class="mono">{{ row.count }}</td>
              <td class="peso">{{ peso(row.expected) }}</td>
              <td class="peso text-green">{{ peso(row.collected) }}</td>
              <td class="peso text-red">{{ peso(row.overdue) }}</td>
              <td>
                <div class="rate-cell">
                  <div class="rate-track">
                    <div class="rate-fill" :style="{ width: `${row.rate}%` }"></div>
                  </div>
                  <span class="mono">{{ row.rate }}%</span>
                </div>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="7" class="empty-row">No collection data available</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="report-card">
        <div class="card-head report-head">
          <div>
            <div class="section-kicker">Detail</div>
            <h3>Recent Collection Detail</h3>
          </div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Loan #</th>
              <th>Member</th>
              <th>Type</th>
              <th>Expected</th>
              <th>Collected</th>
              <th>Balance</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="loan in loanRows" :key="loan.id">
              <td class="mono">{{ loan.loan_no }}</td>
              <td>
                <div class="fw-600">{{ loan.first_name }} {{ loan.last_name }}</div>
                <div class="text-muted" style="font-size:11px">{{ loan.member_no }}</div>
              </td>
              <td>{{ loan.loan_type_label }}</td>
              <td class="peso">{{ peso(loan.expected) }}</td>
              <td class="peso text-green">{{ peso(loan.collected) }}</td>
              <td class="peso">{{ peso(loan.pending) }}</td>
              <td><span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span></td>
            </tr>
            <tr v-if="!loanRows.length">
              <td colspan="7" class="empty-row">No recent collection detail available</td>
            </tr>
          </tbody>
        </table>
      </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const { error } = useToast()
const loans = ref([])
const payments = ref([])
const bills = ref([])
const loading = ref(false)

// Date filter defaults: first and last day of the current month
const now = new Date()
const defaultFrom = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10)
const defaultTo = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10)
const filters = reactive({ status: '', from: defaultFrom, to: defaultTo })

function addDueDates(items, firstDueDate, frequency) {
  const start = firstDueDate ? new Date(firstDueDate) : new Date()
  const dayStep = frequency === 'weekly' ? 7 : frequency === 'bimonthly' ? 15 : 30
  return items.map((item, index) => {
    const due = new Date(start.getTime() + dayStep * index * 86400000)
    return {
      id: item.id || null,
      period_no: item.period_no || item.period,
      due_date: item.due_date || due.toISOString().slice(0, 10),
      principal: Number(item.principal || 0),
      interest: Number(item.interest || 0),
      amount_due: Number(item.amount_due || item.payment || 0),
      balance: Number(item.balance || 0),
      paid_amount: Number(item.paid_amount || 0),
      status: item.status || 'PENDING',
    }
  })
}

function scheduleForLoan(loan) {
  if (loan.schedule?.length) return addDueDates(loan.schedule, loan.first_due_date, loan.frequency)
  const calc = computeSchedule({
    principal: Number(loan.amount || 0),
    termMonths: Number(loan.term_months || 1),
    frequency: loan.frequency || 'monthly',
    annualRate: Number(loan.annual_rate || 0.12),
  })
  return addDueDates(calc.schedule, loan.first_due_date, loan.frequency)
}

function paidForPeriod(loanId, periodNo, scheduleId = null) {
  return payments.value
    .filter(payment => Number(payment.loan_id) === Number(loanId))
    .filter(payment => Number(payment.period_no) === Number(periodNo) || (scheduleId && Number(payment.schedule_id) === Number(scheduleId)))
    .reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0)
}

function billedForPeriod(loanId, periodNo) {
  const key = `${loanId}-${periodNo}`
  return bills.value
    .filter(bill => bill.status !== 'CANCELLED')
    .flatMap(bill => bill.items || [])
    .some(item => item.schedule_key === key || (Number(item.loan_id) === Number(loanId) && Number(item.period_no) === Number(periodNo)))
}

function periodStatus(loan, period) {
  const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
  if (paid >= Number(period.amount_due || 0)) return 'PAID'
  if (paid > 0) return 'PARTIAL'
  if (period.status === 'OVERDUE') return 'OVERDUE'
  if (period.status === 'BILLED' || billedForPeriod(loan.id, period.period_no)) return 'BILLED'
  const due = period.due_date ? new Date(`${period.due_date}T00:00:00`) : null
  const today = new Date()
  const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  if (loan.status === 'ACTIVE' && due && due < todayStart) return 'OVERDUE'
  return loan.status === 'CLOSED' ? 'PAID' : 'PENDING'
}

function profileLoan(loan) {
  const schedule = scheduleForLoan(loan)
  const expected = schedule.reduce((sum, period) => sum + Number(period.amount_due || 0), 0)
  let collected = 0
  let overdue = 0
  let billed = 0
  let paidPeriods = 0

  for (const period of schedule) {
    const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
    const status = periodStatus(loan, period)
    collected += Math.min(Number(period.amount_due || 0), paid)
    if (status === 'PAID') paidPeriods += 1
    if (status === 'OVERDUE') overdue += Math.max(0, Number(period.amount_due || 0) - paid)
    if (status === 'BILLED') billed += Math.max(0, Number(period.amount_due || 0) - paid)
  }

  const pending = Math.max(0, expected - collected)
  return {
    ...loan,
    expected: +expected.toFixed(2),
    collected: +collected.toFixed(2),
    pending: +pending.toFixed(2),
    overdue: +overdue.toFixed(2),
    billed: +billed.toFixed(2),
    paidPeriods,
    periodCount: schedule.length,
  }
}

const loanRows = computed(() => loans.value.map(profileLoan))

// rows is now a ref populated by api.getReport('collection') — server-side aggregated data
const rows = ref([])

const totals = computed(() => {
  const base = loanRows.value.reduce((sum, loan) => {
    sum.expected += loan.expected
    sum.collected += loan.collected
    sum.overdue += loan.overdue
    sum.pending += loan.pending
    sum.billed += loan.billed
    return sum
  }, { expected: 0, collected: 0, overdue: 0, pending: 0, billed: 0 })

  return Object.fromEntries(Object.entries(base).map(([key, value]) => [key, +value.toFixed(2)]))
})

const collectionRate = computed(() => {
  if (!totals.value.expected) return '0%'
  return `${Math.round((totals.value.collected / totals.value.expected) * 100)}%`
})

const summaryText = computed(() => `${rows.value.length} loan group row(s), ${peso(totals.value.pending)} remaining collectible, and ${peso(totals.value.overdue)} currently flagged as overdue exposure.`)

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to
    if (filters.status) params.status = filters.status
    const data = await api.getReport('collection', params)
    rows.value = data || []
  } catch (err) {
    error(err.message || 'Could not load collection summary. Check connection and try again.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.collection-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header {
  display:flex;
  justify-content:space-between;
  align-items:flex-end;
  flex-shrink:0;
}
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.header-actions .form-select { width:220px; min-height:44px; border-radius:9px; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.collection-body {
  flex:1;
  overflow:auto;
  padding:28px 32px;
  display:flex;
  flex-direction:column;
  gap:24px;
  min-width:0;
}
.summary-strip {
  background:#fff;
  border:1px solid var(--coop-border);
  border-left:6px solid var(--coop-red);
  border-radius:10px;
  padding:24px 28px;
  display:flex;
  justify-content:space-between;
  gap:24px;
  align-items:center;
  box-shadow:0 12px 30px rgba(31,41,55,.05);
}
.section-kicker {
  color:var(--coop-red);
  font-size:12px;
  font-weight:900;
  letter-spacing:.11em;
  text-transform:uppercase;
}
.summary-strip h2 {
  color:#202838;
  font-size:32px;
  line-height:1.15;
  margin:10px 0 0;
  font-weight:800;
}
.summary-strip p {
  color:#6D7484;
  margin-top:10px;
  font-size:18px;
  line-height:1.45;
}
.health-meter {
  min-width:150px;
  height:104px;
  border-radius:10px;
  background:var(--coop-red-dim);
  border:1px solid rgba(192,57,43,.18);
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
}
.health-score { color:var(--coop-red); font-size:34px; font-family:var(--font-mono); font-weight:900; }
.health-meter span { color:#6D7484; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:14px; }
.stat-card {
  border-radius:10px;
  min-height:132px;
  padding:22px 24px;
  box-shadow:0 10px 26px rgba(31,41,55,.045);
}
.stat-card .stat-value { font-family:var(--font-sans); font-weight:900; font-size:30px; letter-spacing:0; }
.report-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:10px;
  overflow:auto;
  box-shadow:0 12px 30px rgba(31,41,55,.045);
}
.report-head {
  padding:20px 24px;
  border-bottom:1px solid var(--coop-border);
  margin:0;
}
.report-head h3 {
  margin:4px 0 0;
  color:#202838;
  font-size:22px;
  font-weight:900;
}
.data-table { min-width:920px; }
.data-table th {
  background:#F8FAFC;
  color:#737B8D;
  padding:14px 18px;
}
.data-table td {
  padding:16px 18px;
  border-bottom:1px solid #E8ECF3;
}
.data-table tbody tr:hover { background:#FFF8F6; }
.rate-cell { display:flex; align-items:center; gap:10px; min-width:160px; }
.rate-track {
  flex:1;
  height:9px;
  background:#EEF2F7;
  border-radius:999px;
  overflow:hidden;
}
.rate-fill {
  height:100%;
  background:linear-gradient(90deg, #B93A30, #D96A5D);
  border-radius:999px;
}
.loading-state { min-height:280px; }
.empty-row {
  text-align:center;
  padding:36px;
  color:var(--coop-muted);
}
@media (max-width: 1100px) {
  .summary-strip { flex-direction:column; align-items:flex-start; }
}
@media (max-width: 720px) {
  .collection-body { padding:18px 14px; }
  .stats-row { grid-template-columns:1fr; }
  .header-actions { flex-wrap:wrap; justify-content:flex-end; }
  .data-table { min-width:860px; }
}
</style>
