<template>
  <div v-if="!currentUser" class="login-shell">
    <section class="login-brand-panel">
      <div class="login-logo">CRS</div>
      <div>
        <h1>CRS Holdings Employees Credit Coop</h1>
        <p>Mandaue City · Cebu</p>
      </div>
      <div class="login-summary">
        <span>Loan operations</span>
        <strong>Member records, applications, collections, billing, and reports in one cooperative workspace.</strong>
      </div>
    </section>

    <section class="login-card">
      <div class="login-card-head">
        <div class="eyebrow">Secure Access</div>
        <h2>Sign in to CRS Coop</h2>
        <p>Use your system account to continue.</p>
      </div>
      <form class="login-form" @submit.prevent="login">
        <div class="form-group">
          <label class="form-label">Email or Username</label>
          <input v-model.trim="loginForm.identifier" class="form-input" autocomplete="username" placeholder="admin@crs.com" />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input v-model="loginForm.password" class="form-input" type="password" autocomplete="current-password" placeholder="Password" />
        </div>
        <div v-if="loginError" class="login-error">{{ loginError }}</div>
        <button class="btn btn-primary login-btn" type="submit">Log In</button>
      </form>
      <div class="login-hint">Preview default: <strong>admin@crs.com</strong> / <strong>admin123</strong></div>
    </section>
  </div>

  <div v-else class="app-shell">
    <div class="mobile-topbar">
      <button class="mobile-menu-btn" @click="mobileOpen = true">☰</button>
      <div>
        <div class="mobile-title">Credit Cooperative</div>
        <div class="mobile-sub">Loan Management System</div>
      </div>
    </div>
    <div :class="['mobile-overlay', mobileOpen && 'visible']" @click="mobileOpen = false"></div>
    <!-- Sidebar -->
    <aside :class="['sidebar', mobileOpen && 'mobile-open']">
      <div class="sidebar-brand">
        <div class="brand-logo">
          <span class="brand-crs">CRS</span>
        </div>
        <div class="brand-text">
          <div class="brand-name">CRS HOLDINGS<br>EMPLOYEES CREDIT<br>COOP</div>
          <div class="brand-sub">Mandaue City · Cebu</div>
        </div>
      </div>

      <nav class="sidebar-nav" @click="mobileOpen = false">
        <div class="nav-group-label">Overview</div>
        <router-link to="/"          class="nav-item" exact-active-class="nav-active" data-tour="nav-dashboard">
          <span class="nav-icon">⊞</span> Dashboard
        </router-link>

        <div class="nav-group-label">Membership</div>
        <router-link to="/members"   class="nav-item" active-class="nav-active" data-tour="nav-members">
          <span class="nav-icon">◉</span> Members
        </router-link>
        <router-link to="/share-capital" class="nav-item" active-class="nav-active" data-tour="nav-share-capital">
          <span class="nav-icon">◎</span> Share Capital
        </router-link>
        <router-link to="/beneficiaries" class="nav-item" active-class="nav-active" data-tour="nav-beneficiaries">
          <span class="nav-icon">♡</span> Beneficiaries
        </router-link>

        <div class="nav-group-label">Loans</div>
        <router-link to="/eligibility" class="nav-item" active-class="nav-active" data-tour="nav-eligibility">
          <span class="nav-icon">✓</span> Eligibility Engine
        </router-link>
        <router-link to="/loans"     class="nav-item" active-class="nav-active" data-tour="nav-loans">
          <span class="nav-icon">▤</span> Applications
        </router-link>
        <router-link to="/pipeline"  class="nav-item" active-class="nav-active" data-tour="nav-pipeline">
          <span class="nav-icon">⟶</span> Loan Pipeline
        </router-link>
        <router-link to="/monitoring" class="nav-item" active-class="nav-active" data-tour="nav-monitoring">
          <span class="nav-icon">◈</span> Monitoring
        </router-link>
        <router-link to="/restructuring" class="nav-item" active-class="nav-active">
          <span class="nav-icon">⟲</span> Loan Restructuring
        </router-link>

        <div class="nav-group-label">Payments</div>
        <router-link to="/billing" class="nav-item" active-class="nav-active" data-tour="nav-billing">
          <span class="nav-icon">▤</span> Billing
        </router-link>
        <router-link to="/payments" class="nav-item" active-class="nav-active" data-tour="nav-payments">
          <span class="nav-icon">▭</span> Payments
        </router-link>

        <div class="nav-group-label">Reports</div>
        <router-link to="/reports/collection" class="nav-item" active-class="nav-active" data-tour="nav-collection">
          <span class="nav-icon">▦</span> Collection Summary
        </router-link>
        <router-link to="/reports/aging" class="nav-item" active-class="nav-active" data-tour="nav-aging">
          <span class="nav-icon">◷</span> Aging Report
        </router-link>
        <router-link to="/reports/outstanding" class="nav-item" active-class="nav-active" data-tour="nav-outstanding">
          <span class="nav-icon">◇</span> Outstanding Balance
        </router-link>

        <div class="nav-group-label">Compliance</div>
        <router-link to="/audit-logs" class="nav-item" active-class="nav-active" data-tour="nav-audit">
          <span class="nav-icon">☰</span> Audit Log
        </router-link>

        <div class="nav-group-label">Advanced</div>
        <router-link to="/notifications" class="nav-item" active-class="nav-active">
          <span class="nav-icon">✉</span> Notifications
        </router-link>
        <router-link to="/loan-packet" class="nav-item" active-class="nav-active">
          <span class="nav-icon">▣</span> PDF Loan Packet
        </router-link>

        <div class="nav-group-label">Administration</div>
        <router-link to="/settings" class="nav-item" active-class="nav-active" data-tour="nav-settings" @click="mobileOpen = false">
          <span class="nav-icon">⚙</span> Settings
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <div class="officer-badge">
          <div class="officer-avatar">{{ userInitials }}</div>
          <div class="officer-info">
            <div class="officer-name">{{ currentUser.name }}</div>
            <div class="officer-role">{{ currentUser.email || currentUser.username }}</div>
          </div>
          <button class="logout-btn" type="button" title="Log out" @click="logout">↪</button>
        </div>
      </div>
    </aside>

    <!-- Main content -->
    <main class="main-area" data-tour="main-area">
      <router-view />
    </main>

    <!-- Toast container -->
    <div class="toast-container">
      <div v-for="t in toasts" :key="t.id" :class="['toast', `toast-${t.type}`]">
        {{ t.message }}
      </div>
    </div>

    <div v-if="!tour.active" class="demo-launcher">
      <button class="demo-btn demo-main-btn" type="button" @click="tour.menuOpen = !tour.menuOpen">
        <span>?</span>
        Guide
      </button>
    </div>

    <section v-if="tour.menuOpen && !tour.active" class="demo-menu-panel">
      <div class="demo-menu-kicker">Choose Guide</div>
      <div class="demo-menu-actions">
        <button type="button" @click="seedTourData">
          <span>↻</span>
          Seed Demo Data
        </button>
        <button type="button" @click="startTour('full')">
          <span>▷</span>
          Start Guided Tour
        </button>
      </div>
      <button v-for="guide in guideOptions" :key="guide.key" class="demo-menu-item" type="button" @click="startTour(guide.key)">
        <span>{{ guide.icon }}</span>
        <strong>{{ guide.label }}</strong>
        <small>{{ guide.description }}</small>
      </button>
    </section>

    <div v-if="tour.active" class="tour-scrim"></div>
    <div v-if="tour.active && highlightStyle" class="tour-highlight" :style="highlightStyle"></div>
    <section v-if="tour.active" class="tour-panel">
      <header class="tour-panel-head">
        <div class="tour-title"><span>▷</span>{{ activeGuide?.label || 'Guided Tour' }}</div>
        <div class="tour-dots">
          <button
            v-for="(step, index) in activeSteps"
            :key="step.id"
            :class="['tour-dot', index < tour.step && 'done', index === tour.step && 'active']"
            type="button"
            @click="goToStep(index)"
          ></button>
        </div>
        <button class="tour-close" type="button" @click="endTour">×</button>
      </header>
      <div class="tour-panel-body">
        <div class="tour-step-badge">{{ currentStep.badge }}</div>
        <h2>{{ currentStep.title }}</h2>
        <p>{{ currentStep.body }}</p>
        <div v-if="currentStep.details?.length" class="tour-detail-box">
          <div v-for="item in currentStep.details" :key="item">
            {{ item }}
          </div>
        </div>
        <div v-if="currentStep.micro?.length" class="tour-micro-list">
          <button v-for="guide in currentStep.micro" :key="guide" type="button" @click="startTour(guide)">
            {{ guideMap[guide]?.label }}
          </button>
        </div>
        <div class="tour-actions">
          <button class="tour-primary" type="button" @click="nextTourStep">
            {{ tour.step === activeSteps.length - 1 ? 'Finish Tour' : currentStep.next || 'Next' }}
          </button>
          <button class="tour-secondary" type="button" :disabled="tour.step === 0" @click="prevTourStep">Back</button>
          <button class="tour-secondary" type="button" @click="endTour">Exit tour</button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from './composables/useToast'

const AUTH_KEY = 'crs-coop-auth-user'
const SETTINGS_KEY = 'crs-coop-preview-settings'
const router = useRouter()
const { toasts, success } = useToast()
const mobileOpen = ref(false)
const currentUser = ref(loadAuthUser())
const loginError = ref('')
const loginForm = reactive({ identifier: 'admin@crs.com', password: 'admin123' })
const highlightStyle = ref(null)
const tour = reactive({ active: false, menuOpen: false, guide: 'full', step: 0 })

const guideOptions = [
  { key: 'full', icon: '↔', label: 'Full Operations Tour', description: 'Member upload to final reports' },
  { key: 'members', icon: '◉', label: 'Members Setup', description: 'Create, import, and manage members' },
  { key: 'eligibility', icon: '✓', label: 'Eligibility Check', description: 'Review member loan readiness' },
  { key: 'application', icon: '▤', label: 'Loan Application', description: 'Create and print a loan packet' },
  { key: 'approval', icon: '⟶', label: 'Pipeline Approval', description: 'Approve and attach signed forms' },
  { key: 'billing', icon: '▤', label: 'Billing and Remittance', description: 'Generate payroll billing cycles' },
  { key: 'payments', icon: '▭', label: 'Payments Posting', description: 'Post loan collections' },
  { key: 'reports', icon: '▦', label: 'Reports Review', description: 'Collections, aging, and balances' },
]

const guideMap = computed(() => Object.fromEntries(guideOptions.map(guide => [guide.key, guide])))

const tourSteps = {
  full: [
    {
      id: 'welcome',
      path: '/',
      target: 'main-area',
      badge: 'Welcome',
      title: 'CRS Coop End-to-End Demo',
      body: 'This tour walks through one complete cooperative operations cycle: setup, member onboarding, eligibility, loan application, approval, monitoring, billing, payment posting, and reporting.',
      details: [
        'Use Seed Demo Data first if you want a clean scenario to explain.',
        'Use Demo Mode if you only want a smaller guide for one module.',
        'The tour moves screens for you and explains what to process on each page.',
      ],
      next: 'Next: Dashboard',
    },
    {
      id: 'dashboard',
      path: '/',
      target: 'nav-dashboard',
      badge: 'Step 1',
      title: 'Dashboard: Operations Command Center',
      body: 'Start here to see portfolio health, active loans, applications needing action, collections, and operational risk. This is the executive checkpoint before staff start daily processing.',
      details: ['Check active loan accounts.', 'Review applications requiring movement.', 'Watch exposure, collections, and recent activity.'],
      next: 'Next: Settings',
    },
    {
      id: 'settings',
      path: '/settings',
      target: 'nav-settings',
      badge: 'Step 2',
      title: 'Settings: Rules Before Transactions',
      body: 'Before creating transactions, configure the cooperative profile, companies, loan types, fees, approvals, permissions, and users. These settings drive dropdowns, eligibility, deductions, and access.',
      details: ['Companies feed member records and billing.', 'Fees feed net release and printable packets.', 'Users and permissions live here in one place.'],
      micro: ['members', 'billing', 'payments'],
      next: 'Next: Members',
    },
    {
      id: 'members',
      path: '/members',
      target: 'nav-members',
      badge: 'Step 3',
      title: 'Members: Create or Import the 201 File',
      body: 'Members are the source record for loan eligibility, billing, share capital, beneficiaries, and reports. Add a member manually or import a spreadsheet using the guide template.',
      details: ['Verify company, employment status, salary, supervisor, and share capital.', 'Only basic profile fields are edited here.', 'Employment changes should appear in history.'],
      next: 'Next: Eligibility',
    },
    {
      id: 'eligibility',
      path: '/eligibility',
      target: 'nav-eligibility',
      badge: 'Step 4',
      title: 'Eligibility Engine: Pre-Check Before Application',
      body: 'Run the member through loan rules before encoding the application. This reduces rejected applications and explains why a member is or is not ready.',
      details: ['Checks active status, tenure, salary capacity, share capital, active loans, and overdue balances.', 'Use this as the loan officer pre-screening step.'],
      next: 'Next: Loan Application',
    },
    {
      id: 'application',
      path: '/loans',
      target: 'nav-loans',
      badge: 'Step 5',
      title: 'Applications: Build the Loan Packet',
      body: 'Find the member, choose loan type, enter amount and term, review fees, preview amortization, and generate the printable loan packet.',
      details: ['Application numbers auto-generate.', 'Fees and deductions come from Settings.', 'PDF preview should be checked before signing.'],
      next: 'Next: Approval',
    },
    {
      id: 'pipeline',
      path: '/pipeline',
      target: 'nav-pipeline',
      badge: 'Step 6',
      title: 'Loan Pipeline: Review, Attach, Approve',
      body: 'The pipeline is the approval window. Staff attach the signed approved application, reviewers check requirements, then the loan is activated.',
      details: ['Attach the signed application or approval document.', 'Approval moves the record into monitoring.', 'This is the control point before money is released.'],
      next: 'Next: Monitoring',
    },
    {
      id: 'monitoring',
      path: '/monitoring',
      target: 'nav-monitoring',
      badge: 'Step 7',
      title: 'Monitoring: Active Loan Schedule',
      body: 'Once activated, the loan appears in monitoring with its amortization schedule, period statuses, balances, overdue exposure, and collection tracking.',
      details: ['Monitor pending, billed, paid, partial, and overdue periods.', 'Use this page to understand what should flow into billing or collections.'],
      next: 'Next: Billing',
    },
    {
      id: 'billing',
      path: '/billing',
      target: 'nav-billing',
      badge: 'Step 8',
      title: 'Billing: Company Payroll Deduction',
      body: 'Generate a bill by company and cutoff. The bill groups due amortization rows into a company remittance cycle, then can be printed, issued, uploaded, or settled.',
      details: ['Billing is for company payroll deduction.', 'Issued bills mark included periods as billed.', 'Upload remittance when HR/payroll sends payment details.'],
      next: 'Next: Payments',
    },
    {
      id: 'payments',
      path: '/payments',
      target: 'nav-payments',
      badge: 'Step 9',
      title: 'Payments: Post Loan Collections',
      body: 'Payments records actual loan collections against amortization periods.',
      details: ['Loan payments update period status and loan balance.', 'Excel import supports bulk loan payment posting.'],
      next: 'Next: Reports',
    },
    {
      id: 'reports',
      path: '/reports/collection',
      target: 'nav-collection',
      badge: 'Step 10',
      title: 'Reports: Validate the Result',
      body: 'After billing and payments, review Collection Summary, Aging Report, and Outstanding Balance to confirm that the operational activity updated reporting correctly.',
      details: ['Collection Summary shows expected vs collected.', 'Aging Report shows overdue exposure.', 'Outstanding Balance shows remaining collectible amounts.'],
      micro: ['reports'],
      next: 'Next: Audit Trail',
    },
    {
      id: 'audit',
      path: '/audit-logs',
      target: 'nav-audit',
      badge: 'Step 11',
      title: 'Audit Log: Compliance Trail',
      body: 'Major actions such as user changes, loan approvals, payments, billing, and notifications should be traceable here. This gives management and auditors a record of who did what.',
      details: ['Use this when checking accountability.', 'Notifications can also be reviewed for reminders and system messages.'],
      next: 'Finish Tour',
    },
  ],
  members: [
    { id: 'members-open', path: '/members', target: 'nav-members', badge: 'Members', title: 'Open Members', body: 'Use this guide when staff need to add or import member records before loan processing.', details: ['Search existing records first.', 'Use Add Member for a single record.', 'Use import for spreadsheet migration.'], next: 'Next' },
    { id: 'members-basic', path: '/members', target: 'main-area', badge: 'Profile', title: 'Encode Basic Profile', body: 'Capture identity, contact, address, company, employment status, salary, supervisor, date hired, and share capital. Member image can be attached for identification.', details: ['Company should come from Settings.', 'Supervisor should be selected from existing members.', 'Promotions and salary changes should be historical.'], next: 'Next' },
    { id: 'members-finish', path: '/members', target: 'main-area', badge: 'Finish', title: 'Review Member Tabs', body: 'After saving, review loans, beneficiaries, share capital, audit history, and documents from the member profile.', details: ['This member can now be checked in Eligibility Engine and used in Applications.'], next: 'Finish' },
  ],
  eligibility: [
    { id: 'eligibility-open', path: '/eligibility', target: 'nav-eligibility', badge: 'Eligibility', title: 'Select Member', body: 'Choose a member before starting a loan application. This is the pre-check area for loan readiness.', details: ['Run this before encoding application details.'], next: 'Next' },
    { id: 'eligibility-rules', path: '/eligibility', target: 'main-area', badge: 'Rules', title: 'Review Eligibility Rules', body: 'Check active membership, share capital, salary capacity, DTI, active loan count, overdue exposure, and loan type limits.', details: ['Failed items should be resolved before proceeding.', 'Passing eligibility does not replace approval.'], next: 'Finish' },
  ],
  application: [
    { id: 'application-start', path: '/loans', target: 'nav-loans', badge: 'Application', title: 'Start Loan Application', body: 'Find a member, confirm profile details, choose loan type, and encode amount, term, release date, and purpose.', details: ['Application reference numbers auto-generate.', 'Loan types and fees come from Settings.'], next: 'Next' },
    { id: 'application-packet', path: '/loans', target: 'main-area', badge: 'Packet', title: 'Review Packet and Schedule', body: 'Review fees, net release, amortization schedule, co-makers, and printable packet before sending for signatures.', details: ['Preview the PDF before printing.', 'Attach signed copies later in the pipeline.'], next: 'Finish' },
  ],
  approval: [
    { id: 'approval-open', path: '/pipeline', target: 'nav-pipeline', badge: 'Pipeline', title: 'Open Pending Application', body: 'Use list view to find applications awaiting approval. Open the approval window to review submitted details.', details: ['Check eligibility, fees, co-makers, and documents.'], next: 'Next' },
    { id: 'approval-attach', path: '/pipeline', target: 'main-area', badge: 'Attachment', title: 'Attach Signed Approved Application', body: 'Upload the signed application or approval document, then approve and activate the loan.', details: ['Activation sends the account into Monitoring.', 'This creates the operational loan schedule.'], next: 'Finish' },
  ],
  billing: [
    { id: 'billing-generate', path: '/billing', target: 'nav-billing', badge: 'Billing', title: 'Generate Company Bill', body: 'Choose company and cutoff period. Billing pulls due amortization periods into one payroll deduction bill.', details: ['One bill per company per period.', 'Line items identify member, loan, schedule, and amount due.'], next: 'Next' },
    { id: 'billing-remit', path: '/billing', target: 'main-area', badge: 'Remittance', title: 'Issue, Print, and Upload Remittance', body: 'Issue the bill, print it for HR/payroll, then upload remittance when the company sends payment details.', details: ['Settled bills update line items and balances.', 'Unsettled bills remain outstanding.'], next: 'Finish' },
  ],
  payments: [
    { id: 'payments-loan', path: '/payments', target: 'nav-payments', badge: 'Loan Collection', title: 'Post Loan Payment', body: 'Select a loan, choose due period, enter O.R. number, payment date, amount, method, and remarks, then post payment.', details: ['Payment updates amortization status and balance.', 'Partial payments are allowed based on Settings.', 'Use Share Capital under Membership for member capital ledger transactions.'], next: 'Finish' },
  ],
  reports: [
    { id: 'reports-collection', path: '/reports/collection', target: 'nav-collection', badge: 'Collection', title: 'Collection Summary', body: 'Review expected vs collected, collection rate, overdue amount, and recent collection details.', details: ['This validates billing and payment posting.'], next: 'Next' },
    { id: 'reports-aging', path: '/reports/aging', target: 'nav-aging', badge: 'Aging', title: 'Aging Report', body: 'Move to Aging Report to identify overdue exposure by age bucket.', details: ['Use it for follow-up priority.'], next: 'Next' },
    { id: 'reports-balance', path: '/reports/outstanding', target: 'nav-outstanding', badge: 'Balance', title: 'Outstanding Balance', body: 'Outstanding Balance shows what remains collectible across active loan accounts.', details: ['This is the management view of remaining portfolio exposure.'], next: 'Finish' },
  ],
}

const activeGuide = computed(() => guideMap.value[tour.guide])
const activeSteps = computed(() => tourSteps[tour.guide] || tourSteps.full)
const currentStep = computed(() => activeSteps.value[tour.step] || activeSteps.value[0])

const userInitials = computed(() => {
  const name = currentUser.value?.name || currentUser.value?.username || 'User'
  return name.split(/\s+/).map(part => part[0]).join('').slice(0, 2).toUpperCase()
})

function loadAuthUser() {
  try { return JSON.parse(localStorage.getItem(AUTH_KEY) || 'null') } catch { return null }
}

function availableUsers() {
  const defaults = [
    { id: 1, name: 'System Admin', username: 'admin', email: 'admin@crs.com', password: 'admin123', role: 'Super Admin', active: true },
  ]
  try {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null')
    const roles = settings?.roles || []
    const users = (settings?.users || []).map(user => ({
      ...user,
      role: roles.find(role => role.id === user.role_id)?.name || 'User',
      active: user.active !== false,
    }))
    return [...defaults, ...users]
  } catch {
    return defaults
  }
}

function login() {
  loginError.value = ''
  const identifier = loginForm.identifier.toLowerCase()
  const user = availableUsers().find(item => {
    const email = String(item.email || '').toLowerCase()
    const username = String(item.username || '').toLowerCase()
    return item.active !== false && (email === identifier || username === identifier)
  })
  if (!user || String(user.password || '') !== loginForm.password) {
    loginError.value = 'Invalid account or password.'
    return
  }
  const sessionUser = { id: user.id, name: user.name, username: user.username, email: user.email, role: user.role }
  localStorage.setItem(AUTH_KEY, JSON.stringify(sessionUser))
  currentUser.value = sessionUser
}

function logout() {
  localStorage.removeItem(AUTH_KEY)
  currentUser.value = null
  mobileOpen.value = false
  loginForm.password = ''
  endTour()
}

function seedTourData() {
  localStorage.setItem('crs-coop-demo-seeded', JSON.stringify({
    seeded_at: new Date().toISOString(),
    scenario: 'CRS guided tour demo',
  }))
  success('Demo scenario marked ready. Use Start Guided Tour to begin.')
}

async function startTour(guideKey = 'full') {
  tour.guide = guideKey
  tour.step = 0
  tour.active = true
  tour.menuOpen = false
  await applyCurrentStep()
}

async function applyCurrentStep() {
  const step = currentStep.value
  if (step?.path && router.currentRoute.value.path !== step.path) {
    await router.push(step.path)
  }
  await nextTick()
  window.setTimeout(updateHighlight, 120)
}

async function goToStep(index) {
  tour.step = index
  await applyCurrentStep()
}

async function nextTourStep() {
  if (tour.step >= activeSteps.value.length - 1) {
    endTour()
    return
  }
  tour.step += 1
  await applyCurrentStep()
}

async function prevTourStep() {
  if (tour.step === 0) return
  tour.step -= 1
  await applyCurrentStep()
}

function endTour() {
  tour.active = false
  tour.menuOpen = false
  highlightStyle.value = null
}

function updateHighlight() {
  const target = currentStep.value?.target
  if (!target) {
    highlightStyle.value = null
    return
  }
  const el = document.querySelector(`[data-tour="${target}"]`)
  if (!el) {
    highlightStyle.value = null
    return
  }
  const rect = el.getBoundingClientRect()
  highlightStyle.value = {
    top: `${Math.max(8, rect.top - 6)}px`,
    left: `${Math.max(8, rect.left - 6)}px`,
    width: `${rect.width + 12}px`,
    height: `${rect.height + 12}px`,
  }
}
</script>

<style scoped>
.app-shell {
  display: flex; height: 100vh; overflow: hidden;
}

.login-shell {
  min-height:100vh;
  display:grid;
  grid-template-columns:minmax(360px, .9fr) minmax(420px, 1.1fr);
  background:#F6F7FB;
}
.login-brand-panel {
  background:#8F241E;
  color:#fff;
  padding:56px 52px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  gap:40px;
}
.login-logo {
  width:76px;
  height:76px;
  border-radius:50%;
  background:#fff;
  color:#8F241E;
  display:flex;
  align-items:center;
  justify-content:center;
  font-weight:900;
  font-size:22px;
}
.login-brand-panel h1 {
  max-width:420px;
  margin:24px 0 10px;
  font-family:var(--font-serif);
  font-size:48px;
  line-height:1.02;
  font-weight:400;
}
.login-brand-panel p { color:rgba(255,255,255,.68); font-size:18px; }
.login-summary {
  border-top:1px solid rgba(255,255,255,.18);
  padding-top:24px;
  display:flex;
  flex-direction:column;
  gap:8px;
}
.login-summary span { color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.12em; font-size:12px; font-weight:900; }
.login-summary strong { max-width:520px; font-size:20px; line-height:1.35; }
.login-card {
  align-self:center;
  justify-self:center;
  width:min(460px, calc(100% - 48px));
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:14px;
  padding:34px;
  box-shadow:0 18px 50px rgba(31,41,55,.09);
}
.login-card-head .eyebrow { color:var(--coop-red); text-transform:uppercase; letter-spacing:.12em; font-size:12px; font-weight:900; }
.login-card h2 { margin:8px 0 4px; font-family:var(--font-serif); font-size:38px; line-height:1.05; font-weight:400; color:#202838; }
.login-card p { color:#6D7484; }
.login-form { display:flex; flex-direction:column; gap:16px; margin-top:26px; }
.login-form .form-input { min-height:48px; border-radius:9px; }
.login-btn { min-height:48px; justify-content:center; border-radius:9px; font-size:15px; }
.login-error { border:1px solid #F3C4BE; background:#FFF1F0; color:#A7332B; padding:10px 12px; border-radius:8px; font-weight:800; }
.login-hint { margin-top:18px; color:#6D7484; font-size:12px; }
.logout-btn {
  margin-left:auto;
  width:32px;
  height:32px;
  border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.08);
  color:#fff;
  border-radius:8px;
  cursor:pointer;
}
.logout-btn:hover { background:rgba(255,255,255,.16); }

/* ── Sidebar ─────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w); min-width: var(--sidebar-w);
  background: #8F241E;
  border-right: 1px solid rgba(255,255,255,0.12);
  display: flex; flex-direction: column;
  overflow: hidden;
  font-family: var(--font-sans);
}

.sidebar-brand {
  padding: 24px 20px 22px;
  border-bottom: 1px solid rgba(255,255,255,0.13);
  background: transparent;
  display: flex; align-items: center; gap: 13px;
}
.brand-logo {
  width: 50px; height: 50px; border-radius: 50%;
  background: #fff;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.brand-crs { font-family: var(--font-sans); font-size: 15px; color: #8F241E; font-weight: 900; }
.brand-name { font-family: var(--font-sans); font-size: 13px; font-weight: 900; color: #fff; line-height: 1.15; letter-spacing:.3px; }
.brand-sub  { font-size: 11px; color: rgba(255,255,255,.58); margin-top: 5px; }

.sidebar-nav {
  flex: 1; overflow-y: auto; padding: 12px 10px;
}
.nav-group-label {
  font-family: var(--font-sans);
  font-size: 10px; font-weight: 900; letter-spacing: 1.4px;
  text-transform: uppercase; color: rgba(255,255,255,.42);
  padding: 14px 10px 6px;
}
.nav-item {
  font-family: var(--font-sans);
  display: flex; align-items: center; gap: 11px;
  padding: 10px 10px; border-radius: 0;
  color: rgba(255,255,255,.68); text-decoration: none;
  font-size: 15px; font-weight: 700;
  transition: all var(--tx);
  margin: 0 -10px 1px;
  padding-left: 20px;
  border-right: 3px solid transparent;
}
.nav-item:hover { color: #fff; background: rgba(255,255,255,.08); }
.nav-active { color: #fff !important; background: rgba(255,255,255,.13) !important; font-weight: 900; border-right-color:#EA7A32; }
.nav-active .nav-icon { color: #fff; }
.nav-icon { font-size: 14px; width: 18px; text-align: center; }

.sidebar-footer {
  padding: 18px 18px;
  border-top: 1px solid rgba(255,255,255,.13);
  background: rgba(0,0,0,.06);
}
.officer-badge { display: flex; align-items: center; gap: 10px; }
.officer-avatar {
  width: 38px; height: 38px; border-radius: 50%;
  background: #E9792F; display: flex; align-items: center;
  justify-content: center; font-size: 12px; font-weight: 900; color: #fff;
  flex-shrink: 0;
}
.officer-name { font-size: 13px; font-weight: 900; color: #fff; }
.officer-role { font-size: 12px; color: rgba(255,255,255,.58); }

/* ── Main ────────────────────────────────────────────── */
.main-area {
  flex: 1; overflow: hidden; display: flex; flex-direction: column;
  background: var(--coop-mid);
}

.demo-launcher {
  position:fixed;
  right:22px;
  top:18px;
  z-index:380;
}
.demo-btn {
  border:1px solid rgba(143,36,30,.18);
  border-radius:999px;
  min-width:auto;
  min-height:38px;
  display:flex;
  align-items:center;
  gap:8px;
  justify-content:center;
  padding:8px 14px;
  color:#8F241E;
  font-weight:900;
  font-size:13px;
  cursor:pointer;
  background:#fff;
  box-shadow:0 10px 28px rgba(31,41,55,.09);
  transition:transform .18s ease, box-shadow .18s ease;
}
.demo-btn:hover { transform:translateY(-1px); box-shadow:0 14px 34px rgba(31,41,55,.14); }
.demo-btn span {
  width:18px;
  height:18px;
  border-radius:50%;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#FFF3EF;
  color:#8F241E;
  font-size:12px;
  line-height:1;
}
.demo-menu-panel {
  position:fixed;
  right:22px;
  top:64px;
  z-index:390;
  width:min(360px, calc(100vw - 40px));
  background:#fff;
  border:1px solid var(--coop-border);
  border-radius:14px;
  box-shadow:0 22px 70px rgba(31,41,55,.22);
  padding:16px;
}
.demo-menu-kicker {
  color:var(--coop-red);
  font-size:11px;
  font-weight:900;
  letter-spacing:.12em;
  text-transform:uppercase;
  margin-bottom:10px;
}
.demo-menu-actions {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:8px;
  margin-bottom:10px;
}
.demo-menu-actions button {
  border:1px solid #E5EAF2;
  border-radius:9px;
  background:#F8FAFC;
  color:#202838;
  padding:9px 10px;
  font-weight:900;
  cursor:pointer;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
}
.demo-menu-actions button:hover {
  border-color:#F0D2C8;
  background:#FFF5F2;
  color:#8F241E;
}
.demo-menu-item {
  width:100%;
  border:1px solid transparent;
  border-radius:10px;
  background:#fff;
  padding:11px 12px;
  text-align:left;
  cursor:pointer;
  display:grid;
  grid-template-columns:28px 1fr;
  gap:2px 10px;
  align-items:center;
}
.demo-menu-item:hover { border-color:#D9E3F3; background:#F8FAFC; }
.demo-menu-item span { grid-row:span 2; color:#8F241E; font-size:20px; text-align:center; }
.demo-menu-item strong { color:#202838; font-size:14px; }
.demo-menu-item small { color:#6D7484; font-size:12px; }
.tour-scrim {
  position:fixed;
  inset:0;
  z-index:390;
  background:rgba(11,28,44,.68);
}
.tour-highlight {
  position:fixed;
  z-index:392;
  pointer-events:none;
  border-radius:12px;
  box-shadow:0 0 0 4px #2F6FD8, 0 0 0 8px rgba(47,111,216,.22), 0 0 42px rgba(47,111,216,.26);
  transition:all .2s ease;
}
.tour-panel {
  position:fixed;
  z-index:400;
  left:50%;
  bottom:32px;
  transform:translateX(-50%);
  width:min(680px, calc(100vw - 34px));
  max-height:min(76vh, 760px);
  background:#fff;
  border-radius:18px;
  overflow:hidden;
  box-shadow:0 26px 90px rgba(0,0,0,.36);
}
.tour-panel-head {
  background:#0B1C2C;
  color:#fff;
  padding:16px 20px;
  display:grid;
  grid-template-columns:1fr auto 34px;
  align-items:center;
  gap:16px;
}
.tour-title {
  display:flex;
  align-items:center;
  gap:10px;
  font-weight:900;
  min-width:0;
}
.tour-dots {
  display:flex;
  gap:6px;
  align-items:center;
  flex-wrap:wrap;
  justify-content:flex-end;
}
.tour-dot {
  width:8px;
  height:8px;
  border:0;
  border-radius:50%;
  background:rgba(255,255,255,.25);
  cursor:pointer;
  padding:0;
}
.tour-dot.done { background:#12A57A; }
.tour-dot.active { background:#fff; transform:scale(1.25); }
.tour-close {
  width:34px;
  height:34px;
  border:0;
  border-radius:8px;
  background:rgba(255,255,255,.1);
  color:#fff;
  font-size:22px;
  line-height:1;
  cursor:pointer;
}
.tour-panel-body {
  padding:24px 26px 28px;
  overflow:auto;
  max-height:calc(min(76vh, 760px) - 66px);
}
.tour-step-badge {
  display:inline-flex;
  border-radius:999px;
  background:#EAF2FF;
  color:#2F6FD8;
  padding:5px 12px;
  font-size:11px;
  font-weight:900;
  letter-spacing:.08em;
  text-transform:uppercase;
  margin-bottom:12px;
}
.tour-panel-body h2 {
  margin:0 0 10px;
  color:#202838;
  font-size:27px;
  line-height:1.12;
}
.tour-panel-body p {
  margin:0;
  color:#526174;
  font-size:16px;
  line-height:1.65;
}
.tour-detail-box {
  margin-top:18px;
  border:1px solid #E5EAF2;
  border-radius:12px;
  background:#F8FAFC;
  padding:15px 18px;
  color:#2F394A;
  font-size:14px;
  line-height:1.7;
}
.tour-detail-box div + div { margin-top:6px; }
.tour-detail-box div::before {
  content:"• ";
  color:#8F241E;
  font-weight:900;
}
.tour-micro-list {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-top:16px;
}
.tour-micro-list button {
  border:1px solid #E5EAF2;
  background:#fff;
  color:#8F241E;
  border-radius:999px;
  padding:7px 11px;
  font-weight:900;
  cursor:pointer;
}
.tour-actions {
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:22px;
}
.tour-primary,
.tour-secondary {
  border-radius:10px;
  min-height:44px;
  padding:10px 18px;
  font-weight:900;
  cursor:pointer;
}
.tour-primary {
  border:0;
  background:#2F6FD8;
  color:#fff;
}
.tour-secondary {
  border:1px solid #DDE4EF;
  background:#fff;
  color:#6D7484;
}
.tour-secondary:disabled {
  opacity:.45;
  cursor:not-allowed;
}
</style>


<style scoped>
.mobile-topbar { display:none; align-items:center; gap:12px; padding:12px 16px; border-bottom:1px solid var(--coop-border); background:#fff; flex-shrink:0; }
.mobile-menu-btn { border:1px solid var(--coop-border); background:#fff; border-radius:6px; width:36px; height:36px; cursor:pointer; color:var(--coop-cream); }
.mobile-title { font-weight:900; color:var(--coop-cream); font-size:14px; }
.mobile-sub { color:var(--coop-muted); font-size:11px; }
.mobile-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:999; }
@media (max-width: 900px) {
  .login-shell { grid-template-columns:1fr; }
  .login-brand-panel { padding:34px 28px; }
  .login-brand-panel h1 { font-size:38px; }
  .login-card { margin:32px 0; }
}
@media (max-width: 767px) {
  .app-shell { flex-direction:column; }
  .mobile-topbar { display:flex; }
  .mobile-overlay.visible { display:block; }
  .sidebar { position:fixed; z-index:1000; top:0; left:0; height:100vh; transform:translateX(-100%); transition:transform .24s ease; box-shadow:4px 0 24px rgba(0,0,0,.16); }
  .sidebar.mobile-open { transform:translateX(0); }
  .main-area { min-height:0; }
  .demo-launcher { right:12px; top:10px; }
  .demo-btn { min-height:36px; font-size:12px; padding:7px 11px; }
  .demo-menu-panel { right:12px; top:54px; }
  .tour-panel { bottom:14px; }
  .tour-panel-head { grid-template-columns:1fr 34px; }
  .tour-dots { grid-column:1 / -1; justify-content:flex-start; order:3; }
}
</style>
