<template>
  <div class="settings-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Settings</div>
        <div class="view-sub">Coop profile, loan rules, approvals, payments, users, permissions, companies, and system preferences</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="resetDefaults">Reset Preview</button>
        <button class="btn btn-primary" @click="saveSettings">Save Settings</button>
      </div>
    </header>

    <main class="settings-body">
      <aside class="settings-nav">
        <button v-for="tab in tabs" :key="tab.key" :class="['settings-tab', activeTab === tab.key && 'active']" @click="activeTab = tab.key">
          <span>{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </aside>

      <section class="settings-panel">
        <section v-if="activeTab === 'profile'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Cooperative Profile</h2>
              <p>Used in PDF packet headers, reports, and official declarations.</p>
            </div>
            <span class="badge badge-approved">Letterhead</span>
          </div>
          <div class="form-grid">
            <Field label="Cooperative Name" v-model="settings.profile.name" />
            <Field label="Short Name" v-model="settings.profile.short_name" />
            <Field label="CDA Registration No." v-model="settings.profile.cda_registration" />
            <Field label="TIN" v-model="settings.profile.tin" />
            <Field label="Address" v-model="settings.profile.address" class="wide" />
            <Field label="Contact No." v-model="settings.profile.contact" />
            <Field label="Email" v-model="settings.profile.email" />
          </div>
        </section>

        <section v-if="activeTab === 'loanTypes'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Loan Type Rules</h2>
              <p>Rates, amount caps, terms, and qualification rules used by loan applications and eligibility.</p>
            </div>
            <button class="btn btn-primary btn-sm" @click="addLoanType">Add Type</button>
          </div>
          <div class="loan-type-list">
            <article v-for="type in settings.loanTypes" :key="type.id" class="loan-type-card">
              <div class="loan-type-head">
                <div>
                  <strong>{{ type.label || 'Untitled Loan' }}</strong>
                  <span class="mono">{{ type.code }}</span>
                </div>
                <button
                  class="btn btn-secondary btn-sm delete-type-btn"
                  :disabled="settings.loanTypes.length <= 1"
                  @click="deleteLoanType(type.id)"
                >
                  Delete Type
                </button>
              </div>
              <div class="type-grid">
                <Field label="Code" v-model="type.code" />
                <Field label="Label" v-model="type.label" />
                <NumberField label="Annual Rate" v-model="type.annual_rate" step="0.01" />
                <NumberField label="Min Amount" v-model="type.min_amount" step="1000" />
                <NumberField label="Max Amount" v-model="type.max_amount" step="1000" />
                <NumberField label="Min Term" v-model="type.min_term" />
                <NumberField label="Max Term" v-model="type.max_term" />
                <label class="toggle-field">
                  <span>Allow 1 Month Term</span>
                  <input
                    type="checkbox"
                    :checked="type.allow_one_month_term"
                    @change="toggleOneMonthTerm(type, $event.target.checked)"
                  />
                </label>
              </div>
              <div class="eligibility-rule-block">
                <div class="rule-section-title">Eligibility Rules</div>
                <div class="type-grid">
                  <NumberField label="Min Tenure Months" v-model="type.min_tenure_months" />
                  <NumberField label="Share Capital Req." v-model="type.share_capital_requirement" step="1000" />
                  <NumberField label="Max Active Loans" v-model="type.max_active_loans" />
                  <ToggleField label="Require Active Member" v-model="type.require_active_member" />
                  <ToggleField label="Block If Existing Overdue" v-model="type.block_if_overdue" />
                  <ToggleField label="Require Co-maker" v-model="type.require_comaker" />
                </div>
                <div class="status-rule-row">
                  <span>Allowed Employment Status</span>
                  <label v-for="status in employmentStatuses" :key="status">
                    <input
                      type="checkbox"
                      :checked="type.allowed_employment_statuses.includes(status)"
                      @change="toggleEmploymentStatus(type, status, $event.target.checked)"
                    />
                    {{ status }}
                  </label>
                </div>
              </div>
            </article>
          </div>
        </section>

        <section v-if="activeTab === 'fees'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Fees and Upfront Deductions</h2>
              <p>Controls the deductions shown in loan applications, net release calculations, and printable packet previews.</p>
            </div>
            <button class="btn btn-primary btn-sm" @click="addLoanFee">Add Fee</button>
          </div>

          <div class="fee-settings-list">
            <article v-for="fee in settings.loanFees" :key="fee.key" class="fee-settings-card">
              <div class="fee-settings-head">
                <ToggleField label="Enabled by default" v-model="fee.enabled" />
                <button class="btn btn-secondary btn-sm" @click="removeLoanFee(fee.key)" :disabled="settings.loanFees.length <= 1">Remove</button>
              </div>
              <div class="type-grid">
                <Field label="Fee Key" v-model="fee.key" />
                <Field label="Label" v-model="fee.label" />
                <div class="form-group">
                  <label class="form-label">Calculation Type</label>
                  <select v-model="fee.type" class="form-select">
                    <option value="percent">Percent of principal</option>
                    <option value="fixed">Fixed amount</option>
                    <option value="mri">Percent per year</option>
                  </select>
                </div>
                <NumberField label="Value" v-model="fee.value" step="0.001" />
                <Field label="Description" v-model="fee.note" class="wide" />
              </div>
            </article>
          </div>
        </section>

        <section v-if="activeTab === 'approvals'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Approval Workflow</h2>
              <p>Thresholds define when manager, board, and super-admin review are required.</p>
            </div>
          </div>
          <div class="approval-grid">
            <div v-for="rule in settings.approvals" :key="rule.role" class="rule-card">
              <div class="rule-head">
                <strong>{{ rule.role }}</strong>
                <label class="switch">
                  <input v-model="rule.enabled" type="checkbox" />
                  <span></span>
                </label>
              </div>
              <NumberField label="Amount From" v-model="rule.amount_from" step="1000" />
              <NumberField label="Amount To" v-model="rule.amount_to" step="1000" />
              <Field label="Required Status" v-model="rule.required_status" />
            </div>
          </div>
        </section>

        <section v-if="activeTab === 'payments'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Payment Policy</h2>
              <p>Controls grace periods, penalties, O.R. capture, and loan-close automation.</p>
            </div>
          </div>
          <div class="form-grid">
            <NumberField label="Grace Period Days" v-model="settings.paymentPolicy.grace_days" />
            <NumberField label="Penalty Rate" v-model="settings.paymentPolicy.penalty_rate" step="0.01" />
            <NumberField label="Nightly Due Reminder Hour" v-model="settings.paymentPolicy.reminder_hour" />
            <ToggleField label="Require O.R. Number" v-model="settings.paymentPolicy.require_or_number" />
            <ToggleField label="Auto Close Fully Paid Loans" v-model="settings.paymentPolicy.auto_close_paid_loans" />
            <ToggleField label="Allow Partial Payments" v-model="settings.paymentPolicy.allow_partial_payments" />
          </div>
        </section>

        <section v-if="activeTab === 'notifications'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Notification Settings</h2>
              <p>Toggle SMS and email events for loan workflow and payment reminders.</p>
            </div>
          </div>
          <div class="notification-list">
            <div v-for="event in settings.notifications.events" :key="event.key" class="notification-row">
              <div>
                <strong>{{ event.label }}</strong>
                <span>{{ event.description }}</span>
              </div>
              <label><input v-model="event.sms" type="checkbox" /> SMS</label>
              <label><input v-model="event.email" type="checkbox" /> Email</label>
            </div>
          </div>
          <div class="form-grid notification-config">
            <Field label="Semaphore Sender Name" v-model="settings.notifications.sender_name" />
            <Field label="Reply-To Email" v-model="settings.notifications.reply_to" />
          </div>
        </section>

        <section v-if="activeTab === 'companies'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Companies and Departments</h2>
              <p>Used in member records, report filters, and eligibility context.</p>
            </div>
            <button class="btn btn-primary btn-sm" @click="addCompany">Add Company</button>
          </div>
          <div class="company-list">
            <article v-for="company in settings.companies" :key="company.id" class="company-card">
              <Field label="Company Name" v-model="company.name" />
              <Field label="Branch" v-model="company.branch" />
              <Field label="Departments" v-model="company.departmentsText" />
            </article>
          </div>
        </section>

        <section v-if="activeTab === 'roles'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>Roles and Permissions</h2>
              <p>Control module access by role. User accounts are created and maintained in this Settings area.</p>
            </div>
          </div>

          <div class="permission-layout">
            <section class="permission-card">
              <div class="permission-title">Roles</div>
              <div class="role-list">
                <article v-for="role in settings.roles" :key="role.id" class="role-card">
                  <div class="role-head">
                    <Field label="Role Name" v-model="role.name" />
                    <ToggleField label="Active" v-model="role.active" />
                  </div>
                  <div class="module-access-grid">
                    <label v-for="module in permissionModules" :key="module.key" class="module-check">
                      <input
                        type="checkbox"
                        :checked="role.modules.includes(module.key)"
                        @change="toggleRoleModule(role, module.key, $event.target.checked)"
                      />
                      <span>{{ module.label }}</span>
                    </label>
                  </div>
                </article>
              </div>
            </section>
          </div>
        </section>

        <section v-if="activeTab === 'users'" class="settings-card user-management-card">
          <div class="card-head">
            <div>
              <h2>Users</h2>
              <p>Create system users, assign roles, reset passwords, and disable access when needed.</p>
            </div>
          </div>
          <UserManagementView embedded />
        </section>

        <section v-if="activeTab === 'memberAccess'" class="settings-card">
          <!-- Header row -->
          <div class="pa-header">
            <div>
              <h2>Member Portal Access</h2>
              <p class="pa-sub">All active members auto-provisioned. Admin can activate, suspend, or reset passwords.</p>
            </div>
            <div class="pa-actions">
              <button class="btn btn-secondary" @click="loadPortalMembers">Refresh</button>
              <button class="btn btn-primary" @click="provisionAll" :disabled="provisioningAll">
                {{ provisioningAll ? 'Provisioning...' : 'Provision Unprovisioned' }}
              </button>
            </div>
          </div>

          <!-- Stats bar -->
          <div class="pa-stats">
            <span><strong>{{ portalStats.total }}</strong> total members</span>
            <span><strong class="text-green">{{ portalStats.active }}</strong> active</span>
            <span><strong class="text-muted">{{ portalStats.suspended }}</strong> suspended</span>
            <span><strong class="text-red">{{ portalStats.unprovisioned }}</strong> not provisioned</span>
          </div>

          <!-- Filters row -->
          <div class="pa-search-row">
            <input v-model="portalSearch" @input="debouncedPortalSearch" class="form-input pa-search" placeholder="Search name or member no…" />
            <select v-model="portalCompany" @change="clearSelection" class="form-select pa-company">
              <option value="">All Companies</option>
              <option v-for="c in portalCompanies" :key="c" :value="c">{{ c }}</option>
            </select>
            <select v-model="portalFilter" @change="clearSelection" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="suspended">Suspended</option>
              <option value="unprovisioned">Not Provisioned</option>
            </select>
          </div>

          <!-- Bulk action bar (visible when rows selected) -->
          <div v-if="selectedIds.size > 0" class="pa-bulk-bar">
            <span class="pa-bulk-count">{{ selectedIds.size }} selected</span>
            <button class="btn btn-sm btn-success" @click="bulkAction('activate')">✓ Activate Selected</button>
            <button class="btn btn-sm btn-warning" @click="bulkAction('suspend')">⊘ Suspend Selected</button>
            <button class="btn btn-sm btn-secondary" @click="clearSelection">Clear</button>
          </div>

          <!-- Table -->
          <div class="pa-table-wrap">
            <table class="pa-table">
              <thead>
                <tr>
                  <th class="pa-th-check">
                    <input type="checkbox" :checked="allVisibleSelected" :indeterminate.prop="someSelected && !allVisibleSelected" @change="toggleSelectAll" />
                  </th>
                  <th>Member</th>
                  <th>Company</th>
                  <th>Username</th>
                  <th>Password</th>
                  <th>Last Login</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="portalLoading">
                  <td colspan="8" class="pa-loading">Loading…</td>
                </tr>
                <tr v-else-if="filteredPortalMembers.length === 0">
                  <td colspan="8" class="pa-empty">No members found</td>
                </tr>
                <tr v-for="row in filteredPortalMembers" :key="row.member_id"
                    :class="{ 'pa-row-suspended': row.has_account && !row.active, 'pa-row-selected': selectedIds.has(row.account_id) }">
                  <td class="pa-th-check">
                    <input v-if="row.has_account" type="checkbox"
                      :checked="selectedIds.has(row.account_id)"
                      @change="toggleSelect(row.account_id)" />
                  </td>
                  <td class="pa-name">
                    {{ row.member_name }}
                    <div class="pa-mono pa-memberno">{{ row.member_no }}</div>
                  </td>
                  <td class="pa-company-cell">{{ row.company || '—' }}</td>
                  <td class="pa-mono">{{ row.username || '—' }}</td>
                  <td class="pa-pass">
                    <span v-if="!row.has_account" class="pa-badge pa-badge-none">Not provisioned</span>
                    <span v-else-if="row.password_visible" class="pa-password">{{ row.password_visible }}</span>
                    <span v-else class="pa-changed">✓ Changed</span>
                  </td>
                  <td class="pa-date">{{ row.last_login_at ? formatPortalDate(row.last_login_at) : 'Never' }}</td>
                  <td>
                    <span v-if="!row.has_account" class="pa-badge pa-badge-none">—</span>
                    <span v-else-if="row.active" class="pa-badge pa-badge-active">Active</span>
                    <span v-else class="pa-badge pa-badge-suspended">Suspended</span>
                  </td>
                  <td class="pa-btns">
                    <button v-if="!row.has_account" class="btn btn-sm btn-primary" @click="provisionOne(row.member_id)">Provision</button>
                    <template v-else>
                      <button class="btn btn-sm btn-secondary" @click="resetPassword(row.account_id)">Reset</button>
                      <button class="btn btn-sm" :class="row.active ? 'btn-warning' : 'btn-success'" @click="toggleAccount(row.account_id)">
                        {{ row.active ? 'Suspend' : 'Activate' }}
                      </button>
                    </template>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <section v-if="activeTab === 'system'" class="settings-card">
          <div class="card-head">
            <div>
              <h2>System Preferences</h2>
              <p>Operational controls for audit logs, access, exports, and preview safeguards.</p>
            </div>
          </div>
          <div class="form-grid">
            <ToggleField label="Enable Audit Log" v-model="settings.system.enable_audit_log" />
            <ToggleField label="Manager-only Reports" v-model="settings.system.manager_only_reports" />
            <ToggleField label="Require Password Reset by Admin" v-model="settings.system.admin_password_resets" />
            <ToggleField label="Show Preview Mode Banner" v-model="settings.system.show_preview_banner" />
            <Field label="Default Currency" v-model="settings.system.currency" />
            <Field label="Date Format" v-model="settings.system.date_format" />
          </div>
        </section>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import UserManagementView from './UserManagementView.vue'

const SETTINGS_KEY = 'crs-coop-preview-settings'

const Field = defineComponent({
  props: { label: String, modelValue: [String, Number] },
  emits: ['update:modelValue'],
  setup(props, { emit, attrs }) {
    return () => h('div', { class: ['form-group', attrs.class] }, [
      h('label', { class: 'form-label' }, props.label),
      h('input', {
        class: 'form-input',
        value: props.modelValue,
        onInput: event => emit('update:modelValue', event.target.value),
      }),
    ])
  },
})

const NumberField = defineComponent({
  props: { label: String, modelValue: [String, Number], step: { type: String, default: '1' } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('div', { class: 'form-group' }, [
      h('label', { class: 'form-label' }, props.label),
      h('input', {
        class: 'form-input',
        type: 'number',
        step: props.step,
        value: props.modelValue,
        onInput: event => emit('update:modelValue', Number(event.target.value)),
      }),
    ])
  },
})

const ToggleField = defineComponent({
  props: { label: String, modelValue: Boolean },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('label', { class: 'toggle-field' }, [
      h('span', props.label),
      h('input', {
        type: 'checkbox',
        checked: props.modelValue,
        onChange: event => emit('update:modelValue', event.target.checked),
      }),
    ])
  },
})

const tabs = [
  { key: 'profile', label: 'Coop Profile', icon: '◎' },
  { key: 'loanTypes', label: 'Loan Types', icon: '▦' },
  { key: 'fees', label: 'Fees & Deductions', icon: '₱' },
  { key: 'approvals', label: 'Approvals', icon: '✓' },
  { key: 'payments', label: 'Payment Policy', icon: '₱' },
  { key: 'notifications', label: 'Notifications', icon: '✉' },
  { key: 'companies', label: 'Companies', icon: '◉' },
  { key: 'roles', label: 'Permissions', icon: '◌' },
  { key: 'users', label: 'Users', icon: '◎' },
  { key: 'memberAccess', label: 'Member Portal Access', icon: '◍' },
  { key: 'system', label: 'System', icon: '⚙' },
]

const { success } = useToast()
const activeTab = ref('profile')
const members = ref([])
const employmentStatuses = ['REGULAR', 'PROBI', 'CONTRACTUAL']
const permissionModules = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'members', label: 'Members' },
  { key: 'loans', label: 'Loan Officer Desk' },
  { key: 'pipeline', label: 'Loan Pipeline' },
  { key: 'monitoring', label: 'Monitoring' },
  { key: 'payments', label: 'Collections' },
  { key: 'eligibility', label: 'Eligibility Engine' },
  { key: 'reports', label: 'Reports' },
  { key: 'audit', label: 'Audit Log' },
  { key: 'beneficiaries', label: 'Beneficiaries' },
  { key: 'shareCapital', label: 'Share Capital' },
  { key: 'advanced', label: 'Advanced Operations' },
  { key: 'settings', label: 'Settings' },
]
const memberPortalModules = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'loans', label: 'My Loans' },
  { key: 'payments', label: 'Payment History' },
  { key: 'shareCapital', label: 'Share Capital' },
  { key: 'beneficiaries', label: 'Beneficiaries' },
  { key: 'profile', label: 'Profile' },
]
const settings = reactive(defaultSettings())
const memberAccessForm = reactive(defaultMemberAccessForm())

const activeMemberAccessCount = computed(() => settings.memberPortalAccess?.filter(access => access.active).length || 0)

// Portal access — auto-provision table
const portalMembers = ref([])
const portalSearch = ref('')
const portalFilter = ref('')
const portalCompany = ref('')
const portalLoading = ref(false)
const provisioningAll = ref(false)
const selectedIds = ref(new Set())

const portalCompanies = computed(() => {
  const set = new Set(portalMembers.value.map(r => r.company).filter(Boolean))
  return [...set].sort()
})

const portalStats = computed(() => ({
  total: portalMembers.value.length,
  active: portalMembers.value.filter(r => r.has_account && r.active).length,
  suspended: portalMembers.value.filter(r => r.has_account && !r.active).length,
  unprovisioned: portalMembers.value.filter(r => !r.has_account).length,
}))

const filteredPortalMembers = computed(() => {
  return portalMembers.value.filter(r => {
    if (portalCompany.value && r.company !== portalCompany.value) return false
    if (portalFilter.value === 'active') return r.has_account && r.active
    if (portalFilter.value === 'suspended') return r.has_account && !r.active
    if (portalFilter.value === 'unprovisioned') return !r.has_account
    return true
  })
})

// Selection helpers
const selectableRows = computed(() => filteredPortalMembers.value.filter(r => r.has_account))
const allVisibleSelected = computed(() => selectableRows.value.length > 0 && selectableRows.value.every(r => selectedIds.value.has(r.account_id)))
const someSelected = computed(() => selectableRows.value.some(r => selectedIds.value.has(r.account_id)))

function toggleSelect(accountId) {
  const s = new Set(selectedIds.value)
  s.has(accountId) ? s.delete(accountId) : s.add(accountId)
  selectedIds.value = s
}
function toggleSelectAll() {
  if (allVisibleSelected.value) {
    const s = new Set(selectedIds.value)
    selectableRows.value.forEach(r => s.delete(r.account_id))
    selectedIds.value = s
  } else {
    const s = new Set(selectedIds.value)
    selectableRows.value.forEach(r => s.add(r.account_id))
    selectedIds.value = s
  }
}
function clearSelection() { selectedIds.value = new Set() }

async function bulkAction(action) {
  const ids = [...selectedIds.value]
  if (!ids.length) return
  const label = action === 'activate' ? 'activate' : 'suspend'
  if (!confirm(`${label.charAt(0).toUpperCase() + label.slice(1)} ${ids.length} member(s)?`)) return
  try {
    await Promise.all(ids.map(id => api.toggleMemberPortalAccount(id)))
    clearSelection()
    await loadPortalMembers()
  } catch (e) {
    alert(e.message || 'An error occurred.')
  }
}

function formatPortalDate(dt) {
  if (!dt) return 'Never'
  return new Date(dt).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
}

let portalSearchTimer = null
function debouncedPortalSearch() {
  clearTimeout(portalSearchTimer)
  portalSearchTimer = setTimeout(loadPortalMembers, 350)
}

async function loadPortalMembers() {
  portalLoading.value = true
  try {
    portalMembers.value = await api.getAllMembersWithPortalStatus(portalSearch.value)
  } catch (e) {
    console.error(e)
  } finally {
    portalLoading.value = false
  }
}

async function provisionAll() {
  provisioningAll.value = true
  try {
    const res = await api.provisionAllMembers()
    alert(res.message || 'Done')
    await loadPortalMembers()
  } catch (e) {
    alert(e.message || 'An error occurred.')
  } finally {
    provisioningAll.value = false
  }
}

async function provisionOne(memberId) {
  try {
    await api.provisionOneMember(memberId)
    await loadPortalMembers()
  } catch (e) {
    alert(e.message || 'An error occurred.')
  }
}

async function resetPassword(accountId) {
  try {
    const res = await api.resetMemberPortalPassword(accountId)
    alert('New password: ' + res.temp_password)
    await loadPortalMembers()
  } catch (e) {
    alert(e.message || 'An error occurred.')
  }
}

async function toggleAccount(accountId) {
  try {
    await api.toggleMemberPortalAccount(accountId)
    await loadPortalMembers()
  } catch (e) {
    alert(e.message || 'An error occurred.')
  }
}

watch(activeTab, (tab) => {
  if (tab === 'memberAccess') loadPortalMembers()
}, { immediate: false })

function normalizeLoanType(type) {
  const allowOneMonth = type.allow_one_month_term ?? type.code === 'emergency'
  return {
    ...type,
    min_term: allowOneMonth ? 1 : Number(type.min_term ?? 3),
    allow_one_month_term: allowOneMonth,
    share_capital_requirement: type.share_capital_requirement ?? Math.max(5000, Number(type.min_amount || 0)),
    min_tenure_months: type.min_tenure_months ?? 3,
    max_active_loans: type.max_active_loans ?? 2,
    require_active_member: type.require_active_member ?? true,
    block_if_overdue: type.block_if_overdue ?? true,
    require_comaker: type.require_comaker ?? false,
    allowed_employment_statuses: type.allowed_employment_statuses?.length ? type.allowed_employment_statuses : ['REGULAR', 'PROBI'],
  }
}


function defaultLoanFees() {
  return [
    { key: 'service', label: 'Service Fee', note: '2% of principal', type: 'percent', value: 0.02, enabled: true },
    { key: 'cbu', label: 'Capital Build-Up (CBU)', note: '1% of principal · added to share capital', type: 'percent', value: 0.01, enabled: true },
    { key: 'notarial', label: 'Notarial Fee', note: 'Fixed PHP 200', type: 'fixed', value: 200, enabled: true },
    { key: 'mri', label: 'Loan Insurance (MRI)', note: '0.5% of principal per year', type: 'mri', value: 0.005, enabled: true },
    { key: 'processing', label: 'Processing Fee', note: 'Fixed PHP 100', type: 'fixed', value: 100, enabled: false },
  ]
}

function normalizeLoanFee(fee) {
  return {
    key: fee.key || `fee-${Date.now()}`,
    label: fee.label || 'Loan Fee',
    note: fee.note || '',
    type: ['percent', 'fixed', 'mri'].includes(fee.type) ? fee.type : 'fixed',
    value: Number(fee.value || 0),
    enabled: fee.enabled !== false,
  }
}

function defaultMemberAccessForm() {
  return {
    member_id: '',
    username: '',
    email: '',
    password: 'member123',
    force_password_change: true,
    modules: memberPortalModules.map(module => module.key),
  }
}

function normalizePortalAccount(account) {
  return {
    id: account.id || Date.now(),
    member_id: account.member_id,
    member_no: account.member_no,
    member_name: account.member_name || [account.first_name, account.middle_name, account.last_name].filter(Boolean).join(' '),
    username: account.username,
    email: account.email || '',
    temporary_password: account.temporary_password || '',
    force_password_change: account.force_password_change !== false,
    active: account.active ?? account.is_active !== false,
    modules: account.modules?.length ? account.modules : memberPortalModules.map(module => module.key),
    last_login: account.last_login || account.last_login_at || '',
  }
}

function defaultSettings(loanTypes = []) {
  return {
    profile: {
      name: 'CRS Holdings Corporations Employees Credit Cooperative',
      short_name: 'CRS ECCO',
      cda_registration: 'CDA-REG-______',
      tin: '___-___-___-___',
      address: 'A.C. Cortes Avenue, Alang-alang, Mandaue City, Cebu 6014',
      contact: '(032) 000-0000',
      email: 'coop@crsholdings.test',
    },
    loanTypes: loanTypes.map(normalizeLoanType),
    loanFees: defaultLoanFees(),
    approvals: [
      { role: 'Manager', enabled: true, amount_from: 1, amount_to: 50000, required_status: 'PENDING' },
      { role: 'Board', enabled: true, amount_from: 50001, amount_to: 150000, required_status: 'FOR BOARD' },
      { role: 'Super Admin', enabled: true, amount_from: 150001, amount_to: 500000, required_status: 'FOR FINAL REVIEW' },
    ],
    paymentPolicy: {
      grace_days: 3,
      penalty_rate: 0.02,
      reminder_hour: 8,
      require_or_number: true,
      auto_close_paid_loans: true,
      allow_partial_payments: true,
    },
    notifications: {
      sender_name: 'CRS ECCO',
      reply_to: 'coop@crsholdings.test',
      events: [
        { key: 'loan_submitted', label: 'Loan Submitted', description: 'Notify member when application is received.', sms: true, email: true },
        { key: 'loan_approved', label: 'Loan Approved', description: 'Notify member after approval.', sms: true, email: true },
        { key: 'payment_due', label: 'Payment Due Reminder', description: 'Reminder before upcoming amortization.', sms: true, email: false },
        { key: 'payment_posted', label: 'Payment Posted', description: 'Confirmation after collection posting.', sms: false, email: true },
        { key: 'overdue', label: 'Overdue Notice', description: 'Notify member when period becomes overdue.', sms: true, email: true },
      ],
    },
    companies: [
      { id: 1, name: 'CRS Holdings Corporation', branch: 'Mandaue', departmentsText: 'Operations, Accounting, HR, IT, Warehouse' },
      { id: 2, name: 'CRS Holdings Corporation', branch: 'Cebu', departmentsText: 'Warehouse, HR, Admin' },
    ],
    roles: [
      { id: 1, name: 'Super Admin', active: true, modules: permissionModules.map(module => module.key) },
      { id: 2, name: 'Manager', active: true, modules: ['dashboard', 'members', 'loans', 'pipeline', 'monitoring', 'payments', 'eligibility', 'reports', 'beneficiaries', 'shareCapital', 'advanced'] },
      { id: 3, name: 'Loan Officer', active: true, modules: ['dashboard', 'members', 'loans', 'pipeline', 'monitoring', 'payments', 'eligibility', 'beneficiaries', 'shareCapital'] },
      { id: 4, name: 'Auditor', active: true, modules: ['dashboard', 'members', 'reports', 'audit', 'beneficiaries', 'shareCapital'] },
    ],
    users: [
      { id: 1, name: 'J. Monteverde', username: 'jmonteverde', password: 'preview123', email: 'j.monteverde@crsholdings.test', role_id: 3, active: true },
      { id: 2, name: 'Admin User', username: 'admin', password: 'admin123', email: 'admin@crsholdings.test', role_id: 1, active: true },
    ],
    memberPortalAccess: [
      {
        id: 1,
        member_id: 1,
        member_no: 'CRS-00081',
        member_name: 'Josefina Monteverde',
        username: 'crs00081',
        email: 'j.monteverde@crsholdings.test',
        temporary_password: 'member123',
        force_password_change: true,
        active: true,
        modules: memberPortalModules.map(module => module.key),
        last_login: '',
      },
    ],
    system: {
      enable_audit_log: true,
      manager_only_reports: true,
      admin_password_resets: true,
      show_preview_banner: false,
      currency: 'PHP',
      date_format: 'en-PH',
    },
  }
}

function replaceSettings(next) {
  Object.keys(settings).forEach(key => delete settings[key])
  Object.assign(settings, next)
}

async function loadSettings() {
  const [loanTypes, memberRows] = await Promise.all([api.getLoanTypes(), api.getMembers()])
  members.value = memberRows || []
  const portalAccounts = await api.getMemberPortalAccounts()
  const saved = localStorage.getItem(SETTINGS_KEY)
  const next = saved ? JSON.parse(saved) : defaultSettings(loanTypes)
  next.loanTypes = (next.loanTypes?.length ? next.loanTypes : loanTypes).map(normalizeLoanType)
  next.loanFees = (next.loanFees?.length ? next.loanFees : defaultLoanFees()).map(normalizeLoanFee)
  next.roles = next.roles?.length ? next.roles : defaultSettings(loanTypes).roles
  next.users = next.users?.length ? next.users : defaultSettings(loanTypes).users
  next.memberPortalAccess = portalAccounts?.length ? portalAccounts.map(normalizePortalAccount) : (next.memberPortalAccess?.length ? next.memberPortalAccess : defaultSettings(loanTypes).memberPortalAccess)
  replaceSettings(next)
}

function saveSettings() {
  localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
  success('Settings saved for preview mode.')
}

async function resetDefaults() {
  localStorage.removeItem(SETTINGS_KEY)
  await loadSettings()
  success('Preview settings reset.')
}

function addLoanFee() {
  settings.loanFees.push(normalizeLoanFee({
    key: `fee-${Date.now()}`,
    label: 'New Deduction',
    note: 'Fixed PHP 0',
    type: 'fixed',
    value: 0,
    enabled: true,
  }))
}

function removeLoanFee(key) {
  settings.loanFees = settings.loanFees.filter(fee => fee.key !== key)
}

function addLoanType() {
  settings.loanTypes.push({
    id: Date.now(),
    code: 'new-loan',
    label: 'New Loan Type',
    annual_rate: 0.1,
    min_amount: 5000,
    max_amount: 50000,
    min_term: 3,
    max_term: 24,
    allow_one_month_term: false,
    share_capital_requirement: 10000,
    min_tenure_months: 6,
    max_active_loans: 2,
    require_active_member: true,
    block_if_overdue: true,
    require_comaker: false,
    allowed_employment_statuses: ['REGULAR'],
  })
}

function deleteLoanType(id) {
  if (settings.loanTypes.length <= 1) return
  settings.loanTypes = settings.loanTypes.filter(type => type.id !== id)
  success('Loan type removed. Save settings to keep this change.')
}

function toggleOneMonthTerm(type, checked) {
  type.allow_one_month_term = checked
  if (checked) type.min_term = 1
  else if (Number(type.min_term || 0) < 3) type.min_term = 3
}

function toggleEmploymentStatus(type, status, checked) {
  const set = new Set(type.allowed_employment_statuses || [])
  if (checked) set.add(status)
  else set.delete(status)
  type.allowed_employment_statuses = [...set]
}

function addCompany() {
  settings.companies.push({
    id: Date.now(),
    name: 'New Company',
    branch: 'Main',
    departmentsText: 'Operations, Accounting',
  })
}

function toggleRoleModule(role, moduleKey, checked) {
  const set = new Set(role.modules || [])
  if (checked) set.add(moduleKey)
  else set.delete(moduleKey)
  role.modules = [...set]
}

function memberName(member) {
  return [member.first_name, member.middle_name, member.last_name].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim()
}

function memberUsername(member) {
  return String(member.member_no || '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '')
}

function selectedMember() {
  return members.value.find(member => Number(member.id) === Number(memberAccessForm.member_id))
}

function syncMemberAccessDefaults() {
  const member = selectedMember()
  if (!member) return
  memberAccessForm.username = memberUsername(member)
  memberAccessForm.email = member.email || ''
}

function moduleLabel(key) {
  return memberPortalModules.find(module => module.key === key)?.label || key
}

function toggleMemberAccessModule(moduleKey, checked) {
  const set = new Set(memberAccessForm.modules || [])
  if (checked) set.add(moduleKey)
  else set.delete(moduleKey)
  memberAccessForm.modules = [...set]
}

function resetMemberAccessForm() {
  Object.assign(memberAccessForm, defaultMemberAccessForm())
}

async function createMemberAccess() {
  const member = selectedMember()
  if (!member) return
  const existing = settings.memberPortalAccess.find(access => Number(access.member_id) === Number(member.id))
  const payload = {
    id: existing?.id || Date.now(),
    member_id: member.id,
    member_no: member.member_no,
    member_name: memberName(member),
    username: memberAccessForm.username || memberUsername(member),
    email: memberAccessForm.email || member.email || '',
    temporary_password: memberAccessForm.password || 'member123',
    force_password_change: memberAccessForm.force_password_change,
    active: true,
    modules: memberAccessForm.modules.length ? [...memberAccessForm.modules] : memberPortalModules.map(module => module.key),
    last_login: existing?.last_login || '',
  }

  const saved = existing
    ? await api.updateMemberPortalAccount(existing.id, {
        member_id: payload.member_id,
        username: payload.username,
        email: payload.email,
        password: payload.temporary_password,
        force_password_change: payload.force_password_change,
        modules: payload.modules,
        is_active: payload.active,
      })
    : await api.createMemberPortalAccount({
        member_id: payload.member_id,
        username: payload.username,
        email: payload.email,
        password: payload.temporary_password,
        force_password_change: payload.force_password_change,
        modules: payload.modules,
        is_active: payload.active,
      })

  const next = normalizePortalAccount({ ...payload, ...saved })
  if (existing) Object.assign(existing, next)
  else settings.memberPortalAccess.unshift(next)

  resetMemberAccessForm()
  success('Member portal access saved. Save settings to keep this change.')
}

async function resetMemberAccessPassword(access) {
  const result = await api.resetMemberPortalPassword(access.id)
  access.temporary_password = result.temp_password || 'member123'
  access.force_password_change = true
  success(`${access.member_name} password reset to ${access.temporary_password}.`)
}

async function toggleMemberAccess(access) {
  const result = await api.toggleMemberPortalAccount(access.id)
  access.active = result.active ?? !access.active
}

onMounted(loadSettings)
</script>

<style scoped>
.settings-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
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
.settings-body {
  flex:1;
  min-height:0;
  overflow:hidden;
  display:grid;
  grid-template-columns:250px minmax(0, 1fr);
  background:#F3F5F8;
}
.settings-nav {
  background:#fff;
  border-right:1px solid var(--coop-border);
  padding:12px;
  display:flex;
  flex-direction:column;
  gap:6px;
}
.settings-tab {
  border:0;
  background:transparent;
  border-radius:8px;
  padding:12px;
  color:var(--coop-muted);
  font-weight:800;
  text-align:left;
  display:flex;
  align-items:center;
  gap:10px;
  cursor:pointer;
}
.settings-tab.active, .settings-tab:hover { background:var(--coop-red-dim); color:var(--coop-red); }
.settings-tab span { width:20px; text-align:center; }
.settings-panel { overflow:auto; padding:22px; }
.settings-card {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  box-shadow:0 8px 22px rgba(31,41,55,.04);
  padding:18px;
}
.card-head {
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:18px;
  margin-bottom:18px;
}
h2 { color:var(--coop-cream); font-size:22px; margin:0; }
p { color:var(--coop-muted); margin-top:4px; }
.form-grid, .type-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:12px;
}
.form-group.wide { grid-column:1 / -1; }
.loan-type-list, .company-list, .notification-list {
  display:flex;
  flex-direction:column;
  gap:12px;
}
.loan-type-card, .company-card, .rule-card, .notification-row {
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:14px;
  background:#F8FAFC;
}
.loan-type-head, .rule-head {
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
  margin-bottom:12px;
}
.loan-type-head > div {
  display:flex;
  flex-direction:column;
  gap:4px;
  min-width:0;
}
.loan-type-head strong, .rule-head strong { color:var(--coop-cream); }
.delete-type-btn {
  border-color:#F3C4BE;
  color:#A7332B;
  background:#FFF1F0;
  white-space:nowrap;
}
.eligibility-rule-block {
  margin-top:14px;
  border-top:1px solid var(--coop-border);
  padding-top:14px;
}
.rule-section-title {
  color:var(--coop-red);
  font-size:12px;
  font-weight:900;
  letter-spacing:.7px;
  text-transform:uppercase;
  margin-bottom:12px;
}
.status-rule-row {
  margin-top:12px;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  background:#fff;
  display:flex;
  flex-wrap:wrap;
  gap:12px 18px;
  align-items:center;
}
.status-rule-row span {
  color:var(--coop-cream);
  font-weight:900;
  margin-right:auto;
}
.status-rule-row label {
  color:var(--coop-muted);
  font-weight:800;
  display:flex;
  align-items:center;
  gap:6px;
}
.approval-grid {
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:12px;
}
.rule-card { display:flex; flex-direction:column; gap:12px; }
.notification-row {
  display:grid;
  grid-template-columns:minmax(0, 1fr) 90px 90px;
  gap:14px;
  align-items:center;
}
.notification-row strong { display:block; color:var(--coop-cream); }
.notification-row span { color:var(--coop-muted); font-size:12px; }
.notification-row label { color:var(--coop-cream); font-weight:800; display:flex; gap:6px; align-items:center; }
.notification-config { margin-top:16px; }
.permission-layout {
  display:grid;
  grid-template-columns:minmax(0, 1fr);
  gap:14px;
}
.permission-card {
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#F8FAFC;
  padding:14px;
}
.permission-title {
  color:var(--coop-cream);
  font-size:16px;
  font-weight:900;
  margin-bottom:12px;
}
.role-list { display:flex; flex-direction:column; gap:12px; }
.role-card {
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#fff;
  padding:14px;
}
.role-head {
  display:grid;
  grid-template-columns:minmax(0, 1fr) 220px;
  gap:12px;
  margin-bottom:12px;
}
.module-access-grid {
  display:grid;
  grid-template-columns:repeat(3, minmax(0, 1fr));
  gap:8px;
}
.module-check {
  border:1px solid var(--coop-border);
  border-radius:7px;
  padding:9px;
  color:var(--coop-cream);
  font-weight:800;
  display:flex;
  align-items:center;
  gap:8px;
  background:#F8FAFC;
}
.module-check span { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.portal-access-layout {
  display:grid;
  grid-template-columns:minmax(0, 1.1fr) minmax(320px, .9fr);
  gap:14px;
}
.portal-access-form, .portal-access-list {
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#F8FAFC;
  padding:14px;
}
.portal-module-box { margin-top:14px; }
.portal-submit { width:100%; margin-top:14px; }
.portal-access-list { display:flex; flex-direction:column; gap:10px; }
.portal-access-row {
  border:1px solid var(--coop-border);
  border-radius:8px;
  background:#fff;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:flex-start;
}
.portal-access-row strong { display:block; color:var(--coop-cream); font-weight:900; }
.portal-access-row span, .portal-access-row small { display:block; color:var(--coop-muted); margin-top:3px; }
.portal-access-actions { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:8px; min-width:240px; }
.empty-member-access {
  border:1px dashed var(--coop-border);
  border-radius:8px;
  color:var(--coop-muted);
  padding:18px;
  text-align:center;
  background:#fff;
}
.toggle-field {
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:12px;
  align-items:center;
  color:var(--coop-cream);
  font-weight:800;
  background:#F8FAFC;
}
.switch { display:inline-flex; align-items:center; cursor:pointer; }
.switch input { display:none; }
.switch span {
  width:42px;
  height:24px;
  border-radius:999px;
  background:#CBD5E1;
  position:relative;
}
.switch span::after {
  content:'';
  position:absolute;
  width:18px;
  height:18px;
  border-radius:50%;
  background:#fff;
  top:3px;
  left:3px;
  transition:.18s ease;
}
.switch input:checked + span { background:var(--coop-red); }
.switch input:checked + span::after { transform:translateX(18px); }
@media (max-width: 1100px) {
  .settings-body { grid-template-columns:1fr; overflow:auto; }
  .settings-nav { border-right:0; border-bottom:1px solid var(--coop-border); flex-direction:row; overflow:auto; }
  .settings-tab { white-space:nowrap; }
  .approval-grid, .permission-layout, .portal-access-layout { grid-template-columns:1fr; }
}
@media (max-width: 760px) {
  .view-header { flex-direction:column; align-items:flex-start; gap:12px; }
  .header-actions { width:100%; flex-direction:column; align-items:stretch; }
  .form-grid, .type-grid, .notification-row, .role-head, .module-access-grid { grid-template-columns:1fr; }
  .settings-panel { padding:14px; }
  .portal-access-row, .portal-access-actions { flex-direction:column; align-items:stretch; min-width:0; }
}

/* Portal Access auto-provision table */
.pa-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; }
.pa-sub { font-size:13px; color:#6B7280; margin-top:4px; }
.pa-actions { display:flex; gap:8px; }
.pa-stats { display:flex; gap:20px; font-size:13px; color:#6B7280; padding:10px 14px; background:#F9FAFB; border-radius:8px; margin-bottom:14px; flex-wrap:wrap; }
.pa-stats strong { color:#111827; }
.text-green { color:#1D9E75 !important; }
.text-red { color:#EF4444 !important; }
.text-muted { color:#9CA3AF !important; }
.pa-search-row { display:flex; gap:10px; margin-bottom:10px; }
.pa-search { flex:1; min-width:0; }
.pa-company { min-width:180px; }

/* Bulk bar */
.pa-bulk-bar { display:flex; align-items:center; gap:10px; padding:8px 14px; background:#EFF6FF; border:1px solid #BFDBFE; border-radius:8px; margin-bottom:10px; }
.pa-bulk-count { font-size:13px; font-weight:600; color:#1D4ED8; flex:1; }

/* Table */
.pa-table-wrap { overflow-x:auto; border:1px solid #E3E7EF; border-radius:8px; }
.pa-table { width:100%; border-collapse:collapse; font-size:13px; }
.pa-table th { padding:9px 12px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#9CA3AF; border-bottom:1px solid #E3E7EF; background:#F9FAFB; }
.pa-table td { padding:9px 12px; border-bottom:1px solid #F3F4F6; vertical-align:middle; }
.pa-table tr:last-child td { border-bottom:none; }
.pa-th-check { width:36px; text-align:center; }
.pa-row-suspended td { opacity:0.55; }
.pa-row-selected { background:#F0F9FF !important; }
.pa-name { font-weight:600; color:#111827; }
.pa-memberno { font-size:11px; color:#9CA3AF; margin-top:1px; }
.pa-company-cell { font-size:12px; color:#6B7280; max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pa-mono { font-family:monospace; font-size:12px; color:#374151; }
.pa-password { font-family:monospace; font-size:12px; font-weight:600; color:#1D9E75; background:#F0FDF4; padding:2px 8px; border-radius:4px; }
.pa-changed { font-size:11px; color:#9CA3AF; font-style:italic; }
.pa-date { font-size:12px; color:#6B7280; white-space:nowrap; }
.pa-btns { display:flex; gap:6px; white-space:nowrap; }
.pa-badge { font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px; }
.pa-badge-active { background:#D1FAE5; color:#065F46; }
.pa-badge-suspended { background:#FEE2E2; color:#991B1B; }
.pa-badge-none { background:#F3F4F6; color:#9CA3AF; }
.pa-loading, .pa-empty { text-align:center; padding:32px; color:#9CA3AF; font-size:13px; }
.btn-sm { padding:4px 10px; font-size:12px; }
.btn-warning { background:#FEF3C7; color:#92400E; border-color:#FCD34D; }
.btn-success { background:#D1FAE5; color:#065F46; border-color:#6EE7B7; }
</style>


<style scoped>
.fee-settings-list { display:flex; flex-direction:column; gap:14px; }
.fee-settings-card { border:1px solid var(--coop-border); border-radius:8px; padding:14px; background:#fff; }
.fee-settings-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
.type-grid .wide { grid-column:1 / -1; }
</style>
