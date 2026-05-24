<template>
  <div class="report-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Aging Report</div>
        <div class="view-sub">Overdue loan periods grouped by days past due</div>
      </div>
      <div class="header-actions">
        <select v-model="filters.company" class="form-select">
          <option value="">All companies</option>
          <option v-for="company in companies" :key="company" :value="company">{{ company }}</option>
        </select>
        <select v-model="filters.bucket" class="form-select">
          <option value="">All buckets</option>
          <option value="par30">PAR 30 (1–30 days)</option>
          <option value="par90">PAR 90 (31–90 days)</option>
          <option value="par91">PAR 91+ (91+ days)</option>
        </select>
        <a :href="api.getReportCsvUrl('par')" target="_blank" class="btn btn-secondary" style="min-height:44px; border-radius:9px; text-decoration:none;">Download CSV</a>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="report-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
        <section class="summary-strip">
          <div>
            <div class="section-kicker">Aging Summary</div>
            <h2>{{ agingHeadline }}</h2>
            <p>{{ agingNarrative }}</p>
          </div>
          <div class="health-meter danger-meter">
            <div class="health-score">{{ worstBucketLabel }}</div>
            <span>Worst bucket</span>
          </div>
        </section>

        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Overdue Exposure</div>
            <div class="stat-value text-red">{{ peso(totals.overdue) }}</div>
            <div class="stat-sub">Open overdue balance</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Overdue Periods</div>
            <div class="stat-value">{{ filteredRows.length }}</div>
            <div class="stat-sub">Schedule rows past due</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Delinquent Loans</div>
            <div class="stat-value">{{ delinquentLoanCount }}</div>
            <div class="stat-sub">Unique loan accounts</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Oldest Past Due</div>
            <div class="stat-value">{{ oldestDays }}</div>
            <div class="stat-sub">Days past due</div>
          </div>
        </section>

        <section class="bucket-grid">
          <article v-for="bucket in bucketCards" :key="bucket.key" class="bucket-card">
            <div class="bucket-top">
              <span>{{ bucket.label }}</span>
              <strong>{{ bucket.count }}</strong>
            </div>
            <div class="bucket-amount">{{ peso(bucket.amount) }}</div>
            <div class="bucket-track"><div :style="{ width: `${bucket.share}%` }"></div></div>
          </article>
        </section>

        <section class="report-card">
          <div class="card-head report-head">
            <div>
              <div class="section-kicker">Detail</div>
              <h3>Overdue Periods</h3>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Loan #</th>
                <th>Member</th>
                <th>Company</th>
                <th>Period</th>
                <th>Due Date</th>
                <th>Days</th>
                <th>Amount Due</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Bucket</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in filteredRows" :key="`${row.loanId}-${row.periodNo}`">
                <td class="mono fw-600">{{ row.loanNo }}</td>
                <td>
                  <div class="fw-600">{{ row.memberName }}</div>
                  <div class="text-muted small-text">{{ row.memberNo }}</div>
                </td>
                <td>{{ row.company }}</td>
                <td class="mono">#{{ row.periodNo }}</td>
                <td>{{ row.dueDate }}</td>
                <td class="mono text-red">{{ row.daysPastDue }}</td>
                <td class="peso">{{ peso(row.amountDue) }}</td>
                <td class="peso text-green">{{ peso(row.paid) }}</td>
                <td class="peso text-red fw-600">{{ peso(row.balance) }}</td>
                <td><span :class="`badge bucket-${row.bucketKey}`">{{ row.bucket }}</span></td>
                <td>
                  <button class="btn btn-secondary btn-small" @click="collect(row)">Collect</button>
                </td>
              </tr>
              <tr v-if="!filteredRows.length">
                <td colspan="11" class="empty-row">No overdue periods for the selected filters</td>
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
import { useRouter } from 'vue-router'
import { api } from '../composables/useApi'
import { computeSchedule, peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const router = useRouter()
const { error } = useToast()
const loans = ref([])
const payments = ref([])
const rows = ref([])
const loading = ref(false)
const filters = reactive({ company: '', bucket: '' })
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

function bucketForDays(days) {
  if (days <= 30) return { key: 'par30', label: 'PAR 30 (1–30 days)' }
  if (days <= 90) return { key: 'par90', label: 'PAR 90 (31–90 days)' }
  return { key: 'par91', label: 'PAR 91+ (91+ days)' }
}

function loanMemberName(loan) {
  return [loan.first_name, loan.middle_name, loan.last_name].filter(Boolean).join(' ') || loan.member_name || 'Member'
}

const companies = computed(() => [...new Set(rows.value.map(row => row.company).filter(Boolean))].sort())

const agingRows = computed(() => {
  if (rows.value.length) {
    // Use server-provided PAR data — map server row shape to template shape
    return rows.value.map(row => {
      const dpd = Number(row.days_past_due || 0)
      const bucket = bucketForDays(dpd)
      return {
        loanId: row.loan_id || row.id,
        loanNo: row.loan_no,
        memberName: row.member_name || row.member || '',
        memberNo: row.member_no,
        company: row.company || 'Unassigned',
        periodNo: row.period_no || '',
        dueDate: row.next_due_date || row.due_date || '',
        daysPastDue: dpd,
        amountDue: Number(row.outstanding_balance || row.principal || 0),
        paid: 0,
        balance: Number(row.outstanding_balance || row.principal || 0),
        bucket: bucket.label,
        bucketKey: bucket.key,
      }
    }).sort((a, b) => b.daysPastDue - a.daysPastDue || b.balance - a.balance)
  }
  // Local fallback: compute from loans.value (used in preview mode)
  const localRows = []
  for (const loan of loans.value) {
    if (!['ACTIVE', 'APPROVED', 'RELEASED'].includes(String(loan.status || '').toUpperCase())) continue
    for (const period of scheduleForLoan(loan)) {
      const due = period.due_date ? new Date(`${period.due_date}T00:00:00`) : null
      if (!due || due >= todayStart) continue
      const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
      const amountDue = Number(period.amount_due || 0)
      const balance = Math.max(0, amountDue - paid)
      if (balance <= 0) continue
      const daysPastDue = Math.max(0, Math.floor((todayStart - due) / 86400000))
      const bucket = bucketForDays(daysPastDue)
      localRows.push({
        loanId: loan.id,
        loanNo: loan.loan_no,
        memberName: loanMemberName(loan),
        memberNo: loan.member_no,
        company: loan.company || 'Unassigned',
        periodNo: period.period_no,
        dueDate: period.due_date,
        daysPastDue,
        amountDue,
        paid,
        balance: +balance.toFixed(2),
        bucket: bucket.label,
        bucketKey: bucket.key,
      })
    }
  }
  return localRows.sort((a, b) => b.daysPastDue - a.daysPastDue || b.balance - a.balance)
})

const filteredRows = computed(() => agingRows.value.filter(row => {
  if (filters.company && row.company !== filters.company) return false
  if (filters.bucket && row.bucketKey !== filters.bucket) return false
  return true
}))

const totals = computed(() => ({
  overdue: +filteredRows.value.reduce((sum, row) => sum + row.balance, 0).toFixed(2),
}))

const bucketCards = computed(() => {
  const keys = [
    { key: 'par30', label: 'PAR 30 (1–30 days)' },
    { key: 'par90', label: 'PAR 90 (31–90 days)' },
    { key: 'par91', label: 'PAR 91+ (91+ days)' },
  ]
  const max = Math.max(...keys.map(bucket => filteredRows.value.filter(row => row.bucketKey === bucket.key).reduce((sum, row) => sum + row.balance, 0)), 1)
  return keys.map(bucket => {
    const bucketRows = filteredRows.value.filter(row => row.bucketKey === bucket.key)
    const amount = +bucketRows.reduce((sum, row) => sum + row.balance, 0).toFixed(2)
    return { ...bucket, count: bucketRows.length, amount, share: Math.round((amount / max) * 100) }
  })
})

const delinquentLoanCount = computed(() => new Set(filteredRows.value.map(row => row.loanId)).size)
const oldestDays = computed(() => filteredRows.value.length ? Math.max(...filteredRows.value.map(row => row.daysPastDue)) : 0)
const worstBucketLabel = computed(() => bucketCards.value.reduce((best, bucket) => bucket.amount > best.amount ? bucket : best, bucketCards.value[0])?.label || 'None')
const agingHeadline = computed(() => filteredRows.value.length ? `${delinquentLoanCount.value} loan account(s) need collection attention` : 'No overdue exposure under this filter')
const agingNarrative = computed(() => `${filteredRows.value.length} overdue period(s), ${peso(totals.value.overdue)} open exposure, oldest item ${oldestDays.value} day(s) past due.`)

function collect(row) {
  router.push({ name: 'payments', query: { mode: 'loan', loan_id: row.loanId, period_no: row.periodNo } })
}

async function load() {
  loading.value = true
  try {
    const data = await api.getReport('par')
    rows.value = data || []
  } catch (err) {
    error(err.message || 'Could not load aging report. Check connection and try again.')
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
.health-meter { min-width:160px; height:104px; border-radius:10px; background:var(--coop-red-dim); border:1px solid rgba(192,57,43,.18); display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
.health-score { color:var(--coop-red); font-size:28px; font-family:var(--font-mono); font-weight:900; }
.health-meter span { color:#6D7484; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(230px, 1fr)); gap:14px; }
.stat-card { border-radius:10px; min-height:132px; padding:22px 24px; box-shadow:0 10px 26px rgba(31,41,55,.045); }
.stat-card .stat-value { font-family:var(--font-sans); font-weight:900; font-size:30px; letter-spacing:0; }
.bucket-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:14px; }
.bucket-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; padding:18px; box-shadow:0 10px 26px rgba(31,41,55,.04); }
.bucket-top { display:flex; justify-content:space-between; gap:12px; color:#737B8D; font-size:12px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
.bucket-top strong { color:#202838; font-size:18px; letter-spacing:0; }
.bucket-amount { margin-top:12px; color:#202838; font-weight:900; font-size:22px; }
.bucket-track { height:8px; border-radius:999px; background:#EEF2F7; margin-top:14px; overflow:hidden; }
.bucket-track div { height:100%; border-radius:999px; background:linear-gradient(90deg, #B93A30, #D96A5D); }
.report-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:auto; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.report-head { padding:20px 24px; border-bottom:1px solid var(--coop-border); margin:0; }
.report-head h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:900; }
.data-table { min-width:1120px; }
.data-table th { background:#F8FAFC; color:#737B8D; padding:14px 18px; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; }
.data-table tbody tr:hover { background:#FFF8F6; }
.btn-small { min-height:32px; padding:6px 10px; border-radius:7px; }
.small-text { font-size:11px; }
.loading-state { min-height:280px; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }
.bucket-par30 { background: rgba(230,168,23,0.15); color: #B7791F; }
.bucket-par90 { background: rgba(231,76,60,0.15); color: #B91C1C; }
.bucket-par91 { background: #7F1D1D; color: #fff; }
@media (max-width: 1100px) { .summary-strip { flex-direction:column; align-items:flex-start; } .bucket-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
@media (max-width: 720px) { .report-body { padding:18px 14px; } .stats-row, .bucket-grid { grid-template-columns:1fr; } .header-actions { flex-wrap:wrap; justify-content:flex-end; } }
</style>
