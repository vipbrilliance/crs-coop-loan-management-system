<template>
  <div class="report-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Outstanding Balance</div>
        <div class="view-sub">Collectible loan balances by member, company, and loan status</div>
      </div>
      <div class="header-actions">
        <select v-model="filters.company" class="form-select">
          <option value="">All companies</option>
          <option v-for="company in companies" :key="company" :value="company">{{ company }}</option>
        </select>
        <select v-model="filters.status" class="form-select">
          <option value="">All statuses</option>
          <option value="ACTIVE">Active</option>
          <option value="APPROVED">Approved</option>
          <option value="CLOSED">Closed</option>
        </select>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="report-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
        <section class="summary-strip">
          <div>
            <div class="section-kicker">Exposure Summary</div>
            <h2>{{ exposureHeadline }}</h2>
            <p>{{ exposureNarrative }}</p>
          </div>
          <div class="health-meter">
            <div class="health-score">{{ collectionRate }}%</div>
            <span>Collection rate</span>
          </div>
        </section>

        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-value text-red">{{ peso(totals.outstanding) }}</div>
            <div class="stat-sub">Remaining collectible</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Principal Exposure</div>
            <div class="stat-value">{{ peso(totals.principal) }}</div>
            <div class="stat-sub">Unpaid principal portions</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Overdue Balance</div>
            <div class="stat-value text-red">{{ peso(totals.overdue) }}</div>
            <div class="stat-sub">Past due schedules</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value">{{ activeLoanCount }}</div>
            <div class="stat-sub">Open loan accounts</div>
          </div>
        </section>

        <section class="report-card">
          <div class="card-head report-head">
            <div>
              <div class="section-kicker">Detail</div>
              <h3>Loan Balance Register</h3>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Loan #</th>
                <th>Member</th>
                <th>Company</th>
                <th>Type</th>
                <th>Status</th>
                <th>Total Payable</th>
                <th>Collected</th>
                <th>Outstanding</th>
                <th>Overdue</th>
                <th>Rate</th>
                <th>Next Due</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="loan in filteredLoans" :key="loan.id">
                <td class="mono fw-600">{{ loan.loan_no }}</td>
                <td>
                  <div class="fw-600">{{ loan.memberName }}</div>
                  <div class="text-muted small-text">{{ loan.member_no }}</div>
                </td>
                <td>{{ loan.company }}</td>
                <td>{{ loan.loan_type_label }}</td>
                <td><span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span></td>
                <td class="peso">{{ peso(loan.expected) }}</td>
                <td class="peso text-green">{{ peso(loan.collected) }}</td>
                <td class="peso text-red fw-600">{{ peso(loan.outstanding) }}</td>
                <td class="peso text-red">{{ peso(loan.overdue) }}</td>
                <td>
                  <div class="rate-cell">
                    <div class="rate-track"><div class="rate-fill" :style="{ width: `${loan.rate}%` }"></div></div>
                    <span class="mono">{{ loan.rate }}%</span>
                  </div>
                </td>
                <td>
                  <div class="fw-600">{{ loan.nextDue?.due_date || '-' }}</div>
                  <div v-if="loan.nextDue" class="text-muted small-text">{{ peso(loan.nextDue.balance) }}</div>
                </td>
                <td>
                  <button v-if="loan.nextDue" class="btn btn-secondary btn-small" @click="collect(loan)">Collect</button>
                </td>
              </tr>
              <tr v-if="!filteredLoans.length">
                <td colspan="12" class="empty-row">No outstanding balances for the selected filters</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="company-grid">
          <article v-for="row in companyRows" :key="row.company" class="company-card">
            <div>
              <div class="section-kicker">Company</div>
              <h3>{{ row.company }}</h3>
            </div>
            <strong>{{ peso(row.outstanding) }}</strong>
            <span>{{ row.count }} loan account(s)</span>
          </article>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { error } = useToast()
const loans = ref([])
const payments = ref([])
const loading = ref(false)
const filters = reactive({ company: '', status: 'ACTIVE' })
const today = new Date()
const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())

function addDueDates(items, firstDueDate, frequency) {
  const start = firstDueDate ? new Date(`${firstDueDate}T00:00:00`) : todayStart
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

function memberName(loan) {
  return [loan.first_name, loan.middle_name, loan.last_name].filter(Boolean).join(' ') || loan.member_name || 'Member'
}

function profileLoan(loan) {
  const periods = scheduleForLoan(loan).map(period => {
    const amountDue = Number(period.amount_due || 0)
    const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
    const balance = Math.max(0, amountDue - paid)
    const due = period.due_date ? new Date(`${period.due_date}T00:00:00`) : null
    return {
      ...period,
      amountDue,
      paid,
      balance: +balance.toFixed(2),
      isOverdue: balance > 0 && due && due < todayStart,
    }
  })
  const expected = periods.reduce((sum, period) => sum + period.amountDue, 0)
  const collected = periods.reduce((sum, period) => sum + Math.min(period.amountDue, period.paid), 0)
  const outstanding = periods.reduce((sum, period) => sum + period.balance, 0)
  const principal = periods.filter(period => period.balance > 0).reduce((sum, period) => sum + Number(period.principal || 0), 0)
  const overdue = periods.filter(period => period.isOverdue).reduce((sum, period) => sum + period.balance, 0)
  const nextDue = periods.find(period => period.balance > 0) || null
  return {
    ...loan,
    memberName: memberName(loan),
    company: loan.company || 'Unassigned',
    expected: +expected.toFixed(2),
    collected: +collected.toFixed(2),
    outstanding: +outstanding.toFixed(2),
    principal: +principal.toFixed(2),
    overdue: +overdue.toFixed(2),
    nextDue,
    rate: expected ? Math.round((collected / expected) * 100) : 0,
  }
}

const companies = computed(() => [...new Set(loans.value.map(loan => loan.company || 'Unassigned'))].sort())
const profiledLoans = computed(() => loans.value.map(profileLoan).sort((a, b) => b.outstanding - a.outstanding))
const filteredLoans = computed(() => profiledLoans.value.filter(loan => {
  if (filters.company && loan.company !== filters.company) return false
  if (filters.status && loan.status !== filters.status) return false
  return loan.outstanding > 0 || loan.status !== 'CLOSED'
}))

const totals = computed(() => filteredLoans.value.reduce((sum, loan) => {
  sum.expected += loan.expected
  sum.collected += loan.collected
  sum.outstanding += loan.outstanding
  sum.principal += loan.principal
  sum.overdue += loan.overdue
  return sum
}, { expected: 0, collected: 0, outstanding: 0, principal: 0, overdue: 0 }))

const collectionRate = computed(() => totals.value.expected ? Math.round((totals.value.collected / totals.value.expected) * 100) : 0)
const activeLoanCount = computed(() => filteredLoans.value.filter(loan => ['ACTIVE', 'APPROVED', 'RELEASED'].includes(String(loan.status).toUpperCase())).length)
const exposureHeadline = computed(() => `${peso(totals.value.outstanding)} remains collectible across ${filteredLoans.value.length} loan account(s)`)
const exposureNarrative = computed(() => `${peso(totals.value.overdue)} is already past due, while ${peso(totals.value.principal)} represents unpaid principal portions.`)

const companyRows = computed(() => {
  const grouped = new Map()
  for (const loan of filteredLoans.value) {
    const row = grouped.get(loan.company) || { company: loan.company, count: 0, outstanding: 0 }
    row.count += 1
    row.outstanding += loan.outstanding
    grouped.set(loan.company, row)
  }
  return [...grouped.values()].map(row => ({ ...row, outstanding: +row.outstanding.toFixed(2) })).sort((a, b) => b.outstanding - a.outstanding)
})

function collect(loan) {
  router.push({ name: 'payments', query: { mode: 'loan', loan_id: loan.id, period_no: loan.nextDue.period_no } })
}

async function load() {
  loading.value = true
  try {
    const [loanRowsRaw, paymentRows] = await Promise.all([api.getLoans(), api.getPayments()])
    loans.value = await Promise.all(loanRowsRaw.map(async loan => {
      try { return await api.getLoan(loan.id) } catch { return loan }
    }))
    payments.value = paymentRows
  } catch (err) {
    error(err.message || 'Could not load outstanding balance report.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.report-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; }
.view-title { font-size:clamp(34px,3.1vw,52px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.header-actions .form-select { width:210px; min-height:44px; border-radius:9px; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.report-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }
.summary-strip { background:#fff; border:1px solid var(--coop-border); border-left:6px solid var(--coop-red); border-radius:10px; padding:24px 28px; display:flex; justify-content:space-between; gap:24px; align-items:center; box-shadow:0 12px 30px rgba(31,41,55,.05); }
.section-kicker { color:var(--coop-red); font-size:12px; font-weight:900; letter-spacing:.11em; text-transform:uppercase; }
.summary-strip h2 { color:#202838; font-size:32px; line-height:1.15; margin:10px 0 0; font-weight:800; }
.summary-strip p { color:#6D7484; margin-top:10px; font-size:18px; line-height:1.45; }
.health-meter { min-width:150px; height:104px; border-radius:10px; background:var(--coop-red-dim); border:1px solid rgba(192,57,43,.18); display:flex; flex-direction:column; align-items:center; justify-content:center; }
.health-score { color:var(--coop-red); font-size:34px; font-family:var(--font-mono); font-weight:900; }
.health-meter span { color:#6D7484; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:14px; }
.stat-card { border-radius:10px; min-height:132px; padding:22px 24px; box-shadow:0 10px 26px rgba(31,41,55,.045); }
.stat-card .stat-value { font-family:var(--font-sans); font-weight:900; font-size:30px; letter-spacing:0; }
.report-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:auto; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.report-head { padding:20px 24px; border-bottom:1px solid var(--coop-border); margin:0; }
.report-head h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:900; }
.data-table { min-width:1280px; }
.data-table th { background:#F8FAFC; color:#737B8D; padding:14px 18px; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; }
.data-table tbody tr:hover { background:#FFF8F6; }
.rate-cell { display:flex; align-items:center; gap:10px; min-width:150px; }
.rate-track { flex:1; height:9px; background:#EEF2F7; border-radius:999px; overflow:hidden; }
.rate-fill { height:100%; background:linear-gradient(90deg, #B93A30, #D96A5D); border-radius:999px; }
.company-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; }
.company-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; padding:18px; box-shadow:0 10px 26px rgba(31,41,55,.04); display:grid; gap:8px; }
.company-card h3 { margin:4px 0 0; font-size:18px; color:#202838; }
.company-card strong { color:var(--coop-red); font-size:24px; }
.company-card span { color:#6D7484; font-weight:700; }
.btn-small { min-height:32px; padding:6px 10px; border-radius:7px; }
.small-text { font-size:11px; }
.loading-state { min-height:280px; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }
@media (max-width: 1100px) { .summary-strip { flex-direction:column; align-items:flex-start; } }
@media (max-width: 720px) { .report-body { padding:18px 14px; } .stats-row { grid-template-columns:1fr; } .header-actions { flex-wrap:wrap; justify-content:flex-end; } }
</style>
