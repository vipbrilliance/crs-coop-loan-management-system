<template>
  <div class="view-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Dashboard</div>
        <div class="view-sub">CRS Holdings · Employees Credit Cooperative</div>
      </div>
      <div class="header-actions">
        <span class="text-muted date-label">{{ todayLabel }}</span>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="dashboard-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
        <section class="summary-strip">
          <div>
            <div class="section-kicker">Operations Summary</div>
            <h2>{{ operationsHeadline }}</h2>
            <p>{{ operationsNarrative }}</p>
          </div>
          <div class="health-meter">
            <div class="health-score">{{ collectionRate }}%</div>
            <span>Collection rate</span>
          </div>
        </section>

        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Active Members</div>
            <div class="stat-value">{{ activeMembers }}</div>
            <div class="stat-sub">{{ members.length }} total encoded</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Active Loans</div>
            <div class="stat-value text-red">{{ activeLoans }}</div>
            <div class="stat-sub">{{ peso(totals.outstanding) }} outstanding</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Due / Overdue</div>
            <div class="stat-value text-red">{{ workQueue.length }}</div>
            <div class="stat-sub">{{ peso(totals.overdue) }} overdue exposure</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Open Bills</div>
            <div class="stat-value">{{ openBills.length }}</div>
            <div class="stat-sub">{{ peso(openBillBalance) }} payroll balance</div>
          </div>
        </section>

        <section class="operations-grid">
          <article class="ops-card wide-card">
            <div class="card-head">
              <div>
                <div class="section-kicker">Today</div>
                <h3>Work Queue</h3>
              </div>
              <router-link to="/monitoring" class="mini-link">Open monitoring</router-link>
            </div>
            <table class="data-table queue-table">
              <thead>
                <tr>
                  <th>Priority</th>
                  <th>Loan</th>
                  <th>Member</th>
                  <th>Due Date</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in workQueue" :key="`${item.loanId}-${item.periodNo}`">
                  <td><span :class="['priority-pill', item.priority]">{{ item.priorityLabel }}</span></td>
                  <td class="mono fw-600">{{ item.loanNo }}</td>
                  <td>
                    <div class="fw-600">{{ item.memberName }}</div>
                    <div class="text-muted small-text">{{ item.company }}</div>
                  </td>
                  <td>{{ item.dueDate }}</td>
                  <td class="peso fw-600">{{ peso(item.balance) }}</td>
                  <td><span :class="`badge badge-${item.status.toLowerCase()}`">{{ item.status }}</span></td>
                  <td>
                    <button class="btn btn-secondary btn-small" @click="collect(item)">Collect</button>
                  </td>
                </tr>
                <tr v-if="!workQueue.length">
                  <td colspan="7" class="empty-row">No due or overdue period needs action today</td>
                </tr>
              </tbody>
            </table>
          </article>

          <article class="ops-card">
            <div class="card-head compact">
              <div>
                <div class="section-kicker">Pipeline</div>
                <h3>Applications</h3>
              </div>
              <router-link to="/pipeline" class="mini-link">Review</router-link>
            </div>
            <div class="stack-list">
              <div v-for="row in pipelineRows" :key="row.status" class="stack-row">
                <span :class="`badge badge-${row.status.toLowerCase()}`">{{ row.status }}</span>
                <strong>{{ row.count }}</strong>
                <div class="stack-track"><div :style="{ width: `${row.percent}%` }"></div></div>
              </div>
              <div v-if="!pipelineRows.length" class="empty-inline">No applications in pipeline</div>
            </div>
          </article>

          <article class="ops-card">
            <div class="card-head compact">
              <div>
                <div class="section-kicker">Billing</div>
                <h3>Company Bills</h3>
              </div>
              <router-link to="/billing" class="mini-link">Open billing</router-link>
            </div>
            <div class="bill-list">
              <div v-for="bill in openBills.slice(0, 5)" :key="bill.id" class="bill-row">
                <div>
                  <strong>{{ bill.bill_no }}</strong>
                  <span>{{ bill.company_name || bill.company || 'Company' }}</span>
                </div>
                <div class="bill-right">
                  <span :class="`badge badge-${String(bill.status || 'draft').toLowerCase()}`">{{ bill.status }}</span>
                  <strong>{{ peso(bill.balance || bill.total_amount || 0) }}</strong>
                </div>
              </div>
              <div v-if="!openBills.length" class="empty-inline">No open company bills</div>
            </div>
          </article>
        </section>

        <section class="quick-section">
          <div class="card-head compact">
            <div>
              <div class="section-kicker">Handoff</div>
              <h3>Operational Shortcuts</h3>
            </div>
          </div>
          <div class="quick-grid">
            <router-link to="/loans" class="quick-card">
              <div class="quick-title">New Application</div>
              <div class="quick-sub">Start eligibility, fees, co-makers, and packet</div>
            </router-link>
            <router-link to="/monitoring" class="quick-card">
              <div class="quick-title">Monitor Loans</div>
              <div class="quick-sub">Review schedules, due periods, and overdue flags</div>
            </router-link>
            <router-link to="/billing" class="quick-card">
              <div class="quick-title">Generate Bill</div>
              <div class="quick-sub">Create payroll deduction bills by company</div>
            </router-link>
            <router-link to="/payments" class="quick-card">
              <div class="quick-title">Post Payment</div>
              <div class="quick-sub">Record loan collections or share capital</div>
            </router-link>
            <router-link to="/reports/aging" class="quick-card">
              <div class="quick-title">Aging Report</div>
              <div class="quick-sub">Prioritize oldest overdue exposure</div>
            </router-link>
            <router-link to="/reports/outstanding" class="quick-card">
              <div class="quick-title">Outstanding Balance</div>
              <div class="quick-sub">See open collectible balances by account</div>
            </router-link>
          </div>
        </section>

        <section class="report-card">
          <div class="card-head report-head">
            <div>
              <div class="section-kicker">Portfolio</div>
              <h3>Recent Loan Accounts</h3>
            </div>
          </div>
          <table class="data-table recent-table">
            <thead>
              <tr>
                <th>Loan #</th>
                <th>Member</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Outstanding</th>
                <th>Status</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="loan in recentLoans" :key="loan.id">
                <td class="mono fw-600">{{ loan.loan_no }}</td>
                <td>
                  <div class="fw-600">{{ loan.memberName }}</div>
                  <div class="text-muted small-text">{{ loan.member_no }}</div>
                </td>
                <td>{{ loan.loan_type_label }}</td>
                <td class="peso">{{ peso(loan.amount) }}</td>
                <td class="peso text-red">{{ peso(loan.outstanding) }}</td>
                <td><span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span></td>
                <td class="text-muted small-text">{{ formatDate(loan.created_at) }}</td>
              </tr>
              <tr v-if="!recentLoans.length">
                <td colspan="7" class="empty-row">No loan accounts available</td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'

const router = useRouter()
const members = ref([])
const loans = ref([])
const payments = ref([])
const bills = ref([])
const loading = ref(false)
const today = new Date()
const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())
const todayLabel = today.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('en-PH') : '-'
}

function memberName(loan) {
  return [loan.first_name, loan.middle_name, loan.last_name].filter(Boolean).join(' ') || loan.member_name || 'Member'
}

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

function profileLoan(loan) {
  const periods = scheduleForLoan(loan).map(period => {
    const amountDue = Number(period.amount_due || 0)
    const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
    const balance = Math.max(0, amountDue - paid)
    const due = period.due_date ? new Date(`${period.due_date}T00:00:00`) : null
    const dueOrOverdue = balance > 0 && due && due <= todayStart
    return {
      ...period,
      paid,
      balance: +balance.toFixed(2),
      dueOrOverdue,
      overdue: balance > 0 && due && due < todayStart,
    }
  })
  const expected = periods.reduce((sum, period) => sum + Number(period.amount_due || 0), 0)
  const collected = periods.reduce((sum, period) => sum + Math.min(Number(period.amount_due || 0), Number(period.paid || 0)), 0)
  const outstanding = periods.reduce((sum, period) => sum + period.balance, 0)
  const overdue = periods.filter(period => period.overdue).reduce((sum, period) => sum + period.balance, 0)
  const nextDue = periods.find(period => period.balance > 0) || null
  return {
    ...loan,
    memberName: memberName(loan),
    company: loan.company || 'Unassigned',
    expected: +expected.toFixed(2),
    collected: +collected.toFixed(2),
    outstanding: +outstanding.toFixed(2),
    overdue: +overdue.toFixed(2),
    nextDue,
    periods,
  }
}

const profiledLoans = computed(() => loans.value.map(profileLoan))
const activeMembers = computed(() => members.value.filter(member => String(member.member_status || member.status || '').toUpperCase() === 'ACTIVE').length)
const activeLoans = computed(() => profiledLoans.value.filter(loan => String(loan.status).toUpperCase() === 'ACTIVE').length)
const pipelineLoans = computed(() => profiledLoans.value.filter(loan => ['DRAFT', 'PENDING', 'APPROVED'].includes(String(loan.status).toUpperCase())))

const totals = computed(() => profiledLoans.value.reduce((sum, loan) => {
  sum.expected += loan.expected
  sum.collected += loan.collected
  sum.outstanding += loan.outstanding
  sum.overdue += loan.overdue
  return sum
}, { expected: 0, collected: 0, outstanding: 0, overdue: 0 }))

const collectionRate = computed(() => totals.value.expected ? Math.round((totals.value.collected / totals.value.expected) * 100) : 0)
const operationsHeadline = computed(() => `${activeLoans.value} active loan account(s), ${pipelineLoans.value.length} application(s) in movement`)
const operationsNarrative = computed(() => `${peso(totals.value.outstanding)} remains collectible, ${peso(totals.value.overdue)} is overdue, and ${openBills.value.length} company bill(s) are still open.`)

const workQueue = computed(() => {
  const rows = []
  for (const loan of profiledLoans.value) {
    if (!['ACTIVE', 'APPROVED', 'RELEASED'].includes(String(loan.status).toUpperCase())) continue
    for (const period of loan.periods) {
      if (!period.dueOrOverdue) continue
      const due = new Date(`${period.due_date}T00:00:00`)
      const daysPastDue = Math.floor((todayStart - due) / 86400000)
      const priority = daysPastDue > 0 ? 'danger' : 'warn'
      rows.push({
        loanId: loan.id,
        loanNo: loan.loan_no,
        memberName: loan.memberName,
        memberNo: loan.member_no,
        company: loan.company,
        periodNo: period.period_no,
        dueDate: period.due_date,
        balance: period.balance,
        status: daysPastDue > 0 ? 'OVERDUE' : 'DUE',
        priority,
        priorityLabel: daysPastDue > 0 ? `${daysPastDue}d late` : 'Due today',
      })
    }
  }
  return rows.sort((a, b) => b.balance - a.balance).slice(0, 8)
})

const openBills = computed(() => bills.value
  .filter(bill => !['PAID', 'SETTLED', 'CANCELLED'].includes(String(bill.status || '').toUpperCase()))
  .map(bill => ({ ...bill, balance: Number(bill.balance ?? bill.outstanding ?? bill.total_amount ?? 0) })))
const openBillBalance = computed(() => openBills.value.reduce((sum, bill) => sum + Number(bill.balance || 0), 0))

const pipelineRows = computed(() => {
  const grouped = ['DRAFT', 'PENDING', 'APPROVED'].map(status => ({ status, count: pipelineLoans.value.filter(loan => loan.status === status).length })).filter(row => row.count)
  const max = Math.max(1, ...grouped.map(row => row.count))
  return grouped.map(row => ({ ...row, percent: Math.round((row.count / max) * 100) }))
})

const recentLoans = computed(() => profiledLoans.value.slice().sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0)).slice(0, 8))

function collect(item) {
  router.push({ name: 'payments', query: { mode: 'loan', loan_id: item.loanId, period_no: item.periodNo } })
}

async function load() {
  loading.value = true
  try {
    const [memberRows, loanRowsRaw, paymentRows, billRowsRaw] = await Promise.all([
      api.getMembers(),
      api.getLoans(),
      api.getPayments(),
      api.getBills(),
    ])
    members.value = memberRows
    payments.value = paymentRows
    bills.value = await Promise.all(billRowsRaw.map(async bill => {
      if (!bill.id) return bill
      try { return await api.getBill(bill.id) } catch { return bill }
    }))
    loans.value = await Promise.all(loanRowsRaw.map(async loan => {
      try { return await api.getLoan(loan.id) } catch { return loan }
    }))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.view-wrap { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.view-header { padding:20px 28px; border-bottom:1px solid var(--coop-border); display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; }
.view-title { font-size:clamp(34px,3.1vw,52px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; align-items:center; gap:10px; }
.date-label { font-size:12px; }
.dashboard-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }
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
.operations-grid { display:grid; grid-template-columns:1.3fr .7fr; gap:14px; align-items:start; }
.wide-card { grid-row:span 2; }
.ops-card, .quick-section, .report-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; box-shadow:0 12px 30px rgba(31,41,55,.045); overflow:hidden; }
.ops-card { padding:18px; }
.card-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
.card-head.compact { align-items:center; }
.card-head h3, .report-head h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:900; }
.mini-link { color:var(--coop-red); font-size:12px; font-weight:800; text-decoration:none; }
.queue-table { min-width:880px; }
.data-table th { background:#F8FAFC; color:#737B8D; padding:13px 16px; }
.data-table td { padding:14px 16px; border-bottom:1px solid #E8ECF3; }
.data-table tbody tr:hover { background:#FFF8F6; }
.small-text { font-size:11px; }
.priority-pill { display:inline-flex; align-items:center; border-radius:999px; padding:5px 8px; font-size:11px; font-weight:900; white-space:nowrap; }
.priority-pill.warn { background:#FFF7E6; color:#B7791F; }
.priority-pill.danger { background:#FEE2E2; color:#B91C1C; }
.btn-small { min-height:32px; padding:6px 10px; border-radius:7px; }
.stack-list, .bill-list { display:flex; flex-direction:column; gap:12px; }
.stack-row { display:grid; grid-template-columns:94px 28px minmax(0, 1fr); align-items:center; gap:10px; }
.stack-row strong { color:#202838; font-family:var(--font-mono); }
.stack-track { height:8px; background:#EEF2F7; border-radius:999px; overflow:hidden; }
.stack-track div { height:100%; background:var(--coop-red); border-radius:999px; }
.bill-row { display:flex; justify-content:space-between; gap:12px; border:1px solid var(--coop-border); border-radius:9px; padding:12px; background:#F8FAFC; }
.bill-row strong { color:#202838; display:block; }
.bill-row span { color:#6D7484; font-size:12px; font-weight:700; }
.bill-right { text-align:right; display:grid; gap:8px; justify-items:end; }
.quick-section { padding:18px; }
.quick-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:10px; }
.quick-card { background:#F8FAFC; border:1px solid var(--coop-border); border-radius:9px; padding:14px; text-decoration:none; color:inherit; transition:all var(--tx); }
.quick-card:hover { border-color:var(--coop-red); background:var(--coop-red-dim); transform:translateY(-1px); }
.quick-title { font-weight:900; font-size:15px; color:#202838; }
.quick-sub { color:#6D7484; font-size:12px; margin-top:5px; line-height:1.35; }
.report-head { padding:20px 24px; border-bottom:1px solid var(--coop-border); margin:0; }
.recent-table { min-width:980px; }
.loading-state { min-height:280px; }
.empty-row { text-align:center; padding:34px; color:var(--coop-muted); }
.empty-inline { border:1px dashed var(--coop-border); border-radius:9px; padding:18px; color:var(--coop-muted); text-align:center; }
@media (max-width: 1180px) { .operations-grid { grid-template-columns:1fr; } .wide-card { grid-row:auto; } .summary-strip { flex-direction:column; align-items:flex-start; } }
@media (max-width: 720px) { .dashboard-body { padding:18px 14px; } .stats-row { grid-template-columns:1fr; } .header-actions { flex-wrap:wrap; justify-content:flex-end; } }
</style>
