<template>
  <div class="eligibility-wrap">
    <header class="view-header">
      <div>
        <div class="view-title serif">Eligibility Engine</div>
        <div class="view-sub">Automatic pre-checks before loan application submission</div>
      </div>
      <span :class="['decision-pill', decision.allowed ? 'ok' : 'blocked']">
        {{ decision.allowed ? 'Eligible' : 'Blocked' }}
      </span>
    </header>

    <main class="eligibility-body">
      <section class="check-panel">
        <div class="panel-title">Loan Request</div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Member</label>
            <select v-model.number="form.member_id" class="form-select">
              <option v-for="member in members" :key="member.id" :value="member.id">
                {{ member.member_no }} - {{ member.first_name }} {{ member.last_name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Loan Type</label>
            <select v-model.number="form.loan_type_id" class="form-select">
              <option v-for="type in loanTypes" :key="type.id" :value="type.id">
                {{ type.label }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Amount</label>
            <input v-model.number="form.amount" type="number" class="form-input" min="0" step="1000" />
          </div>

          <div class="form-group">
            <label class="form-label">Term</label>
            <input v-model.number="form.term_months" type="number" class="form-input" min="1" />
          </div>
        </div>

        <div v-if="member" class="member-snapshot">
          <div>
            <span class="snap-label">Status</span>
            <strong>{{ member.member_status }} / {{ member.status }}</strong>
          </div>
          <div>
            <span class="snap-label">Tenure</span>
            <strong>{{ tenureMonths }} months</strong>
          </div>
          <div>
            <span class="snap-label">Share Capital</span>
            <strong>{{ peso(member.share_capital) }}</strong>
          </div>
          <div>
            <span class="snap-label">Active Loans</span>
            <strong>{{ member.active_loans || 0 }}</strong>
          </div>
        </div>
      </section>

      <section class="results-panel">
        <div class="panel-title">Eligibility Result</div>
        <div class="checks-list">
          <div v-for="check in checks" :key="check.key" :class="['check-row', check.pass ? 'pass' : 'fail']">
            <div class="check-icon">{{ check.pass ? '✓' : '!' }}</div>
            <div class="check-content">
              <div class="check-title">{{ check.title }}</div>
              <div class="check-detail">{{ check.detail }}</div>
            </div>
          </div>
        </div>

        <div class="decision-box">
          <div class="decision-title">{{ decision.allowed ? 'Ready to proceed' : 'Submission blocked' }}</div>
          <p>{{ decision.message }}</p>
          <router-link v-if="decision.allowed" :to="{ name: 'loans', query: { member_id: form.member_id } }" class="btn btn-primary">
            Continue to Loan Application
          </router-link>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { api } from '../composables/useApi'
import { peso } from '../composables/useLoanCalc'

const members = ref([])
const loanTypes = ref([])
const form = reactive({
  member_id: null,
  loan_type_id: null,
  amount: 25000,
  term_months: 12,
})

const member = computed(() => members.value.find(m => m.id === Number(form.member_id)))
const loanType = computed(() => loanTypes.value.find(t => t.id === Number(form.loan_type_id)))

const tenureMonths = computed(() => {
  if (!member.value?.date_hired) return 0
  const hired = new Date(member.value.date_hired)
  const now = new Date()
  return Math.max(0, (now.getFullYear() - hired.getFullYear()) * 12 + now.getMonth() - hired.getMonth())
})

const checks = computed(() => {
  if (!member.value || !loanType.value) return []

  const minimumCapital = Number(loanType.value.share_capital_requirement ?? Math.max(5000, form.amount * 0.1))
  const minimumTenure = Number(loanType.value.min_tenure_months ?? 3)
  const minimumTerm = loanType.value.allow_one_month_term ? 1 : Number(loanType.value.min_term || 1)
  const maxActiveLoans = Number(loanType.value.max_active_loans ?? 2)
  const allowedEmployment = loanType.value.allowed_employment_statuses?.length
    ? loanType.value.allowed_employment_statuses
    : ['REGULAR', 'PROBI']
  const requireActiveMember = loanType.value.require_active_member ?? true
  return [
    {
      key: 'member-status',
      title: 'Member status',
      pass: !requireActiveMember || member.value.member_status === 'ACTIVE',
      detail: requireActiveMember
        ? `${member.value.member_no} must be ACTIVE; current status is ${member.value.member_status}.`
        : 'Active member status is not required for this loan type.',
    },
    {
      key: 'employment-status',
      title: 'Employment status',
      pass: allowedEmployment.includes(member.value.status),
      detail: `${loanType.value.label} allows ${allowedEmployment.join(', ')}; member is ${member.value.status}.`,
    },
    {
      key: 'tenure',
      title: 'Tenure',
      pass: tenureMonths.value >= minimumTenure,
      detail: `${tenureMonths.value} months rendered; minimum is ${minimumTenure} months.`,
    },
    {
      key: 'capital',
      title: 'Share capital',
      pass: Number(member.value.share_capital || 0) >= minimumCapital,
      detail: `${peso(member.value.share_capital)} available; required ${peso(minimumCapital)}.`,
    },
    {
      key: 'amount',
      title: 'Amount limit',
      pass: form.amount >= loanType.value.min_amount && form.amount <= loanType.value.max_amount,
      detail: `${loanType.value.label} allows ${peso(loanType.value.min_amount)} to ${peso(loanType.value.max_amount)}.`,
    },
    {
      key: 'term',
      title: 'Term limit',
      pass: form.term_months >= minimumTerm && form.term_months <= loanType.value.max_term,
      detail: `${loanType.value.label} allows ${minimumTerm}-${loanType.value.max_term} months.`,
    },
    {
      key: 'concurrent',
      title: 'Concurrent loans',
      pass: Number(member.value.active_loans || 0) < maxActiveLoans,
      detail: `${member.value.active_loans || 0} active loan(s); ${loanType.value.label} limit is ${maxActiveLoans}.`,
    },
  ]
})

const decision = computed(() => {
  const allowed = checks.value.length > 0 && checks.value.every(check => check.pass)
  return {
    allowed,
    message: allowed
      ? 'All automatic checks passed. This member can continue to the loan application screen.'
      : 'One or more checks failed. Adjust the request or review the member profile before proceeding.',
  }
})

watch(loanType, type => {
  if (!type) return
  form.amount = Math.min(type.max_amount, Math.max(type.min_amount, form.amount))
  const minimumTerm = type.allow_one_month_term ? 1 : Number(type.min_term || 1)
  form.term_months = Math.min(type.max_term, Math.max(minimumTerm, form.term_months))
})

onMounted(async () => {
  members.value = await api.getMembers()
  loanTypes.value = await api.getLoanTypes()
  form.member_id = members.value[0]?.id ?? null
  form.loan_type_id = loanTypes.value[0]?.id ?? null
})
</script>

<style scoped>
.eligibility-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
.view-header {
  padding: 20px 28px;
  border-bottom: 1px solid var(--coop-border);
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fff;
}
.view-title { font-size:clamp(34px,3.1vw,48px); color:#202838; }
.view-sub { font-size:clamp(15px,1.2vw,19px); color:#6D7484; margin-top:12px; }
.eligibility-body {
  flex:1;
  overflow:auto;
  padding:24px 28px;
  display:grid;
  grid-template-columns:minmax(360px, 0.9fr) 1.1fr;
  gap:16px;
}
.check-panel, .results-panel {
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:20px;
  box-shadow:0 8px 24px rgba(31,41,55,0.04);
}
.panel-title { font-size:16px; font-weight:800; color:var(--coop-cream); margin-bottom:14px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.member-snapshot {
  margin-top:18px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
  padding:14px;
  border-radius:8px;
  background:var(--coop-red-dim);
  border:1px solid rgba(192,57,43,0.16);
}
.member-snapshot div { display:flex; flex-direction:column; gap:2px; }
.snap-label { font-size:11px; text-transform:uppercase; color:var(--coop-muted); letter-spacing:.5px; }
.member-snapshot strong { color:var(--coop-cream); }
.decision-pill {
  padding:7px 12px;
  border-radius:999px;
  font-weight:800;
  font-size:12px;
}
.decision-pill.ok { background:rgba(39,174,96,.14); color:var(--status-approved); }
.decision-pill.blocked { background:var(--coop-red-dim); color:var(--coop-red); }
.checks-list { display:flex; flex-direction:column; gap:8px; }
.check-row {
  display:flex;
  gap:12px;
  align-items:flex-start;
  padding:12px;
  border:1px solid var(--coop-border);
  border-radius:8px;
}
.check-row.pass { border-color:rgba(39,174,96,.28); background:rgba(39,174,96,.06); }
.check-row.fail { border-color:rgba(192,57,43,.28); background:var(--coop-red-dim); }
.check-icon {
  width:24px;
  height:24px;
  border-radius:50%;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  font-weight:800;
  flex-shrink:0;
}
.pass .check-icon { background:var(--status-approved); }
.fail .check-icon { background:var(--coop-red); }
.check-title { color:var(--coop-cream); font-weight:800; }
.check-detail { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.decision-box {
  margin-top:14px;
  padding:16px;
  border-radius:8px;
  background:#fff8f7;
  border:1px solid rgba(192,57,43,.18);
}
.decision-title { font-weight:900; color:var(--coop-cream); margin-bottom:4px; }
.decision-box p { color:var(--coop-muted); margin-bottom:12px; }
@media (max-width: 1100px) {
  .eligibility-body { grid-template-columns:1fr; }
}
</style>
