<template>
  <div class="view-wrap">
    <!-- Header -->
    <div class="view-header">
      <div>
        <div class="view-title serif">Members</div>
        <div class="view-sub">{{ members.length }} members loaded</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary add-btn" @click="openAdd">+ Add Member</button>
      </div>
    </div>

    <div class="members-layout">
      <!-- LEFT: Member list -->
      <div class="member-list-panel">
        <div class="member-panel-tools">
          <div class="search-wrap">
            <input v-model="search" class="form-input search-input"
              placeholder="Search by name or member #..." @input="fetchMembers" />
          </div>
          <button class="btn btn-secondary import-btn" @click="openImport">Upload File</button>
        </div>
        <div class="filter-row">
          <button v-for="s in statuses" :key="s"
            :class="['btn btn-sm', filterStatus === s ? 'btn-primary' : 'btn-ghost']"
            @click="filterStatus = s; fetchMembers()">{{ s || 'All' }}</button>
        </div>

        <div v-if="loading" class="empty-state"><div class="spinner"></div></div>
        <div v-else-if="!members.length" class="empty-state">
          <div class="empty-icon">◉</div>
          <div class="empty-title">No members found</div>
        </div>
        <div v-else class="member-list">
          <div v-for="m in members" :key="m.id"
            :class="['member-row', selectedMember?.id === m.id && 'selected']"
            @click="selectMember(m)">
            <div class="member-avatar" :style="memberPhotoStyle(m)">
              <img v-if="memberPhoto(m)" :src="memberPhoto(m)" alt="" />
              <span v-else>{{ initials(m) }}</span>
            </div>
            <div class="member-row-info">
              <div class="member-row-name">{{ m.first_name }} {{ m.last_name }}</div>
              <div class="member-row-meta">{{ m.member_no }} · {{ m.position || '—' }}</div>
            </div>
            <div class="member-row-right">
              <span :class="['badge', m.member_status === 'ACTIVE' ? 'badge-approved' : 'badge-closed']">
                {{ m.member_status }}
              </span>
              <div v-if="m.active_loans > 0" class="loan-dot" title="Has active loan">●</div>
            </div>
          </div>
        </div>
      </div>

      <!-- RIGHT: 201 file detail -->
      <div class="member-detail-panel">
        <div v-if="!selectedMember" class="empty-state" style="height:100%">
          <div class="empty-icon">◉</div>
          <div class="empty-title">Select a member</div>
          <div class="text-muted">Click a member to view their profile</div>
        </div>

        <template v-else>
          <div class="detail-header">
            <div class="detail-avatar" :style="memberPhotoStyle(selectedMember)">
              <img v-if="memberPhoto(selectedMember)" :src="memberPhoto(selectedMember)" alt="" />
              <span v-else>{{ initials(selectedMember) }}</span>
            </div>
            <div class="detail-header-info">
              <div class="detail-name">{{ selectedMember.first_name }} {{ selectedMember.last_name }}</div>
              <div class="detail-meta">{{ selectedMember.member_no }} · {{ selectedMember.company }}</div>
              <div class="profile-chips">
                <span>{{ selectedMember.member_status }}</span>
                <span>{{ selectedMember.status }}</span>
                <span>{{ tenureMonths }} mo tenure</span>
              </div>
            </div>
            <div class="detail-header-actions">
              <button class="btn btn-secondary btn-sm" @click="openEdit(selectedMember)">Edit</button>
              <button class="btn btn-primary btn-sm" @click="openNewLoan(selectedMember)">
                ✦ New Loan
              </button>
            </div>
          </div>

          <!-- Tabs -->
          <div class="detail-tabs">
            <button v-for="tab in tabs" :key="tab"
              :class="['tab-btn', activeTab === tab && 'tab-active']"
              @click="activeTab = tab">{{ tab }}</button>
          </div>

          <div class="detail-body">
            <!-- Basic Info -->
            <div v-if="activeTab === 'Basic Info'" class="info-grid">
              <InfoRow label="Member ID"      :value="selectedMember.member_no" />
              <InfoRow label="Full Name"      :value="`${selectedMember.first_name} ${selectedMember.middle_name || ''} ${selectedMember.last_name}`" />
              <InfoRow label="Address"        :value="selectedMember.address" />
              <InfoRow label="Contact"        :value="selectedMember.contact" />
              <InfoRow label="Email"          :value="selectedMember.email" />
              <InfoRow label="Company"        :value="selectedMember.company" />
              <InfoRow label="Branch"         :value="selectedMember.branch" />
              <InfoRow label="Department"     :value="selectedMember.department" />
              <InfoRow label="Position"       :value="selectedMember.position" />
              <InfoRow label="Emp. Status"    :value="selectedMember.status" />
              <InfoRow label="Direct Supervisor" :value="selectedMember.supervisor" />
              <InfoRow label="Date Hired"     :value="formatDate(selectedMember.date_hired)" />
              <InfoRow label="Monthly Salary" :value="peso(selectedMember.monthly_salary)" mono />
              <InfoRow label="Share Capital"  :value="peso(selectedMember.share_capital)" mono />
              <InfoRow label="Member Status"  :value="selectedMember.member_status" />
            </div>

            <!-- Employment tab -->
            <div v-if="activeTab === 'Employment'" class="tab-stack">
              <div class="summary-grid">
                <div class="summary-card">
                  <div class="summary-label">Tenure</div>
                  <div class="summary-value">{{ tenureMonths }} mo</div>
                  <div class="summary-sub">Since {{ formatDate(selectedMember.date_hired) }}</div>
                </div>
                <div class="summary-card">
                  <div class="summary-label">Monthly Salary</div>
                  <div class="summary-value">{{ peso(selectedMember.monthly_salary) }}</div>
                  <div class="summary-sub">{{ selectedMember.status }}</div>
                </div>
                <div class="summary-card">
                  <div class="summary-label">Department</div>
                  <div class="summary-value compact">{{ selectedMember.department || '—' }}</div>
                  <div class="summary-sub">{{ selectedMember.branch || '—' }} branch</div>
                </div>
              </div>
              <div class="info-card">
                <div class="card-title">Employment Details</div>
                <div class="info-grid">
                  <InfoRow label="Company" :value="selectedMember.company" />
                  <InfoRow label="Branch" :value="selectedMember.branch" />
                  <InfoRow label="Department" :value="selectedMember.department" />
                  <InfoRow label="Position" :value="selectedMember.position" />
                  <InfoRow label="Employment Status" :value="selectedMember.status" />
                  <InfoRow label="Direct Supervisor" :value="selectedMember.supervisor" />
                </div>
              </div>
              <div class="info-card">
                <div class="card-title history-title">
                  <span>Position & Salary History</span>
                  <button class="btn btn-primary btn-sm" @click="openEmploymentChange">Record Change</button>
                </div>
                <table class="data-table history-table">
                  <thead><tr><th>Date Changed</th><th>Position</th><th>Salary</th><th>Supervisor</th><th>Reason</th></tr></thead>
                  <tbody>
                    <tr v-for="row in employmentHistory" :key="row.changed_at + row.position">
                      <td>{{ formatDate(row.changed_at) }}</td>
                      <td>{{ row.position || '—' }}</td>
                      <td class="peso">{{ peso(row.monthly_salary || 0) }}</td>
                      <td>{{ row.supervisor || '—' }}</td>
                      <td>{{ row.reason || 'Initial record' }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Loans tab -->
            <div v-if="activeTab === 'Loans'">
              <div class="tab-action-row">
                <button class="btn btn-primary btn-sm" @click="openNewLoan(selectedMember)">
                  + New Loan Application
                </button>
              </div>
              <div v-if="!detailLoans.length" class="empty-state" style="padding:32px">
                <div class="empty-icon">✦</div>
                <div class="empty-title">No loans yet</div>
              </div>
              <table v-else class="data-table">
                <thead><tr>
                  <th>Loan #</th><th>Type</th><th>Amount</th>
                  <th>Term</th><th>Status</th><th>Date</th>
                </tr></thead>
                <tbody>
                  <tr v-for="l in detailLoans" :key="l.id">
                    <td class="mono" style="font-size:12px">{{ l.loan_no }}</td>
                    <td>{{ l.loan_type_label }}</td>
                    <td class="peso">{{ peso(l.amount) }}</td>
                    <td>{{ l.term_months }} mo</td>
                    <td><span :class="`badge badge-${l.status.toLowerCase()}`">{{ l.status }}</span></td>
                    <td class="text-muted" style="font-size:12px">{{ formatDate(l.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Beneficiaries tab -->
            <div v-if="activeTab === 'Beneficiaries'" class="tab-stack">
              <div class="tab-action-row">
                <button class="btn btn-secondary btn-sm">Print Declaration</button>
                <button class="btn btn-primary btn-sm">+ Add Beneficiary</button>
              </div>
              <div class="split-grid">
                <div v-for="group in beneficiaryGroups" :key="group.type" class="info-card beneficiary-group-card">
                  <div class="beneficiary-group-head">
                    <div>
                      <div class="card-title">{{ group.label }}</div>
                      <div class="row-sub">{{ group.items.length }} encoded · {{ group.total }}% allocation</div>
                    </div>
                    <span :class="['allocation-total', group.total === 100 && 'ok']">{{ group.total }}%</span>
                  </div>
                  <div v-for="b in group.items" :key="b.name" class="beneficiary-row member-beneficiary-card">
                    <div class="beneficiary-card-main">
                      <div class="beneficiary-mini-avatar">{{ nameInitials(b.name) }}</div>
                      <div>
                        <div class="row-title">{{ b.name }}</div>
                        <div class="beneficiary-meta-line">
                          <span>{{ b.relationship }}</span>
                          <span>{{ b.contact || 'No contact' }}</span>
                        </div>
                        <div v-if="b.guardian" class="beneficiary-note">Guardian: {{ b.guardian }}</div>
                      </div>
                    </div>
                    <div class="allocation">{{ b.share }}%</div>
                  </div>
                  <div v-if="!group.items.length" class="empty-inline">No {{ group.label.toLowerCase() }} encoded</div>
                </div>
              </div>
            </div>

            <!-- Share Capital tab -->
            <div v-if="activeTab === 'Share Capital'" class="tab-stack">
              <div class="summary-grid">
                <div class="summary-card">
                  <div class="summary-label">Current Balance</div>
                  <div class="summary-value">{{ peso(selectedMember.share_capital) }}</div>
                  <div class="summary-sub">Synced to member profile</div>
                </div>
                <div class="summary-card">
                  <div class="summary-label">YTD Deposits</div>
                  <div class="summary-value">{{ peso(shareCapitalRows.deposit) }}</div>
                  <div class="summary-sub">Preview transactions</div>
                </div>
                <div class="summary-card">
                  <div class="summary-label">Dividend / Adjustments</div>
                  <div class="summary-value">{{ peso(shareCapitalRows.adjustment) }}</div>
                  <div class="summary-sub">Current fiscal year</div>
                </div>
              </div>
              <table class="data-table">
                <thead>
                  <tr><th>Date</th><th>Type</th><th>Reference</th><th>Amount</th><th>Running Balance</th></tr>
                </thead>
                <tbody>
                  <tr v-for="row in shareCapitalRows.rows" :key="row.ref">
                    <td>{{ formatDate(row.date) }}</td>
                    <td>{{ row.type }}</td>
                    <td class="mono">{{ row.ref }}</td>
                    <td class="peso">{{ peso(row.amount) }}</td>
                    <td class="peso fw-600">{{ peso(row.balance) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Audit History tab -->
            <div v-if="activeTab === 'Audit History'" class="tab-stack">
              <div class="timeline">
                <div v-for="item in auditItems" :key="item.time + item.action" class="timeline-item">
                  <div class="timeline-dot"></div>
                  <div class="timeline-content">
                    <div class="row-title">{{ item.action }}</div>
                    <div class="row-sub">{{ item.actor }} · {{ item.time }}</div>
                    <div class="audit-detail">{{ item.detail }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Documents tab -->
            <div v-if="activeTab === 'Documents'" class="tab-stack">
              <div class="doc-grid">
                <router-link :to="{ name: 'loans', query: { member_id: selectedMember.id } }" class="doc-card">
                  <div class="doc-icon">✦</div>
                  <div>
                    <div class="row-title">New Loan Application</div>
                    <div class="row-sub">Open loan officer desk with this member selected</div>
                  </div>
                </router-link>
                <router-link to="/loan-packet" class="doc-card">
                  <div class="doc-icon">▣</div>
                  <div>
                    <div class="row-title">Loan Packet</div>
                    <div class="row-sub">Application, authority to deduct, schedule, disclosure</div>
                  </div>
                </router-link>
                <router-link to="/beneficiaries" class="doc-card">
                  <div class="doc-icon">♡</div>
                  <div>
                    <div class="row-title">Beneficiary Declaration</div>
                    <div class="row-sub">Printable beneficiary declaration PDF</div>
                  </div>
                </router-link>
                <router-link to="/notifications" class="doc-card">
                  <div class="doc-icon">✉</div>
                  <div>
                    <div class="row-title">Notification Log</div>
                    <div class="row-sub">SMS and email events for this member</div>
                  </div>
                </router-link>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Import Members Modal -->
    <div v-if="showImportModal" class="modal-overlay" @click.self="showImportModal = false">
      <div class="modal import-modal">
        <div class="modal-header">
          <div>
            <div class="modal-title">Import Members</div>
            <div class="modal-sub">Migrate member records from a spreadsheet export.</div>
          </div>
          <button class="btn btn-ghost btn-sm" @click="showImportModal = false">✕</button>
        </div>
        <div class="modal-body import-body">
          <div class="import-drop">
            <input type="file" accept=".csv,.tsv,.txt" @change="handleImportFile" />
            <strong>{{ importFileName || 'Upload CSV or TSV file' }}</strong>
            <span>Export your Excel or Google Sheets member list as CSV, then upload it here.</span>
          </div>
          <div class="import-guide-row">
            <div class="import-help">
              Required columns: <code>member_no</code>, <code>first_name</code>, <code>last_name</code>. Optional: <code>email</code>, <code>contact</code>, <code>company</code>, <code>branch</code>, <code>department</code>, <code>position</code>, <code>supervisor</code>, <code>date_hired</code>, <code>monthly_salary</code>, <code>share_capital</code>, <code>status</code>.
            </div>
            <button class="btn btn-secondary guide-btn" type="button" @click="downloadImportGuide">Download Excel Guide</button>
          </div>
          <div v-if="importErrors.length" class="import-errors">
            <div v-for="err in importErrors" :key="err">{{ err }}</div>
          </div>
          <div v-if="importRows.length" class="import-preview">
            <div class="preview-head">
              <strong>{{ importRows.length }} member{{ importRows.length === 1 ? '' : 's' }} ready</strong>
              <span>Previewing first 8 rows</span>
            </div>
            <table class="data-table">
              <thead><tr><th>Member #</th><th>Name</th><th>Company</th><th>Position</th></tr></thead>
              <tbody>
                <tr v-for="row in importRows.slice(0, 8)" :key="row.member_no">
                  <td class="mono">{{ row.member_no }}</td>
                  <td>{{ row.first_name }} {{ row.last_name }}</td>
                  <td>{{ row.company || '—' }}</td>
                  <td>{{ row.position || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showImportModal = false">Cancel</button>
          <button class="btn btn-primary" @click="importMembers" :disabled="saving || !importRows.length">Import Members</button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Member Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:760px">
        <div class="modal-header">
          <div class="modal-title">{{ editingMember?.id ? 'Edit Member' : 'Add New Member' }}</div>
          <button class="btn btn-ghost btn-sm" @click="showModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="profile-editor-head">
            <div class="profile-editor-photo" :style="memberPhotoStyle(form)">
              <img v-if="memberPhoto(form)" :src="memberPhoto(form)" alt="" />
              <span v-else>{{ initials(form) }}</span>
            </div>
            <div>
              <div class="profile-editor-title">Basic Profile</div>
              <div class="profile-editor-sub">{{ editingMember?.id ? 'Edit personal and contact details only.' : 'Create the member record and starter employment details.' }}</div>
              <label class="photo-upload">
                <input type="file" accept="image/*" @change="handlePhotoUpload" />
                Upload member image
              </label>
            </div>
          </div>
          <div class="form-2col">
            <div class="form-group">
              <label class="form-label">Member #</label>
              <input v-model="form.member_no" class="form-input" placeholder="CRS-00XXX" :disabled="!!editingMember?.id" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Employment Status</label>
              <select v-model="form.status" class="form-select">
                <option>REGULAR</option><option>PROBI</option>
                <option>SUSPENDED</option><option>INACTIVE</option>
              </select>
            </div>
            <div v-else class="form-group">
              <label class="form-label">Member Status</label>
              <select v-model="form.member_status" class="form-select">
                <option>ACTIVE</option><option>INACTIVE</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">First Name</label>
              <input v-model="form.first_name" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Last Name</label>
              <input v-model="form.last_name" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input v-model="form.middle_name" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Contact #</label>
              <input v-model="form.contact" class="form-input" />
            </div>
            <div class="form-group" style="grid-column:span 2">
              <label class="form-label">Address</label>
              <input v-model="form.address" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="form.email" class="form-input" type="email" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Company</label>
              <select v-model="form.company" class="form-select" @change="applyCompanyDefaults">
                <option value="">Select company</option>
                <option v-for="company in companyOptions" :key="company.id || company.name + company.branch" :value="company.name">
                  {{ company.name }}{{ company.branch ? ` · ${company.branch}` : '' }}
                </option>
              </select>
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Branch</label>
              <input v-model="form.branch" class="form-input" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Department</label>
              <select v-model="form.department" class="form-select">
                <option value="">Select department</option>
                <option v-for="department in departmentOptions" :key="department" :value="department">{{ department }}</option>
              </select>
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Position</label>
              <input v-model="form.position" class="form-input" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Direct Supervisor</label>
              <select v-model="form.supervisor" class="form-select">
                <option value="">Select supervisor</option>
                <option v-for="member in supervisorOptions" :key="member.id" :value="`${member.first_name} ${member.last_name}`">
                  {{ member.first_name }} {{ member.last_name }} · {{ member.position || 'Member' }}
                </option>
              </select>
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Date Hired</label>
              <input v-model="form.date_hired" class="form-input" type="date" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Monthly Salary (₱)</label>
              <input v-model="form.monthly_salary" class="form-input" type="number" />
            </div>
            <div v-if="!editingMember?.id" class="form-group">
              <label class="form-label">Share Capital (₱)</label>
              <input v-model="form.share_capital" class="form-input" type="number" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showModal = false">Cancel</button>
          <button class="btn btn-primary" @click="saveMember" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save Member' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showEmploymentModal" class="modal-overlay" @click.self="showEmploymentModal = false">
      <div class="modal employment-modal">
        <div class="modal-header">
          <div class="modal-title">Record Employment Change</div>
          <button class="btn btn-ghost btn-sm" @click="showEmploymentModal = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-2col">
            <div class="form-group">
              <label class="form-label">Date Changed</label>
              <input v-model="employmentForm.changed_at" type="date" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Reason</label>
              <select v-model="employmentForm.reason" class="form-select">
                <option>Promotion</option>
                <option>Salary Adjustment</option>
                <option>Transfer</option>
                <option>Correction</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Position</label>
              <input v-model="employmentForm.position" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Monthly Salary (₱)</label>
              <input v-model.number="employmentForm.monthly_salary" type="number" class="form-input" />
            </div>
            <div class="form-group">
              <label class="form-label">Company</label>
              <select v-model="employmentForm.company" class="form-select" @change="applyEmploymentCompanyDefaults">
                <option value="">Select company</option>
                <option v-for="company in companyOptions" :key="company.id || company.name + company.branch" :value="company.name">
                  {{ company.name }}{{ company.branch ? ` · ${company.branch}` : '' }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Department</label>
              <select v-model="employmentForm.department" class="form-select">
                <option value="">Select department</option>
                <option v-for="department in employmentDepartmentOptions" :key="department" :value="department">{{ department }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Direct Supervisor</label>
              <select v-model="employmentForm.supervisor" class="form-select">
                <option value="">Select supervisor</option>
                <option v-for="member in supervisorOptions" :key="member.id" :value="`${member.first_name} ${member.last_name}`">
                  {{ member.first_name }} {{ member.last_name }} · {{ member.position || 'Member' }}
                </option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" @click="showEmploymentModal = false">Cancel</button>
          <button class="btn btn-primary" @click="saveEmploymentChange" :disabled="saving">Save Change</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import { peso } from '../composables/useLoanCalc'
import InfoRow from '../components/shared/InfoRow.vue'

const router = useRouter()
const { success, error } = useToast()

const members = ref([])
const selectedMember = ref(null)
const detailLoans = ref([])
const search = ref('')
const filterStatus = ref('')
const loading = ref(false)
const showModal = ref(false)
const showImportModal = ref(false)
const saving = ref(false)
const editingMember = ref(null)
const activeTab = ref('Basic Info')
const tabs = ['Basic Info', 'Employment', 'Loans', 'Beneficiaries', 'Share Capital', 'Audit History', 'Documents']
const statuses = ['', 'ACTIVE', 'INACTIVE']
const SETTINGS_KEY = 'crs-coop-preview-settings'

const form = ref({})
const importRows = ref([])
const importErrors = ref([])
const importFileName = ref('')
const showEmploymentModal = ref(false)
const employmentForm = reactive({ changed_at: '', reason: 'Promotion', position: '', monthly_salary: 0, company: '', branch: '', department: '', supervisor: '' })

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-PH') : '—'

const companyOptions = computed(() => {
  try {
    const saved = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null')
    if (saved?.companies?.length) return saved.companies
  } catch {}
  return [
    { id: 1, name: 'CRS Holdings Corporation', branch: 'Mandaue', departmentsText: 'Operations, Accounting, HR, IT, Warehouse' },
    { id: 2, name: 'CRS Holdings Corporation', branch: 'Cebu', departmentsText: 'Warehouse, HR, Admin' },
  ]
})
const selectedCompany = computed(() => companyOptions.value.find(company => company.name === form.value.company) || companyOptions.value.find(company => company.name === form.value.company && company.branch === form.value.branch))
const departmentOptions = computed(() => splitDepartments(selectedCompany.value?.departmentsText))
const selectedEmploymentCompany = computed(() => companyOptions.value.find(company => company.name === employmentForm.company) || companyOptions.value[0])
const employmentDepartmentOptions = computed(() => splitDepartments(selectedEmploymentCompany.value?.departmentsText))
const supervisorOptions = computed(() => members.value.filter(member => member.id !== selectedMember.value?.id && member.member_status === 'ACTIVE'))

const tenureMonths = computed(() => {
  if (!selectedMember.value?.date_hired) return 0
  const hired = new Date(selectedMember.value.date_hired)
  const now = new Date()
  return Math.max(0, (now.getFullYear() - hired.getFullYear()) * 12 + now.getMonth() - hired.getMonth())
})

const employmentHistory = computed(() => {
  const rows = parseEmploymentHistory(selectedMember.value)
  if (!rows.length && selectedMember.value) {
    rows.push({
      changed_at: selectedMember.value.date_hired,
      position: selectedMember.value.position,
      monthly_salary: Number(selectedMember.value.monthly_salary || 0),
      company: selectedMember.value.company,
      branch: selectedMember.value.branch,
      department: selectedMember.value.department,
      supervisor: selectedMember.value.supervisor,
      reason: 'Initial record',
    })
  }
  return rows.sort((a, b) => new Date(b.changed_at || 0) - new Date(a.changed_at || 0))
})

const sampleBeneficiaries = computed(() => {
  if (!selectedMember.value) return []
  const last = selectedMember.value.last_name
  return [
    { type: 'primary', name: `${selectedMember.value.first_name} ${last} Jr.`, relationship: 'Child', contact: selectedMember.value.contact, share: 60, guardian: `${selectedMember.value.first_name} ${last}` },
    { type: 'primary', name: `Ana ${last}`, relationship: 'Spouse', contact: '09170000001', share: 40 },
    { type: 'secondary', name: `Roberto ${last}`, relationship: 'Sibling', contact: '09170000002', share: 100 },
  ]
})

const beneficiaryGroups = computed(() => ['primary', 'secondary'].map(type => {
  const items = sampleBeneficiaries.value.filter(b => b.type === type)
  return {
    type,
    label: type === 'primary' ? 'Primary Beneficiaries' : 'Secondary Beneficiaries',
    items,
    total: items.reduce((sum, item) => sum + Number(item.share || 0), 0),
  }
}))

const shareCapitalRows = computed(() => {
  const balance = Number(selectedMember.value?.share_capital || 0)
  const rows = [
    { date: '2026-01-15', type: 'Opening', ref: 'SC-OPEN-2026', amount: Math.max(0, balance - 4500), balance: Math.max(0, balance - 4500) },
    { date: '2026-02-28', type: 'Deposit', ref: 'OR-2026-0218', amount: 2500, balance: Math.max(0, balance - 2000) },
    { date: '2026-03-31', type: 'Deposit', ref: 'OR-2026-0342', amount: 2500, balance: balance + 500 },
    { date: '2026-04-30', type: 'Adjustment', ref: 'SC-ADJ-041', amount: -500, balance },
  ]
  return {
    rows,
    deposit: rows.filter(r => r.type === 'Deposit').reduce((sum, r) => sum + r.amount, 0),
    adjustment: rows.filter(r => r.type === 'Adjustment').reduce((sum, r) => sum + r.amount, 0),
  }
})

const auditItems = computed(() => [
  { action: 'Member profile viewed', actor: 'J. Monteverde', time: 'Today 12:24 PM', detail: `Opened 201 file for ${selectedMember.value?.member_no}.` },
  { action: 'Share capital balance updated', actor: 'System', time: 'Apr 30, 2026', detail: 'Recomputed running share capital balance after adjustment.' },
  { action: 'Beneficiary declaration generated', actor: 'Loan Officer', time: 'Apr 18, 2026', detail: 'Printed declaration PDF for member signature.' },
  { action: 'Loan application created', actor: 'J. Monteverde', time: 'Apr 22, 2026', detail: `${detailLoans.value[0]?.loan_no || 'Loan'} attached to member record.` },
])

const avatarColors = ['#C0392B','#2980B9','#27AE60','#8E44AD','#D35400','#2C3E50']
const avatarColor = (name) => avatarColors[name?.charCodeAt(0) % avatarColors.length] || avatarColors[0]
const initials = (m = {}) => `${m.first_name?.[0] || ''}${m.last_name?.[0] || ''}`.toUpperCase() || 'CR'
const nameInitials = (name = '') => name.split(' ').filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase() || 'BN'
const memberPhoto = (m = {}) => m.profile_image_url || m.photo_url || m.image_url || ''
const memberPhotoStyle = (m = {}) => memberPhoto(m) ? {} : { background: avatarColor(m.last_name) }

function handlePhotoUpload(event) {
  const file = event.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => { form.value.profile_image_url = reader.result }
  reader.readAsDataURL(file)
}

function splitDepartments(value = '') {
  return String(value || '').split(',').map(item => item.trim()).filter(Boolean)
}

function parseEmploymentHistory(member) {
  if (!member?.employment_history) return []
  if (Array.isArray(member.employment_history)) return [...member.employment_history]
  try { return JSON.parse(member.employment_history) || [] } catch { return [] }
}

function stringifyEmploymentHistory(rows) {
  return JSON.stringify(rows || [])
}

function applyCompanyDefaults() {
  const company = companyOptions.value.find(item => item.name === form.value.company)
  if (!company) return
  form.value.branch = company.branch || form.value.branch || ''
  const departments = splitDepartments(company.departmentsText)
  if (!departments.includes(form.value.department)) form.value.department = departments[0] || ''
}

function applyEmploymentCompanyDefaults() {
  const company = companyOptions.value.find(item => item.name === employmentForm.company)
  if (!company) return
  employmentForm.branch = company.branch || employmentForm.branch || ''
  const departments = splitDepartments(company.departmentsText)
  if (!departments.includes(employmentForm.department)) employmentForm.department = departments[0] || ''
}


async function fetchMembers() {
  loading.value = true
  try {
    const params = {}
    if (search.value)  params.search = search.value
    if (filterStatus.value) params.status = filterStatus.value
    members.value = await api.getMembers(params)
  } catch (e) { error(e.message) }
  finally { loading.value = false }
}

async function selectMember(m) {
  selectedMember.value = m
  activeTab.value = 'Basic Info'
  detailLoans.value = []
  try {
    const full = await api.getMember(m.id)
    selectedMember.value = full
    detailLoans.value = full.loans || []
  } catch {}
}

function downloadImportGuide() {
  const headers = [
    'member_no', 'first_name', 'middle_name', 'last_name', 'email', 'contact', 'address',
    'company', 'branch', 'department', 'position', 'supervisor', 'date_hired',
    'monthly_salary', 'share_capital', 'status', 'member_status'
  ]
  const samples = [
    ['CRS-00301', 'Juan', 'D.', 'Santos', 'juan.santos@example.com', '09171230001', 'Mandaue City, Cebu', 'CRS Holdings Corporation', 'Mandaue', 'Operations', 'Warehouse Staff', 'Josefina Monteverde', '2024-02-15', '28000', '5000', 'REGULAR', 'ACTIVE'],
    ['CRS-00302', 'Ana', 'L.', 'Reyes', 'ana.reyes@example.com', '09171230002', 'Cebu City', 'CRS Holdings Corporation', 'Cebu', 'Accounting', 'Accounting Assistant', 'Maria Santos', '2025-06-01', '32000', '7500', 'PROBI', 'ACTIVE'],
  ]
  const rows = [headers, ...samples]
  const xmlRows = rows.map(row => `
    <Row>${row.map(cell => `<Cell><Data ss:Type="String">${escapeXml(cell)}</Data></Cell>`).join('')}</Row>`).join('')
  const xml = `<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
  xmlns:o="urn:schemas-microsoft-com:office:office"
  xmlns:x="urn:schemas-microsoft-com:office:excel"
  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
  <Worksheet ss:Name="Members Import Guide">
    <Table>${xmlRows}
    </Table>
  </Worksheet>
</Workbook>`
  const blob = new Blob([xml], { type: 'application/vnd.ms-excel' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'crs-members-import-guide.xls'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

function escapeXml(value) {
  return String(value ?? '').replace(/[<>&'"]/g, char => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;' }[char]))
}

function openImport() {
  importRows.value = []
  importErrors.value = []
  importFileName.value = ''
  showImportModal.value = true
}

function handleImportFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  importFileName.value = file.name
  importErrors.value = []
  const reader = new FileReader()
  reader.onload = () => parseImportText(String(reader.result || ''))
  reader.readAsText(file)
}

function parseImportText(text) {
  const delimiter = text.includes('\t') ? '\t' : ','
  const lines = text.split(/\r?\n/).filter(line => line.trim())
  if (lines.length < 2) {
    importRows.value = []
    importErrors.value = ['File must include a header row and at least one member row.']
    return
  }
  const headers = splitDelimitedLine(lines[0], delimiter).map(normalizeHeader)
  const rows = []
  const errors = []
  lines.slice(1).forEach((line, idx) => {
    const values = splitDelimitedLine(line, delimiter)
    const raw = Object.fromEntries(headers.map((key, i) => [key, values[i] ?? '']))
    const row = normalizeImportRow(raw)
    if (!row.member_no || !row.first_name || !row.last_name) {
      errors.push(`Row ${idx + 2}: member_no, first_name, and last_name are required.`)
      return
    }
    rows.push(row)
  })
  importRows.value = rows
  importErrors.value = errors
}

function splitDelimitedLine(line, delimiter) {
  const out = []
  let current = ''
  let quoted = false
  for (let i = 0; i < line.length; i++) {
    const char = line[i]
    if (char === '"' && line[i + 1] === '"') { current += '"'; i++; continue }
    if (char === '"') { quoted = !quoted; continue }
    if (char === delimiter && !quoted) { out.push(current.trim()); current = ''; continue }
    current += char
  }
  out.push(current.trim())
  return out
}

function normalizeHeader(value) {
  const key = String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
  const aliases = {
    member_id: 'member_no', member_number: 'member_no', employee_no: 'member_no', employee_number: 'member_no',
    firstname: 'first_name', given_name: 'first_name', lastname: 'last_name', surname: 'last_name', middlename: 'middle_name',
    phone: 'contact', mobile: 'contact', emp_status: 'status', employment_status: 'status', salary: 'monthly_salary',
    share_capital_balance: 'share_capital', supervisor_name: 'supervisor', direct_supervisor: 'supervisor',
  }
  return aliases[key] || key
}

function normalizeImportRow(raw) {
  const company = raw.company || companyOptions.value[0]?.name || ''
  const companyConfig = companyOptions.value.find(item => item.name === company)
  return {
    member_no: raw.member_no || '',
    first_name: raw.first_name || '',
    middle_name: raw.middle_name || '',
    last_name: raw.last_name || '',
    address: raw.address || '',
    contact: raw.contact || '',
    email: raw.email || '',
    company,
    branch: raw.branch || companyConfig?.branch || '',
    department: raw.department || splitDepartments(companyConfig?.departmentsText)[0] || '',
    status: raw.status || 'REGULAR',
    position: raw.position || '',
    supervisor: raw.supervisor || '',
    date_hired: raw.date_hired || '',
    monthly_salary: Number(String(raw.monthly_salary || 0).replace(/,/g, '')),
    share_capital: Number(String(raw.share_capital || 0).replace(/,/g, '')),
    member_status: raw.member_status || 'ACTIVE',
  }
}

async function importMembers() {
  if (!importRows.value.length) return
  saving.value = true
  let imported = 0
  try {
    for (const row of importRows.value) {
      await api.createMember(row)
      imported++
    }
    success(`${imported} member${imported === 1 ? '' : 's'} imported.`)
    showImportModal.value = false
    await fetchMembers()
  } catch (e) { error(e.message || 'Import failed.') }
  finally { saving.value = false }
}

function openAdd() {
  editingMember.value = null
  form.value = { status: 'PROBI', member_status: 'ACTIVE', monthly_salary: 0, share_capital: 0, company: companyOptions.value[0]?.name || '', branch: companyOptions.value[0]?.branch || '', department: splitDepartments(companyOptions.value[0]?.departmentsText)[0] || '', supervisor: '' }
  showModal.value = true
}

function openEdit(m) {
  editingMember.value = m
  form.value = { ...m }
  showModal.value = true
}

function openEmploymentChange() {
  if (!selectedMember.value) return
  Object.assign(employmentForm, {
    changed_at: new Date().toISOString().slice(0, 10),
    reason: 'Promotion',
    position: selectedMember.value.position || '',
    monthly_salary: Number(selectedMember.value.monthly_salary || 0),
    company: selectedMember.value.company || companyOptions.value[0]?.name || '',
    branch: selectedMember.value.branch || companyOptions.value[0]?.branch || '',
    department: selectedMember.value.department || '',
    supervisor: selectedMember.value.supervisor || '',
  })
  showEmploymentModal.value = true
}

async function saveEmploymentChange() {
  if (!selectedMember.value) return
  const rows = parseEmploymentHistory(selectedMember.value)
  rows.unshift({ ...employmentForm })
  const payload = {
    ...selectedMember.value,
    position: employmentForm.position,
    monthly_salary: Number(employmentForm.monthly_salary || 0),
    company: employmentForm.company,
    branch: employmentForm.branch,
    department: employmentForm.department,
    supervisor: employmentForm.supervisor,
    employment_history: stringifyEmploymentHistory(rows),
  }
  saving.value = true
  try {
    await api.updateMember(selectedMember.value.id, payload)
    success('Employment history updated!')
    showEmploymentModal.value = false
    await fetchMembers()
    const refreshed = members.value.find(m => m.id === selectedMember.value.id)
    if (refreshed) await selectMember(refreshed)
  } catch (e) { error(e.message) }
  finally { saving.value = false }
}

function openNewLoan(m) {
  router.push({ name: 'loans', query: { member_id: m.id } })
}

async function saveMember() {
  saving.value = true
  try {
    if (editingMember.value?.id) {
      await api.updateMember(editingMember.value.id, form.value)
      success('Member updated!')
    } else {
      await api.createMember(form.value)
      success('Member added!')
    }
    showModal.value = false
    await fetchMembers()
    if (editingMember.value?.id) {
      const refreshed = members.value.find(m => m.id === editingMember.value.id)
      if (refreshed) await selectMember(refreshed)
    }
  } catch (e) { error(e.message) }
  finally { saving.value = false }
}

onMounted(fetchMembers)
</script>

<style scoped>
.view-wrap { display:flex; flex-direction:column; height:100%; overflow:hidden; }
.view-header {
  padding:22px 28px; border-bottom:1px solid var(--coop-border);
  display:flex; justify-content:space-between; align-items:center; flex-shrink:0;
  background:linear-gradient(135deg, #fff 0%, #fff7f4 52%, #f8fafc 100%);
  box-shadow:0 8px 24px rgba(31,41,55,.04);
}
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:12px; align-items:center; margin-left:auto; }
.search-input { width:100%; min-height:44px; border-color:rgba(192,57,43,.32); border-radius:10px; font-size:14px; }
.import-btn, .add-btn { min-height:48px; border-radius:10px; font-weight:900; }
.import-btn { min-height:44px; color:#8F241E; border-color:#F0D2C8; background:#FFF8F5; }

.members-layout { display:grid; grid-template-columns:360px 1fr; flex:1; overflow:hidden; }

/* List panel */
.member-list-panel {
  border-right:1px solid var(--coop-border); display:flex;
  flex-direction:column; overflow:hidden; background:#F8FAFC;
}
.member-panel-tools {
  padding:12px;
  border-bottom:1px solid var(--coop-border);
  background:#fff;
  display:grid;
  grid-template-columns:minmax(0, 1fr) auto;
  gap:8px;
  align-items:center;
}
.member-panel-tools .import-btn { padding-inline:14px; white-space:nowrap; }
.filter-row { padding:10px 12px; border-bottom:1px solid var(--coop-border); display:flex; gap:4px; flex-wrap:wrap; }
.member-list { flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px; }
.member-row {
  display:flex; align-items:center; gap:12px;
  padding:13px 14px; cursor:pointer; border:1px solid var(--coop-border);
  border-radius:10px; background:#fff; transition:all var(--tx);
  box-shadow:0 6px 16px rgba(31,41,55,.035);
}
.member-row:hover { border-color:rgba(192,57,43,.35); transform:translateY(-1px); }
.member-row.selected { background:var(--coop-red-dim); border-color:rgba(192,57,43,.45); box-shadow:inset 3px 0 0 var(--coop-red), 0 8px 20px rgba(192,57,43,.08); }
.member-avatar {
  width:44px; height:44px; border-radius:50%; display:flex;
  align-items:center; justify-content:center; font-size:13px;
  font-weight:900; color:#fff; flex-shrink:0; overflow:hidden;
}
.member-avatar img, .detail-avatar img, .profile-editor-photo img { width:100%; height:100%; object-fit:cover; display:block; }
.member-row-info { flex:1; min-width:0; }
.member-row-name { font-size:13.5px; font-weight:600; color:var(--coop-cream); truncate:overflow; }
.member-row-meta { font-size:11px; color:var(--coop-muted); }
.member-row-right { display:flex; flex-direction:column; align-items:flex-end; gap:4px; }
.loan-dot { color:var(--coop-red-soft); font-size:10px; }

/* Detail panel */
.member-detail-panel { display:flex; flex-direction:column; overflow:hidden; background:var(--coop-mid); }
.detail-header {
  padding:26px 28px; border-bottom:1px solid var(--coop-border);
  display:flex; align-items:center; gap:18px; flex-shrink:0;
  background:linear-gradient(135deg, #ffffff 0%, #fff3ef 100%);
}
.detail-avatar {
  width:78px; height:78px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-size:24px; font-weight:900; color:#fff; overflow:hidden;
  box-shadow:0 10px 28px rgba(31,41,55,.18); border:4px solid #fff;
}
.detail-name { font-size:28px; color:var(--coop-cream); }
.detail-meta { font-size:12px; color:var(--coop-muted); }
.detail-header-info { flex:1; }
.detail-header-actions { display:flex; gap:8px; }

.detail-tabs {
  display:flex; border-bottom:1px solid var(--coop-border); flex-shrink:0;
  padding:0 24px;
  overflow-x:auto;
}
.tab-btn {
  padding:10px 14px; background:transparent; border:none;
  color:var(--coop-muted); cursor:pointer; font-size:13px;
  border-bottom:2px solid transparent; transition:all var(--tx);
  white-space:nowrap;
}
.tab-btn:hover { color:var(--coop-cream); }
.tab-active { color:var(--coop-cream) !important; border-bottom-color:var(--coop-red) !important; }

.detail-body { flex:1; overflow-y:auto; padding:24px 28px; background:#F6F7FB; }

.info-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px 32px; background:#fff; border:1px solid var(--coop-border); border-radius:12px; padding:22px; box-shadow:0 8px 22px rgba(31,41,55,.04); }

.tab-stack { display:flex; flex-direction:column; gap:14px; }

.summary-grid {
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:12px;
}
.summary-card, .info-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:16px;
  box-shadow:0 8px 24px rgba(31,41,55,0.04);
}
.summary-label {
  color:var(--coop-muted);
  font-size:11px;
  font-weight:700;
  letter-spacing:.5px;
  text-transform:uppercase;
}
.summary-value {
  color:var(--coop-cream);
  font-size:22px;
  font-weight:900;
  margin-top:4px;
}
.summary-value.compact { font-size:18px; }
.summary-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.card-title {
  color:var(--coop-cream);
  font-size:15px;
  font-weight:900;
  margin-bottom:12px;
}
.split-grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}
.beneficiary-group-card { padding:0; overflow:hidden; }
.beneficiary-group-head {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  padding:16px 18px;
  border-bottom:1px solid var(--coop-border);
  background:linear-gradient(180deg,#fff,#F8FAFC);
}
.beneficiary-group-head .card-title { margin-bottom:4px; }
.beneficiary-row {
  display:flex;
  justify-content:space-between;
  gap:14px;
  padding:14px 16px;
  border-bottom:1px solid var(--coop-border);
}
.beneficiary-row:last-child { border-bottom:none; }
.member-beneficiary-card { align-items:center; background:#fff; }
.beneficiary-card-main { display:flex; align-items:center; gap:12px; min-width:0; }
.beneficiary-mini-avatar {
  width:42px;
  height:42px;
  border-radius:50%;
  display:grid;
  place-items:center;
  background:var(--coop-red-dim);
  color:var(--coop-red);
  border:1px solid rgba(178,63,48,.18);
  font-weight:900;
  flex:0 0 auto;
}
.row-title { color:var(--coop-cream); font-weight:800; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.beneficiary-meta-line { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.beneficiary-meta-line span, .beneficiary-note {
  display:inline-flex;
  align-items:center;
  border:1px solid var(--coop-border);
  border-radius:999px;
  padding:4px 8px;
  color:var(--coop-muted);
  background:#F8FAFC;
  font-size:12px;
  font-weight:700;
}
.beneficiary-note { margin-top:8px; color:#7A4B13; background:#FFF7E6; border-color:#F5D8A8; }
.allocation, .allocation-total {
  align-self:flex-start;
  padding:5px 10px;
  border-radius:999px;
  background:var(--coop-red-dim);
  color:var(--coop-red);
  font-weight:900;
  font-size:12px;
}
.allocation-total.ok { background:rgba(39,174,96,.1); color:var(--status-approved); }
.empty-inline {
  color:var(--coop-muted);
  padding:18px 0;
  text-align:center;
}
.timeline {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:16px;
}
.timeline-item {
  position:relative;
  display:flex;
  gap:12px;
  padding:0 0 18px;
}
.timeline-item:last-child { padding-bottom:0; }
.timeline-item::before {
  content:'';
  position:absolute;
  left:7px;
  top:18px;
  bottom:0;
  width:2px;
  background:var(--coop-border);
}
.timeline-item:last-child::before { display:none; }
.timeline-dot {
  width:16px;
  height:16px;
  border-radius:50%;
  background:var(--coop-red);
  border:3px solid #fff;
  box-shadow:0 0 0 1px var(--coop-red);
  flex-shrink:0;
  margin-top:2px;
  z-index:1;
}
.audit-detail {
  margin-top:6px;
  color:var(--coop-muted);
  background:#F8FAFC;
  border:1px solid var(--coop-border);
  border-radius:6px;
  padding:8px 10px;
  font-size:12px;
}
.doc-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:12px;
}
.doc-card {
  display:flex;
  align-items:center;
  gap:12px;
  padding:16px;
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  text-decoration:none;
  transition:all var(--tx);
}
.doc-card:hover {
  border-color:var(--coop-red);
  background:var(--coop-red-dim);
}
.doc-icon {
  width:36px;
  height:36px;
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:var(--coop-red);
  color:#fff;
  flex-shrink:0;
}

.form-2col { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

.tab-action-row { display:flex; justify-content:flex-end; margin-bottom:14px; }


.profile-chips { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.profile-chips span { background:#fff; border:1px solid #F0D2C8; color:#8F241E; border-radius:999px; padding:4px 9px; font-size:11px; font-weight:900; }
.profile-editor-head { display:flex; align-items:center; gap:16px; padding:0 0 18px; margin-bottom:18px; border-bottom:1px solid var(--coop-border); }
.profile-editor-photo { width:76px; height:76px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:24px; font-weight:900; overflow:hidden; border:4px solid #fff; box-shadow:0 8px 24px rgba(31,41,55,.16); background:#C0392B; }
.profile-editor-title { color:var(--coop-cream); font-size:18px; font-weight:900; }
.profile-editor-sub { color:var(--coop-muted); font-size:12px; margin:2px 0 10px; }
.photo-upload { display:inline-flex; align-items:center; justify-content:center; border:1px solid #F0D2C8; background:#FFF5F2; color:#8F241E; border-radius:999px; padding:7px 11px; font-weight:900; font-size:12px; cursor:pointer; }
.photo-upload input { display:none; }
.header-actions .btn-primary { min-height:42px; border-radius:10px; font-weight:900; }



.import-modal { max-width:820px; }
.modal-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.import-body { display:flex; flex-direction:column; gap:14px; }
.import-drop { position:relative; min-height:120px; border:1px dashed #D2D8E3; border-radius:12px; background:#F8FAFC; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; color:var(--coop-muted); text-align:center; padding:20px; }
.import-drop input { position:absolute; inset:0; opacity:0; cursor:pointer; }
.import-drop strong { color:var(--coop-cream); font-size:16px; }
.import-guide-row { display:grid; grid-template-columns:1fr auto; gap:12px; align-items:stretch; }
.import-help { background:#FFF8E8; color:#986A17; border:1px solid #F4E3B8; border-radius:8px; padding:12px 14px; line-height:1.55; }
.guide-btn { align-self:stretch; border-color:#F0D2C8; color:#8F241E; background:#fff; font-weight:900; }
.import-help code { background:rgba(255,255,255,.7); padding:1px 4px; border-radius:4px; }
.import-errors { border:1px solid #F3C4BE; background:#FFF1F0; color:#A7332B; border-radius:8px; padding:10px 12px; display:flex; flex-direction:column; gap:4px; }
.import-preview { border:1px solid var(--coop-border); border-radius:10px; overflow:hidden; background:#fff; }
.preview-head { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; border-bottom:1px solid var(--coop-border); color:var(--coop-muted); }
.preview-head strong { color:var(--coop-cream); }

.history-title { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.history-table th, .history-table td { font-size:12px; }
.employment-modal { max-width:720px; }

@media (max-width: 980px) {
  .import-guide-row { grid-template-columns:1fr; }
  .members-layout { grid-template-columns:1fr; }
  .member-list-panel { min-height:260px; border-right:none; border-bottom:1px solid var(--coop-border); }
  .summary-grid, .split-grid, .doc-grid { grid-template-columns:1fr; }
}
</style>
