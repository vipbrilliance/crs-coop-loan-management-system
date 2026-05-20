<template>
  <div class="share-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Share Capital Ledger</div>
        <div class="view-sub">Member deposits, withdrawals, dividends, adjustments, and running balances</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="loadData">Refresh</button>
        <button class="btn btn-primary" @click="printLedger" :disabled="!selectedMember">Print Share Capital</button>
      </div>
    </header>

    <main class="share-body">
      <aside class="member-panel">
        <div class="panel-title">Members</div>
        <div class="member-search-panel">
          <input v-model.trim="searchTerm" class="form-input search-input" type="search" placeholder="Search member or ledger ref" />
        </div>
        <div class="member-list">
          <button
            v-for="member in filteredMembers"
            :key="member.id"
            :class="['member-row', selectedMember?.id === member.id && 'active']"
            @click="selectMember(member)"
          >
            <div>
              <div class="row-title">{{ member.first_name }} {{ member.last_name }}</div>
              <div class="row-sub">{{ member.member_no }} · {{ member.department || 'No department' }}</div>
            </div>
            <strong>{{ peso(balanceFor(member.id)) }}</strong>
          </button>
          <div v-if="!filteredMembers.length" class="empty-inline">No matching members</div>
        </div>
      </aside>

      <section class="ledger-workspace">
        <section class="ledger-grid">
          <form class="posting-card" @submit.prevent="postTransaction">
            <div class="panel-title inline">Post Transaction</div>
            <div v-if="selectedMember" class="selected-member">
              <div>
                <div class="row-title">{{ selectedMember.first_name }} {{ selectedMember.last_name }}</div>
                <div class="row-sub">{{ selectedMember.member_no }} · Current {{ peso(selectedBalance) }}</div>
              </div>
              <span class="badge badge-approved">{{ selectedMember.member_status }}</span>
            </div>
            <div v-else class="empty-inline compact">Select a member before posting.</div>
            <div class="transaction-number">
              <span>Transaction No.</span>
              <strong>{{ nextTransactionNo }}</strong>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Transaction Type</label>
                <select v-model="form.type" class="form-select">
                  <option value="DEPOSIT">Deposit</option>
                  <option value="WITHDRAWAL">Withdrawal</option>
                  <option value="DIVIDEND">Dividend</option>
                  <option value="ADJUSTMENT">Adjustment</option>
                  <option value="OPENING">Opening Balance</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Amount</label>
                <input v-model.number="form.amount" class="form-input" type="number" min="0" step="0.01" />
              </div>
              <div class="form-group">
                <label class="form-label">Transaction Date</label>
                <input v-model="form.date" class="form-input" type="date" />
              </div>
              <div class="form-group">
                <label class="form-label">Reference</label>
                <input v-model.trim="form.reference" class="form-input" :placeholder="nextTransactionNo" />
              </div>
            </div>
            <textarea v-model.trim="form.remarks" class="form-textarea" placeholder="Remarks / approval note"></textarea>
            <button class="btn btn-primary submit-btn" type="submit" :disabled="!selectedMember">Post and Sync Balance</button>
          </form>

          <section class="report-card">
            <div class="panel-title inline">Member Capital Details</div>
            <div v-if="selectedMember" class="member-capital-summary">
              <div>
                <span>Member</span>
                <strong>{{ selectedMember.first_name }} {{ selectedMember.last_name }}</strong>
                <small>{{ selectedMember.member_no }} · {{ selectedMember.department || 'No department' }}</small>
              </div>
              <div>
                <span>Current Balance</span>
                <strong>{{ peso(selectedBalance) }}</strong>
                <small>{{ selectedLedger.length }} transaction(s)</small>
              </div>
              <div>
                <span>Total Deposits</span>
                <strong>{{ peso(memberTotals.deposits) }}</strong>
                <small>Posted deposits only</small>
              </div>
              <div>
                <span>Withdrawals / Adjustments</span>
                <strong>{{ peso(memberTotals.outflows) }}</strong>
                <small>Withdrawals and negative adjustments</small>
              </div>
            </div>
            <div v-else class="empty-inline compact">Select a member to view capital details.</div>
          </section>
        </section>

        <section class="ledger-card">
          <div class="ledger-card-head">
            <div class="panel-title inline">Ledger Transactions</div>
            <div class="ledger-actions">
              <button class="btn btn-secondary btn-sm" @click="printLedger">Print Ledger</button>
            </div>
          </div>
          <table class="ledger-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Member</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Attachment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in displayedLedger" :key="row.id" :class="{ voided: row.voided }">
                <td>{{ formatDate(row.date) }}</td>
                <td class="mono">{{ row.reference }}</td>
                <td>
                  <div class="row-title">{{ memberName(row.member_id) }}</div>
                  <div class="row-sub">{{ memberNo(row.member_id) }}</div>
                </td>
                <td><span :class="['type-pill', row.type.toLowerCase()]">{{ row.type }}</span></td>
                <td class="peso" :class="{ negative: signedAmount(row) < 0 }">{{ peso(signedAmount(row)) }}</td>
                <td class="peso fw-600">{{ peso(row.balance_after) }}</td>
                <td><span :class="['badge', row.voided ? 'badge-closed' : 'badge-approved']">{{ row.voided ? 'VOIDED' : 'POSTED' }}</span></td>
                <td class="attachment-cell">
                  <label class="table-attach-btn">
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="attachToRow(row, $event)" />
                    <span>{{ row.attachment_name ? 'View' : 'Attach' }}</span>
                  </label>
                  <small v-if="row.attachment_name">{{ row.attachment_name }}</small>
                </td>
                <td>
                  <button class="btn btn-secondary btn-sm" :disabled="row.voided" @click="voidTransaction(row)">Void</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="!displayedLedger.length" class="empty-inline">No ledger entries yet</div>
        </section>
      </section>
    </main>

    <section v-if="selectedMember" class="print-report">
      <div class="print-header">
        <div>
          <h1>CRS Holdings Corporations Employees Credit Cooperative</h1>
          <p>Mandaue City · Cebu</p>
        </div>
        <div>
          <strong>Share Capital Member Report</strong>
          <span>Generated {{ formatDate(new Date().toISOString()) }}</span>
        </div>
      </div>

      <div class="print-section">
        <h2>Basic Member Details</h2>
        <div class="print-grid">
          <div><span>Member No.</span><strong>{{ selectedMember.member_no }}</strong></div>
          <div><span>Full Name</span><strong>{{ selectedMember.first_name }} {{ selectedMember.middle_name || '' }} {{ selectedMember.last_name }}</strong></div>
          <div><span>Contact</span><strong>{{ selectedMember.contact || '-' }}</strong></div>
          <div><span>Email</span><strong>{{ selectedMember.email || '-' }}</strong></div>
          <div class="wide"><span>Address</span><strong>{{ selectedMember.address || '-' }}</strong></div>
          <div><span>Member Status</span><strong>{{ selectedMember.member_status || '-' }}</strong></div>
          <div><span>Active Loans</span><strong>{{ selectedMember.active_loans || 0 }}</strong></div>
        </div>
      </div>

      <div class="print-section">
        <h2>Employment Details</h2>
        <div class="print-grid">
          <div><span>Company</span><strong>{{ selectedMember.company || '-' }}</strong></div>
          <div><span>Branch</span><strong>{{ selectedMember.branch || '-' }}</strong></div>
          <div><span>Department</span><strong>{{ selectedMember.department || '-' }}</strong></div>
          <div><span>Position</span><strong>{{ selectedMember.position || '-' }}</strong></div>
          <div><span>Employment Status</span><strong>{{ selectedMember.status || '-' }}</strong></div>
          <div><span>Direct Supervisor</span><strong>{{ selectedMember.supervisor || '-' }}</strong></div>
          <div><span>Date Hired</span><strong>{{ formatDate(selectedMember.date_hired) }}</strong></div>
          <div><span>Monthly Salary</span><strong>{{ peso(selectedMember.monthly_salary || 0) }}</strong></div>
        </div>
      </div>

      <div class="print-section">
        <h2>Capital Share Summary</h2>
        <div class="print-summary-grid">
          <div><span>Current Balance</span><strong>{{ peso(selectedBalance) }}</strong></div>
          <div><span>Total Deposits</span><strong>{{ peso(memberTotals.deposits) }}</strong></div>
          <div><span>Withdrawals / Adjustments</span><strong>{{ peso(memberTotals.outflows) }}</strong></div>
          <div><span>Transactions</span><strong>{{ selectedLedger.length }}</strong></div>
        </div>
      </div>

      <div class="print-section">
        <h2>Share Capital Transactions</h2>
        <table class="print-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Transaction No.</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Attachment</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in selectedLedgerForPrint" :key="`print-${row.id}`">
              <td>{{ formatDate(row.date) }}</td>
              <td>{{ row.reference }}</td>
              <td>{{ row.type }}</td>
              <td>{{ peso(signedAmount(row)) }}</td>
              <td>{{ peso(row.balance_after) }}</td>
              <td>{{ row.voided ? 'VOIDED' : 'POSTED' }}</td>
              <td>{{ row.attachment_name || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="print-signatures">
        <div><span></span><strong>Prepared by</strong></div>
        <div><span></span><strong>Verified by</strong></div>
        <div><span></span><strong>Member Signature</strong></div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { api } from '../composables/useApi'
import { peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const { success, error } = useToast()
const members = ref([])
const ledger = ref([])
const selectedMember = ref(null)
const searchTerm = ref('')
const printMode = ref(false)

const form = reactive({
  type: 'DEPOSIT',
  amount: 1000,
  date: new Date().toISOString().slice(0, 10),
  reference: '',
  remarks: '',
})

const memberMap = computed(() => new Map(members.value.map(member => [member.id, member])))
const filteredMembers = computed(() => {
  const query = searchTerm.value.toLowerCase()
  if (!query) return members.value
  return members.value.filter(member => [
    member.member_no,
    member.first_name,
    member.last_name,
    `${member.first_name} ${member.last_name}`,
    member.department,
    member.position,
  ].some(value => String(value || '').toLowerCase().includes(query)))
})

const activeLedger = computed(() => ledger.value.filter(row => !row.voided))
const displayedLedger = computed(() => {
  const query = searchTerm.value.toLowerCase()
  const base = selectedMember.value
    ? ledger.value.filter(row => row.member_id === selectedMember.value.id)
    : ledger.value

  return base
    .filter(row => {
      if (!query) return true
      return [
        row.reference,
        row.type,
        row.remarks,
        memberName(row.member_id),
        memberNo(row.member_id),
      ].some(value => String(value || '').toLowerCase().includes(query))
    })
    .sort((a, b) => new Date(b.date) - new Date(a.date) || b.id - a.id)
})

const selectedBalance = computed(() => selectedMember.value ? balanceFor(selectedMember.value.id) : 0)
const selectedLedger = computed(() => selectedMember.value ? activeLedger.value.filter(row => row.member_id === selectedMember.value.id) : [])
const selectedLedgerForPrint = computed(() => [...selectedLedger.value].sort((a, b) => new Date(a.date) - new Date(b.date) || a.id - b.id))
const nextTransactionNo = computed(() => `SC-${new Date().getFullYear()}-${String(ledger.value.length + 1).padStart(5, '0')}`)
const memberTotals = computed(() => ({
  deposits: selectedLedger.value.filter(row => ['OPENING', 'DEPOSIT', 'DIVIDEND'].includes(row.type)).reduce((sum, row) => sum + Number(row.amount || 0), 0),
  outflows: selectedLedger.value.filter(row => row.type === 'WITHDRAWAL' || (row.type === 'ADJUSTMENT' && signedAmount(row) < 0)).reduce((sum, row) => sum + Math.abs(signedAmount(row)), 0),
}))

function signedAmount(row) {
  return row.type === 'WITHDRAWAL' ? -Number(row.amount || 0) : Number(row.amount || 0)
}

function balanceFor(memberId) {
  return activeLedger.value
    .filter(row => row.member_id === memberId)
    .sort((a, b) => new Date(a.date) - new Date(b.date) || a.id - b.id)
    .reduce((balance, row) => balance + signedAmount(row), 0)
}

function recomputeLedger(rows = ledger.value) {
  const balances = {}
  const sorted = [...rows].sort((a, b) => new Date(a.date) - new Date(b.date) || a.id - b.id)
  sorted.forEach(row => {
    if (!balances[row.member_id]) balances[row.member_id] = 0
    if (!row.voided) balances[row.member_id] += signedAmount(row)
    row.balance_after = balances[row.member_id]
  })
  ledger.value = sorted.sort((a, b) => new Date(b.date) - new Date(a.date) || b.id - a.id)
}

function seedLedgerFromMembers(memberRows) {
  return memberRows
    .filter(member => Number(member.share_capital || 0) > 0)
    .map((member, index) => ({
      id: index + 1,
      member_id: member.id,
      date: '2026-01-01',
      type: 'OPENING',
      amount: Number(member.share_capital || 0),
      reference: `SC-OPEN-${member.member_no}`,
      remarks: 'Opening balance from member profile',
      balance_after: Number(member.share_capital || 0),
      voided: false,
      created_at: new Date().toISOString(),
    }))
}

async function ensureOpeningBalances(memberRows) {
  const rows = await api.getShareCapitalLedger()
  if (rows.length) return rows

  const seeded = []
  for (const member of memberRows.filter(item => Number(item.share_capital || 0) > 0)) {
    const entry = await api.createShareCapitalEntry({
      member_id: member.id,
      date: '2026-01-01',
      type: 'OPENING',
      amount: Number(member.share_capital || 0),
      reference: `SC-OPEN-${member.member_no}`,
      source: 'opening',
      remarks: 'Opening balance from member profile',
      source_key: `share-opening-${member.id}`,
    })
    seeded.push(entry)
  }
  return seeded.length ? seeded : seedLedgerFromMembers(memberRows)
}

async function loadLedger(memberRows) {
  const rows = await ensureOpeningBalances(memberRows)
  ledger.value = rows.map(row => ({ ...row, date: row.date || row.transaction_date, voided: Boolean(Number(row.voided ?? row.voided)) }))
  recomputeLedger()
}

function saveLedger() {
  // The API is the source of truth. Local recompute keeps the current screen responsive.
}

async function syncMemberBalance(memberId) {
  const member = memberMap.value.get(memberId)
  if (!member) return
  const balance = balanceFor(memberId)
  member.share_capital = balance
  try {
    await api.updateMember(memberId, { ...member, share_capital: balance })
  } catch {
    // Preview mode can still keep the displayed ledger correct.
  }
}

async function postTransaction() {
  if (!selectedMember.value) return error('Select a member before posting.')
  if (!form.amount || Number(form.amount) <= 0) return error('Enter a valid amount.')

  const reference = form.reference || nextTransactionNo.value
  const entry = await api.createShareCapitalEntry({
    member_id: selectedMember.value.id,
    date: form.date,
    type: form.type,
    amount: Number(form.amount),
    reference,
    remarks: form.remarks,
    source: 'manual',
  })
  ledger.value.unshift({ ...entry, date: entry.date || entry.transaction_date, voided: Boolean(Number(entry.voided ?? entry.voided)) })
  recomputeLedger()
  await syncMemberBalance(selectedMember.value.id)
  form.reference = ''
  form.remarks = ''
  success(`${reference} posted and balance synced.`)
}

async function attachToRow(row, event) {
  const file = event.target.files?.[0]
  if (!file) return
  row.attachment_name = file.name
  try {
    await api.updateShareCapitalEntry(row.id, { attachment_name: file.name })
  } catch {}
  success(`Attachment added to ${row.reference}.`)
}

async function voidTransaction(row) {
  const updated = await api.updateShareCapitalEntry(row.id, { voided: true })
  row.voided = true
  row.voided_at = updated.voided_at || new Date().toISOString()
  recomputeLedger()
  await syncMemberBalance(row.member_id)
  success(`${row.reference} voided.`)
}

function selectMember(member) {
  selectedMember.value = member
}

function memberName(id) {
  const member = memberMap.value.get(id)
  return member ? `${member.first_name} ${member.last_name}` : 'Unknown member'
}

function memberNo(id) {
  return memberMap.value.get(id)?.member_no || '-'
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

async function printLedger() {
  if (!selectedMember.value) return
  printMode.value = true
  document.body.classList.add('share-capital-print')
  await nextTick()
  const cleanup = () => {
    printMode.value = false
    document.body.classList.remove('share-capital-print')
    window.removeEventListener('afterprint', cleanup)
  }
  window.addEventListener('afterprint', cleanup)
  window.print()
  setTimeout(cleanup, 1000)
}

async function loadData() {
  members.value = await api.getMembers()
  await loadLedger(members.value)
  selectedMember.value = members.value.find(member => member.id === selectedMember.value?.id) || members.value[0] || null
}

onMounted(loadData)
</script>

<style scoped>
.share-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
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
.search-input { width:100%; }
.share-body {
  flex:1;
  min-height:0;
  overflow:hidden;
  display:grid;
  grid-template-columns:320px minmax(0, 1fr);
}
.member-panel {
  background:#fff;
  border-right:1px solid var(--coop-border);
  min-height:0;
  display:flex;
  flex-direction:column;
}
.panel-title {
  padding:14px 16px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  font-size:16px;
  font-weight:900;
}
.panel-title.inline { padding:0 0 14px; border-bottom:0; }
.member-search-panel {
  padding:10px;
  border-bottom:1px solid var(--coop-border);
  background:#F8FAFC;
}
.member-list { padding:10px; display:flex; flex-direction:column; gap:8px; overflow:auto; }
.member-row {
  border:1px solid var(--coop-border);
  background:#fff;
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  text-align:left;
  cursor:pointer;
}
.member-row:hover, .member-row.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.25); }
.member-row.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.member-row strong { color:var(--coop-cream); font-family:var(--font-mono); white-space:nowrap; }
.row-title { color:var(--coop-cream); font-weight:900; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.ledger-workspace { min-width:0; overflow:auto; padding:18px 22px 30px; background:#F3F5F8; }
.posting-card, .report-card, .ledger-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
}
.ledger-grid { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:14px; margin-bottom:14px; }
.posting-card, .report-card, .ledger-card { padding:16px; }
.selected-member {
  border:1px solid rgba(192,57,43,.22);
  background:var(--coop-red-dim);
  border-radius:8px;
  padding:12px;
  margin-bottom:12px;
  display:flex;
  justify-content:space-between;
  gap:10px;
  align-items:center;
}
.transaction-number {
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#F8FAFC;
  padding:10px 12px;
  margin-bottom:12px;
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
}
.transaction-number span {
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:.6px;
  text-transform:uppercase;
}
.transaction-number strong { color:var(--coop-cream); font-family:var(--font-mono); }
.form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; margin-bottom:12px; }
.submit-btn { margin-top:12px; width:100%; justify-content:center; }
.member-capital-summary { display:flex; flex-direction:column; gap:8px; }
.member-capital-summary > div {
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  flex-direction:column;
  gap:3px;
}
.member-capital-summary span {
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:.5px;
  text-transform:uppercase;
}
.member-capital-summary strong { color:var(--coop-cream); font-family:var(--font-mono); }
.member-capital-summary small { color:var(--coop-muted); }
.ledger-card-head { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.ledger-table { width:100%; border-collapse:collapse; margin-top:4px; }
.ledger-table th {
  background:#F8FAFC;
  color:var(--coop-muted);
  font-size:11px;
  font-weight:900;
  letter-spacing:.5px;
  text-transform:uppercase;
  padding:10px;
  text-align:left;
  border-bottom:1px solid var(--coop-border);
}
.ledger-table td {
  padding:10px;
  border-bottom:1px solid var(--coop-border);
  color:var(--coop-cream);
  vertical-align:middle;
}
.attachment-cell {
  max-width:160px;
  color:var(--coop-muted) !important;
}
.table-attach-btn {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border:1px solid #D2D8E3;
  border-radius:7px;
  background:#fff;
  color:var(--coop-cream);
  padding:6px 10px;
  font-size:12px;
  font-weight:900;
  cursor:pointer;
}
.table-attach-btn input { display:none; }
.attachment-cell small {
  display:block;
  margin-top:4px;
  max-width:140px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}
.ledger-table tr.voided { opacity:.55; text-decoration:line-through; }
.type-pill {
  display:inline-flex;
  border-radius:999px;
  padding:4px 8px;
  font-size:11px;
  font-weight:900;
  background:#EEF2F7;
  color:var(--coop-muted);
}
.type-pill.deposit, .type-pill.opening { background:rgba(39,174,96,.12); color:#1f8f4f; }
.type-pill.dividend { background:rgba(41,128,185,.12); color:#256d9e; }
.type-pill.adjustment { background:rgba(243,156,18,.14); color:#a96906; }
.type-pill.withdrawal { background:rgba(192,57,43,.12); color:var(--coop-red); }
.negative { color:var(--coop-red) !important; }
.empty-inline { padding:22px; text-align:center; color:var(--coop-muted); }
.empty-inline.compact { padding:14px; border:1px dashed var(--coop-border); border-radius:8px; margin-bottom:12px; }
.print-report { display:none; }
:global(body.share-capital-print) {
  background:#fff !important;
}
:global(body.share-capital-print .sidebar),
:global(body.share-capital-print .mobile-topbar),
:global(body.share-capital-print .mobile-overlay),
:global(body.share-capital-print .demo-launcher),
:global(body.share-capital-print .demo-menu-panel),
:global(body.share-capital-print .tour-scrim),
:global(body.share-capital-print .toast-container),
:global(body.share-capital-print .view-header),
:global(body.share-capital-print .share-body) {
  display:none !important;
}
:global(body.share-capital-print #app),
:global(body.share-capital-print .app-shell),
:global(body.share-capital-print .main-area),
:global(body.share-capital-print .share-wrap) {
  display:block !important;
  width:auto !important;
  height:auto !important;
  min-height:0 !important;
  overflow:visible !important;
  background:#fff !important;
}
:global(body.share-capital-print) .print-report {
  display:block;
  color:#111827;
  background:#fff;
  padding:18mm 16mm;
  font-size:11px;
  line-height:1.35;
}
:global(body.share-capital-print) .print-header {
  display:flex;
  justify-content:space-between;
  gap:20px;
  border-bottom:2px solid #111827;
  padding-bottom:10px;
  margin-bottom:14px;
}
:global(body.share-capital-print) .print-header h1 { font-size:15px; margin:0; }
:global(body.share-capital-print) .print-header p { margin:3px 0 0; color:#4B5563; }
:global(body.share-capital-print) .print-header div:last-child { text-align:right; display:flex; flex-direction:column; gap:3px; }
:global(body.share-capital-print) .print-header strong { font-size:13px; }
:global(body.share-capital-print) .print-header span { color:#4B5563; }
:global(body.share-capital-print) .print-section { margin-top:14px; break-inside:avoid; }
:global(body.share-capital-print) .print-section h2 {
  font-size:13px;
  margin:0 0 8px;
  color:#111827;
  border-bottom:1px solid #D1D5DB;
  padding-bottom:4px;
}
:global(body.share-capital-print) .print-grid,
:global(body.share-capital-print) .print-summary-grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:6px 14px;
}
:global(body.share-capital-print) .print-summary-grid { grid-template-columns:repeat(4, 1fr); }
:global(body.share-capital-print) .print-grid div,
:global(body.share-capital-print) .print-summary-grid div {
  border:1px solid #D1D5DB;
  padding:6px 8px;
  display:flex;
  flex-direction:column;
  gap:2px;
}
:global(body.share-capital-print) .print-grid .wide { grid-column:1 / -1; }
:global(body.share-capital-print) .print-grid span,
:global(body.share-capital-print) .print-summary-grid span {
  color:#6B7280;
  font-size:9px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.4px;
}
:global(body.share-capital-print) .print-grid strong,
:global(body.share-capital-print) .print-summary-grid strong { color:#111827; font-weight:700; }
:global(body.share-capital-print) .print-table {
  width:100%;
  border-collapse:collapse;
  margin-top:6px;
}
:global(body.share-capital-print) .print-table th {
  background:#F3F4F6;
  border:1px solid #9CA3AF;
  padding:5px;
  text-align:left;
  font-size:9px;
  text-transform:uppercase;
}
:global(body.share-capital-print) .print-table td {
  border:1px solid #D1D5DB;
  padding:5px;
  vertical-align:top;
}
:global(body.share-capital-print) .print-signatures {
  display:grid;
  grid-template-columns:repeat(3, 1fr);
  gap:28px;
  margin-top:30px;
}
:global(body.share-capital-print) .print-signatures span {
  display:block;
  border-bottom:1px solid #111827;
  height:24px;
  margin-bottom:5px;
}
:global(body.share-capital-print) .print-signatures strong { font-size:10px; text-transform:uppercase; }
@media (max-width: 1180px) {
  .share-body, .ledger-grid { grid-template-columns:1fr; overflow:auto; }
  .member-panel { border-right:0; border-bottom:1px solid var(--coop-border); max-height:340px; }
}
@media (max-width: 760px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .header-actions { width:100%; flex-direction:column; align-items:stretch; }
  .search-input { width:100%; }
  .form-grid { grid-template-columns:1fr; }
  .ledger-workspace { padding:14px; }
}
@media print {
  :global(.sidebar),
  :global(.mobile-topbar),
  :global(.mobile-overlay),
  :global(.demo-launcher),
  :global(.demo-menu-panel),
  :global(.tour-scrim),
  :global(.toast-container) {
    display:none !important;
  }
  :global(.app-shell),
  :global(.main-area) {
    display:block !important;
    width:auto !important;
    height:auto !important;
    min-height:0 !important;
    overflow:visible !important;
    background:#fff !important;
  }
  .view-header, .share-body { display:none !important; }
  .share-wrap { display:block; height:auto; overflow:visible; background:#fff; }
  .print-report {
    display:block;
    color:#111827;
    background:#fff;
    padding:18mm 16mm;
    font-size:11px;
    line-height:1.35;
  }
  .print-header {
    display:flex;
    justify-content:space-between;
    gap:20px;
    border-bottom:2px solid #111827;
    padding-bottom:10px;
    margin-bottom:14px;
  }
  .print-header h1 { font-size:15px; margin:0; }
  .print-header p { margin:3px 0 0; color:#4B5563; }
  .print-header div:last-child { text-align:right; display:flex; flex-direction:column; gap:3px; }
  .print-header strong { font-size:13px; }
  .print-header span { color:#4B5563; }
  .print-section { margin-top:14px; break-inside:avoid; }
  .print-section h2 {
    font-size:13px;
    margin:0 0 8px;
    color:#111827;
    border-bottom:1px solid #D1D5DB;
    padding-bottom:4px;
  }
  .print-grid, .print-summary-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:6px 14px;
  }
  .print-summary-grid { grid-template-columns:repeat(4, 1fr); }
  .print-grid div, .print-summary-grid div {
    border:1px solid #D1D5DB;
    padding:6px 8px;
    display:flex;
    flex-direction:column;
    gap:2px;
  }
  .print-grid .wide { grid-column:1 / -1; }
  .print-grid span, .print-summary-grid span {
    color:#6B7280;
    font-size:9px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.4px;
  }
  .print-grid strong, .print-summary-grid strong { color:#111827; font-weight:700; }
  .print-table {
    width:100%;
    border-collapse:collapse;
    margin-top:6px;
  }
  .print-table th {
    background:#F3F4F6;
    border:1px solid #9CA3AF;
    padding:5px;
    text-align:left;
    font-size:9px;
    text-transform:uppercase;
  }
  .print-table td {
    border:1px solid #D1D5DB;
    padding:5px;
    vertical-align:top;
  }
  .print-signatures {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:28px;
    margin-top:30px;
  }
  .print-signatures span {
    display:block;
    border-bottom:1px solid #111827;
    height:24px;
    margin-bottom:5px;
  }
  .print-signatures strong { font-size:10px; text-transform:uppercase; }
}
</style>
