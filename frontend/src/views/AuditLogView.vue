<template>
  <div class="audit-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Audit Log</div>
        <div class="view-sub">Compliance activity across members, loans, billing, payments, and system records</div>
      </div>
      <div class="header-actions">
        <select v-model="filters.module" class="form-select">
          <option value="">All modules</option>
          <option v-for="module in modules" :key="module" :value="module">{{ module }}</option>
        </select>
        <select v-model="filters.action" class="form-select">
          <option value="">All actions</option>
          <option value="CREATED">Created</option>
          <option value="UPDATED">Updated</option>
          <option value="POSTED">Posted</option>
          <option value="ISSUED">Issued</option>
          <option value="SYSTEM">System</option>
        </select>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="audit-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
        <section class="summary-strip">
          <div>
            <div class="section-kicker">Compliance Summary</div>
            <h2>{{ summaryHeadline }}</h2>
            <p>{{ summaryText }}</p>
          </div>
          <div class="health-meter">
            <div class="health-score">{{ filteredRows.length }}</div>
            <span>Visible events</span>
          </div>
        </section>

        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Total Events</div>
            <div class="stat-value">{{ auditRows.length }}</div>
            <div class="stat-sub">Generated from operational records</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Today</div>
            <div class="stat-value">{{ todayEvents }}</div>
            <div class="stat-sub">Activity dated today</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Financial Events</div>
            <div class="stat-value text-red">{{ financialEvents }}</div>
            <div class="stat-sub">Payments, bills, and capital</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">System Events</div>
            <div class="stat-value">{{ systemEvents }}</div>
            <div class="stat-sub">Generated workflow events</div>
          </div>
        </section>

        <section class="audit-grid">
          <article class="report-card">
            <div class="card-head report-head">
              <div>
                <div class="section-kicker">Log</div>
                <h3>Activity Register</h3>
              </div>
              <input v-model.trim="filters.search" class="form-input search-input" type="search" placeholder="Search module, actor, record, or detail" />
            </div>
            <table class="data-table audit-table">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Module</th>
                  <th>Action</th>
                  <th>Record</th>
                  <th>Actor</th>
                  <th>Detail</th>
                  <th>Risk</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in pagedRows" :key="row.id" :class="selected?.id === row.id && 'selected-row'" @click="selected = row">
                  <td>
                    <div class="fw-600">{{ formatDate(row.createdAt) }}</div>
                    <div class="text-muted small-text">{{ formatTime(row.createdAt) }}</div>
                  </td>
                  <td><span class="module-pill">{{ row.module }}</span></td>
                  <td><span :class="`badge action-${row.action.toLowerCase()}`">{{ row.action }}</span></td>
                  <td>
                    <div class="fw-600">{{ row.record }}</div>
                    <div class="text-muted small-text">{{ row.recordType }}</div>
                  </td>
                  <td>{{ row.actor }}</td>
                  <td class="detail-cell">{{ row.detail }}</td>
                  <td><span :class="['risk-pill', row.risk.toLowerCase()]">{{ row.risk }}</span></td>
                </tr>
                <tr v-if="!pagedRows.length">
                  <td colspan="7" class="empty-row">No audit events match the selected filters</td>
                </tr>
              </tbody>
            </table>
            <div class="pager-row">
              <span>{{ filteredRows.length }} event(s)</span>
              <div class="pager-actions">
                <button class="btn btn-secondary btn-small" :disabled="page === 1" @click="page -= 1">Previous</button>
                <span class="mono">Page {{ page }} / {{ totalPages }}</span>
                <button class="btn btn-secondary btn-small" :disabled="page === totalPages" @click="page += 1">Next</button>
              </div>
            </div>
          </article>

          <aside class="detail-panel">
            <div class="card-head compact">
              <div>
                <div class="section-kicker">Inspector</div>
                <h3>Event Detail</h3>
              </div>
            </div>
            <template v-if="selected">
              <div class="inspector-title">
                <strong>{{ selected.record }}</strong>
                <span :class="['risk-pill', selected.risk.toLowerCase()]">{{ selected.risk }}</span>
              </div>
              <dl class="audit-fields">
                <div><dt>Module</dt><dd>{{ selected.module }}</dd></div>
                <div><dt>Action</dt><dd>{{ selected.action }}</dd></div>
                <div><dt>Actor</dt><dd>{{ selected.actor }}</dd></div>
                <div><dt>Date</dt><dd>{{ formatDateTime(selected.createdAt) }}</dd></div>
                <div><dt>Reference</dt><dd>{{ selected.reference || '-' }}</dd></div>
              </dl>
              <div class="diff-box">
                <div class="section-kicker">Narrative</div>
                <p>{{ selected.detail }}</p>
              </div>
              <div class="json-box">
                <div class="section-kicker">Payload Snapshot</div>
                <pre>{{ selected.payload }}</pre>
              </div>
            </template>
            <div v-else class="empty-inline">Select a log row to inspect details</div>
          </aside>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../composables/useApi'
import { peso } from '../composables/useLoanCalc'

const NOTIFY_LOG_KEY = 'crs-notification-log-state'
const AUTH_KEY = 'crs-coop-auth-user'
const loading = ref(false)
const members = ref([])
const loans = ref([])
const payments = ref([])
const bills = ref([])
const shareCapital = ref([])
const users = ref([])
const durableRows = ref([])
const selected = ref(null)
const page = ref(1)
const pageSize = 12
const filters = reactive({ module: '', action: '', search: '' })
const todayKey = new Date().toISOString().slice(0, 10)

function currentUserName() {
  try {
    const user = JSON.parse(localStorage.getItem(AUTH_KEY) || 'null')
    return user?.name || user?.email || 'System Admin'
  } catch { return 'System Admin' }
}

function notificationLog() {
  try { return Object.values(JSON.parse(localStorage.getItem(NOTIFY_LOG_KEY) || '{}') || {}) } catch { return [] }
}

function row({ id, module, action, record, recordType, actor = 'System', detail, risk = 'Low', createdAt, reference = '', payload = {} }) {
  return {
    id,
    module,
    action,
    record,
    recordType,
    actor,
    detail,
    risk,
    createdAt: createdAt || new Date().toISOString(),
    reference,
    payload: JSON.stringify(payload, null, 2),
  }
}

const auditRows = computed(() => {
  const rows = durableRows.value.map(entry => row({
    id: `durable-${entry.id}`,
    module: titleCase(entry.module || 'System'),
    action: String(entry.action || 'SYSTEM').toUpperCase(),
    record: entry.record_label || entry.record_id || 'Audit event',
    recordType: entry.record_type || 'Audit record',
    actor: entry.actor_user_name || entry.actor_name || 'System',
    detail: entry.detail || 'Stored audit event.',
    risk: titleCase(entry.risk || 'LOW'),
    createdAt: entry.created_at,
    reference: entry.record_id || entry.id,
    payload: entry.payload || entry,
  }))

  for (const member of members.value) {
    const fullName = [member.first_name, member.middle_name, member.last_name].filter(Boolean).join(' ')
    rows.push(row({
      id: `member-created-${member.id}`,
      module: 'Members',
      action: 'CREATED',
      record: member.member_no || fullName,
      recordType: 'Member profile',
      actor: currentUserName(),
      detail: `${fullName} was encoded as ${member.member_status || member.status || 'ACTIVE'} under ${member.company || 'no company'}.`,
      risk: 'Low',
      createdAt: member.created_at,
      reference: member.member_no,
      payload: member,
    }))
    const history = parseHistory(member.employment_history)
    for (const [index, item] of history.entries()) {
      rows.push(row({
        id: `member-history-${member.id}-${index}`,
        module: 'Members',
        action: 'UPDATED',
        record: member.member_no || fullName,
        recordType: 'Employment history',
        actor: currentUserName(),
        detail: `Employment changed to ${item.position || 'position'} with salary ${peso(item.salary || 0)} effective ${item.date || item.effective_date || 'unspecified date'}.`,
        risk: 'Medium',
        createdAt: item.date || item.effective_date || member.updated_at || member.created_at,
        reference: member.member_no,
        payload: item,
      }))
    }
  }

  for (const loan of loans.value) {
    const borrower = [loan.first_name, loan.middle_name, loan.last_name].filter(Boolean).join(' ') || loan.member_name || 'Member'
    rows.push(row({
      id: `loan-created-${loan.id}`,
      module: 'Loans',
      action: 'CREATED',
      record: loan.loan_no,
      recordType: loan.loan_type_label || 'Loan application',
      actor: currentUserName(),
      detail: `${borrower} created a ${loan.loan_type_label || 'loan'} application for ${peso(loan.amount || 0)} with status ${loan.status}.`,
      risk: Number(loan.amount || 0) >= 100000 ? 'High' : 'Medium',
      createdAt: loan.created_at,
      reference: loan.loan_no,
      payload: loan,
    }))
    if (loan.updated_at && loan.updated_at !== loan.created_at) {
      rows.push(row({
        id: `loan-updated-${loan.id}`,
        module: 'Loans',
        action: 'UPDATED',
        record: loan.loan_no,
        recordType: 'Loan status',
        actor: currentUserName(),
        detail: `${loan.loan_no} was last updated with status ${loan.status}.`,
        risk: ['APPROVED', 'ACTIVE', 'CLOSED'].includes(String(loan.status).toUpperCase()) ? 'High' : 'Medium',
        createdAt: loan.updated_at,
        reference: loan.loan_no,
        payload: loan,
      }))
    }
  }

  for (const payment of payments.value) {
    rows.push(row({
      id: `payment-${payment.id || payment.or_number}`,
      module: 'Payments',
      action: 'POSTED',
      record: payment.or_number || 'Payment',
      recordType: 'Loan collection',
      actor: payment.received_by_name || currentUserName(),
      detail: `${peso(payment.amount_paid || 0)} was posted to ${payment.loan_no || 'loan account'} period ${payment.period_no || '-'}.`,
      risk: 'High',
      createdAt: payment.created_at || payment.payment_date,
      reference: payment.or_number,
      payload: payment,
    }))
  }

  for (const bill of bills.value) {
    rows.push(row({
      id: `bill-${bill.id}`,
      module: 'Billing',
      action: String(bill.status || '').toUpperCase() === 'ISSUED' ? 'ISSUED' : 'CREATED',
      record: bill.bill_no,
      recordType: 'Company bill',
      actor: currentUserName(),
      detail: `${bill.bill_no} for ${bill.company_name || bill.company || 'company'} totals ${peso(bill.total_amount || 0)} with ${bill.status || 'DRAFT'} status.`,
      risk: 'High',
      createdAt: bill.issued_at || bill.created_at,
      reference: bill.bill_no,
      payload: bill,
    }))
    for (const remittance of bill.remittances || []) {
      rows.push(row({
        id: `bill-remit-${bill.id}-${remittance.id || remittance.or_number}`,
        module: 'Billing',
        action: 'POSTED',
        record: remittance.or_number || bill.bill_no,
        recordType: 'Company remittance',
        actor: remittance.posted_by_name || currentUserName(),
        detail: `Company remittance of ${peso(remittance.amount || 0)} was posted against ${bill.bill_no}.`,
        risk: 'High',
        createdAt: remittance.created_at || remittance.remittance_date,
        reference: bill.bill_no,
        payload: remittance,
      }))
    }
  }

  for (const entry of shareCapital.value) {
    rows.push(row({
      id: `share-capital-${entry.id || entry.reference}`,
      module: 'Share Capital',
      action: 'POSTED',
      record: entry.reference || 'Share capital entry',
      recordType: entry.type || 'Capital transaction',
      actor: entry.posted_by_name || currentUserName(),
      detail: `${peso(entry.amount || 0)} ${entry.type || 'entry'} posted for ${entry.member_name || entry.member_no || 'member'}.`,
      risk: 'High',
      createdAt: entry.created_at || entry.posting_date,
      reference: entry.reference,
      payload: entry,
    }))
  }

  for (const notice of notificationLog()) {
    rows.push(row({
      id: `notification-${notice.id}`,
      module: 'Notifications',
      action: 'SYSTEM',
      record: notice.reference || notice.eventLabel || 'Notice',
      recordType: notice.channel || 'Notification',
      actor: currentUserName(),
      detail: `${notice.channel || 'SYSTEM'} notice for ${notice.recipientName || 'recipient'} is ${notice.status || 'QUEUED'}.`,
      risk: notice.status === 'FAILED' ? 'Medium' : 'Low',
      createdAt: notice.updatedAt || notice.createdAt,
      reference: notice.id,
      payload: notice,
    }))
  }

  for (const user of users.value) {
    rows.push(row({
      id: `user-${user.id}`,
      module: 'Users',
      action: 'CREATED',
      record: user.email || user.name,
      recordType: user.role || 'User account',
      actor: currentUserName(),
      detail: `${user.name} has ${user.role || 'User'} access and is ${Number(user.is_active) ? 'active' : 'inactive'}.`,
      risk: ['Super Admin', 'ADMIN'].includes(user.role) ? 'High' : 'Medium',
      createdAt: user.created_at,
      reference: user.email,
      payload: user,
    }))
  }

  rows.push(row({
    id: 'system-audit-generated',
    module: 'System',
    action: 'SYSTEM',
    record: 'Audit Log View',
    recordType: 'Generated report',
    actor: currentUserName(),
    detail: 'Audit log was generated from merged operational records because a backend immutable audit table is not yet connected.',
    risk: 'Low',
    createdAt: new Date().toISOString(),
    reference: 'frontend-audit',
    payload: { source: ['members', 'loans', 'payments', 'bills', 'share_capital', 'notifications', 'users'] },
  }))

  return rows.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
})

function titleCase(value) {
  return String(value || '').toLowerCase().replace(/(^|[_\s-])([a-z])/g, (_, sep, char) => `${sep ? ' ' : ''}${char.toUpperCase()}`).trim()
}

function parseHistory(raw) {
  if (!raw) return []
  if (Array.isArray(raw)) return raw
  try { return JSON.parse(raw) || [] } catch { return [] }
}

const modules = computed(() => [...new Set(auditRows.value.map(row => row.module))].sort())
const filteredRows = computed(() => auditRows.value.filter(row => {
  const haystack = `${row.module} ${row.action} ${row.record} ${row.recordType} ${row.actor} ${row.detail}`.toLowerCase()
  if (filters.module && row.module !== filters.module) return false
  if (filters.action && row.action !== filters.action) return false
  if (filters.search && !haystack.includes(filters.search.toLowerCase())) return false
  return true
}))
const totalPages = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize)))
const pagedRows = computed(() => filteredRows.value.slice((page.value - 1) * pageSize, page.value * pageSize))
const todayEvents = computed(() => auditRows.value.filter(row => String(row.createdAt || '').slice(0, 10) === todayKey).length)
const financialEvents = computed(() => auditRows.value.filter(row => ['Payments', 'Billing', 'Share Capital'].includes(row.module)).length)
const systemEvents = computed(() => auditRows.value.filter(row => row.action === 'SYSTEM').length)
const summaryHeadline = computed(() => `${auditRows.value.length} compliance event(s) assembled from live records`)
const summaryText = computed(() => `Use this page to inspect who touched financial records, member data, loan status, billing, notifications, and user access. A backend immutable audit table should replace this generated view later.`)

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' }) : '-'
}
function formatTime(value) {
  return value ? new Date(value).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) : '-'
}
function formatDateTime(value) {
  return value ? new Date(value).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '-'
}

watch(filteredRows, () => {
  page.value = 1
  selected.value = filteredRows.value[0] || null
})

async function load() {
  loading.value = true
  try {
    const [memberRows, loanRowsRaw, paymentRows, billRowsRaw, shareRows, userRows, auditRowsRaw] = await Promise.all([
      api.getMembers(),
      api.getLoans(),
      api.getPayments(),
      api.getBills(),
      api.getShareCapitalLedger().catch(() => []),
      api.getUsers().catch(() => []),
      api.getAuditLogs({ limit: 500 }).catch(() => []),
    ])
    members.value = memberRows
    durableRows.value = auditRowsRaw
    payments.value = paymentRows
    shareCapital.value = shareRows
    users.value = userRows
    bills.value = await Promise.all(billRowsRaw.map(async bill => {
      if (!bill.id) return bill
      try { return await api.getBill(bill.id) } catch { return bill }
    }))
    loans.value = await Promise.all(loanRowsRaw.map(async loan => {
      try { return await api.getLoan(loan.id) } catch { return loan }
    }))
    selected.value = filteredRows.value[0] || null
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.audit-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; }
.view-title { font-size:clamp(34px,3.1vw,52px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.header-actions .form-select { width:190px; min-height:44px; border-radius:9px; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.audit-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }
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
.audit-grid { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:16px; align-items:start; }
.report-card, .detail-panel { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:hidden; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.report-head { padding:18px 20px; border-bottom:1px solid var(--coop-border); margin:0; display:flex; justify-content:space-between; gap:14px; align-items:center; }
.report-head h3, .detail-panel h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:900; }
.search-input { width:330px; min-height:42px; }
.audit-table { min-width:1120px; }
.data-table th { background:#F8FAFC; color:#737B8D; padding:14px 18px; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; vertical-align:top; }
.data-table tbody tr { cursor:pointer; }
.data-table tbody tr:hover, .selected-row { background:#FFF8F6; }
.module-pill { display:inline-flex; border-radius:999px; background:#F3F4F6; color:#4B5563; padding:5px 8px; font-size:11px; font-weight:900; }
.risk-pill { display:inline-flex; border-radius:999px; padding:5px 8px; font-size:11px; font-weight:900; }
.risk-pill.low { background:#EAF7EF; color:#2F7D46; }
.risk-pill.medium { background:#FFF7E6; color:#B7791F; }
.risk-pill.high { background:#FEE2E2; color:#B91C1C; }
.action-created, .action-updated { background:#EAF2FF; color:#2B5C9B; }
.action-posted, .action-issued { background:#EAF7EF; color:#2F7D46; }
.action-system { background:#F3F4F6; color:#4B5563; }
.detail-cell { max-width:380px; line-height:1.4; color:#3E4656; }
.small-text { font-size:11px; }
.pager-row { padding:14px 18px; display:flex; justify-content:space-between; gap:12px; align-items:center; color:#6D7484; border-top:1px solid var(--coop-border); }
.pager-actions { display:flex; gap:10px; align-items:center; }
.btn-small { min-height:30px; padding:6px 9px; border-radius:7px; }
.detail-panel { padding:18px; position:sticky; top:0; }
.card-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
.card-head.compact { align-items:center; }
.inspector-title { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; border:1px solid var(--coop-border); background:#F8FAFC; border-radius:9px; padding:12px; margin-bottom:14px; }
.inspector-title strong { color:#202838; }
.audit-fields { display:grid; gap:8px; margin:0 0 14px; }
.audit-fields div { display:grid; grid-template-columns:92px 1fr; gap:10px; border-bottom:1px solid #EEF2F7; padding-bottom:8px; }
.audit-fields dt { color:#737B8D; font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
.audit-fields dd { margin:0; color:#202838; font-weight:700; }
.diff-box, .json-box { border:1px solid var(--coop-border); border-radius:9px; padding:12px; margin-top:12px; background:#fff; }
.diff-box p { color:#3E4656; line-height:1.45; margin:8px 0 0; }
.json-box pre { margin:8px 0 0; max-height:260px; overflow:auto; color:#3E4656; font-size:11px; white-space:pre-wrap; }
.loading-state { min-height:280px; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }
.empty-inline { border:1px dashed var(--coop-border); border-radius:9px; padding:18px; color:var(--coop-muted); text-align:center; }
@media (max-width: 1180px) { .audit-grid { grid-template-columns:1fr; } .summary-strip { flex-direction:column; align-items:flex-start; } .detail-panel { position:static; } }
@media (max-width: 760px) { .audit-body { padding:18px 14px; } .stats-row { grid-template-columns:1fr; } .header-actions, .report-head, .pager-row { flex-wrap:wrap; } .search-input { width:100%; } }
</style>
