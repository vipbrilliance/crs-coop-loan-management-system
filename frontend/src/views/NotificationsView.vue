<template>
  <div class="notify-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Notifications</div>
        <div class="view-sub">Due reminders, overdue notices, billing events, and delivery tracking</div>
      </div>
      <div class="header-actions">
        <select v-model="filters.channel" class="form-select">
          <option value="">All channels</option>
          <option value="SMS">SMS</option>
          <option value="EMAIL">Email</option>
          <option value="SYSTEM">System</option>
        </select>
        <select v-model="filters.status" class="form-select">
          <option value="">All statuses</option>
          <option value="QUEUED">Queued</option>
          <option value="SENT">Sent</option>
          <option value="FAILED">Failed</option>
          <option value="DISABLED">Disabled</option>
        </select>
        <button class="btn btn-secondary" @click="load">Refresh</button>
      </div>
    </header>

    <main class="notify-body">
      <div v-if="loading" class="empty-state loading-state"><div class="spinner"></div></div>
      <template v-else>
        <section class="summary-strip">
          <div>
            <div class="section-kicker">Notification Center</div>
            <h2>{{ summaryHeadline }}</h2>
            <p>{{ summaryText }}</p>
          </div>
          <div class="health-meter">
            <div class="health-score">{{ enabledEventCount }}</div>
            <span>Enabled events</span>
          </div>
        </section>

        <section class="stats-row">
          <div class="stat-card">
            <div class="stat-label">Queued</div>
            <div class="stat-value">{{ totals.queued }}</div>
            <div class="stat-sub">Ready for dispatch</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Sent</div>
            <div class="stat-value text-green">{{ totals.sent }}</div>
            <div class="stat-sub">Marked delivered in this workstation</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Failed</div>
            <div class="stat-value text-red">{{ totals.failed }}</div>
            <div class="stat-sub">Needs retry or review</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Disabled</div>
            <div class="stat-value">{{ totals.disabled }}</div>
            <div class="stat-sub">Blocked by settings channel toggle</div>
          </div>
        </section>

        <section class="notify-grid">
          <article class="report-card queue-card">
            <div class="card-head report-head">
              <div>
                <div class="section-kicker">Queue</div>
                <h3>Notification Log</h3>
              </div>
              <div class="table-actions">
                <input v-model.trim="filters.search" class="form-input search-input" type="search" placeholder="Search member, loan, bill, or message" />
                <button class="btn btn-primary" @click="markAllQueuedSent">Mark queued sent</button>
              </div>
            </div>
            <table class="data-table notify-table">
              <thead>
                <tr>
                  <th>Event</th>
                  <th>Recipient</th>
                  <th>Channel</th>
                  <th>Message</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in filteredNotifications" :key="item.id">
                  <td>
                    <div class="fw-600">{{ item.eventLabel }}</div>
                    <div class="text-muted small-text">{{ item.reference }}</div>
                  </td>
                  <td>
                    <div class="fw-600">{{ item.recipientName }}</div>
                    <div class="text-muted small-text">{{ item.destination }}</div>
                  </td>
                  <td><span :class="`channel-pill channel-${item.channel.toLowerCase()}`">{{ item.channel }}</span></td>
                  <td class="message-cell">{{ item.message }}</td>
                  <td><span :class="`badge badge-${item.status.toLowerCase()}`">{{ item.status }}</span></td>
                  <td class="small-text text-muted">{{ formatDateTime(item.createdAt) }}</td>
                  <td>
                    <div class="inline-actions">
                      <button class="btn btn-secondary btn-small" @click="markSent(item)">Sent</button>
                      <button class="btn btn-secondary btn-small" @click="markFailed(item)">Fail</button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!filteredNotifications.length">
                  <td colspan="7" class="empty-row">No notification events for the selected filters</td>
                </tr>
              </tbody>
            </table>
          </article>

          <aside class="side-stack">
            <article class="settings-card">
              <div class="card-head compact">
                <div>
                  <div class="section-kicker">Settings</div>
                  <h3>Enabled Events</h3>
                </div>
                <router-link to="/settings" class="mini-link">Configure</router-link>
              </div>
              <div class="event-list">
                <div v-for="event in notificationEvents" :key="event.key" class="event-row">
                  <div>
                    <strong>{{ event.label }}</strong>
                    <span>{{ event.description }}</span>
                  </div>
                  <div class="event-channels">
                    <span :class="['tiny-toggle', event.sms && 'on']">SMS</span>
                    <span :class="['tiny-toggle', event.email && 'on']">Email</span>
                  </div>
                </div>
              </div>
            </article>

            <article class="settings-card">
              <div class="card-head compact">
                <div>
                  <div class="section-kicker">Dispatch</div>
                  <h3>Manual Notice</h3>
                </div>
              </div>
              <div class="manual-form">
                <select v-model="manual.channel" class="form-select">
                  <option value="SYSTEM">System</option>
                  <option value="SMS">SMS</option>
                  <option value="EMAIL">Email</option>
                </select>
                <input v-model.trim="manual.recipient" class="form-input" placeholder="Recipient name" />
                <input v-model.trim="manual.destination" class="form-input" placeholder="Contact or email" />
                <textarea v-model.trim="manual.message" class="form-textarea" placeholder="Message to log"></textarea>
                <button class="btn btn-primary" @click="addManualNotice">Queue Manual Notice</button>
              </div>
            </article>
          </aside>
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

const SETTINGS_KEY = 'crs-coop-preview-settings'
const { success, error } = useToast()
const loading = ref(false)
const loans = ref([])
const payments = ref([])
const bills = ref([])
const durableLog = ref([])
const filters = reactive({ channel: '', status: '', search: '' })
const manual = reactive({ channel: 'SYSTEM', recipient: '', destination: '', message: '' })
const today = new Date()
const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate())

const defaultEvents = [
  { key: 'loan_submitted', label: 'Loan Submitted', description: 'Application received and queued for review.', sms: true, email: true },
  { key: 'loan_approved', label: 'Loan Approved', description: 'Approval or release notice for member.', sms: true, email: true },
  { key: 'payment_due', label: 'Payment Due Reminder', description: 'Upcoming amortization reminder.', sms: true, email: false },
  { key: 'payment_posted', label: 'Payment Posted', description: 'Collection confirmation after posting.', sms: false, email: true },
  { key: 'overdue', label: 'Overdue Notice', description: 'Past due schedule warning.', sms: true, email: true },
  { key: 'bill_issued', label: 'Bill Issued', description: 'Payroll deduction bill sent to company.', sms: false, email: true },
]

function readSettings() {
  try { return JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null') || {} } catch { return {} }
}


const notificationEvents = computed(() => readSettings()?.notifications?.events || defaultEvents)
const eventByKey = computed(() => Object.fromEntries(notificationEvents.value.map(event => [event.key, event])))
const enabledEventCount = computed(() => notificationEvents.value.filter(event => event.sms || event.email).length)

function addDueDates(items, firstDueDate, frequency) {
  const start = firstDueDate ? new Date(`${firstDueDate}T00:00:00`) : todayStart
  const dayStep = frequency === 'weekly' ? 7 : frequency === 'bimonthly' ? 15 : 30
  return items.map((item, index) => {
    const due = new Date(start.getTime() + dayStep * index * 86400000)
    return {
      id: item.id || null,
      period_no: item.period_no || item.period,
      due_date: item.due_date || due.toISOString().slice(0, 10),
      amount_due: Number(item.amount_due || item.payment || 0),
      paid_amount: Number(item.paid_amount || 0),
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

function channelsFor(eventKey) {
  const event = eventByKey.value[eventKey]
  if (!event) return ['SYSTEM']
  const channels = []
  if (event.sms) channels.push('SMS')
  if (event.email) channels.push('EMAIL')
  return channels.length ? channels : ['DISABLED']
}

function destinationFor(loan, channel) {
  if (channel === 'EMAIL') return loan.email || 'No email on file'
  if (channel === 'SMS') return loan.contact || 'No mobile on file'
  return 'System log'
}

function baseNotice({ id, eventKey, channel, recipientName, destination, reference, message, createdAt }) {
  const event = eventByKey.value[eventKey] || defaultEvents.find(item => item.key === eventKey) || { label: eventKey }
  const saved = persistedBySource.value[id] || {}
  return {
    id,
    eventKey,
    eventLabel: event.label,
    channel,
    recipientName,
    destination,
    reference,
    message,
    createdAt,
    status: channel === 'DISABLED' ? 'DISABLED' : (saved.status || 'QUEUED'),
    backendId: saved.backendId || saved.id || null,
  }
}

const persistedBySource = computed(() => Object.fromEntries(durableLog.value.map(item => [item.source_key || item.sourceKey || item.id, {
  ...item,
  id: item.source_key || item.sourceKey || item.id,
  backendId: item.id,
  eventLabel: item.event_label || item.eventLabel || item.event_key,
  recipientName: item.recipient_name || item.recipientName,
  destination: item.destination,
  reference: item.reference,
  message: item.message,
  channel: item.channel,
  status: item.status,
  createdAt: item.created_at || item.createdAt,
}])))

const generatedNotifications = computed(() => {
  const rows = []

  for (const loan of loans.value) {
    const status = String(loan.status || '').toUpperCase()
    if (['PENDING', 'DRAFT'].includes(status)) {
      for (const channel of channelsFor('loan_submitted')) {
        rows.push(baseNotice({
          id: `loan-submitted-${loan.id}-${channel}`,
          eventKey: 'loan_submitted',
          channel,
          recipientName: memberName(loan),
          destination: destinationFor(loan, channel),
          reference: loan.loan_no,
          message: `Loan application ${loan.loan_no} is received and pending review.`,
          createdAt: loan.created_at || new Date().toISOString(),
        }))
      }
    }
    if (['APPROVED', 'ACTIVE', 'RELEASED'].includes(status)) {
      for (const channel of channelsFor('loan_approved')) {
        rows.push(baseNotice({
          id: `loan-approved-${loan.id}-${channel}`,
          eventKey: 'loan_approved',
          channel,
          recipientName: memberName(loan),
          destination: destinationFor(loan, channel),
          reference: loan.loan_no,
          message: `Loan ${loan.loan_no} has moved to ${status}. Please coordinate signing, release, or deduction start.`,
          createdAt: loan.updated_at || loan.created_at || new Date().toISOString(),
        }))
      }
    }

    for (const period of scheduleForLoan(loan)) {
      const due = period.due_date ? new Date(`${period.due_date}T00:00:00`) : null
      if (!due) continue
      const paid = paidForPeriod(loan.id, period.period_no, period.id) || Number(period.paid_amount || 0)
      const balance = Math.max(0, Number(period.amount_due || 0) - paid)
      if (balance <= 0) continue
      const daysUntilDue = Math.floor((due - todayStart) / 86400000)
      const isUpcoming = daysUntilDue >= 0 && daysUntilDue <= 3
      const isOverdue = due < todayStart
      if (isUpcoming) {
        for (const channel of channelsFor('payment_due')) {
          rows.push(baseNotice({
            id: `payment-due-${loan.id}-${period.period_no}-${channel}`,
            eventKey: 'payment_due',
            channel,
            recipientName: memberName(loan),
            destination: destinationFor(loan, channel),
            reference: `${loan.loan_no} period #${period.period_no}`,
            message: `Payment of ${peso(balance)} for ${loan.loan_no} period #${period.period_no} is due on ${period.due_date}.`,
            createdAt: new Date().toISOString(),
          }))
        }
      }
      if (isOverdue) {
        for (const channel of channelsFor('overdue')) {
          rows.push(baseNotice({
            id: `overdue-${loan.id}-${period.period_no}-${channel}`,
            eventKey: 'overdue',
            channel,
            recipientName: memberName(loan),
            destination: destinationFor(loan, channel),
            reference: `${loan.loan_no} period #${period.period_no}`,
            message: `Overdue notice: ${peso(balance)} remains unpaid for ${loan.loan_no} period #${period.period_no}, due ${period.due_date}.`,
            createdAt: new Date().toISOString(),
          }))
        }
      }
    }
  }

  for (const payment of payments.value.slice(0, 50)) {
    for (const channel of channelsFor('payment_posted')) {
      rows.push(baseNotice({
        id: `payment-posted-${payment.id || payment.or_number}-${channel}`,
        eventKey: 'payment_posted',
        channel,
        recipientName: payment.member_name || payment.borrower_name || 'Member',
        destination: channel === 'EMAIL' ? (payment.email || 'Member email') : 'System log',
        reference: payment.or_number || 'Payment',
        message: `Payment ${payment.or_number || ''} for ${peso(payment.amount_paid || 0)} has been posted.`,
        createdAt: payment.payment_date || payment.created_at || new Date().toISOString(),
      }))
    }
  }

  for (const bill of bills.value) {
    if (!['ISSUED', 'PARTIAL'].includes(String(bill.status || '').toUpperCase())) continue
    for (const channel of channelsFor('bill_issued')) {
      rows.push(baseNotice({
        id: `bill-issued-${bill.id}-${channel}`,
        eventKey: 'bill_issued',
        channel,
        recipientName: bill.company_name || bill.company || 'Company payroll',
        destination: channel === 'EMAIL' ? (bill.email || 'Company email') : 'System log',
        reference: bill.bill_no,
        message: `Billing cycle ${bill.bill_no} for ${peso(bill.total_amount || 0)} is issued for payroll deduction.`,
        createdAt: bill.issued_at || bill.created_at || new Date().toISOString(),
      }))
    }
  }

  return rows.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
})

const durableNotifications = computed(() => durableLog.value.map(item => ({
  id: item.source_key || `notification-${item.id}`,
  backendId: item.id,
  manual: item.event_key === 'manual',
  eventKey: item.event_key,
  eventLabel: item.event_label || (item.event_key === 'manual' ? 'Manual Notice' : item.event_key),
  channel: item.channel,
  recipientName: item.recipient_name,
  destination: item.destination,
  reference: item.reference || 'Stored Notice',
  message: item.message,
  status: item.status,
  createdAt: item.created_at,
})))

const manualNotifications = computed(() => [])

const notifications = computed(() => {
  const storedKeys = new Set(durableNotifications.value.map(item => item.id))
  const generated = generatedNotifications.value.filter(item => !storedKeys.has(item.id))
  return [...durableNotifications.value, ...manualNotifications.value, ...generated]
})

const filteredNotifications = computed(() => notifications.value.filter(item => {
  const haystack = `${item.eventLabel} ${item.recipientName} ${item.destination} ${item.reference} ${item.message}`.toLowerCase()
  if (filters.channel && item.channel !== filters.channel) return false
  if (filters.status && item.status !== filters.status) return false
  if (filters.search && !haystack.includes(filters.search.toLowerCase())) return false
  return true
}))

const totals = computed(() => notifications.value.reduce((sum, item) => {
  const key = String(item.status || 'QUEUED').toLowerCase()
  if (sum[key] !== undefined) sum[key] += 1
  return sum
}, { queued: 0, sent: 0, failed: 0, disabled: 0 }))

const summaryHeadline = computed(() => `${totals.value.queued} queued notice(s), ${totals.value.failed} needing retry`)
const summaryText = computed(() => `This log is generated from applications, amortization schedules, payments, and company bills. Sent, failed, and manual notices are stored in the backend notification log when available.`)

function formatDateTime(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString('en-PH', { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

async function persistNotice(item, status) {
  const payload = {
    source_key: item.id,
    event_key: item.eventKey || 'manual',
    event_label: item.eventLabel || 'Manual Notice',
    channel: item.channel || 'SYSTEM',
    recipient_name: item.recipientName || item.recipient || 'Recipient',
    destination: item.destination || 'System log',
    reference: item.reference || null,
    message: item.message,
    status,
    payload: item,
  }
  const saved = item.backendId
    ? await api.updateNotificationLog(item.backendId, { status })
    : await api.createNotificationLog(payload)
  durableLog.value = [saved, ...durableLog.value.filter(row => Number(row.id) !== Number(saved.id) && row.source_key !== saved.source_key)]
}

async function updateNotice(item, status) {
  await persistNotice(item, status)
}

async function markSent(item) {
  if (item.status === 'DISABLED') return
  await updateNotice(item, 'SENT')
  success('Notification marked as sent.')
}

async function markFailed(item) {
  if (item.status === 'DISABLED') return
  await updateNotice(item, 'FAILED')
  error('Notification marked as failed for retry.')
}

async function markAllQueuedSent() {
  for (const item of filteredNotifications.value) {
    if (item.status === 'QUEUED') await updateNotice(item, 'SENT')
  }
  success('Queued notifications marked as sent.')
}

async function addManualNotice() {
  if (!manual.recipient || !manual.message) {
    error('Recipient and message are required.')
    return
  }
  const id = `manual-${Date.now()}`
  const notice = {
    id,
    manual: true,
    eventKey: 'manual',
    eventLabel: 'Manual Notice',
    channel: manual.channel,
    recipientName: manual.recipient,
    destination: manual.destination || 'System log',
    reference: 'Manual',
    message: manual.message,
    status: 'QUEUED',
    createdAt: new Date().toISOString(),
  }
  await persistNotice(notice, 'QUEUED')
  Object.assign(manual, { channel: 'SYSTEM', recipient: '', destination: '', message: '' })
  success('Manual notice queued.')
}

async function load() {
  loading.value = true
  try {
    const [loanRowsRaw, paymentRows, billRowsRaw, notificationRows] = await Promise.all([api.getLoans(), api.getPayments(), api.getBills(), api.getNotificationLogs({ limit: 500 }).catch(() => [])])
    payments.value = paymentRows
    durableLog.value = notificationRows
    bills.value = await Promise.all(billRowsRaw.map(async bill => {
      if (!bill.id) return bill
      try { return await api.getBill(bill.id) } catch { return bill }
    }))
    loans.value = await Promise.all(loanRowsRaw.map(async loan => {
      try { return await api.getLoan(loan.id) } catch { return loan }
    }))
  } catch (err) {
    error(err.message || 'Could not load notifications.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.notify-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header { display:flex; justify-content:space-between; align-items:flex-end; flex-shrink:0; }
.view-title { font-size:clamp(34px,3.1vw,52px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:12px; align-items:center; }
.header-actions .form-select { width:190px; min-height:44px; border-radius:9px; }
.header-actions .btn { min-height:44px; border-radius:9px; }
.notify-body { flex:1; overflow:auto; padding:28px 32px; display:flex; flex-direction:column; gap:24px; min-width:0; }
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
.notify-grid { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:16px; align-items:start; }
.report-card, .settings-card { background:#fff; border:1px solid var(--coop-border); border-radius:10px; overflow:hidden; box-shadow:0 12px 30px rgba(31,41,55,.045); }
.report-head { padding:18px 20px; border-bottom:1px solid var(--coop-border); margin:0; display:flex; justify-content:space-between; gap:14px; align-items:center; }
.report-head h3, .settings-card h3 { margin:4px 0 0; color:#202838; font-size:22px; font-weight:900; }
.table-actions { display:flex; gap:10px; align-items:center; }
.search-input { width:320px; min-height:42px; }
.notify-table { min-width:1050px; }
.data-table th { background:#F8FAFC; color:#737B8D; padding:14px 18px; }
.data-table td { padding:16px 18px; border-bottom:1px solid #E8ECF3; vertical-align:top; }
.data-table tbody tr:hover { background:#FFF8F6; }
.message-cell { max-width:360px; color:#3E4656; line-height:1.4; }
.channel-pill { display:inline-flex; border-radius:999px; padding:5px 8px; font-size:11px; font-weight:900; letter-spacing:.05em; }
.channel-sms { background:#EAF7EF; color:#2F7D46; }
.channel-email { background:#EAF2FF; color:#2B5C9B; }
.channel-system { background:#F3F4F6; color:#4B5563; }
.channel-disabled { background:#FEE2E2; color:#B91C1C; }
.inline-actions { display:flex; gap:6px; }
.btn-small { min-height:30px; padding:6px 9px; border-radius:7px; }
.small-text { font-size:11px; }
.side-stack { display:flex; flex-direction:column; gap:16px; }
.settings-card { padding:18px; }
.card-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
.card-head.compact { align-items:center; }
.mini-link { color:var(--coop-red); font-size:12px; font-weight:800; text-decoration:none; }
.event-list { display:flex; flex-direction:column; gap:10px; }
.event-row { border:1px solid var(--coop-border); background:#F8FAFC; border-radius:9px; padding:12px; display:flex; justify-content:space-between; gap:12px; }
.event-row strong { display:block; color:#202838; }
.event-row span { color:#6D7484; font-size:12px; }
.event-channels { display:flex; gap:5px; align-items:flex-start; }
.tiny-toggle { border-radius:999px; background:#EEF2F7; color:#8B94A3; padding:4px 7px; font-size:10px; font-weight:900; }
.tiny-toggle.on { background:#EAF7EF; color:#2F7D46; }
.manual-form { display:flex; flex-direction:column; gap:10px; }
.manual-form .form-textarea { min-height:100px; }
.loading-state { min-height:280px; }
.empty-row { text-align:center; padding:36px; color:var(--coop-muted); }
@media (max-width: 1180px) { .notify-grid { grid-template-columns:1fr; } .summary-strip { flex-direction:column; align-items:flex-start; } }
@media (max-width: 760px) { .notify-body { padding:18px 14px; } .stats-row { grid-template-columns:1fr; } .header-actions, .table-actions { flex-wrap:wrap; justify-content:flex-end; } .search-input { width:100%; } }
</style>
