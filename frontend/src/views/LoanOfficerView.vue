<template>
  <div class="loan-app-page">
    <header class="loan-topbar">
      <div class="crumbs">
        <strong>Loan Application</strong>
        <span v-if="selectedMember">/ New · {{ memberFullName }} · {{ selectedMember.member_no }}</span>
      </div>
      <button class="top-search" type="button" @click="openMemberModal">
        <span>⌕</span>
        Find member
      </button>
    </header>

    <main class="loan-scroll">
      <section class="hero-row">
        <div>
          <h1>New Loan Application</h1>
          <div class="ref-row">
            <span class="ref-pill mono">APP · {{ applicationNo }}</span>
            <span>Auto-generated from member 201 file</span>
            <span class="draft-chip">Draft</span>
          </div>
        </div>
        <div class="hero-actions">
          <button class="btn btn-secondary" type="button" @click="saveLoan('DRAFT')" :disabled="saving || !selectedMember">⇩ Save Draft</button>
          <button class="btn btn-secondary" type="button" :disabled="!selectedMember">▤ Preview PDF</button>
          <button class="btn btn-primary" type="button" @click="saveLoan('PENDING')" :disabled="saving || !canSubmit">☑ Generate & Print</button>
        </div>
      </section>

      <section class="stepper-card">
        <div v-for="step in steps" :key="step.no" :class="['step-item', step.no < activeStep && 'done', step.no === activeStep && 'active']">
          <div class="step-line" v-if="step.no > 1"></div>
          <div class="step-dot">{{ step.no < activeStep ? '✓' : step.no }}</div>
          <div class="step-label">{{ step.label }}</div>
        </div>
      </section>

      <section v-if="!selectedMember" class="begin-state">
        <div class="begin-icon">⌕</div>
        <h2>Search for a member to begin</h2>
        <p>Use the Find Member button in the top right to look up an employee</p>
        <button class="btn btn-primary" type="button" @click="openMemberModal">Find Member</button>
      </section>

      <template v-else>
        <section class="member-banner card-panel">
          <div class="member-main">
            <div class="member-avatar" :style="{ background: avatarColor(selectedMember.last_name) }">{{ initials(selectedMember) }}</div>
            <div>
              <div class="member-title-row">
                <h2>{{ memberFullName }}</h2>
                <span class="status-chip">{{ selectedMember.status || 'REGULAR' }} · Active</span>
              </div>
              <div class="member-meta">
                <span class="mono">{{ selectedMember.member_no }}</span>
                <span>{{ selectedMember.position || 'Member' }}</span>
                <span>{{ selectedMember.company || 'CRS Holdings Corporation' }}</span>
                <span>{{ selectedMember.contact || selectedMember.phone || 'No contact' }}</span>
              </div>
            </div>
          </div>
          <div class="member-metrics">
            <div><span>Share Capital</span><strong class="text-red mono">{{ peso(shareCapital) }}</strong></div>
            <div><span>Monthly Salary</span><strong class="mono">{{ peso(selectedMember.monthly_salary || 0) }}</strong></div>
            <div><span>Outstanding Loan</span><strong class="text-green mono">{{ peso(outstandingLoan) }}</strong></div>
            <div><span>Eligible Up To</span><strong class="mono">{{ peso(eligibleAmount) }}</strong></div>
            <div><span>Eligibility</span><strong :class="['mono', eligibility.eligible ? 'text-green' : 'text-red']">{{ eligibility.eligible ? 'PASSED' : 'CHECK' }}</strong></div>
          </div>
        </section>

        <section class="application-grid">
          <div class="form-column">
            <section class="card-panel section-card">
              <div class="section-heading">
                <span class="section-icon">▥</span>
                <div>
                  <h3>Loan Type</h3>
                  <p>Each type has its own limits, fees, and interest rate</p>
                </div>
              </div>
              <div class="loan-type-grid">
                <button
                  v-for="lt in loanTypes"
                  :key="lt.id"
                  type="button"
                  :class="['loan-type-card', form.loan_type_id == lt.id && 'selected']"
                  @click="selectLoanType(lt)"
                >
                  <span class="check-dot">✓</span>
                  <strong>{{ lt.label }}</strong>
                  <span class="mono">{{ moneyRange(lt) }} · up to {{ lt.max_term }} mo</span>
                  <small>{{ rateLabel(lt) }} p.a. diminishing</small>
                </button>
              </div>
            </section>

            <section class="card-panel section-card">
              <div class="section-heading">
                <span class="section-icon">$</span>
                <div>
                  <h3>Amount, Term & Schedule</h3>
                  <p>Everything on the right updates as you type</p>
                </div>
              </div>
              <div class="section-body">
                <div class="form-group">
                  <label class="form-label">Loan Amount Requested *</label>
                  <div class="amount-input">
                    <span>₱</span>
                    <input v-model.number="form.amount" type="number" :min="selectedLoanType?.min_amount || 0" :max="selectedLoanType?.max_amount || 999999" step="1000" @input="recalc" />
                  </div>
                  <div v-if="selectedLoanType" class="hint">Range {{ peso(selectedLoanType.min_amount) }} - {{ peso(selectedLoanType.max_amount) }} · {{ selectedLoanType.min_term }}-{{ selectedLoanType.max_term }} mo</div>
                </div>

                <div class="form-row-2">
                  <div class="form-group">
                    <label class="form-label">Term (months) *</label>
                    <select v-model.number="form.term_months" class="form-select tall" @change="recalc">
                      <option v-for="term in termOptions" :key="term" :value="term">{{ term }} months</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Payment Frequency *</label>
                    <select v-model="form.frequency" class="form-select tall" @change="recalc">
                      <option value="monthly">Monthly</option>
                      <option value="bimonthly">Bi-Monthly (15th & 30th)</option>
                      <option value="weekly">Weekly</option>
                    </select>
                  </div>
                </div>

                <div class="form-row-2">
                  <div class="form-group">
                    <label class="form-label">Interest Rate</label>
                    <div class="locked-field mono">{{ rateLabel(selectedLoanType) }} p.a. <span>▣</span></div>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Release Date</label>
                    <input v-model="form.application_date" type="date" class="form-input tall" @change="recalc" />
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Purpose of Loan *</label>
                  <textarea v-model="form.purpose" class="form-textarea purpose-box" placeholder="e.g. Home appliance purchase, child's tuition, working capital..." />
                </div>
              </div>
            </section>

            <section class="card-panel section-card">
              <div class="section-heading fee-heading">
                <span class="section-icon">$</span>
                <div>
                  <h3>Fees & Upfront Deductions</h3>
                  <p>Deducted from release amount, not added to balance</p>
                </div>
                <router-link to="/settings" class="fee-settings-link">Settings</router-link>
                <span class="total-fee">Total fees: {{ peso(totalFees) }}</span>
              </div>
              <div class="fees-list">
                <label v-for="fee in fees" :key="fee.key" class="fee-row">
                  <input v-model="fee.enabled" type="checkbox" />
                  <div>
                    <strong>{{ fee.label }}</strong>
                    <span>{{ fee.note }}</span>
                  </div>
                  <b class="mono">{{ peso(feeAmount(fee)) }}</b>
                </label>
              </div>
            </section>

            <section class="card-panel section-card">
              <div class="section-heading">
                <span class="section-icon blue">♙</span>
                <div>
                  <h3>Co-Makers</h3>
                  <p>2 regular members required · both must sign the printed form</p>
                </div>
              </div>
              <div class="comaker-grid" style="display:block;padding:20px 24px;">
                <template v-if="savedLoanId">
                  <div v-for="cm in coMakersList" :key="cm.id"
                       style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid #eee;">
                    <span>{{ cm.first_name }} {{ cm.last_name }} · {{ cm.member_no }} <small style="color:#888">({{ cm.role }})</small></span>
                    <button class="btn btn-secondary btn-sm" type="button" @click="removeCoMaker(cm.id)">Remove</button>
                  </div>
                  <div v-if="coMakersList.length === 0" style="color:#888;font-size:0.9em;">No co-makers added yet.</div>
                  <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                    <select v-model="newCoMakerId" class="form-select" style="flex:1;">
                      <option value="" disabled>+ Select co-maker</option>
                      <option v-for="m in coMakersEligible" :key="m.id" :value="m.id">
                        {{ m.first_name }} {{ m.last_name }} · {{ m.member_no }}
                      </option>
                    </select>
                    <button class="btn btn-primary btn-sm" type="button" :disabled="!newCoMakerId" @click="addCoMaker()">Add</button>
                  </div>
                </template>
                <template v-else>
                  <div class="form-hint" style="color:#888;font-style:italic;">Save the loan first to add co-makers.</div>
                </template>
              </div>
            </section>
          </div>

          <aside class="summary-column">
            <section class="release-card">
              <div class="release-head">
                <span>Net Amount To Release</span>
                <strong class="mono">{{ peso(netRelease) }}</strong>
                <small>after fees · released on {{ displayDate(form.application_date) }}</small>
              </div>
              <div class="release-lines">
                <div><span>Loan Principal</span><b class="mono">{{ peso(form.amount) }}</b></div>
                <div v-for="fee in enabledFees" :key="fee.key"><span>- {{ fee.label }}</span><b class="mono danger">- {{ peso(feeAmount(fee)) }}</b></div>
              </div>
              <div class="net-line"><span>✓ Net released to member</span><b class="mono">{{ peso(netRelease) }}</b></div>
              <div class="payable-lines">
                <div><span>Principal <small>(repaid)</small></span><b class="mono">{{ peso(form.amount) }}</b></div>
                <div><span>+ Total Interest <small>({{ rateLabel(selectedLoanType) }} p.a. diminishing)</small></span><b class="mono">{{ peso(calc?.totalInterest || 0) }}</b></div>
                <div class="total"><span>Total Amount Payable</span><b class="mono">{{ peso(calc?.totalPayment || form.amount) }}</b></div>
              </div>
              <div class="date-cards">
                <div><span>First Deduction</span><strong class="mono">{{ firstDeductionLabel }}</strong><small>{{ peso(calc?.firstPayment || 0) }}</small></div>
                <div><span>End Of Deduction</span><strong class="mono">{{ endDate || '-' }}</strong><small>{{ peso(calc?.lastPayment || 0) }}</small></div>
              </div>
            </section>

            <section class="schedule-card card-panel">
              <div class="schedule-head">
                <div>
                  <h3>Amortization Schedule</h3>
                  <p>{{ calc?.nPeriods || 0 }} periods · {{ freqLabel(form.frequency) }}</p>
                </div>
                <button class="ghost-mini">↓ CSV</button>
              </div>
              <div class="schedule-table-wrap">
                <table class="mini-table">
                  <thead><tr><th>#</th><th>Date</th><th>Principal</th><th>Interest</th><th>Amount Due</th></tr></thead>
                  <tbody>
                    <tr v-for="row in datedSchedule.slice(0, 12)" :key="row.period">
                      <td class="mono">{{ String(row.period).padStart(2, '0') }}</td>
                      <td>{{ row.date }}</td>
                      <td class="mono">{{ number(row.principal) }}</td>
                      <td class="mono">{{ number(row.interest) }}</td>
                      <td class="mono strong">{{ number(row.payment) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="eligibility-panel">
                <div class="eligibility-title">
                  <span :class="eligibility.eligible ? 'ok' : 'warn'">{{ eligibility.eligible ? '✓' : '!' }}</span>
                  <strong>Eligibility Check</strong>
                  <em>{{ eligibility.eligible ? 'Ready for packet generation' : 'Needs review before submission' }}</em>
                </div>
                <div class="validation-list">
                  <div :class="eligibility.withinLimit ? 'ok' : 'warn'">{{ eligibility.withinLimit ? '✓' : '!' }} Loan amount within eligible limit</div>
                  <div :class="eligibility.dtiOk ? 'ok' : 'warn'">{{ eligibility.dtiOk ? '✓' : '!' }} DTI ratio {{ dtiRatio.toFixed(1) }}%</div>
                  <div :class="eligibility.coMakersOk ? 'ok' : 'warn'">{{ eligibility.coMakersOk ? '✓' : '!' }} {{ coMakerCount }} of 2 co-makers attached</div>
                </div>
              </div>
              <div class="packet-note">
                <strong>Printable Packet</strong>
                <span>Generates a 5-page PDF: Loan Application, Authority to Deduct, and full amortization. Member, co-makers, HR & COOP officer signatures required before re-upload.</span>
              </div>
            </section>

            <section class="pdf-card card-panel">
              <div class="pdf-header">
                <div>
                  <h3>PDF Preview · Page {{ previewPage }} — {{ previewPage === 1 ? 'Loan Application' : previewPage === 2 ? 'Authority to Deduct' : 'Schedule' }}</h3>
                  <p>Exactly what will be printed & signed</p>
                </div>
                <div class="page-nav">
                  <button v-for="n in 5" :key="n" :class="['page-btn', previewPage === n && 'active']" @click="previewPage = n">{{ n }}</button>
                </div>
              </div>
              <div class="pdf-paper">
                <div class="unsigned-stamp">UNSIGNED</div>
                <div class="pdf-title">
                  <strong>CRS HOLDINGS CORPORATIONS · EMPLOYEES CREDIT COOPERATIVE</strong>
                  <span>A.C. Cortes Ave., Alang-alang, Mandaue City, Cebu · CDA Reg. No. ---</span>
                  <h4>{{ selectedLoanType?.label || 'Loan' }} · {{ previewPage === 1 ? 'Application Form' : previewPage === 2 ? 'Authority to Deduct' : 'Amortization Schedule' }}</h4>
                </div>
                <template v-if="previewPage === 1">
                  <dl class="pdf-dl">
                    <dt>Member ID</dt><dd>{{ selectedMember.member_no }}</dd>
                    <dt>Name</dt><dd>{{ memberFullName }}</dd>
                    <dt>Address</dt><dd>{{ selectedMember.address || '-' }}</dd>
                    <dt>Contact / Email</dt><dd>{{ selectedMember.contact || '-' }} · {{ selectedMember.email || '-' }}</dd>
                    <dt>Company</dt><dd>{{ selectedMember.company || '-' }}</dd>
                    <dt>Status / Position</dt><dd>{{ selectedMember.status || 'REGULAR' }} · {{ selectedMember.position || '-' }}</dd>
                    <dt>Loan Amount</dt><dd>{{ peso(form.amount) }}</dd>
                    <dt>Term</dt><dd>{{ form.term_months }} months · {{ freqLabel(form.frequency) }}</dd>
                    <dt>Interest Rate</dt><dd>{{ rateLabel(selectedLoanType) }} p.a. diminishing</dd>
                    <dt>Less: Total Fees</dt><dd>{{ peso(totalFees) }}</dd>
                    <dt>Net Released</dt><dd>{{ peso(netRelease) }}</dd>
                    <dt>Release Date</dt><dd>{{ displayDate(form.application_date) }}</dd>
                    <dt>Total Payable</dt><dd>{{ peso(calc?.totalPayment || 0) }}</dd>
                  </dl>
                  <div class="signature-grid">
                    <span>Borrower Signature</span><span>Date</span><span>Co-Maker 1</span><span>Co-Maker 2</span><span>HR Manager (Approved)</span><span>COOP Manager / Loan Officer</span>
                  </div>
                </template>
                <template v-else>
                  <table class="pdf-schedule"><tbody><tr v-for="row in datedSchedule.slice(0, 12)" :key="row.period"><td>{{ row.period }}</td><td>{{ row.date }}</td><td>{{ peso(row.payment) }}</td></tr></tbody></table>
                </template>
              </div>
            </section>
          </aside>
        </section>
      </template>
    </main>

    <footer v-if="selectedMember" class="bottom-bar">
      <div class="autosave">✓ Draft auto-saved <span>·</span> Generating PDF takes ~3s</div>
      <div class="bottom-actions">
        <button class="btn btn-secondary" type="button" @click="clearMember">Cancel</button>
        <button class="btn btn-secondary" type="button" @click="saveLoan('DRAFT')" :disabled="saving">Save & Continue Later</button>
        <button class="btn btn-primary" type="button" @click="saveLoan('PENDING')" :disabled="saving || !canSubmit">▤ Generate & Print 5-page Packet</button>
      </div>
    </footer>

    <div v-if="memberModalOpen" class="find-overlay" @click.self="memberModalOpen = false">
      <div class="find-modal">
        <div class="find-head">
          <h2>Find Member</h2>
          <button type="button" @click="memberModalOpen = false">×</button>
        </div>
        <div class="find-body">
          <div class="modal-search">
            <span>⌕</span>
            <input v-model.trim="memberSearch" type="search" placeholder="Search by name, member ID, or company..." autofocus />
          </div>
          <div class="modal-results">
            <button v-for="m in memberSearchResults" :key="m.id" class="modal-member" type="button" @click="selectMember(m)">
              <div class="member-avatar small" :style="{ background: avatarColor(m.last_name) }">{{ initials(m) }}</div>
              <div>
                <strong>{{ m.first_name }} {{ m.last_name }}</strong>
                <span class="mono">{{ m.member_no }} · {{ m.company || m.department || 'CRS Holdings Corporation' }}</span>
              </div>
              <em>ACTIVE</em>
            </button>
            <div v-if="!memberSearchResults.length" class="no-results">No members found</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../composables/useApi'
const SETTINGS_KEY = 'crs-coop-preview-settings'
import { computeSchedule, peso, freqLabel } from '../composables/useLoanCalc'
import { useToast } from '../composables/useToast'

const route = useRoute()
const { success, error } = useToast()

const memberList = ref([])
const loanTypes = ref([])
const selectedMember = ref(null)
const selectedLoanType = ref(null)
const memberSearch = ref('')
const memberModalOpen = ref(false)
const loadingMembers = ref(false)
const previewPage = ref(1)
const saving = ref(false)
const calc = ref(null)
const savedLoanNo = ref('')
const coMakersList = ref([])
const newCoMakerId = ref('')
const savedLoanId = ref(null)

const form = ref({
  member_id: null,
  loan_type_id: null,
  amount: 10000,
  term_months: 12,
  frequency: 'bimonthly',
  purpose: '',
  co_maker_1_id: '',
  co_maker_2_id: '',
  first_due_date: '',
  application_date: new Date().toISOString().slice(0, 10),
})

const fees = ref(defaultLoanFees())

const steps = [
  { no: 1, label: 'Member Selected' },
  { no: 2, label: 'Loan Type' },
  { no: 3, label: 'Amount, Fees & Terms' },
  { no: 4, label: 'Co-Makers' },
  { no: 5, label: 'Generate & Print' },
  { no: 6, label: 'Upload Signed Copy' },
]

const memberFullName = computed(() => selectedMember.value ? `${selectedMember.value.first_name} ${selectedMember.value.last_name}` : '')
const coMakers = computed(() => memberList.value.filter(m => m.id !== selectedMember.value?.id))
const coMakerCount = computed(() => [form.value.co_maker_1_id, form.value.co_maker_2_id].filter(Boolean).length)
const coMakersEligible = computed(() =>
  memberList.value.filter(m =>
    m.id !== selectedMember.value?.id &&
    !coMakersList.value.some(cm => cm.member_id === m.id)
  )
)
const shareCapital = computed(() => Number(selectedMember.value?.share_capital || selectedMember.value?.capital_balance || 15000))
const outstandingLoan = computed(() => Number(selectedMember.value?.outstanding_balance || selectedMember.value?.outstanding_loan || 0))
const eligibleAmount = computed(() => Math.max(0, Number(selectedMember.value?.monthly_salary || 0) * 4.2857 - outstandingLoan.value))
const eligibility = computed(() => {
  const amount = Number(form.value.amount || 0)
  const hasMember = Boolean(selectedMember.value)
  const withinLimit = hasMember && amount > 0 && amount <= eligibleAmount.value
  const salary = Number(selectedMember.value?.monthly_salary || 0)
  const payment = calc.value?.firstPayment || 0
  const dti = salary ? (payment / salary) * 100 : 0
  const dtiOk = hasMember && dti <= 40
  const coMakersOk = coMakerCount.value >= 2
  const messages = []
  if (!withinLimit) messages.push(`Requested amount exceeds eligible limit of ${peso(eligibleAmount.value)}`)
  if (!dtiOk) messages.push(`DTI ratio ${dti.toFixed(1)}% exceeds 40% threshold`)
  if (!coMakersOk) messages.push(`${coMakerCount.value} of 2 co-makers attached`)
  return { eligible: withinLimit && dtiOk && coMakersOk, withinLimit, dtiOk, coMakersOk, messages }
})
const applicationNo = computed(() => {
  if (savedLoanNo.value) return savedLoanNo.value
  const year = new Date().getFullYear()
  const memberPart = String(selectedMember.value?.id || selectedMember.value?.member_no || 0).replace(/\D/g, '').slice(-4).padStart(4, '0')
  const typePart = String(form.value.loan_type_id || 0).padStart(2, '0')
  return `LA-${year}-${memberPart}${typePart}`
})
const enabledFees = computed(() => fees.value.filter(f => f.enabled))
const totalFees = computed(() => enabledFees.value.reduce((sum, fee) => sum + feeAmount(fee), 0))
const netRelease = computed(() => Math.max(0, Number(form.value.amount || 0) - totalFees.value))
const canSubmit = computed(() => selectedMember.value && selectedLoanType.value && form.value.amount > 0 && form.value.purpose.trim() && eligibility.value.eligible)
const dtiRatio = computed(() => {
  const salary = Number(selectedMember.value?.monthly_salary || 0)
  const payment = calc.value?.firstPayment || 0
  return salary ? (payment / salary) * 100 : 0
})
const activeStep = computed(() => {
  if (!selectedMember.value) return 1
  if (!selectedLoanType.value) return 2
  if (!form.value.amount || !form.value.purpose.trim()) return 3
  if (coMakerCount.value < 2) return 4
  return 5
})
const termOptions = computed(() => {
  const min = selectedLoanType.value?.allow_one_month_term ? 1 : Number(selectedLoanType.value?.min_term || 3)
  const max = Number(selectedLoanType.value?.max_term || 48)
  const out = []
  if (min === 1) out.push(1)
  const start = Math.max(3, min)
  for (let term = start; term <= max; term += 3) out.push(term)
  if (!out.includes(form.value.term_months)) out.push(form.value.term_months)
  return out.sort((a, b) => a - b)
})
const firstDeductionLabel = computed(() => datedSchedule.value[0]?.date || '-')
const endDate = computed(() => datedSchedule.value.at(-1)?.date || '-')
const datedSchedule = computed(() => {
  if (!calc.value) return []
  return calc.value.schedule.map(row => ({ ...row, date: scheduleDate(row.period) }))
})
const memberSearchResults = computed(() => {
  const query = memberSearch.value.toLowerCase()
  const rows = query
    ? memberList.value.filter(m => [m.member_no, m.first_name, m.middle_name, m.last_name, `${m.first_name} ${m.last_name}`, m.company, m.department, m.position, m.status].some(value => String(value || '').toLowerCase().includes(query)))
    : memberList.value
  return rows.slice(0, 8)
})


function defaultLoanFees() {
  return [
    { key: 'service', label: 'Service Fee', note: '2% of principal', type: 'percent', value: 0.02, enabled: true },
    { key: 'cbu', label: 'Capital Build-Up (CBU)', note: '1% of principal · added to share capital', type: 'percent', value: 0.01, enabled: true },
    { key: 'notarial', label: 'Notarial Fee', note: 'Fixed PHP 200', type: 'fixed', value: 200, enabled: true },
    { key: 'mri', label: 'Loan Insurance (MRI)', note: '0.5% of principal per year', type: 'mri', value: 0.005, enabled: true },
    { key: 'processing', label: 'Processing Fee', note: 'Fixed PHP 100', type: 'fixed', value: 100, enabled: false },
  ]
}

function loadFeeSettings() {
  try {
    const saved = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null')
    if (saved?.loanFees?.length) fees.value = saved.loanFees.map(fee => ({ ...fee, value: Number(fee.value || 0), enabled: fee.enabled !== false }))
  } catch {}
}

const avatarColors = ['#C0392B', '#2F65B0', '#3F8F55', '#7A3EB1', '#C4532C', '#8B2B24']
const avatarColor = name => avatarColors[Math.abs(name?.charCodeAt(0) || 0) % avatarColors.length]
const initials = m => `${m?.first_name?.[0] || ''}${m?.last_name?.[0] || ''}`.toUpperCase() || 'CR'
const number = n => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function annualRate(lt = selectedLoanType.value) {
  const rate = Number(lt?.annual_rate)
  return Number.isFinite(rate) ? rate : 0.12
}

function rateLabel(lt) {
  return `${(annualRate(lt) * 100).toFixed(2)}%`
}

function feeAmount(fee) {
  const amount = Number(form.value.amount || 0)
  if (fee.type === 'percent') return amount * fee.value
  if (fee.type === 'mri') return amount * fee.value * Math.max(1, Number(form.value.term_months || 12) / 12)
  return Number(fee.value || 0)
}

function moneyRange(lt) {
  return `${shortPeso(lt.min_amount)}-${shortPeso(lt.max_amount)}`
}

function shortPeso(n) {
  return `₱${Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`
}

function displayDate(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' }) : '-'
}

function scheduleDate(period) {
  const base = new Date(`${form.value.application_date || new Date().toISOString().slice(0, 10)}T00:00:00`)
  const days = form.value.frequency === 'weekly' ? 7 * period : form.value.frequency === 'bimonthly' ? 15 * period : 30 * period
  base.setDate(base.getDate() + days)
  return base.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' })
}

function recalc() {
  if (!selectedLoanType.value) return
  const min = Number(selectedLoanType.value.min_amount || 0)
  const max = Number(selectedLoanType.value.max_amount || 999999)
  const minTerm = selectedLoanType.value.allow_one_month_term ? 1 : Number(selectedLoanType.value.min_term || 1)
  form.value.amount = Math.min(max, Math.max(min, Number(form.value.amount || min)))
  form.value.term_months = Math.min(Number(selectedLoanType.value.max_term || 48), Math.max(minTerm, Number(form.value.term_months || 1)))
  calc.value = computeSchedule({
    principal: Number(form.value.amount || 0),
    termMonths: Number(form.value.term_months || 1),
    frequency: form.value.frequency,
    annualRate: annualRate(),
  })
}

function selectLoanType(lt) {
  selectedLoanType.value = lt
  form.value.loan_type_id = lt.id
  recalc()
}

function openMemberModal() {
  memberModalOpen.value = true
}

function selectMember(m) {
  selectedMember.value = m
  form.value.member_id = m.id
  memberSearch.value = `${m.first_name} ${m.last_name} · ${m.member_no}`
  memberModalOpen.value = false
}

function clearMember() {
  selectedMember.value = null
  form.value.member_id = null
  memberSearch.value = ''
  memberModalOpen.value = true
}

async function loadCoMakers() {
  if (!savedLoanId.value) return
  try {
    coMakersList.value = await api.getCoMakers(savedLoanId.value)
  } catch (e) {
    error(e.message || 'Could not load co-makers.')
  }
}

async function addCoMaker() {
  if (!newCoMakerId.value || !savedLoanId.value) return
  try {
    await api.createCoMaker({ loan_id: savedLoanId.value, member_id: Number(newCoMakerId.value) })
    newCoMakerId.value = ''
    await loadCoMakers()
    success('Co-maker added.')
  } catch (e) {
    error(e.message || 'Could not add co-maker.')
  }
}

async function removeCoMaker(id) {
  try {
    await api.deleteCoMaker(id)
    await loadCoMakers()
    success('Co-maker removed.')
  } catch (e) {
    error(e.message || 'Could not remove co-maker.')
  }
}

async function fetchMembers() {
  loadingMembers.value = true
  try {
    memberList.value = await api.getMembers()
  } catch (e) {
    error(e.message || 'Could not load members')
  } finally {
    loadingMembers.value = false
  }
}

async function saveLoan(status) {
  if (!selectedMember.value) return error('Select a member first')
  if (!form.value.loan_type_id) return error('Select a loan type')
  if (status === 'PENDING' && !form.value.purpose.trim()) return error('Enter the purpose of loan')
  saving.value = true
  try {
    const payload = { ...form.value, status, annual_rate: annualRate(), notes: `Application No: ${applicationNo.value}` }
    const result = await api.createLoan(payload)
    savedLoanNo.value = result.loan_no || applicationNo.value
    savedLoanId.value = result.id || null
    if (savedLoanId.value) await loadCoMakers()
    success(`Loan ${savedLoanNo.value} saved as ${status}!`)
  } catch (e) {
    error(e.message || 'Could not save loan')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loadFeeSettings()
  await fetchMembers()
  const types = await api.getLoanTypes()
  loanTypes.value = types
  const defaultType = types.find(t => t.code === 'commodity') || types[0]
  if (defaultType) selectLoanType(defaultType)
  if (route.query.member_id) {
    const m = memberList.value.find(x => x.id == route.query.member_id)
    if (m) selectMember(m)
  }
})
</script>

<style scoped>
.loan-app-page { height:100%; display:flex; flex-direction:column; background:#F6F7FB; color:#20232B; overflow:hidden; }
.loan-topbar { height:64px; flex-shrink:0; padding:0 28px; display:flex; align-items:center; justify-content:space-between; background:#fff; border-bottom:1px solid #E4E7EE; box-shadow:0 1px 8px rgba(16,24,40,.05); }
.crumbs { display:flex; align-items:center; gap:12px; font-size:15px; color:#8B90A1; }
.crumbs strong { color:#171A22; font-size:20px; }
.top-search { display:inline-flex; align-items:center; gap:8px; height:36px; padding:0 14px; border:1px solid #D8DCE5; border-radius:7px; background:#fff; color:#2F333D; font-weight:700; cursor:pointer; }
.loan-scroll { flex:1; overflow:auto; padding:24px 28px 96px; }
.hero-row { display:flex; justify-content:space-between; gap:18px; align-items:flex-start; margin-bottom:16px; }
h1 { font-size:28px; line-height:1.1; margin:0 0 10px; color:#171A22; }
.ref-row { display:flex; align-items:center; gap:12px; color:#8C91A2; font-size:15px; flex-wrap:wrap; }
.ref-pill { border:1px solid #E5E8EF; background:#fff; border-radius:6px; padding:5px 10px; color:#565C6B; }
.draft-chip { background:#FFF7E8; color:#9B6A12; border-radius:999px; padding:4px 11px; font-weight:800; font-size:12px; }
.hero-actions { display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; }
.stepper-card { height:96px; background:#fff; border:1px solid #E3E7EF; border-radius:12px; box-shadow:0 8px 22px rgba(16,24,40,.05); display:grid; grid-template-columns:repeat(6, 1fr); align-items:center; padding:18px 36px; margin-bottom:18px; }
.step-item { position:relative; display:flex; flex-direction:column; align-items:center; gap:8px; min-width:0; color:#8C91A2; }
.step-line { position:absolute; height:2px; background:#E3E7EF; left:-50%; right:50%; top:14px; z-index:0; }
.step-item.done .step-line, .step-item.active .step-line { background:#E75D2E; }
.step-dot { width:32px; height:32px; border-radius:50%; background:#fff; border:2px solid #DDE1EA; display:flex; align-items:center; justify-content:center; font-weight:900; position:relative; z-index:1; }
.step-item.done .step-dot { background:#E75D2E; border-color:#E75D2E; color:#fff; }
.step-item.active .step-dot { border-color:#8F241E; color:#8F241E; box-shadow:0 0 0 4px #FFF1F0; }
.step-label { font-weight:800; font-size:13px; text-align:center; white-space:nowrap; }
.step-item.done .step-label, .step-item.active .step-label { color:#E75D2E; }
.begin-state { min-height:520px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; gap:14px; }
.begin-icon { width:84px; height:84px; border-radius:50%; background:#FFF1F0; color:#922821; display:flex; align-items:center; justify-content:center; font-size:42px; }
.begin-state h2 { font-size:24px; margin:0; }
.begin-state p { color:#8C91A2; font-size:16px; margin-bottom:16px; }
.card-panel { background:#fff; border:1px solid #E3E7EF; border-radius:12px; box-shadow:0 8px 22px rgba(16,24,40,.05); overflow:hidden; }
.member-banner { display:flex; justify-content:space-between; align-items:center; gap:22px; padding:22px 26px; margin-bottom:18px; }
.member-main { display:flex; align-items:center; gap:18px; min-width:0; }
.member-avatar { width:64px; height:64px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:24px; flex-shrink:0; }
.member-avatar.small { width:44px; height:44px; font-size:15px; }
.member-title-row { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.member-title-row h2 { margin:0; font-size:22px; }
.status-chip { background:#E8F6EC; color:#3F8F55; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:900; }
.member-meta { display:flex; gap:16px; flex-wrap:wrap; color:#8B90A1; font-size:14px; margin-top:4px; }
.member-metrics { display:grid; grid-template-columns:repeat(5, auto); gap:28px; border-left:1px solid #E7EAF1; padding-left:24px; }
.member-metrics div { display:flex; flex-direction:column; gap:4px; }
.member-metrics span { text-transform:uppercase; color:#8B90A1; letter-spacing:.7px; font-size:11px; font-weight:900; }
.member-metrics strong { font-size:17px; }
.application-grid { display:grid; grid-template-columns:minmax(560px, 1fr) minmax(440px, 520px); gap:20px; align-items:start; }
.form-column { display:flex; flex-direction:column; gap:18px; }
.summary-column { display:flex; flex-direction:column; gap:18px; position:sticky; top:0; }
.section-heading { display:flex; align-items:center; gap:14px; padding:20px 24px; border-bottom:1px solid #E7EAF1; }
.section-heading h3, .schedule-head h3, .pdf-header h3 { margin:0; color:#20232B; font-size:18px; }
.section-heading p, .schedule-head p, .pdf-header p { margin:2px 0 0; color:#8B90A1; font-size:14px; }
.section-icon { color:#9A2A23; font-size:20px; width:20px; text-align:center; }
.section-icon.blue { color:#2F65B0; }
.section-body { padding:22px 24px 24px; display:flex; flex-direction:column; gap:18px; }
.loan-type-grid { padding:20px 24px 24px; display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; }
.loan-type-card { min-height:116px; text-align:left; border:1px solid #E0E4EC; background:#fff; border-radius:10px; padding:18px 18px; display:flex; flex-direction:column; gap:7px; position:relative; cursor:pointer; color:#222631; }
.loan-type-card strong { font-size:16px; }
.loan-type-card span:not(.check-dot), .loan-type-card small { color:#8B90A1; font-size:12px; }
.loan-type-card.selected { border-color:#972A23; background:#FFF5F2; box-shadow:inset 0 0 0 1px #972A23; }
.check-dot { display:none; position:absolute; right:16px; top:16px; width:24px; height:24px; border-radius:50%; background:#972A23; color:#fff; align-items:center; justify-content:center; font-size:12px; }
.loan-type-card.selected .check-dot { display:flex; }
.amount-input { height:54px; display:grid; grid-template-columns:52px 1fr; border:1px solid #DDE2EA; border-radius:9px; overflow:hidden; background:#fff; }
.amount-input span { display:flex; align-items:center; justify-content:center; background:#F7F8FB; border-right:1px solid #DDE2EA; color:#8B90A1; font-weight:900; }
.amount-input input { border:0; outline:0; padding:0 16px; font:700 18px var(--font-mono); color:#20232B; }
.hint { color:#8B90A1; font-size:13px; }
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
.tall { min-height:52px; font-size:16px; }
.locked-field { min-height:52px; border:1px solid #DDE2EA; border-radius:9px; background:#F7F8FB; color:#8B90A1; display:flex; align-items:center; justify-content:space-between; padding:0 14px; font-weight:900; }
.purpose-box { min-height:86px; font-size:16px; }
.fee-heading { position:relative; }
.fee-settings-link { margin-left:auto; color:#8F241E; font-weight:900; text-decoration:none; border:1px solid #F0D2C8; background:#FFF5F2; padding:7px 12px; border-radius:999px; }
.total-fee { margin-left:0; background:#FFF8E8; color:#A07116; border-radius:999px; padding:7px 13px; font-weight:900; }
.fees-list { padding:22px 24px; display:flex; flex-direction:column; gap:12px; }
.fee-row { min-height:72px; display:grid; grid-template-columns:24px 1fr auto; align-items:center; gap:14px; padding:14px; border:1.5px solid #E75D2E; background:#FFF5F2; border-radius:9px; cursor:pointer; }
.fee-row input { accent-color:#8F241E; width:18px; height:18px; }
.fee-row div { display:flex; flex-direction:column; }
.fee-row strong { font-size:16px; }
.fee-row span { color:#8B90A1; }
.comaker-grid { padding:24px; display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.release-card { border-radius:12px; overflow:hidden; background:#fff; box-shadow:0 8px 22px rgba(16,24,40,.08); border:1px solid #E3E7EF; }
.release-head { padding:24px 26px; background:linear-gradient(135deg, #8F241E, #741B17); color:#fff; display:flex; flex-direction:column; gap:8px; }
.release-head span { text-transform:uppercase; letter-spacing:1.4px; font-size:12px; font-weight:900; opacity:.78; }
.release-head strong { font-size:40px; line-height:1; }
.release-head small { opacity:.78; font-size:14px; }
.release-lines, .payable-lines { padding:22px 26px; display:flex; flex-direction:column; gap:16px; }
.release-lines div, .payable-lines div { display:flex; justify-content:space-between; gap:12px; align-items:center; }
.danger { color:#9A2A23; }
.net-line { display:flex; justify-content:space-between; align-items:center; padding:18px 26px; background:#EAF7EF; color:#3F7E51; border-top:2px solid #3F7E51; border-bottom:2px solid #3F7E51; font-weight:900; }
.payable-lines { background:#FAFBFD; border-bottom:1px solid #E7EAF1; }
.payable-lines small { color:#B1B6C4; }
.payable-lines .total { border-top:1px solid #E3E7EF; padding-top:14px; font-weight:900; }
.date-cards { padding:20px 26px; display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.date-cards div { background:#F7F8FB; border:1px solid #E3E7EF; border-radius:9px; padding:14px; display:flex; flex-direction:column; gap:4px; }
.date-cards span { text-transform:uppercase; color:#8B90A1; font-size:11px; font-weight:900; }
.date-cards small { color:#8B90A1; }
.schedule-head, .pdf-header { padding:18px 22px; display:flex; justify-content:space-between; gap:14px; align-items:center; border-bottom:1px solid #E7EAF1; }
.ghost-mini { border:0; background:transparent; color:#8B90A1; font-weight:900; cursor:pointer; }
.schedule-table-wrap { max-height:318px; overflow:auto; }
.mini-table { width:100%; border-collapse:collapse; font-size:12px; }
.mini-table th { position:sticky; top:0; background:#F7F8FB; color:#8B90A1; text-transform:uppercase; font-size:10px; letter-spacing:.7px; padding:10px 12px; text-align:right; border-bottom:1px solid #E3E7EF; }
.mini-table th:nth-child(1), .mini-table th:nth-child(2), .mini-table td:nth-child(1), .mini-table td:nth-child(2) { text-align:left; }
.mini-table td { padding:10px 12px; text-align:right; border-bottom:1px solid #E9ECF2; color:#5B6170; }
.mini-table .strong { color:#20232B; font-weight:900; }
.eligibility-panel { border-top:1px solid #E7EAF1; background:#fff; }
.eligibility-title { display:grid; grid-template-columns:26px 1fr; gap:2px 8px; align-items:center; padding:14px 22px 4px; }
.eligibility-title span { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:900; }
.eligibility-title .ok { background:#E5F5EA; color:#3F8F55; }
.eligibility-title .warn { background:#FFF3D8; color:#986A17; }
.eligibility-title strong { font-size:15px; }
.eligibility-title em { grid-column:2; font-style:normal; color:#8B90A1; font-size:12px; }
.validation-list { padding:10px 22px 14px; display:flex; flex-direction:column; gap:8px; }
.validation-list div { font-weight:700; color:#5B6170; }
.validation-list .ok::first-letter { color:#3F8F55; }
.validation-list .warn { color:#986A17; }
.packet-note { background:#FFF8E8; color:#986A17; padding:18px 22px; display:flex; flex-direction:column; gap:4px; line-height:1.45; }
.pdf-card { margin-bottom:20px; }
.page-nav { display:flex; gap:7px; }
.page-btn { width:32px; height:30px; border-radius:7px; border:1px solid #DDE2EA; background:#fff; color:#606675; font-weight:900; cursor:pointer; }
.page-btn.active { background:#8F241E; border-color:#8F241E; color:#fff; }
.pdf-paper { margin:22px; min-height:520px; background:#fff; border:1px solid #E3E7EF; border-radius:8px; padding:28px 32px; position:relative; font-family:Georgia, serif; color:#2A2A2A; }
.unsigned-stamp { position:absolute; right:28px; top:22px; color:#C96F61; border:2px solid #C96F61; padding:3px 14px; border-radius:2px; transform:rotate(-4deg); font:700 12px var(--font-mono); letter-spacing:1px; }
.pdf-title { text-align:center; border-bottom:2px solid #20232B; padding-bottom:16px; margin-bottom:18px; display:flex; flex-direction:column; gap:4px; }
.pdf-title strong { color:#9A2A23; font-size:13px; }
.pdf-title span { color:#8B90A1; font-size:11px; }
.pdf-title h4 { margin:10px 0 0; font-size:17px; }
.pdf-dl { display:grid; grid-template-columns:140px 1fr; gap:0; font:700 12px var(--font-mono); }
.pdf-dl dt, .pdf-dl dd { padding:7px 0; border-bottom:1px dotted #D5D9E2; }
.pdf-dl dt { color:#8B90A1; text-transform:uppercase; }
.signature-grid { margin-top:22px; display:grid; grid-template-columns:1fr 1fr; gap:26px; text-align:center; color:#8B90A1; text-transform:uppercase; font:700 11px var(--font-sans); }
.signature-grid span::before { content:''; display:block; border-top:1px solid #20232B; margin-bottom:10px; }
.pdf-schedule { width:100%; border-collapse:collapse; font:700 12px var(--font-mono); }
.pdf-schedule td { border-bottom:1px dotted #D5D9E2; padding:8px; }
.bottom-bar { height:64px; flex-shrink:0; background:rgba(255,255,255,.97); border-top:1px solid #E3E7EF; box-shadow:0 -8px 20px rgba(16,24,40,.05); padding:0 28px; display:flex; align-items:center; justify-content:space-between; gap:16px; }
.autosave { color:#4E8D5D; font-weight:700; }
.autosave span { color:#B7BCC8; padding:0 8px; }
.bottom-actions { display:flex; align-items:center; gap:10px; }
.find-overlay { position:fixed; inset:0; background:rgba(0,0,0,.62); z-index:1100; display:flex; align-items:center; justify-content:center; padding:24px; }
.find-modal { width:min(720px, 100%); max-height:78vh; background:#fff; border-radius:14px; box-shadow:0 24px 80px rgba(0,0,0,.28); overflow:hidden; display:flex; flex-direction:column; }
.find-head { padding:26px 32px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E3E7EF; }
.find-head h2 { margin:0; font-size:24px; }
.find-head button { border:0; background:transparent; color:#8B90A1; font-size:28px; cursor:pointer; }
.find-body { padding:28px 32px 32px; overflow:auto; }
.modal-search { height:50px; border:1px solid #D4D9E3; border-radius:9px; display:grid; grid-template-columns:42px 1fr; align-items:center; margin-bottom:18px; }
.modal-search span { text-align:center; color:#8B90A1; }
.modal-search input { border:0; outline:0; font-size:16px; }
.modal-results { display:flex; flex-direction:column; gap:6px; max-height:420px; overflow:auto; }
.modal-member { border:0; background:#F8F9FC; border-radius:9px; padding:14px 16px; display:grid; grid-template-columns:48px 1fr auto; align-items:center; gap:12px; text-align:left; cursor:pointer; }
.modal-member:hover { background:#FFF1F0; }
.modal-member div:nth-child(2) { display:flex; flex-direction:column; gap:2px; }
.modal-member strong { font-size:16px; color:#20232B; }
.modal-member span { color:#8B90A1; font-size:13px; }
.modal-member em { font-style:normal; background:#DDF4E5; color:#42A05B; border-radius:999px; padding:5px 12px; font-weight:900; font-size:12px; }
.no-results { text-align:center; color:#8B90A1; padding:24px; }
@media (max-width: 1280px) { .application-grid { grid-template-columns:1fr; } .summary-column { position:static; } .member-banner { align-items:flex-start; flex-direction:column; } .member-metrics { width:100%; border-left:0; padding-left:0; grid-template-columns:repeat(4, 1fr); } }
@media (max-width: 900px) { .loan-topbar, .hero-row, .bottom-bar { flex-direction:column; align-items:flex-start; height:auto; padding:14px 18px; } .loan-scroll { padding:18px 14px 120px; } .stepper-card { overflow-x:auto; grid-template-columns:repeat(6, 150px); padding:16px; } .loan-type-grid, .form-row-2, .comaker-grid, .date-cards, .member-metrics { grid-template-columns:1fr; } .hero-actions, .bottom-actions { width:100%; } .hero-actions .btn, .bottom-actions .btn { flex:1; justify-content:center; } }
@media (max-width: 640px) { .member-main { align-items:flex-start; } .member-meta { flex-direction:column; gap:4px; } .release-head strong { font-size:30px; } .pdf-paper { margin:12px; padding:20px 16px; } .pdf-dl { grid-template-columns:1fr; } }
</style>
