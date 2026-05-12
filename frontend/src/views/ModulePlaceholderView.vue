<template>
  <div class="module-wrap">
    <header class="module-header">
      <div>
        <div class="eyebrow">{{ module.phase }}</div>
        <h1>{{ module.title }}</h1>
        <p>{{ module.description }}</p>
      </div>
      <span class="status-pill">{{ module.status }}</span>
    </header>

    <section class="module-grid">
      <article v-for="item in module.items" :key="item.title" class="module-card">
        <div class="card-icon">{{ item.icon }}</div>
        <h2>{{ item.title }}</h2>
        <p>{{ item.text }}</p>
      </article>
    </section>

    <section class="integration-note">
      <strong>Integration note</strong>
      <span>{{ module.note }}</span>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const modules = {
  payments: {
    phase: 'Phase 2',
    title: 'Collections & Payments',
    description: 'Payment recording, O.R. capture, balance tracking, and auto loan-close workflow.',
    status: 'Delivered as package',
    note: 'The PHP/Vue payment package exists in crs-phase2 2 and still needs to be merged into this older v1 frontend/backend.',
    items: [
      { icon: '₱', title: 'Payment posting', text: 'Record period payments, receipt numbers, and payment method.' },
      { icon: '✓', title: 'Balance update', text: 'Recompute loan balances and amortization period status.' },
      { icon: '↻', title: 'Overdue detection', text: 'Nightly overdue checks and penalty application.' },
    ],
  },
  eligibility: {
    phase: 'Phase 2',
    title: 'Eligibility Engine',
    description: 'Automatic checks for member tenure, share capital, status, loan caps, and concurrent loans.',
    status: 'Delivered as package',
    note: 'Eligibility service files are present in the Phase 2 backend package.',
    items: [
      { icon: '✓', title: 'Tenure and status', text: 'Block submissions for inactive or not-yet-qualified members.' },
      { icon: '◎', title: 'Share capital rule', text: 'Validate member capital requirements against loan type settings.' },
      { icon: '▦', title: 'Loan exposure', text: 'Check amount caps and concurrent loan limits.' },
    ],
  },
  collection: {
    phase: 'Phase 3',
    title: 'Collection Summary',
    description: 'Expected versus collected reports by loan type and status, with rate bars and exports.',
    status: 'Delivered as package',
    note: 'Report pages and export services are in crs-phase3 and crs-phase3-patch.',
    items: [
      { icon: '▦', title: 'Collection rate', text: 'Compare expected, collected, and overdue totals.' },
      { icon: '◇', title: 'Breakdowns', text: 'Group by loan type, department, officer, and status.' },
      { icon: '⇩', title: 'Exports', text: 'Excel and PDF report output from the backend package.' },
    ],
  },
  aging: {
    phase: 'Phase 3',
    title: 'Aging Report',
    description: '0-30, 31-60, 61-90, and 90+ day overdue buckets.',
    status: 'Delivered as package',
    note: 'The AgingReportPage exists in the Phase 3 frontend package.',
    items: [
      { icon: '◷', title: 'Aging buckets', text: 'Classify overdue amounts by days past due.' },
      { icon: '!', title: 'Penalty visibility', text: 'Surface penalty totals per delinquent period.' },
      { icon: '⇩', title: 'PDF export', text: 'Produce formatted reports for management review.' },
    ],
  },
  outstanding: {
    phase: 'Phase 3',
    title: 'Outstanding Balance',
    description: 'Full loan exposure report per member with overdue and penalty amounts.',
    status: 'Delivered as package',
    note: 'Outstanding balance request, service, page, and export views are in the Phase 3 folders.',
    items: [
      { icon: '₱', title: 'Exposure totals', text: 'Show outstanding principal, interest, and penalties.' },
      { icon: '◉', title: 'Member view', text: 'Trace balances back to member records and loan terms.' },
      { icon: '▣', title: 'Letterhead PDF', text: 'Export report with cooperative header details.' },
    ],
  },
  audit: {
    phase: 'Phase 4',
    title: 'Audit Log',
    description: 'Immutable activity history for members, loans, payments, settings, and loan types.',
    status: 'Delivered as package',
    note: 'Audit observers, service, policy, and UI files are in crs-phase4-audit.',
    items: [
      { icon: '☰', title: 'Change history', text: 'Track created, updated, deleted, and system events.' },
      { icon: '◇', title: 'Diff viewer', text: 'Compare old and new values for audited records.' },
      { icon: '🔒', title: 'Restricted access', text: 'Super-admin only visibility by policy.' },
    ],
  },
  beneficiaries: {
    phase: 'Phase 4',
    title: 'Member Beneficiaries',
    description: 'Primary and secondary beneficiaries with allocation validation and declaration PDF.',
    status: 'Delivered as package',
    note: 'Beneficiary tab, resources, service, and declaration view are in crs-phase4-beneficiaries.',
    items: [
      { icon: '♡', title: 'Beneficiary list', text: 'Manage primary and secondary beneficiary records.' },
      { icon: '%', title: 'Allocation checks', text: 'Validate total share percentages and guardian details.' },
      { icon: '▣', title: 'Declaration PDF', text: 'Generate member beneficiary declaration document.' },
    ],
  },
  shareCapital: {
    phase: 'Phase 4',
    title: 'Share Capital Ledger',
    description: 'Member deposits, withdrawals, dividends, adjustments, voiding, and reports.',
    status: 'Delivered as package',
    note: 'Share capital package is separate and can be merged into member detail and reports.',
    items: [
      { icon: '◎', title: 'Ledger', text: 'Track all share capital transactions per member.' },
      { icon: '↻', title: 'Auto sync', text: 'Keep member share capital balance updated.' },
      { icon: '▦', title: 'Aggregate report', text: 'Management report for total cooperative capital.' },
    ],
  },
  restructuring: {
    phase: 'Phase 5',
    title: 'Loan Restructuring',
    description: 'Two-step restructuring wizard with live schedule preview and audit trail.',
    status: 'Delivered as package',
    note: 'Restructuring UI and backend service are in crs-phase5-restructuring.',
    items: [
      { icon: '⟲', title: 'New terms', text: 'Enter revised amount, rate, term, and first due date.' },
      { icon: '▦', title: 'Preview schedule', text: 'Compare old and new amortization schedules.' },
      { icon: '✓', title: 'Confirm', text: 'Void old periods and generate the restructured loan record.' },
    ],
  },
  notifications: {
    phase: 'Phase 5',
    title: 'SMS & Email Notifications',
    description: 'Semaphore SMS, Laravel mail, event hooks, and notification log.',
    status: 'Patched today',
    note: 'Notification template data, policy, and restructured email view were patched in the Phase 5 package.',
    items: [
      { icon: '✉', title: 'Notification log', text: 'Track sent and failed SMS/email events.' },
      { icon: '⚙', title: 'Settings', text: 'Toggle channels by event and test SMS configuration.' },
      { icon: '◷', title: 'Due reminders', text: 'Nightly reminder command for upcoming payments.' },
    ],
  },
  loanPacket: {
    phase: 'Phase 5',
    title: 'PDF Loan Packet',
    description: 'Five-page application, authority to deduct, promissory note, schedule, and disclosure packet.',
    status: 'Patched today',
    note: 'The promissory note amount-in-words placeholder was fixed in the Phase 5 loan packet package.',
    items: [
      { icon: '▣', title: 'One-click download', text: 'Generate complete loan document packet from loan detail.' },
      { icon: '✎', title: 'Auto-filled pages', text: 'Fill borrower, co-maker, amount, schedule, and disclosure terms.' },
      { icon: '✓', title: 'Ready for signing', text: 'Produce print-ready PDF packet for approved loans.' },
    ],
  },
  settings: {
    phase: 'Phase 2',
    title: 'Settings',
    description: 'Coop profile, loan type qualification rules, approval thresholds, companies, and preferences.',
    status: 'Delivered as package',
    note: 'Settings pages and backend APIs are in crs-phase2 2.',
    items: [
      { icon: '⚙', title: 'Coop profile', text: 'Maintain cooperative name, registration, and letterhead data.' },
      { icon: '▦', title: 'Loan types', text: 'Configure loan rates, terms, caps, and qualification rules.' },
      { icon: '✓', title: 'Approvals', text: 'Set manager and board threshold workflow.' },
    ],
  },
}

const module = computed(() => modules[route.meta.module] ?? modules.settings)
</script>

<style scoped>
.module-wrap {
  height: 100%;
  overflow-y: auto;
  padding: 28px;
  background: var(--coop-mid);
}
.module-header {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  align-items: flex-start;
  background: #fff;
  border: 1px solid var(--coop-border);
  border-left: 5px solid var(--coop-red);
  border-radius: 8px;
  padding: 24px;
  box-shadow: 0 12px 30px rgba(31,41,55,0.05);
}
.eyebrow {
  color: var(--coop-red);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 6px;
}
h1 {
  font-family: var(--font-serif);
  font-size: 32px;
  line-height: 1.1;
  color: var(--coop-cream);
}
p {
  color: var(--coop-muted);
  margin-top: 8px;
  max-width: 780px;
}
.status-pill {
  display: inline-flex;
  padding: 6px 10px;
  border-radius: 999px;
  color: var(--coop-red);
  background: var(--coop-red-dim);
  font-size: 12px;
  font-weight: 800;
  white-space: nowrap;
}
.module-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 18px;
}
.module-card {
  background: #fff;
  border: 1px solid var(--coop-border);
  border-radius: 8px;
  padding: 20px;
  min-height: 170px;
}
.card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  background: var(--coop-red);
  margin-bottom: 16px;
}
h2 {
  color: var(--coop-cream);
  font-size: 16px;
  margin-bottom: 6px;
}
.integration-note {
  margin-top: 18px;
  display: flex;
  gap: 10px;
  align-items: flex-start;
  background: #fff8f7;
  border: 1px solid rgba(192,57,43,0.25);
  color: var(--coop-muted);
  border-radius: 8px;
  padding: 14px 16px;
}
.integration-note strong {
  color: var(--coop-red);
  white-space: nowrap;
}
@media (max-width: 980px) {
  .module-grid { grid-template-columns: 1fr; }
  .module-header { flex-direction: column; }
}
</style>
