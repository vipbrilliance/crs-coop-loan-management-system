<template>
  <div class="view-wrap">
    <div class="view-header">
      <div>
        <div class="view-title serif">Loan Pipeline</div>
        <div class="view-sub">Track applications from draft to active</div>
      </div>
      <div class="header-actions">
        <div class="view-toggle">
          <button :class="viewMode === 'board' && 'active'" @click="viewMode = 'board'">Board</button>
          <button :class="viewMode === 'list' && 'active'" @click="viewMode = 'list'">List</button>
        </div>
        <button class="btn btn-primary" @click="$router.push('/loans')">+ New Application</button>
      </div>
    </div>

    <div v-if="loading" class="empty-state" style="flex:1"><div class="spinner"></div></div>

    <div v-else-if="viewMode === 'board'" class="pipeline-board">
      <div v-for="(col, status) in columns" :key="status" class="pipeline-col">
        <div class="col-header" :style="{ borderTopColor: col.color }">
          <div class="col-title">{{ col.label }}</div>
          <div class="col-count">{{ pipeline[status]?.length || 0 }}</div>
        </div>
        <div class="col-cards">
          <div v-if="!pipeline[status]?.length" class="col-empty">No loans</div>
          <div v-for="loan in pipeline[status]" :key="loan.id" class="loan-card" @click="openLoan(loan)">
            <div class="lc-no mono">{{ loan.loan_no }}</div>
            <div class="lc-name">{{ loan.first_name }} {{ loan.last_name }}</div>
            <div class="lc-type text-muted">{{ loan.loan_type_label }}</div>
            <div class="lc-amount peso">{{ peso(loan.amount) }}</div>
            <div class="lc-meta">
              <span class="text-muted" style="font-size:11px">{{ formatDate(loan.created_at) }}</span>
              <span class="text-muted mono" style="font-size:11px">{{ loan.term_months }}mo</span>
            </div>

            <!-- Quick actions -->
            <div class="lc-actions" v-if="status === 'PENDING'">
              <button class="btn btn-sm" style="background:var(--status-approved);color:#fff;border:none"
                @click.stop="openApproval(loan, 'APPROVED')">Approve</button>
              <button class="btn btn-sm btn-secondary"
                @click.stop="openApproval(loan, 'REJECTED')">Reject</button>
            </div>
            <div class="lc-actions" v-if="status === 'APPROVED'">
              <button class="btn btn-sm" style="background:var(--status-active);color:#fff;border:none"
                @click.stop="openApproval(loan, 'ACTIVE')">Activate</button>
            </div>
          </div>
        </div>
      </div>
    </div>



    <div v-else class="pipeline-list">
      <table class="pipeline-table">
        <thead>
          <tr>
            <th>Loan No.</th>
            <th>Member</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Term</th>
            <th>Status</th>
            <th>Created</th>
            <th>Signed Approval</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="loan in loanRows" :key="loan.id" @click="openLoan(loan)">
            <td class="mono">{{ loan.loan_no }}</td>
            <td><strong>{{ loan.first_name }} {{ loan.last_name }}</strong><span class="muted-line mono">{{ loan.member_no }}</span></td>
            <td>{{ loan.loan_type_label }}</td>
            <td class="peso">{{ peso(loan.amount) }}</td>
            <td>{{ loan.term_months }} mo</td>
            <td><span :class="['status-pill', loan.status.toLowerCase()]">{{ loan.status }}</span></td>
            <td>{{ formatDate(loan.created_at) }}</td>
            <td>{{ loan.signed_form_url || loan.approval_attachment_name || '—' }}</td>
            <td @click.stop>
              <div class="table-actions">
                <button v-if="loan.status === 'PENDING'" class="mini-action approve" @click="openApproval(loan, 'APPROVED')">Approve</button>
                <button v-if="loan.status === 'PENDING'" class="mini-action" @click="openApproval(loan, 'REJECTED')">Reject</button>
                <button v-if="loan.status === 'APPROVED'" class="mini-action activate" @click="openApproval(loan, 'ACTIVE')">Activate</button>
                <button class="mini-action" @click="openLoan(loan)">Details</button>
              </div>
            </td>
          </tr>
          <tr v-if="!loanRows.length"><td colspan="9" class="empty-cell">No loan applications found</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Loan detail modal -->
    <div v-if="activeLoan" class="modal-overlay" @click.self="activeLoan = null">
      <div class="modal" style="max-width:600px">
        <div class="modal-header">
          <div class="modal-title">{{ activeLoan.loan_no }}</div>
          <button class="btn btn-ghost btn-sm" @click="activeLoan = null">✕</button>
        </div>
        <div class="modal-body">
          <div class="info-grid-2">
            <InfoRow label="Member" :value="`${activeLoan.first_name} ${activeLoan.last_name}`" />
            <InfoRow label="Member #" :value="activeLoan.member_no" />
            <InfoRow label="Loan Type" :value="activeLoan.loan_type_label" />
            <InfoRow label="Amount" :value="peso(activeLoan.amount)" />
            <InfoRow label="Term" :value="`${activeLoan.term_months} months`" />
            <InfoRow label="Frequency" :value="activeLoan.frequency" />
            <InfoRow label="Status" :value="activeLoan.status" />
            <InfoRow label="Total Payable" :value="peso(activeLoan.total_payment)" />
          </div>
          <div class="status-actions">
            <div class="form-group" style="flex:1">
              <label class="form-label">Change Status</label>
              <select v-model="newStatus" class="form-select">
                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <button class="btn btn-primary" @click="openApproval(activeLoan, newStatus)">Review & Update</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="approvalLoan" class="modal-overlay" @click.self="closeApproval">
      <div class="modal approval-modal">
        <div class="modal-header">
          <div>
            <div class="modal-title">Approval Review</div>
            <div class="modal-sub">{{ approvalLoan.loan_no }} · {{ approvalLoan.first_name }} {{ approvalLoan.last_name }}</div>
          </div>
          <button class="btn btn-ghost btn-sm" @click="closeApproval">✕</button>
        </div>
        <div class="modal-body approval-body">
          <section class="approval-summary">
            <InfoRow label="Loan Type" :value="approvalLoan.loan_type_label" />
            <InfoRow label="Amount" :value="peso(approvalLoan.amount)" />
            <InfoRow label="Current Status" :value="approvalLoan.status" />
            <InfoRow label="Next Status" :value="approvalForm.status" />
          </section>
          <div class="form-grid-approval">
            <div class="form-group">
              <label class="form-label">Decision</label>
              <select v-model="approvalForm.status" class="form-select">
                <option value="APPROVED">Approve application</option>
                <option value="REJECTED">Reject application</option>
                <option value="ACTIVE">Activate approved loan</option>
                <option value="PENDING">Return to pending</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Approval Date</label>
              <input v-model="approvalForm.approval_date" type="date" class="form-input" />
            </div>
            <div v-if="approvalForm.status === 'ACTIVE'" class="form-group">
              <label class="form-label">First Deduction Date</label>
              <input v-model="approvalForm.first_due_date" type="date" class="form-input" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Attach Signed Approved Application</label>
            <label class="file-drop">
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="handleApprovalFile" />
              <span>{{ approvalForm.attachment_name || 'Upload signed application PDF/JPG/PNG' }}</span>
            </label>
            <div class="file-note">Stored with the loan record as the signed approval reference for audit and release processing.</div>
          </div>
          <div class="form-group">
            <label class="form-label">Approval Notes</label>
            <textarea v-model="approvalForm.notes" class="form-textarea" placeholder="Optional approval, rejection, or release notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="closeApproval">Cancel</button>
          <button class="btn btn-primary" @click="submitApproval" :disabled="savingApproval">Save Decision</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, reactive } from 'vue'
import { api } from '../composables/useApi'
import { peso } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'
import InfoRow from '../components/shared/InfoRow.vue'

const { success, error } = useToast()
const pipeline  = ref({})
const loading   = ref(false)
const activeLoan = ref(null)
const newStatus  = ref('')
const viewMode = ref('board')
const approvalLoan = ref(null)
const savingApproval = ref(false)
const approvalForm = reactive({ status: 'APPROVED', approval_date: new Date().toISOString().slice(0, 10), first_due_date: '', attachment_name: '', attachment_data: '', notes: '' })

const statuses = ['DRAFT','PENDING','APPROVED','ACTIVE','CLOSED','REJECTED']

const loanRows = computed(() => statuses.flatMap(status => pipeline.value[status] || []))

const columns = {
  DRAFT:    { label: 'Draft',    color: 'var(--status-draft)'    },
  PENDING:  { label: 'Pending',  color: 'var(--status-pending)'  },
  APPROVED: { label: 'Approved', color: 'var(--status-approved)' },
  ACTIVE:   { label: 'Active',   color: 'var(--status-active)'   },
  CLOSED:   { label: 'Closed',   color: 'var(--status-closed)'   },
  REJECTED: { label: 'Rejected', color: 'var(--status-rejected)' },
}

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-PH') : '—'

function openLoan(loan) {
  activeLoan.value = loan
  newStatus.value  = loan.status
}

function nextDeductionDate(from = new Date().toISOString().slice(0, 10)) {
  const date = new Date(`${from}T00:00:00`)
  const day = date.getDate()
  if (day <= 15) date.setDate(15)
  else date.setDate(Math.min(30, new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()))
  return date.toISOString().slice(0, 10)
}

function openApproval(loan, status = loan.status) {
  approvalLoan.value = loan
  approvalForm.status = status
  approvalForm.approval_date = loan.approval_date || new Date().toISOString().slice(0, 10)
  approvalForm.first_due_date = loan.first_due_date || nextDeductionDate(approvalForm.approval_date)
  approvalForm.attachment_name = loan.signed_form_name || loan.signed_form_url || loan.approval_attachment_name || ''
  approvalForm.attachment_data = ''
  approvalForm.notes = loan.notes || ''
}

function closeApproval() {
  approvalLoan.value = null
}

function handleApprovalFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  approvalForm.attachment_name = file.name
  const reader = new FileReader()
  reader.onload = () => { approvalForm.attachment_data = reader.result }
  reader.readAsDataURL(file)
}

async function submitApproval() {
  if (!approvalLoan.value) return
  if (['APPROVED', 'ACTIVE'].includes(approvalForm.status) && !approvalForm.attachment_name) {
    return error('Attach the signed approved application before saving this decision.')
  }
  savingApproval.value = true
  try {
    await api.updateLoan(approvalLoan.value.id, {
      status: approvalForm.status,
      approval_date: approvalForm.approval_date,
      first_due_date: approvalForm.status === 'ACTIVE' ? approvalForm.first_due_date : approvalLoan.value.first_due_date,
      signed_form_url: approvalForm.attachment_name,
      signed_form_name: approvalForm.attachment_name,
      signed_form_data: approvalForm.attachment_data,
      notes: approvalForm.notes,
      approval_attachment_name: approvalForm.attachment_name,
      approval_attachment_data: approvalForm.attachment_data,
    })
    success(`Loan ${approvalForm.status.toLowerCase()}!`)
    activeLoan.value = null
    closeApproval()
    await load()
  } catch (e) { error(e.message) }
  finally { savingApproval.value = false }
}

async function changeStatus(id, status) {
  try {
    await api.updateLoan(id, { status })
    success(`Loan ${status.toLowerCase()}!`)
    await load()
  } catch (e) { error(e.message) }
}

async function updateStatus() {
  if (!newStatus.value) return
  openApproval(activeLoan.value, newStatus.value)
}

async function load() {
  loading.value = true
  try { pipeline.value = await api.getPipeline() }
  catch (e) { error(e.message) }
  finally { loading.value = false }
}

onMounted(load)
</script>

<style scoped>
.view-wrap { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.view-header { padding:18px 24px; border-bottom:1px solid var(--coop-border); display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }

.pipeline-board { display:flex; gap:0; flex:1; overflow-x:auto; overflow-y:hidden; }

.pipeline-col {
  min-width: 200px; flex: 1; display: flex; flex-direction: column;
  border-right: 1px solid var(--coop-border); overflow: hidden;
}
.pipeline-col:last-child { border-right: none; }

.col-header {
  padding: 12px 14px; border-top: 3px solid;
  display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
  background: var(--coop-dark);
}
.col-title { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--coop-cream); }
.col-count {
  font-size: 11px; background: var(--coop-surface);
  border-radius: 10px; padding: 1px 7px; color: var(--coop-muted);
}

.col-cards { flex: 1; overflow-y: auto; padding: 10px 8px; display: flex; flex-direction: column; gap: 8px; }
.col-empty { font-size: 12px; color: var(--coop-muted); text-align: center; padding: 24px; }

.loan-card {
  background: var(--coop-surface); border: 1px solid var(--coop-border);
  border-radius: 7px; padding: 12px; cursor: pointer;
  transition: all var(--tx); display: flex; flex-direction: column; gap: 4px;
}
.loan-card:hover { border-color: var(--coop-red); transform: translateY(-1px); }
.lc-no   { font-size: 10px; color: var(--coop-muted); }
.lc-name { font-size: 13px; font-weight: 600; color: var(--coop-cream); }
.lc-type { font-size: 11px; }
.lc-amount { font-size: 15px; font-weight: 700; color: var(--coop-cream); margin: 2px 0; }
.lc-meta { display: flex; justify-content: space-between; }
.lc-actions { display: flex; gap: 6px; margin-top: 6px; }
.lc-actions .btn { flex: 1; justify-content: center; font-size: 11px; padding: 4px 6px; }

.info-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin-bottom: 16px; }
.status-actions { display: flex; gap: 10px; align-items: flex-end; }
</style>


<style scoped>
.header-actions { display:flex; align-items:center; gap:10px; }
.view-toggle { display:inline-flex; border:1px solid var(--coop-border); border-radius:7px; overflow:hidden; background:#fff; }
.view-toggle button { border:0; background:#fff; padding:8px 12px; color:var(--coop-muted); font-weight:800; cursor:pointer; }
.view-toggle button.active { background:var(--coop-red); color:#fff; }
.pipeline-list { flex:1; overflow:auto; padding:18px 22px; }
.pipeline-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--coop-border); border-radius:8px; overflow:hidden; box-shadow:0 8px 22px rgba(31,41,55,.04); }
.pipeline-table th { background:#F8FAFC; color:var(--coop-muted); text-transform:uppercase; letter-spacing:.5px; font-size:11px; text-align:left; padding:11px 12px; border-bottom:1px solid var(--coop-border); }
.pipeline-table td { padding:12px; border-bottom:1px solid var(--coop-border); vertical-align:middle; color:var(--coop-cream); }
.pipeline-table tr:hover td { background:var(--coop-red-dim); cursor:pointer; }
.muted-line { display:block; color:var(--coop-muted); font-size:11px; margin-top:2px; }
.status-pill { display:inline-flex; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:900; background:#EDF2F7; color:#64748B; }
.status-pill.pending { background:#FFF7D6; color:#9A6B08; }
.status-pill.approved { background:#E7F7EC; color:#2F7D47; }
.status-pill.active { background:#E7F0FB; color:#2F65B0; }
.status-pill.rejected { background:#FCE8E6; color:#B8322A; }
.table-actions { display:flex; gap:6px; flex-wrap:wrap; }
.mini-action { border:1px solid var(--coop-border); background:#fff; color:var(--coop-muted); border-radius:5px; padding:5px 8px; font-weight:800; cursor:pointer; }
.mini-action.approve { background:#2F8F4E; border-color:#2F8F4E; color:#fff; }
.mini-action.activate { background:#2F65B0; border-color:#2F65B0; color:#fff; }
.empty-cell { text-align:center; color:var(--coop-muted) !important; padding:30px !important; }
.approval-modal { max-width:720px; }
.modal-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.approval-body { display:flex; flex-direction:column; gap:16px; }
.approval-summary { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; padding:12px; border:1px solid var(--coop-border); border-radius:8px; background:#F8FAFC; }
.form-grid-approval { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.file-drop { min-height:70px; border:1px dashed #C8CEDA; border-radius:8px; background:#F8FAFC; display:flex; align-items:center; justify-content:center; padding:14px; cursor:pointer; color:var(--coop-muted); font-weight:800; text-align:center; }
.file-drop input { display:none; }
.file-note { color:var(--coop-muted); font-size:12px; margin-top:6px; }
@media (max-width: 900px) { .approval-summary, .form-grid-approval { grid-template-columns:1fr; } .pipeline-table { min-width:980px; } .header-actions { flex-wrap:wrap; justify-content:flex-end; } }
</style>
