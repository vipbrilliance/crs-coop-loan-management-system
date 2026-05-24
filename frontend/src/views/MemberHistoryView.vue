<template>
  <div class="report-wrap">
    <header class="view-header">
      <div>
        <div class="view-title">Member Loan History</div>
        <div class="view-sub">Full repayment history for a selected member</div>
      </div>
      <div class="header-actions">
        <select v-model="selectedMemberId" class="form-select" style="width:280px; min-height:44px; border-radius:9px;">
          <option value="">Select a member...</option>
          <option v-for="m in members" :key="m.id" :value="m.id">{{ m.last_name }}, {{ m.first_name }} ({{ m.member_no }})</option>
        </select>
        <a
          :href="csvUrl"
          target="_blank"
          :class="['btn', 'btn-secondary', !selectedMemberId ? 'btn-disabled' : '']"
          :style="!selectedMemberId
            ? 'pointer-events:none; opacity:0.5; min-height:44px; border-radius:9px; text-decoration:none;'
            : 'min-height:44px; border-radius:9px; text-decoration:none;'"
        >Download CSV</a>
        <button class="btn btn-secondary" @click="selectedMemberId && loadMemberHistory(selectedMemberId)">Refresh Report</button>
      </div>
    </header>

    <main class="report-body">
      <!-- Empty state — no member selected -->
      <div v-if="!selectedMemberId" class="empty-state-panel">
        <div class="empty-icon">◎</div>
        <h3>Select a member above to view their loan history</h3>
        <p>Choose a member from the dropdown to see all loans and payment history.</p>
      </div>

      <!-- Loading -->
      <div v-else-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>

      <!-- Error -->
      <div v-else-if="error" class="error-banner">{{ error }}</div>

      <!-- Data loaded -->
      <template v-else-if="history.length">
        <!-- Member summary card -->
        <section class="member-summary-card">
          <div class="member-summary-body">
            <div>
              <h2 class="member-name">{{ selectedMemberName }}</h2>
              <div class="member-no mono">{{ selectedMemberNo }}</div>
            </div>
            <div class="member-stats">
              <div class="mstat">
                <div class="mstat-value">{{ groupedHistory.length }}</div>
                <div class="mstat-label">Total Loans</div>
              </div>
              <div class="mstat">
                <div class="mstat-value">₱ {{ peso(memberTotalPrincipal) }}</div>
                <div class="mstat-label">Total Borrowed</div>
              </div>
              <div class="mstat">
                <div class="mstat-value text-green">₱ {{ peso(memberTotalPaid) }}</div>
                <div class="mstat-label">Total Paid</div>
              </div>
            </div>
          </div>
        </section>

        <!-- Per-loan sections -->
        <section v-for="loanGroup in groupedHistory" :key="loanGroup.loan_no" class="report-card">
          <div class="card-head report-head">
            <div class="loan-head-info">
              <div class="section-kicker">Loan</div>
              <h3>{{ loanGroup.loan_no }}</h3>
              <span :class="['badge', 'badge-' + (loanGroup.status || 'pending').toLowerCase()]">{{ loanGroup.status }}</span>
            </div>
            <div class="loan-head-meta" v-if="loanGroup.application_date">
              <small>Applied {{ loanGroup.application_date }} · {{ loanGroup.term_months }} months {{ loanGroup.frequency }}</small>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Period</th>
                <th>Due Date</th>
                <th>Amount Due</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th>O.R.</th>
                <th>Paid Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in loanGroup.rows" :key="row.period_no">
                <td class="mono">#{{ row.period_no }}</td>
                <td>{{ row.due_date || '—' }}</td>
                <td class="peso">{{ peso(row.amount_due) }}</td>
                <td class="peso text-green">{{ peso(row.paid_amount) }}</td>
                <td :class="['peso', Number(row.amount_due) - Number(row.paid_amount) > 0 ? 'text-red' : 'text-green']">
                  {{ peso(Math.max(0, Number(row.amount_due) - Number(row.paid_amount))) }}
                </td>
                <td>
                  <span :class="['badge', 'badge-' + (row.period_status || 'pending').toLowerCase()]">
                    {{ row.period_status || 'PENDING' }}
                  </span>
                </td>
                <td class="mono">{{ row.or_number || '—' }}</td>
                <td>{{ row.paid_date || '—' }}</td>
              </tr>
              <tr v-if="!loanGroup.rows.length">
                <td colspan="8" class="empty-row">No schedule rows for this loan</td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>

      <!-- No history returned -->
      <div v-else class="empty-state-panel">
        <div class="empty-icon">◎</div>
        <h3>No loan history found</h3>
        <p>This member has no loan records in the system.</p>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { api } from '../composables/useApi.js'

const members = ref([])
const selectedMemberId = ref('')
const history = ref([])
const loading = ref(false)
const error = ref(null)

const selectedMember = computed(() => members.value.find(m => String(m.id) === String(selectedMemberId.value)))
const selectedMemberName = computed(() => {
  const m = selectedMember.value
  if (!m) return ''
  return [m.last_name, m.first_name].filter(Boolean).join(', ')
})
const selectedMemberNo = computed(() => selectedMember.value?.member_no || '')

const csvUrl = computed(() =>
  selectedMemberId.value
    ? api.getReportCsvUrl('member', { member_id: selectedMemberId.value })
    : '#'
)

const groupedHistory = computed(() => {
  const loanMap = new Map()
  for (const row of history.value) {
    if (!loanMap.has(row.loan_no)) {
      loanMap.set(row.loan_no, {
        loan_no: row.loan_no,
        amount: row.amount,
        term_months: row.term_months,
        frequency: row.frequency,
        annual_rate: row.annual_rate,
        status: row.status,
        application_date: row.application_date,
        first_due_date: row.first_due_date,
        rows: [],
      })
    }
    loanMap.get(row.loan_no).rows.push(row)
  }
  return [...loanMap.values()]
})

const memberTotalPrincipal = computed(() =>
  groupedHistory.value.reduce((sum, loan) => sum + Number(loan.amount || 0), 0)
)
const memberTotalPaid = computed(() =>
  history.value.reduce((sum, row) => sum + Number(row.paid_amount || 0), 0)
)

function peso(n) {
  const num = Number(n)
  if (isNaN(num)) return '—'
  return num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadMemberHistory(id) {
  loading.value = true
  error.value = null
  try {
    history.value = await api.getReport('member', { member_id: id }) || []
  } catch (e) {
    error.value = 'Could not load member history. Check connection and try again.'
  } finally {
    loading.value = false
  }
}

watch(selectedMemberId, (id) => {
  history.value = []
  error.value = null
  if (id) loadMemberHistory(id)
})

onMounted(async () => {
  try {
    members.value = await api.getMembers() || []
  } catch (e) {
    // non-fatal — member dropdown may be empty in preview mode
  }
})
</script>

<style scoped>
.report-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; padding:32px 32px 0; }
.view-title { font-size:clamp(34px,3.1vw,48px); font-weight:800; color:#202838; line-height:1.02; }
.view-sub { font-size:11px; font-weight:800; color:#6B7280; margin-top:8px; }
.header-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:flex-end; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.report-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }

/* Empty state */
.empty-state-panel { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 24px; text-align:center; background:#fff; border:1px solid var(--coop-border); border-radius:10px; }
.empty-icon { font-size:32px; color:#6B7280; margin-bottom:16px; }
.empty-state-panel h3 { font-size:18px; font-weight:800; color:#202838; margin:0 0 8px; }
.empty-state-panel p { font-size:14px; color:#6B7280; margin:0; }

/* Loading */
.loading-state { min-height:280px; display:flex; align-items:center; justify-content:center; }

/* Error */
.error-banner { background:#FFF1F0; border:1px solid rgba(231,76,60,.25); border-radius:8px; color:#C0392B; padding:14px 18px; font-size:14px; font-weight:600; }

/* Member summary card */
.member-summary-card { background:#fff; border:1px solid var(--coop-border); border-left:6px solid var(--coop-red); border-radius:10px; padding:24px 28px; box-shadow:0 12px 30px rgba(31,41,55,.05); }
.member-summary-body { display:flex; justify-content:space-between; align-items:flex-start; gap:24px; flex-wrap:wrap; }
.member-name { font-size:22px; font-weight:800; color:#202838; margin:0 0 6px; }
.member-no { font-size:14px; color:#6B7280; }
.member-stats { display:flex; gap:32px; }
.mstat { text-align:center; }
.mstat-value { font-size:22px; font-weight:800; color:#202838; }
.mstat-label { font-size:11px; font-weight:800; color:#6B7280; text-transform:uppercase; letter-spacing:.08em; margin-top:4px; }

/* Report card */
.report-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:auto; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.card-head.report-head { padding:20px 24px; border-bottom:1px solid var(--coop-border); }
.section-kicker { color:var(--coop-red); font-size:11px; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
.report-head h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:800; display:inline; margin-right:10px; }
.loan-head-info { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.loan-head-meta { margin-top:6px; color:#6B7280; font-size:12px; }

/* Table */
.data-table { width:100%; border-collapse:collapse; }
.data-table th { background:#F8FAFC; color:#737B8D; font-size:11px; font-weight:800; padding:14px 18px; text-align:left; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; font-size:14px; }
.data-table tbody tr:hover { background:#FFF8F6; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }

/* Utilities */
.mono { font-family:var(--font-mono, monospace); }
.fw-600 { font-weight:600; }
.peso { font-family:var(--font-mono, monospace); }
.text-red { color:var(--coop-red-soft, #E8534A); }
.text-green { color:var(--status-approved, #27AE60); }
.badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:800; }

@media (max-width:720px) {
  .report-body { padding:18px 14px; }
  .header-actions { flex-direction:column; align-items:stretch; }
  .header-actions .form-select { width:100% !important; }
  .member-stats { flex-direction:column; gap:12px; }
}
</style>
