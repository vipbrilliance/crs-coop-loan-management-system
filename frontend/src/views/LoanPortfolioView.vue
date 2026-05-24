<template>
  <div class="report-wrap">
    <header class="view-header">
      <div>
        <div class="view-title">Loan Portfolio</div>
        <div class="view-sub">Active loans with outstanding balance and next due date</div>
      </div>
      <div class="header-actions">
        <a :href="csvUrl" target="_blank" class="btn btn-secondary" style="min-height:44px; border-radius:9px; text-decoration:none;">Download CSV</a>
        <button class="btn btn-secondary" @click="load">Refresh Report</button>
      </div>
    </header>

    <main class="report-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <div v-else-if="error" class="error-banner">{{ error }}</div>
      <template v-else>
        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Total Active Loans</div>
            <div class="stat-value">{{ totalLoans }}</div>
            <div class="stat-sub">Open loan accounts</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Principal</div>
            <div class="stat-value">₱ {{ peso(totalPrincipal) }}</div>
            <div class="stat-sub">Sum of loan principals</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-value text-red">₱ {{ peso(totalOutstanding) }}</div>
            <div class="stat-sub">Remaining collectible</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Next Due</div>
            <div class="stat-value">{{ nearestDue || '—' }}</div>
            <div class="stat-sub">Earliest due date</div>
          </div>
        </section>

        <section class="report-card">
          <div class="card-head report-head">
            <div>
              <div class="section-kicker">Portfolio</div>
              <h3>Active Loan Register</h3>
            </div>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Loan #</th>
                <th>Member</th>
                <th>Member #</th>
                <th>Principal</th>
                <th>Outstanding</th>
                <th>Next Due Date</th>
                <th>Days Past Due</th>
                <th>PAR Bucket</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.loan_no">
                <td class="mono fw-600">{{ row.loan_no }}</td>
                <td>
                  <div class="fw-600">{{ row.member_name }}</div>
                  <small class="text-muted">{{ row.member_no }}</small>
                </td>
                <td class="mono">{{ row.member_no }}</td>
                <td class="peso">{{ peso(row.principal) }}</td>
                <td :class="['peso', 'fw-600', Number(row.outstanding_balance) > 0 ? 'text-red' : 'text-green']">
                  {{ peso(row.outstanding_balance) }}
                </td>
                <td>{{ row.next_due_date || '—' }}</td>
                <td class="mono">{{ row.days_past_due || '—' }}</td>
                <td>
                  <span
                    v-if="row.par_bucket && row.par_bucket !== 'Current'"
                    class="badge"
                    :class="{
                      'badge-par-30': row.par_bucket === 'PAR 30',
                      'badge-par-90': row.par_bucket === 'PAR 90',
                      'badge-par-91': row.par_bucket === 'PAR 91+'
                    }"
                  >{{ row.par_bucket }}</span>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="8" class="empty-row">No active loans in portfolio</td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../composables/useApi.js'

const rows = ref([])
const loading = ref(false)
const error = ref(null)

const totalLoans = computed(() => rows.value.length)
const totalPrincipal = computed(() => rows.value.reduce((sum, r) => sum + Number(r.principal || 0), 0))
const totalOutstanding = computed(() => rows.value.reduce((sum, r) => sum + Number(r.outstanding_balance || 0), 0))
const nearestDue = computed(() => {
  const dates = rows.value.map(r => r.next_due_date).filter(Boolean)
  if (!dates.length) return null
  return dates.sort()[0]
})

const csvUrl = computed(() => api.getReportCsvUrl('portfolio'))

function peso(n) {
  const num = Number(n)
  if (isNaN(num)) return '—'
  return num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function load() {
  loading.value = true
  error.value = null
  try {
    rows.value = await api.getReport('portfolio') || []
  } catch (e) {
    error.value = 'Could not load Loan Portfolio. Check connection and try again.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.report-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; padding:32px 32px 0; }
.view-title { font-size:clamp(34px,3.1vw,48px); font-weight:800; color:#202838; line-height:1.02; }
.view-sub { font-size:11px; font-weight:800; color:#6B7280; margin-top:8px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.report-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }
.stats-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; }
.stat-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; min-height:132px; padding:22px 24px; box-shadow:0 10px 26px rgba(31,41,55,.045); }
.stat-label { font-size:11px; font-weight:800; color:#6B7280; text-transform:uppercase; letter-spacing:.08em; margin-bottom:8px; }
.stat-value { font-size:22px; font-weight:800; color:#202838; line-height:1.0; }
.stat-sub { font-size:11px; color:#6B7280; margin-top:6px; }
.report-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:auto; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.card-head.report-head { padding:20px 24px; border-bottom:1px solid var(--coop-border); }
.section-kicker { color:var(--coop-red); font-size:11px; font-weight:800; letter-spacing:.11em; text-transform:uppercase; }
.report-head h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:800; }
.data-table { width:100%; border-collapse:collapse; }
.data-table th { background:#F8FAFC; color:#737B8D; font-size:11px; font-weight:800; padding:14px 18px; text-align:left; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; font-size:14px; }
.data-table tbody tr:hover { background:#FFF8F6; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }
.loading-state { min-height:280px; display:flex; align-items:center; justify-content:center; }
.error-banner { background:#FFF1F0; border:1px solid rgba(231,76,60,.25); border-radius:8px; color:#C0392B; padding:14px 18px; font-size:14px; font-weight:600; }
.mono { font-family:var(--font-mono, monospace); }
.fw-600 { font-weight:600; }
.peso { font-family:var(--font-mono, monospace); }
.text-red { color:var(--coop-red-soft, #E8534A); }
.text-green { color:var(--status-approved, #27AE60); }
.text-muted { color:#6B7280; }
.badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:800; }
.badge-par-30 { background:rgba(230,168,23,0.15); color:#E6A817; }
.badge-par-90 { background:rgba(231,76,60,0.15); color:#E74C3C; }
.badge-par-91 { background:#7F1D1D; color:#fff; }
@media (max-width:720px) { .report-body { padding:18px 14px; } .stats-row { grid-template-columns:1fr; } .header-actions { flex-wrap:wrap; justify-content:flex-end; } }
</style>
