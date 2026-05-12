import { createRouter, createWebHistory } from 'vue-router'
import DashboardView    from './views/DashboardView.vue'
import MembersView      from './views/MembersView.vue'
import LoanOfficerView  from './views/LoanOfficerView.vue'
import PipelineView     from './views/PipelineView.vue'
import MonitoringView   from './views/MonitoringView.vue'
import ModulePlaceholderView from './views/ModulePlaceholderView.vue'
import EligibilityView from './views/EligibilityView.vue'
import CollectionSummaryView from './views/CollectionSummaryView.vue'
import AgingReportView from './views/AgingReportView.vue'
import OutstandingBalanceView from './views/OutstandingBalanceView.vue'
import PaymentsView from './views/PaymentsView.vue'
import BillingView from './views/BillingView.vue'
import RestructuringView from './views/RestructuringView.vue'
import LoanPacketView from './views/LoanPacketView.vue'
import ShareCapitalView from './views/ShareCapitalView.vue'
import BeneficiariesView from './views/BeneficiariesView.vue'
import SettingsView from './views/SettingsView.vue'
import NotificationsView from './views/NotificationsView.vue'
import AuditLogView from './views/AuditLogView.vue'

const routes = [
  { path: '/',          component: DashboardView,   name: 'dashboard' },
  { path: '/members',   component: MembersView,     name: 'members' },
  { path: '/loans',     component: LoanOfficerView, name: 'loans' },
  { path: '/pipeline',  component: PipelineView,    name: 'pipeline' },
  { path: '/monitoring',component: MonitoringView,  name: 'monitoring' },
  { path: '/payments',  component: PaymentsView, name: 'payments' },
  { path: '/billing', component: BillingView, name: 'billing' },
  { path: '/eligibility', component: EligibilityView, name: 'eligibility' },
  { path: '/reports/collection', component: CollectionSummaryView, name: 'reports.collection' },
  { path: '/reports/aging', component: AgingReportView, name: 'reports.aging' },
  { path: '/reports/outstanding', component: OutstandingBalanceView, name: 'reports.outstanding' },
  { path: '/audit-logs', component: AuditLogView, name: 'audit' },
  { path: '/beneficiaries', component: BeneficiariesView, name: 'beneficiaries' },
  { path: '/share-capital', component: ShareCapitalView, name: 'share-capital' },
  { path: '/restructuring', component: RestructuringView, name: 'restructuring' },
  { path: '/notifications', component: NotificationsView, name: 'notifications' },
  { path: '/loan-packet', component: LoanPacketView, name: 'loan-packet' },
  { path: '/users', redirect: '/settings' },
  { path: '/settings', component: SettingsView, name: 'settings' },
]

export default createRouter({ history: createWebHistory(), routes })
