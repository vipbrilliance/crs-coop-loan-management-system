<template>
  <div class="packet-wrap">
    <header class="view-header no-print">
      <div>
        <div class="view-title serif">PDF Loan Packet</div>
        <div class="view-sub">Generate print-ready application, authority, note, schedule, and disclosure pages</div>
      </div>
      <div class="header-actions">
        <select v-model="statusFilter" class="form-select" @change="loadLoans">
          <option value="">All loans</option>
          <option value="ACTIVE">Active</option>
          <option value="APPROVED">Approved</option>
          <option value="PENDING">Pending</option>
        </select>
        <button class="btn btn-secondary" @click="loadLoans">Refresh</button>
        <button class="btn btn-primary" :disabled="!selectedLoan" @click="printPacket">Print / Save PDF</button>
      </div>
    </header>

    <main class="packet-body">
      <aside class="packet-sidebar no-print">
        <div class="panel-title">Loan Search</div>
        <div class="packet-search">
          <input v-model.trim="searchTerm" class="form-input" type="search" placeholder="Search loan no, member, or type" />
        </div>
        <div class="packet-loan-list">
          <button
            v-for="loan in filteredLoans"
            :key="loan.id"
            :class="['packet-loan', selectedLoan?.id === loan.id && 'active']"
            @click="selectLoan(loan)"
          >
            <div>
              <div class="row-title">{{ loan.loan_no }}</div>
              <div class="row-sub">{{ loan.first_name }} {{ loan.last_name }} · {{ loan.member_no }}</div>
              <div class="row-sub">{{ loan.loan_type_label }} · {{ peso(loan.amount) }}</div>
            </div>
            <span :class="`badge badge-${loan.status.toLowerCase()}`">{{ loan.status }}</span>
          </button>
          <div v-if="!filteredLoans.length" class="empty-inline">No matching loans</div>
        </div>
      </aside>

      <section class="packet-workspace">
        <div v-if="selectedLoan && member" class="packet-toolbar no-print">
          <div>
            <strong>{{ selectedLoan.loan_no }}</strong>
            <span>{{ member.first_name }} {{ member.last_name }} · {{ selectedLoan.loan_type_label }}</span>
          </div>
          <div class="page-tabs">
            <button v-for="page in pages" :key="page.no" :class="['page-tab', activePage === page.no && 'active']" @click="activePage = page.no">
              {{ page.no }} {{ page.label }}
            </button>
          </div>
        </div>

        <div v-if="selectedLoan && member" class="packet-preview" :class="{ all: activePage === 'all' }">
          <section v-show="activePage === 1 || activePage === 'all'" class="pdf-paper packet-page">
            <PacketHeader title="LOAN APPLICATION FORM" />
            <div class="pdf-section-title">Member Information</div>
            <div class="pdf-grid-2">
              <div>Member No.: <span class="pdf-field">{{ member.member_no }}</span></div>
              <div>Name: <span class="pdf-field">{{ fullName }}</span></div>
              <div>Company: <span class="pdf-field">{{ member.company }}</span></div>
              <div>Department: <span class="pdf-field">{{ member.department }}</span></div>
              <div>Position: <span class="pdf-field">{{ member.position }}</span></div>
              <div>Employment Status: <span class="pdf-field">{{ member.status }}</span></div>
              <div class="wide">Address: <span class="pdf-field full">{{ member.address }}</span></div>
              <div>Contact: <span class="pdf-field">{{ member.contact }}</span></div>
              <div>Email: <span class="pdf-field">{{ member.email }}</span></div>
            </div>

            <div class="pdf-section-title">Loan Details</div>
            <div class="pdf-grid-2">
              <div>Loan No.: <span class="pdf-field">{{ selectedLoan.loan_no }}</span></div>
              <div>Loan Type: <span class="pdf-field">{{ selectedLoan.loan_type_label }}</span></div>
              <div>Amount Applied: <span class="pdf-field">{{ peso(selectedLoan.amount) }}</span></div>
              <div>Term: <span class="pdf-field">{{ selectedLoan.term_months }} months</span></div>
              <div>Payment Frequency: <span class="pdf-field">{{ freqLabel(selectedLoan.frequency) }}</span></div>
              <div>Annual Rate: <span class="pdf-field">{{ rateLabel }}</span></div>
              <div class="wide">Purpose: <span class="pdf-field full">{{ selectedLoan.purpose || 'For member personal use' }}</span></div>
            </div>

            <div class="signature-grid">
              <div><div class="pdf-sig-line"></div><span>Borrower Signature / Date</span></div>
              <div><div class="pdf-sig-line"></div><span>Co-maker 1 / Date</span></div>
              <div><div class="pdf-sig-line"></div><span>Co-maker 2 / Date</span></div>
              <div><div class="pdf-sig-line"></div><span>COOP Manager / Date</span></div>
            </div>
          </section>

          <section v-show="activePage === 2 || activePage === 'all'" class="pdf-paper packet-page">
            <PacketHeader title="AUTHORITY TO DEDUCT" />
            <p>
              I, <strong>{{ fullName }}</strong>, authorize CRS Holdings Corporations Employees Credit Cooperative and my employer to deduct
              loan payments from my salary or payroll until the full obligation is settled.
            </p>
            <div class="pdf-grid-2 authority-grid">
              <div>Total Payable: <span class="pdf-field">{{ peso(schedule.totalPayment || 0) }}</span></div>
              <div>First Deduction: <span class="pdf-field">{{ dueDate(1) }}</span></div>
              <div>Per Period Payment: <span class="pdf-field">{{ peso(schedule.firstPayment || 0) }}</span></div>
              <div>Last Deduction: <span class="pdf-field">{{ dueDate(schedule.nPeriods || 1) }}</span></div>
            </div>
            <table>
              <thead>
                <tr><th>#</th><th>Due Date</th><th>Amount Due</th><th>Borrower Initial</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in scheduleRows.slice(0, 18)" :key="row.period">
                  <td>{{ row.period }}</td>
                  <td>{{ dueDate(row.period) }}</td>
                  <td>{{ peso(row.payment) }}</td>
                  <td></td>
                </tr>
              </tbody>
            </table>
            <div class="signature-grid two">
              <div><div class="pdf-sig-line"></div><span>Employee/Member Signature</span></div>
              <div><div class="pdf-sig-line"></div><span>Payroll / HR Verification</span></div>
            </div>
          </section>

          <section v-show="activePage === 3 || activePage === 'all'" class="pdf-paper packet-page">
            <PacketHeader title="PROMISSORY NOTE" />
            <p>
              For value received, I promise to pay CRS Holdings Corporations Employees Credit Cooperative the principal amount of
              <strong>{{ peso(selectedLoan.amount) }}</strong>, plus interest and applicable charges, according to the payment schedule attached to this packet.
            </p>
            <div class="note-box">
              <div><span>Loan No.</span><strong>{{ selectedLoan.loan_no }}</strong></div>
              <div><span>Borrower</span><strong>{{ fullName }}</strong></div>
              <div><span>Principal</span><strong>{{ peso(selectedLoan.amount) }}</strong></div>
              <div><span>Total Interest</span><strong>{{ peso(schedule.totalInterest || 0) }}</strong></div>
              <div><span>Total Payable</span><strong>{{ peso(schedule.totalPayment || 0) }}</strong></div>
              <div><span>Payment Terms</span><strong>{{ schedule.nPeriods || 0 }} payments · {{ freqLabel(selectedLoan.frequency) }}</strong></div>
            </div>
            <p>
              In case of resignation, separation, default, or failure to pay, I authorize the Cooperative to apply my collectible benefits,
              share capital, deposits, or other amounts due to me against the outstanding loan balance, subject to cooperative policies.
            </p>
            <div class="signature-grid two">
              <div><div class="pdf-sig-line"></div><span>Borrower Signature / Date</span></div>
              <div><div class="pdf-sig-line"></div><span>Witness / Date</span></div>
            </div>
          </section>

          <section v-show="activePage === 4 || activePage === 'all'" class="pdf-paper packet-page">
            <PacketHeader title="AMORTIZATION SCHEDULE" />
            <table>
              <thead>
                <tr>
                  <th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Amount Due</th><th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in scheduleRows" :key="row.period">
                  <td>{{ row.period }}</td>
                  <td>{{ dueDate(row.period) }}</td>
                  <td>{{ peso(row.principal) }}</td>
                  <td>{{ peso(row.interest) }}</td>
                  <td>{{ peso(row.payment) }}</td>
                  <td>{{ peso(row.balance) }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-show="activePage === 5 || activePage === 'all'" class="pdf-paper packet-page">
            <PacketHeader title="LOAN DISCLOSURE STATEMENT" />
            <div class="disclosure-grid">
              <div><span>Principal Amount</span><strong>{{ peso(selectedLoan.amount) }}</strong></div>
              <div><span>Finance Charge / Interest</span><strong>{{ peso(schedule.totalInterest || 0) }}</strong></div>
              <div><span>Total of Payments</span><strong>{{ peso(schedule.totalPayment || 0) }}</strong></div>
              <div><span>Annual Interest Rate</span><strong>{{ rateLabel }}</strong></div>
              <div><span>Payment Frequency</span><strong>{{ freqLabel(selectedLoan.frequency) }}</strong></div>
              <div><span>Number of Payments</span><strong>{{ schedule.nPeriods || 0 }}</strong></div>
            </div>
            <p>
              The borrower acknowledges receipt of this disclosure and confirms that the loan terms, deduction authority, and amortization
              schedule were reviewed before signing. This preview is unsigned until printed and manually signed by the required parties.
            </p>
            <div class="signature-grid two">
              <div><div class="pdf-sig-line"></div><span>Borrower Acknowledgement</span></div>
              <div><div class="pdf-sig-line"></div><span>COOP Authorized Representative</span></div>
            </div>
          </section>
        </div>

        <div v-else class="empty-state">
          <div class="empty-icon">▣</div>
          <div class="empty-title">Select a loan to generate packet</div>
          <div class="text-muted">Choose an approved or active loan from the list to preview the PDF packet.</div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue'
import { api } from '../composables/useApi'
import { computeSchedule, freqLabel, peso } from '../composables/useLoanCalc'

const PacketHeader = defineComponent({
  props: { title: { type: String, required: true } },
  setup(props) {
    return () => h('div', { class: 'packet-header' }, [
      h('h1', 'CRS HOLDINGS CORPORATIONS EMPLOYEES CREDIT COOPERATIVE'),
      h('div', { class: 'pdf-address' }, 'A.C. Cortes Avenue, Alang-alang, Mandaue City, Cebu 6014'),
      h('h2', props.title),
    ])
  },
})

const pages = [
  { no: 1, label: 'Application' },
  { no: 2, label: 'Authority' },
  { no: 3, label: 'Note' },
  { no: 4, label: 'Schedule' },
  { no: 5, label: 'Disclosure' },
  { no: 'all', label: 'All' },
]

const loans = ref([])
const selectedLoan = ref(null)
const member = ref(null)
const searchTerm = ref('')
const statusFilter = ref('ACTIVE')
const activePage = ref(1)

const filteredLoans = computed(() => {
  const query = searchTerm.value.toLowerCase()
  if (!query) return loans.value
  return loans.value.filter(loan => [
    loan.loan_no,
    loan.member_no,
    loan.first_name,
    loan.last_name,
    `${loan.first_name} ${loan.last_name}`,
    loan.loan_type_label,
    loan.status,
  ].some(value => String(value || '').toLowerCase().includes(query)))
})

const fullName = computed(() => member.value ? `${member.value.first_name} ${member.value.middle_name || ''} ${member.value.last_name}`.replace(/\s+/g, ' ').trim() : '')
const rateLabel = computed(() => `${(Number(selectedLoan.value?.annual_rate || 0) * 100).toFixed(2)}%`)
const schedule = computed(() => selectedLoan.value ? computeSchedule({
  principal: Number(selectedLoan.value.amount || 0),
  termMonths: Number(selectedLoan.value.term_months || 1),
  frequency: selectedLoan.value.frequency || 'monthly',
  annualRate: Number(selectedLoan.value.annual_rate || 0.12),
}) : { schedule: [] })
const scheduleRows = computed(() => schedule.value.schedule || [])

function dueDate(periodNo) {
  const start = selectedLoan.value?.first_due_date ? new Date(selectedLoan.value.first_due_date) : new Date()
  const days = selectedLoan.value?.frequency === 'weekly' ? 7 : selectedLoan.value?.frequency === 'bimonthly' ? 15 : 30
  const date = new Date(start.getTime() + days * (periodNo - 1) * 86400000)
  return date.toLocaleDateString('en-PH')
}

async function selectLoan(loan) {
  selectedLoan.value = loan
  member.value = await api.getMember(loan.member_id)
  activePage.value = 1
}

async function loadLoans() {
  const params = statusFilter.value ? { status: statusFilter.value } : {}
  loans.value = await api.getLoans(params)
  const next = loans.value.find(loan => loan.id === selectedLoan.value?.id) || loans.value[0]
  if (next) await selectLoan(next)
}

function printPacket() {
  activePage.value = 'all'
  requestAnimationFrame(() => window.print())
}

onMounted(loadLoans)
</script>

<style scoped>
.packet-wrap { height:100%; display:flex; flex-direction:column; overflow:hidden; }
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
.header-actions .form-select { width:150px; }
.packet-body {
  flex:1;
  min-height:0;
  overflow:hidden;
  display:grid;
  grid-template-columns:330px minmax(0, 1fr);
}
.packet-sidebar {
  border-right:1px solid var(--coop-border);
  background:#fff;
  min-height:0;
  overflow:hidden;
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
.packet-search { padding:10px; border-bottom:1px solid var(--coop-border); background:#F8FAFC; }
.packet-loan-list { padding:10px; display:flex; flex-direction:column; gap:8px; overflow:auto; }
.packet-loan {
  width:100%;
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
.packet-loan:hover, .packet-loan.active { background:var(--coop-red-dim); border-color:rgba(192,57,43,.25); }
.packet-loan.active { box-shadow:inset 4px 0 0 var(--coop-red); }
.row-title { color:var(--coop-cream); font-weight:900; }
.row-sub { color:var(--coop-muted); font-size:12px; margin-top:2px; }
.packet-workspace { min-width:0; overflow:auto; background:#F3F5F8; padding:18px 22px 30px; }
.packet-toolbar {
  max-width:850px;
  margin:0 auto 14px;
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:8px;
  padding:12px;
  display:flex;
  justify-content:space-between;
  gap:16px;
  align-items:center;
}
.packet-toolbar strong { color:var(--coop-cream); display:block; }
.packet-toolbar span { color:var(--coop-muted); font-size:12px; }
.page-tabs { display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
.page-tab {
  border:1px solid var(--coop-border);
  background:#fff;
  color:var(--coop-cream);
  border-radius:6px;
  padding:7px 9px;
  font-weight:800;
  cursor:pointer;
}
.page-tab.active { background:var(--coop-red); border-color:var(--coop-red); color:#fff; }
.packet-preview { display:flex; flex-direction:column; align-items:center; gap:18px; }
.packet-preview:not(.all) .packet-page { min-height:1000px; }
.packet-page {
  width:816px;
  max-width:100%;
  border-radius:4px;
  box-shadow:0 14px 35px rgba(31,41,55,.12);
}
.packet-header { text-align:center; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:14px; }
.packet-header h1 { font-size:13px; margin:0; color:#000; }
.packet-header h2 { font-size:17px; margin:10px 0 0; color:#000; }
.pdf-grid-2 .wide { grid-column:1 / -1; }
.pdf-field.full { width:78%; }
.signature-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:24px 34px;
  margin-top:34px;
}
.signature-grid.two { margin-top:40px; }
.signature-grid span { font-size:9px; text-transform:uppercase; }
.note-box, .disclosure-grid {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:8px;
  margin:16px 0;
}
.note-box div, .disclosure-grid div {
  border:1px solid #000;
  padding:8px;
  display:flex;
  flex-direction:column;
  gap:4px;
}
.note-box span, .disclosure-grid span { font-size:9px; text-transform:uppercase; color:#555; }
.note-box strong, .disclosure-grid strong { font-size:12px; color:#000; }
.authority-grid { margin-bottom:12px; }
.empty-inline { padding:22px; text-align:center; color:var(--coop-muted); }
@media (max-width: 1100px) {
  .packet-body { grid-template-columns:1fr; overflow:auto; }
  .packet-sidebar { border-right:0; border-bottom:1px solid var(--coop-border); max-height:360px; }
  .packet-toolbar { flex-direction:column; align-items:flex-start; }
  .page-tabs { justify-content:flex-start; }
}
@media print {
  .no-print { display:none !important; }
  .packet-wrap, .packet-body, .packet-workspace, .packet-preview { display:block; height:auto; overflow:visible; padding:0; background:#fff; }
  .packet-page { width:auto; max-width:none; min-height:auto; box-shadow:none; border-radius:0; page-break-after:always; break-after:page; }
  .packet-page:last-child { page-break-after:auto; break-after:auto; }
}
</style>
