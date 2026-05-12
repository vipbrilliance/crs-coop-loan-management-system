<template>
  <div :class="['users-wrap', embedded && 'embedded']">
    <header v-if="!embedded" class="view-header">
      <div>
        <div class="view-title serif">User Management</div>
        <div class="view-sub">Create users, assign roles, reset passwords, and control access</div>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" @click="loadUsers">Refresh</button>
        <button class="btn btn-primary" @click="openCreate">New User</button>
      </div>
    </header>

    <main class="users-body">
      <section class="user-kpis">
        <div class="user-kpi">
          <div class="kpi-label">Total Users</div>
          <div class="kpi-value">{{ meta.total }}</div>
          <div class="kpi-sub">System accounts</div>
        </div>
        <div class="user-kpi">
          <div class="kpi-label">Active</div>
          <div class="kpi-value success">{{ meta.active }}</div>
          <div class="kpi-sub">Can sign in</div>
        </div>
        <div class="user-kpi">
          <div class="kpi-label">Inactive</div>
          <div class="kpi-value danger">{{ meta.inactive }}</div>
          <div class="kpi-sub">Access disabled</div>
        </div>
      </section>

      <section class="filter-card">
        <div class="form-group search-field">
          <label class="form-label">Search</label>
          <input v-model="filters.search" class="form-input" placeholder="Name or email" @keyup.enter="loadUsers" />
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select v-model="filters.role" class="form-select" @change="loadUsers">
            <option value="">All roles</option>
            <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select v-model="filters.is_active" class="form-select" @change="loadUsers">
            <option value="">All statuses</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
        </div>
        <button class="btn btn-secondary" @click="resetFilters">Reset</button>
      </section>

      <section class="users-card">
        <table class="users-table">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td>
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }}</span>
              </td>
              <td><span class="role-chip">{{ roleLabel(user.role) }}</span></td>
              <td><span :class="Number(user.is_active) ? 'badge badge-approved' : 'badge badge-rejected'">{{ Number(user.is_active) ? 'ACTIVE' : 'INACTIVE' }}</span></td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="row-actions">
                  <button class="mini-btn" @click="openEdit(user)">Edit</button>
                  <button class="mini-btn" @click="resetPassword(user)">Reset PW</button>
                  <button :class="['mini-btn', Number(user.is_active) && 'danger']" @click="toggleActive(user)">{{ Number(user.is_active) ? 'Deactivate' : 'Reactivate' }}</button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !users.length"><td colspan="5" class="empty-cell">No users found</td></tr>
            <tr v-if="loading"><td colspan="5" class="empty-cell"><div class="spinner"></div></td></tr>
          </tbody>
        </table>
      </section>
    </main>

    <div v-if="formOpen" class="modal-overlay" @click.self="formOpen = false">
      <form class="modal" @submit.prevent="saveUser">
        <div class="modal-header">
          <div class="modal-title">{{ editing ? 'Edit User' : 'New User' }}</div>
          <button type="button" class="btn btn-ghost btn-sm" @click="formOpen = false">Close</button>
        </div>
        <div class="modal-body form-stack">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Name</label>
              <input v-model="form.name" class="form-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="form.email" type="email" class="form-input" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Role</label>
              <select v-model="form.role" class="form-select" required>
                <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Password</label>
              <input v-model="form.password" type="password" class="form-input" :placeholder="editing ? 'Leave blank to keep current' : 'Temporary password'" />
            </div>
          </div>
          <label class="toggle-row"><input v-model="form.is_active" type="checkbox" /> Active account</label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" @click="formOpen = false">Cancel</button>
          <button class="btn btn-primary" type="submit" :disabled="saving">Save User</button>
        </div>
      </form>
    </div>

    <div v-if="tempPassword" class="modal-overlay" @click.self="tempPassword = ''">
      <div class="modal small-modal">
        <div class="modal-header">
          <div class="modal-title">Temporary Password</div>
          <button type="button" class="btn btn-ghost btn-sm" @click="tempPassword = ''">Close</button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Give this to the user securely. It is shown once.</p>
          <div class="temp-password mono">{{ tempPassword }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { api } from '../composables/useApi'
import { useToast } from '../composables/useToast'

defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const { success, error } = useToast()
const roles = [
  { value: 'SUPER_ADMIN', label: 'Super Admin' },
  { value: 'ADMIN', label: 'Admin' },
  { value: 'MANAGER', label: 'Manager' },
  { value: 'LOAN_OFFICER', label: 'Loan Officer' },
  { value: 'STAFF', label: 'Staff' },
  { value: 'AUDITOR', label: 'Auditor' },
]
const users = ref([])
const meta = reactive({ total: 0, active: 0, inactive: 0 })
const loading = ref(false)
const saving = ref(false)
const formOpen = ref(false)
const editing = ref(null)
const tempPassword = ref('')
const filters = reactive({ search: '', role: '', is_active: '' })
const form = reactive({ name: '', email: '', role: 'STAFF', password: '', is_active: true })

function params() {
  return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''))
}

async function loadUsers() {
  loading.value = true
  try {
    const res = await api.getUsers(params())
    users.value = res.users || []
    Object.assign(meta, res.meta || { total: users.value.length, active: 0, inactive: 0 })
  } catch (err) {
    error(err.message || 'Could not load users.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  Object.assign(filters, { search: '', role: '', is_active: '' })
  loadUsers()
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', email: '', role: 'STAFF', password: '', is_active: true })
  formOpen.value = true
}

function openEdit(user) {
  editing.value = user
  Object.assign(form, { name: user.name, email: user.email, role: user.role, password: '', is_active: Boolean(Number(user.is_active)) })
  formOpen.value = true
}

async function saveUser() {
  saving.value = true
  try {
    if (editing.value) await api.updateUser(editing.value.id, { ...form })
    else await api.createUser({ ...form })
    formOpen.value = false
    success('User saved.')
    await loadUsers()
  } catch (err) {
    error(err.message || 'Could not save user.')
  } finally {
    saving.value = false
  }
}

async function toggleActive(user) {
  try {
    const res = await api.toggleUserActive(user.id)
    success(res.message || 'User updated.')
    await loadUsers()
  } catch (err) {
    error(err.message || 'Could not update user.')
  }
}

async function resetPassword(user) {
  if (!window.confirm(`Reset password for ${user.name}?`)) return
  try {
    const res = await api.resetUserPassword(user.id)
    tempPassword.value = res.temp_password
  } catch (err) {
    error(err.message || 'Could not reset password.')
  }
}

function roleLabel(value) {
  return roles.find(role => role.value === value)?.label || value || '-'
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-PH') : '-'
}

onMounted(loadUsers)
</script>

<style scoped>
.users-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.users-wrap.embedded { height:auto; overflow:visible; }
.view-header { padding:20px 28px; border-bottom:1px solid var(--coop-border); display:flex; justify-content:space-between; align-items:center; background:#fff; }
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.header-actions { display:flex; gap:10px; align-items:center; }
.users-body { flex:1; overflow:auto; padding:18px 22px 24px; display:flex; flex-direction:column; gap:14px; }
.users-wrap.embedded .users-body { padding:0; overflow:visible; }
.user-kpis { display:grid; grid-template-columns:repeat(3, minmax(180px, 1fr)); gap:12px; }
.user-kpi, .filter-card, .users-card { background:#fff; border:1px solid var(--coop-border); border-radius:8px; box-shadow:0 8px 22px rgba(31,41,55,.04); }
.user-kpi { padding:16px 18px; }
.kpi-label { color:var(--coop-muted); font-size:11px; font-weight:900; letter-spacing:.8px; text-transform:uppercase; }
.kpi-value { margin-top:8px; color:var(--coop-cream); font-size:28px; line-height:1; font-weight:900; }
.kpi-value.success { color:var(--status-approved); }
.kpi-value.danger { color:var(--coop-red); }
.kpi-sub { color:var(--coop-muted); font-size:12px; margin-top:8px; }
.filter-card { padding:14px; display:grid; grid-template-columns:minmax(220px, 1fr) 190px 160px auto; gap:12px; align-items:end; }
.users-card { overflow:auto; }
.users-table { width:100%; border-collapse:collapse; }
.users-table th { background:#F8FAFC; color:var(--coop-muted); font-size:11px; font-weight:900; letter-spacing:.5px; text-transform:uppercase; padding:10px 12px; text-align:left; border-bottom:1px solid var(--coop-border); }
.users-table td { padding:12px; border-bottom:1px solid var(--coop-border); color:var(--coop-cream); vertical-align:middle; }
.users-table td:first-child span { display:block; color:var(--coop-muted); font-size:12px; }
.role-chip { display:inline-flex; padding:3px 8px; border-radius:999px; background:var(--coop-red-dim); color:var(--coop-red); font-weight:800; font-size:12px; }
.row-actions { display:flex; gap:6px; flex-wrap:wrap; }
.mini-btn { border:1px solid var(--coop-border); background:#fff; color:var(--coop-muted); border-radius:4px; padding:4px 7px; font-size:11px; cursor:pointer; }
.mini-btn:hover { color:var(--coop-red); border-color:rgba(192,57,43,.35); background:var(--coop-red-dim); }
.mini-btn.danger { color:var(--coop-red); }
.empty-cell { text-align:center; padding:28px !important; color:var(--coop-muted) !important; }
.form-stack { display:flex; flex-direction:column; gap:14px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.toggle-row { display:flex; align-items:center; gap:8px; color:var(--coop-cream); font-weight:800; }
.small-modal { max-width:420px; }
.temp-password { margin-top:14px; padding:14px; border:1px solid var(--coop-border); border-radius:8px; background:#F8FAFC; font-size:22px; text-align:center; color:var(--coop-cream); }
@media (max-width: 900px) { .user-kpis, .filter-card { grid-template-columns:1fr; } .view-header { flex-direction:column; align-items:flex-start; gap:12px; } }
@media (max-width: 720px) { .users-body { padding:14px; } .form-row { grid-template-columns:1fr; } .users-card { overflow-x:auto; } .users-table { min-width:760px; } }
</style>
