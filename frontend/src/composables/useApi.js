// src/composables/useApi.js
import { computeSchedule } from './useLoanCalc'

const BASE = import.meta.env.VITE_API_URL || 'http://localhost/crs-coop/backend/api'
const STORAGE_KEY = 'crs-coop-preview-data'
const SETTINGS_KEY = 'crs-coop-preview-settings'

const seedData = {
  members: [
    {
      id: 1,
      member_no: 'CRS-00081',
      first_name: 'Josefina',
      middle_name: 'A.',
      last_name: 'Monteverde',
      address: 'A.C. Cortes Avenue, Mandaue City, Cebu',
      contact: '09171234567',
      email: 'j.monteverde@crsholdings.test',
      company: 'CRS Holdings Corporation',
      branch: 'Mandaue',
      department: 'Operations',
      position: 'Loan Officer',
      status: 'REGULAR',
      supervisor: 'R. Villanueva',
      date_hired: '2020-03-16',
      monthly_salary: 42000,
      share_capital: 38500,
      member_status: 'ACTIVE',
      active_loans: 1,
    },
    {
      id: 2,
      member_no: 'CRS-00124',
      first_name: 'Maria',
      middle_name: 'L.',
      last_name: 'Santos',
      address: 'Basak, Mandaue City, Cebu',
      contact: '09182345678',
      email: 'm.santos@crsholdings.test',
      company: 'CRS Holdings Corporation',
      branch: 'Mandaue',
      department: 'Accounting',
      position: 'Bookkeeper',
      status: 'REGULAR',
      supervisor: 'A. Reyes',
      date_hired: '2019-07-08',
      monthly_salary: 36000,
      share_capital: 64200,
      member_status: 'ACTIVE',
      active_loans: 0,
    },
    {
      id: 3,
      member_no: 'CRS-00177',
      first_name: 'Ramon',
      middle_name: 'D.',
      last_name: 'Dela Cruz',
      address: 'Alang-alang, Mandaue City, Cebu',
      contact: '09273456789',
      email: 'r.delacruz@crsholdings.test',
      company: 'CRS Holdings Corporation',
      branch: 'Cebu',
      department: 'Warehouse',
      position: 'Inventory Custodian',
      status: 'REGULAR',
      supervisor: 'M. Tan',
      date_hired: '2021-11-22',
      monthly_salary: 31500,
      share_capital: 27500,
      member_status: 'ACTIVE',
      active_loans: 1,
    },
    {
      id: 4,
      member_no: 'CRS-00203',
      first_name: 'Angela',
      middle_name: 'P.',
      last_name: 'Lim',
      address: 'Banilad, Cebu City',
      contact: '09384567890',
      email: 'a.lim@crsholdings.test',
      company: 'CRS Holdings Corporation',
      branch: 'Cebu',
      department: 'HR',
      position: 'HR Associate',
      status: 'PROBI',
      supervisor: 'C. Ong',
      date_hired: '2026-01-15',
      monthly_salary: 28000,
      share_capital: 9500,
      member_status: 'ACTIVE',
      active_loans: 0,
    },
    {
      id: 5,
      member_no: 'CRS-00218',
      first_name: 'Michael',
      middle_name: 'J.',
      last_name: 'Villanueva',
      address: 'Lapu-Lapu City, Cebu',
      contact: '09495678901',
      email: 'm.villanueva@crsholdings.test',
      company: 'CRS Holdings Corporation',
      branch: 'Mandaue',
      department: 'IT',
      position: 'Systems Analyst',
      status: 'REGULAR',
      supervisor: 'D. Co',
      date_hired: '2018-05-03',
      monthly_salary: 52000,
      share_capital: 82000,
      member_status: 'ACTIVE',
      active_loans: 0,
    },
  ],
  loanTypes: [
    { id: 1, code: 'commodity', label: 'Commodity Loan', annual_rate: 0.12, min_amount: 5000, max_amount: 80000, min_term: 3, max_term: 36 },
    { id: 2, code: 'salary', label: 'Salary Loan', annual_rate: 0.10, min_amount: 10000, max_amount: 150000, min_term: 6, max_term: 48 },
    { id: 3, code: 'emergency', label: 'Emergency Loan', annual_rate: 0.08, min_amount: 3000, max_amount: 50000, min_term: 3, max_term: 24 },
    { id: 4, code: 'educational', label: 'Educational Loan', annual_rate: 0.09, min_amount: 10000, max_amount: 100000, min_term: 6, max_term: 36 },
    { id: 5, code: 'multi', label: 'Multi-Purpose Loan', annual_rate: 0.12, min_amount: 10000, max_amount: 200000, min_term: 6, max_term: 60 },
  ],
  loans: [
    {
      id: 1,
      loan_no: 'LN-2026-0001',
      member_id: 1,
      loan_type_id: 2,
      loan_type_label: 'Salary Loan',
      amount: 60000,
      term_months: 36,
      frequency: 'bimonthly',
      annual_rate: 0.12,
      status: 'ACTIVE',
      purpose: 'Home appliance purchase',
      first_due_date: '2026-05-15',
      created_at: '2026-04-22',
    },
    {
      id: 2,
      loan_no: 'LN-2026-0002',
      member_id: 3,
      loan_type_id: 1,
      loan_type_label: 'Commodity Loan',
      amount: 25000,
      term_months: 12,
      frequency: 'monthly',
      annual_rate: 0.12,
      status: 'PENDING',
      purpose: 'Motor repair',
      first_due_date: '2026-05-30',
      created_at: '2026-05-02',
    },
  ],
}

let backendAvailable = null

function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

function loadStore() {
  const saved = localStorage.getItem(STORAGE_KEY)
  if (!saved) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(seedData))
    return clone(seedData)
  }
  return JSON.parse(saved)
}

function saveStore(data) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
}

async function request(path, options = {}) {
  if (backendAvailable === false) {
    throw new Error('Preview data mode')
  }

  try {
    const session = JSON.parse(localStorage.getItem('crs-admin-session') || 'null')
    const authHeader = session?.token ? { 'Authorization': `Bearer ${session.token}` } : {}
    const res = await fetch(`${BASE}${path}`, {
      headers: { 'Content-Type': 'application/json', ...authHeader, ...options.headers },
      ...options,
      body: options.body ? JSON.stringify(options.body) : undefined,
    })
    if (res.status === 401) {
      localStorage.removeItem('crs-admin-session')
      if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
      throw new Error('Session expired')
    }
    const json = await res.json()
    if (!json.success) throw new Error(json.message || 'API error')
    backendAvailable = true
    return json.data
  } catch (error) {
    backendAvailable = false
    throw error
  }
}

function memberMatches(member, params = {}) {
  const query = (params.search || '').toLowerCase()
  const status = params.status || ''
  const haystack = [
    member.member_no,
    member.first_name,
    member.middle_name,
    member.last_name,
    member.company,
    member.department,
    member.position,
  ].join(' ').toLowerCase()

  return (!query || haystack.includes(query)) && (!status || member.member_status === status)
}

function attachLoanLabels(data) {
  const members = new Map(data.members.map(m => [m.id, m]))
  const types = new Map(data.loanTypes.map(t => [t.id, t]))
  return data.loans.map(loan => {
    const member = members.get(Number(loan.member_id)) || {}
    const type = types.get(Number(loan.loan_type_id)) || {}
    return {
      ...loan,
      loan_type_label: loan.loan_type_label || type.label || 'Loan',
      first_name: member.first_name,
      last_name: member.last_name,
      member_no: member.member_no,
      company: member.company,
      position: member.position,
    }
  })
}

function dueDatesForLoan(loan, nPeriods) {
  const start = loan.first_due_date ? new Date(loan.first_due_date) : new Date()
  const step = loan.frequency === 'weekly' ? 7 : loan.frequency === 'bimonthly' ? 15 : 30
  return Array.from({ length: nPeriods }, (_, index) => {
    const due = new Date(start.getTime() + step * index * 86400000)
    return due.toISOString().slice(0, 10)
  })
}

function ensureBillingData(data) {
  if (!data.bills) data.bills = []
  if (!data.billRemittances) data.billRemittances = []
  if (!data.payments) data.payments = []
  if (!data.shareCapitalLedger) data.shareCapitalLedger = seedShareLedgerFromMembers(data.members || [])
  return data
}

function signedShareAmount(row) {
  return row.type === 'WITHDRAWAL' ? -Number(row.amount || 0) : Number(row.amount || 0)
}

function seedShareLedgerFromMembers(memberRows) {
  return memberRows.map((member, index) => ({
    id: index + 1,
    member_id: member.id,
    date: '2026-01-01',
    transaction_date: '2026-01-01',
    type: 'OPENING',
    amount: Number(member.share_capital || 0),
    reference: `SC-OPEN-${member.member_no}`,
    remarks: 'Opening balance from member profile',
    balance_after: Number(member.share_capital || 0),
    voided: false,
    created_at: new Date().toISOString(),
  }))
}

function recomputeShareLedger(data, memberId = null) {
  const balances = {}
  const rows = data.shareCapitalLedger || []
  const sorted = [...rows].sort((a, b) => new Date(a.date || a.transaction_date) - new Date(b.date || b.transaction_date) || Number(a.id) - Number(b.id))
  sorted.forEach(row => {
    if (memberId && Number(row.member_id) !== Number(memberId)) return
    if (!balances[row.member_id]) balances[row.member_id] = 0
    if (!row.voided) balances[row.member_id] += signedShareAmount(row)
    row.balance_after = +balances[row.member_id].toFixed(2)
  })
  Object.entries(balances).forEach(([id, balance]) => {
    const member = data.members.find(m => Number(m.id) === Number(id))
    if (member) member.share_capital = +Number(balance).toFixed(2)
  })
  data.shareCapitalLedger = sorted.sort((a, b) => new Date(b.date || b.transaction_date) - new Date(a.date || a.transaction_date) || Number(b.id) - Number(a.id))
}

function getCompaniesFromMembers(data) {
  const names = [...new Set(data.members.map(m => m.company).filter(Boolean))]
  return names.map((name, index) => ({ id: index + 1, name }))
}

function companyById(data, id) {
  return getCompaniesFromMembers(data).find(company => company.id === Number(id))
}

function hydrateBill(data, bill) {
  const companies = getCompaniesFromMembers(data)
  const company = companies.find(c => c.id === Number(bill.company_id))
  const remittances = (data.billRemittances || []).filter(r => r.bill_id === bill.id)
  return {
    ...bill,
    company_name: company?.name || bill.company_name || 'Company',
    balance: Math.max(0, +(Number(bill.total_amount || 0) - Number(bill.amount_remitted || 0)).toFixed(2)),
    item_count: bill.items?.length || 0,
    remittances,
  }
}

function createBillingPayments(data, bill, remittanceAmount = null, remittance = {}) {
  const existingKeys = new Set((data.payments || []).map(payment => payment.source_key).filter(Boolean))
  let remaining = remittanceAmount == null
    ? (Number(bill.total_amount || 0) - (bill.items || []).reduce((sum, item) => sum + Number(item.amount_paid || 0), 0))
    : Number(remittanceAmount || 0)
  const created = []
  for (const item of bill.items || []) {
    if (remaining <= 0) break
    const alreadyPaid = Number(item.amount_paid || 0)
    const itemBalance = Math.max(0, Number(item.amount_due || 0) - alreadyPaid)
    if (!itemBalance) continue
    const applied = Math.min(itemBalance, remaining)
    const sourceKey = `bill-${bill.id}-${remittance.id || 'settle'}-${item.schedule_key || item.schedule_id || item.id}`
    if (!existingKeys.has(sourceKey) && applied > 0) {
      created.push({
        id: Date.now() + created.length,
        loan_id: Number(item.loan_id),
        loan_no: item.loan_no,
        schedule_id: item.schedule_id || item.id,
        schedule_key: item.schedule_key,
        period_no: Number(item.period_no),
        or_number: remittance.or_number || `BILL-${bill.bill_no}`,
        payment_date: remittance.remittance_date || new Date().toISOString().slice(0, 10),
        amount_paid: +applied.toFixed(2),
        method: 'Payroll Deduction',
        payment_type: 'billing',
        remarks: `Posted via billing ${bill.bill_no}`,
        source_key: sourceKey,
        bill_id: bill.id,
        created_at: new Date().toISOString(),
      })
    }
    item.amount_paid = +(alreadyPaid + applied).toFixed(2)
    item.status = item.amount_paid >= Number(item.amount_due || 0) ? 'PAID' : 'PARTIAL'
    remaining = +(remaining - applied).toFixed(2)
  }
  if (created.length) data.payments = [...created, ...(data.payments || [])]
}

function eligibleBillingItems(data, input) {
  const company = companyById(data, input.company_id)
  if (!company) throw new Error('Company not found')
  const members = new Map(data.members.map(m => [m.id, m]))
  const billedScheduleKeys = new Set(
    (data.bills || [])
      .filter(b => b.status !== 'CANCELLED')
      .flatMap(b => b.items || [])
      .map(item => item.schedule_key)
  )

  return attachLoanLabels(data)
    .filter(loan => loan.status === 'ACTIVE')
    .filter(loan => (members.get(Number(loan.member_id))?.company || '') === company.name)
    .flatMap(loan => {
      const calc = computeSchedule({
        principal: Number(loan.amount || 0),
        termMonths: Number(loan.term_months || 1),
        frequency: loan.frequency || 'monthly',
        annualRate: Number(loan.annual_rate || 0.12),
      })
      const dates = dueDatesForLoan(loan, calc.nPeriods)
      return calc.schedule.map((period, index) => {
        const scheduleKey = `${loan.id}-${period.period}`
        return {
          id: Number(`${loan.id}${String(period.period).padStart(3, '0')}`),
          schedule_key: scheduleKey,
          member_id: loan.member_id,
          loan_id: loan.id,
          loan_no: loan.loan_no,
          member_no: loan.member_no,
          member_name: `${loan.first_name || ''} ${loan.last_name || ''}`.trim(),
          period_no: period.period,
          due_date: dates[index],
          principal: period.principal,
          interest: period.interest,
          amount_due: period.payment,
          amount_paid: 0,
          status: 'PENDING',
        }
      })
    })
    .filter(item => item.due_date >= input.billing_period_start && item.due_date <= input.billing_period_end)
    .filter(item => !billedScheduleKeys.has(item.schedule_key))
}

const fallback = {
  getMembers(params = {}) {
    const data = loadStore()
    return data.members.filter(m => memberMatches(m, params))
  },
  getMember(id) {
    const data = loadStore()
    const member = data.members.find(m => m.id === Number(id))
    if (!member) throw new Error('Member not found')
    return {
      ...member,
      loans: attachLoanLabels(data).filter(l => Number(l.member_id) === Number(id)),
    }
  },
  createMember(input) {
    const data = loadStore()
    const nextId = Math.max(0, ...data.members.map(m => m.id)) + 1
    const member = {
      branch: 'Mandaue',
      department: '',
      active_loans: 0,
      member_status: 'ACTIVE',
      ...input,
      id: nextId,
      monthly_salary: Number(input.monthly_salary || 0),
      share_capital: Number(input.share_capital || 0),
    }
    data.members.unshift(member)
    saveStore(data)
    return member
  },
  updateMember(id, input) {
    const data = loadStore()
    const index = data.members.findIndex(m => m.id === Number(id))
    if (index === -1) throw new Error('Member not found')
    data.members[index] = {
      ...data.members[index],
      ...input,
      id: Number(id),
      monthly_salary: Number(input.monthly_salary || 0),
      share_capital: Number(input.share_capital || 0),
    }
    saveStore(data)
    return data.members[index]
  },
  deleteMember(id) {
    const data = loadStore()
    data.members = data.members.filter(m => m.id !== Number(id))
    saveStore(data)
    return true
  },
  getLoans(params = {}) {
    const data = loadStore()
    let loans = attachLoanLabels(data)
    if (params.status) loans = loans.filter(l => l.status === params.status)
    return loans.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  },
  getLoan(id) {
    const data = loadStore()
    const loan = attachLoanLabels(data).find(l => l.id === Number(id))
    if (!loan) throw new Error('Loan not found')
    return loan
  },
  getPipeline() {
    const statuses = ['DRAFT', 'PENDING', 'APPROVED', 'ACTIVE', 'CLOSED', 'REJECTED']
    const loans = this.getLoans()
    return statuses.reduce((grouped, status) => {
      grouped[status] = loans.filter(l => l.status === status)
      return grouped
    }, {})
  },
  createLoan(input) {
    const data = loadStore()
    const type = data.loanTypes.find(t => t.id === Number(input.loan_type_id))
    const nextId = Math.max(0, ...data.loans.map(l => l.id)) + 1
    const loanNo = `LN-2026-${String(nextId).padStart(4, '0')}`
    const loan = {
      id: nextId,
      loan_no: loanNo,
      loan_type_label: type?.label || 'Loan',
      created_at: new Date().toISOString().slice(0, 10),
      ...input,
      member_id: Number(input.member_id),
      loan_type_id: Number(input.loan_type_id),
      amount: Number(input.amount || 0),
      term_months: Number(input.term_months || 0),
      annual_rate: Number(input.annual_rate || type?.annual_rate || 0.12),
    }
    data.loans.unshift(loan)

    const member = data.members.find(m => m.id === Number(input.member_id))
    if (member && ['PENDING', 'APPROVED', 'ACTIVE'].includes(loan.status)) {
      member.active_loans = Number(member.active_loans || 0) + 1
    }

    saveStore(data)
    return loan
  },
  updateLoan(id, input) {
    const data = loadStore()
    const index = data.loans.findIndex(l => l.id === Number(id))
    if (index < 0) throw new Error('Loan not found')
    const nextInput = { ...input }
    if (nextInput.status === 'APPROVED' && !nextInput.approval_date) nextInput.approval_date = new Date().toISOString().slice(0, 10)
    if (nextInput.status === 'ACTIVE') {
      const approvalDate = nextInput.approval_date || data.loans[index].approval_date || new Date().toISOString().slice(0, 10)
      const date = new Date(`${approvalDate}T00:00:00`)
      date.setDate(date.getDate() <= 15 ? 15 : Math.min(30, new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate()))
      nextInput.approval_date = approvalDate
      nextInput.first_due_date = nextInput.first_due_date || data.loans[index].first_due_date || date.toISOString().slice(0, 10)
    }
    if (nextInput.approval_attachment_name && !nextInput.signed_form_name) nextInput.signed_form_name = nextInput.approval_attachment_name
    if (nextInput.signed_form_name && !nextInput.signed_form_url) nextInput.signed_form_url = nextInput.signed_form_name
    data.loans[index] = {
      ...data.loans[index],
      ...nextInput,
      updated_at: new Date().toISOString(),
    }
    saveStore(data)
    return attachLoanLabels(data).find(l => l.id === Number(id))
  },
  calcLoan(input) {
    return computeSchedule({
      principal: Number(input.amount || input.principal || 0),
      termMonths: Number(input.term_months || input.termMonths || 0),
      frequency: input.frequency || 'monthly',
      annualRate: Number(input.annual_rate || input.annualRate || 0.12),
    })
  },
  updateSchedulePeriod(input) {
    const data = ensureBillingData(loadStore())
    const loan = data.loans.find(item => Number(item.id) === Number(input.loan_id))
    if (!loan) throw new Error('Loan not found')
    loan.schedule_overrides = { ...(loan.schedule_overrides || {}), [input.period_no]: input.status }
    saveStore(data)
    return { updated: true, loan_id: Number(input.loan_id), period_no: Number(input.period_no), status: input.status }
  },
  getLoanTypes() {
    const savedSettings = localStorage.getItem(SETTINGS_KEY)
    if (savedSettings) {
      const parsed = JSON.parse(savedSettings)
      if (parsed.loanTypes?.length) return parsed.loanTypes
    }
    return loadStore().loanTypes
  },

  getCompanies() {
    const data = ensureBillingData(loadStore())
    return getCompaniesFromMembers(data)
  },
  getBills(params = {}) {
    const data = ensureBillingData(loadStore())
    let bills = data.bills.map(bill => hydrateBill(data, bill))
    if (params.company_id) bills = bills.filter(bill => Number(bill.company_id) === Number(params.company_id))
    if (params.status) bills = bills.filter(bill => bill.status === params.status)
    if (params.date_from) bills = bills.filter(bill => bill.billing_period_start >= params.date_from)
    if (params.date_to) bills = bills.filter(bill => bill.billing_period_end <= params.date_to)
    return bills.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  },
  getBill(id) {
    const data = ensureBillingData(loadStore())
    const bill = data.bills.find(b => b.id === Number(id))
    if (!bill) throw new Error('Bill not found')
    return hydrateBill(data, bill)
  },
  createBill(input) {
    const data = ensureBillingData(loadStore())
    const items = eligibleBillingItems(data, input)
    if (!items.length) throw new Error('No pending amortization periods found for this company in the selected billing period.')
    const nextId = Math.max(0, ...data.bills.map(b => b.id)) + 1
    const year = new Date().getFullYear()
    const bill = {
      id: nextId,
      bill_no: `BL-${year}-${String(nextId).padStart(5, '0')}`,
      company_id: Number(input.company_id),
      status: 'DRAFT',
      billing_period_start: input.billing_period_start,
      billing_period_end: input.billing_period_end,
      total_amount: +items.reduce((sum, item) => sum + Number(item.amount_due || 0), 0).toFixed(2),
      amount_remitted: 0,
      issued_at: null,
      settled_at: null,
      prepared_by: 1,
      notes: input.notes || '',
      created_at: new Date().toISOString(),
      items: items.map((item, index) => ({ ...item, id: index + 1, bill_id: nextId })),
    }
    data.bills.unshift(bill)
    saveStore(data)
    return hydrateBill(data, bill)
  },
  issueBill(id) {
    const data = ensureBillingData(loadStore())
    const bill = data.bills.find(b => b.id === Number(id))
    if (!bill) throw new Error('Bill not found')
    if (bill.status !== 'DRAFT') throw new Error('Only draft bills can be issued')
    bill.status = 'ISSUED'
    bill.issued_at = new Date().toISOString()
    saveStore(data)
    return hydrateBill(data, bill)
  },
  remitBill(id, input) {
    const data = ensureBillingData(loadStore())
    const bill = data.bills.find(b => b.id === Number(id))
    if (!bill) throw new Error('Bill not found')
    if (!['ISSUED', 'PARTIAL'].includes(bill.status)) throw new Error('Only issued or partial bills can receive remittance')
    const remittance = {
      id: Date.now(),
      bill_id: bill.id,
      or_number: input.or_number || '',
      amount: Number(input.amount || 0),
      remittance_date: input.remittance_date,
      notes: input.notes || '',
      file_name: input.file_name || '',
      created_at: new Date().toISOString(),
      posted_by_name: 'J. Monteverde',
    }
    data.billRemittances.unshift(remittance)
    bill.amount_remitted = +(Number(bill.amount_remitted || 0) + remittance.amount).toFixed(2)
    createBillingPayments(data, bill, remittance.amount, remittance)
    if (bill.amount_remitted >= bill.total_amount) {
      bill.amount_remitted = bill.total_amount
      bill.status = 'SETTLED'
      bill.settled_at = new Date().toISOString()
      bill.items = bill.items.map(item => ({ ...item, status: Number(item.amount_paid || 0) >= Number(item.amount_due || 0) ? 'PAID' : item.status }))
    } else {
      bill.status = 'PARTIAL'
    }
    saveStore(data)
    return hydrateBill(data, bill)
  },
  settleBill(id) {
    const data = ensureBillingData(loadStore())
    const bill = data.bills.find(b => b.id === Number(id))
    if (!bill) throw new Error('Bill not found')
    if (!['ISSUED', 'PARTIAL'].includes(bill.status)) throw new Error('Only issued or partial bills can be settled')
    const remaining = Math.max(0, Number(bill.total_amount || 0) - Number(bill.amount_remitted || 0))
    bill.amount_remitted = bill.total_amount
    bill.status = 'SETTLED'
    bill.settled_at = new Date().toISOString()
    createBillingPayments(data, bill, remaining, { id: `settle-${Date.now()}`, or_number: `BILL-${bill.bill_no}`, remittance_date: new Date().toISOString().slice(0, 10) })
    bill.items = bill.items.map(item => ({ ...item, status: 'PAID', amount_paid: item.amount_due }))
    saveStore(data)
    return hydrateBill(data, bill)
  },
  cancelBill(id) {
    const data = ensureBillingData(loadStore())
    const bill = data.bills.find(b => b.id === Number(id))
    if (!bill) throw new Error('Bill not found')
    if (!['DRAFT', 'ISSUED'].includes(bill.status)) throw new Error('Only draft or issued bills can be cancelled')
    bill.status = 'CANCELLED'
    saveStore(data)
    return hydrateBill(data, bill)
  },


  getDashboard() {
    const data = ensureBillingData(loadStore())
    const loans = attachLoanLabels(data)
    const payments = data.payments || []
    const activeLoans = loans.filter(loan => loan.status === 'ACTIVE')
    const pendingLoans = loans.filter(loan => ['DRAFT', 'PENDING', 'APPROVED'].includes(loan.status))
    const totalOutstanding = activeLoans.reduce((sum, loan) => sum + Number(loan.amount || 0), 0)
    const today = new Date()
    const thisMonth = today.toISOString().slice(0, 7)
    const collectionsThisMonth = payments
      .filter(payment => (payment.payment_date || '').startsWith(thisMonth))
      .reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0)
    const collectionRate = totalOutstanding ? Math.min(100, Math.round((collectionsThisMonth / Math.max(totalOutstanding * 0.08, 1)) * 100)) : 0
    const monthly_collections = Array.from({ length: 6 }, (_, index) => {
      const d = new Date(today.getFullYear(), today.getMonth() - (5 - index), 1)
      const monthKey = d.toISOString().slice(0, 7)
      const collected = payments.filter(payment => (payment.payment_date || '').startsWith(monthKey)).reduce((sum, payment) => sum + Number(payment.amount_paid || 0), 0)
      const expected = Math.max(1, totalOutstanding * 0.08)
      return {
        month: d.toLocaleDateString('en-PH', { month: 'short' }),
        label: d.toLocaleDateString('en-PH', { month: 'short', year: 'numeric' }),
        expected,
        collected,
        rate: Math.min(100, Math.round((collected / expected) * 100)),
      }
    })
    const loan_status = ['DRAFT', 'PENDING', 'APPROVED', 'ACTIVE', 'CLOSED', 'REJECTED']
      .map(status => ({ status, count: loans.filter(loan => loan.status === status).length }))
      .filter(row => row.count > 0)
    const groups = loans.reduce((map, loan) => {
      const key = loan.loan_type_label || 'Loan'
      if (!map[key]) map[key] = { label: key, count: 0, amount: 0 }
      map[key].count += 1
      map[key].amount += Number(loan.amount || 0)
      return map
    }, {})
    return {
      stats: {
        active_loans: activeLoans.length,
        total_members: data.members.filter(member => member.member_status === 'ACTIVE').length,
        pending_loans: pendingLoans.length,
        total_outstanding: totalOutstanding,
        collection_rate: collectionRate,
        overdue_count: activeLoans.length ? 1 : 0,
        overdue_balance: activeLoans[0] ? Number(activeLoans[0].amount || 0) * 0.04 : 0,
        new_loans_this_month: loans.filter(loan => (loan.created_at || '').startsWith(thisMonth)).length,
        collections_this_month: collectionsThisMonth,
      },
      monthly_collections,
      loan_status,
      loan_types: Object.values(groups),
      recent_loans: loans.slice(0, 8),
      top_overdue: activeLoans.slice(0, 5).map(loan => ({ ...loan, overdue_periods: 1, balance: Number(loan.amount || 0) * 0.04 })),
      generated_at: new Date().toISOString(),
    }
  },
  getUsers(params = {}) {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null')
    const roles = settings?.roles || []
    let users = (settings?.users || []).map(user => ({
      id: user.id,
      name: user.name,
      email: user.email,
      role: roles.find(role => role.id === user.role_id)?.name || 'Staff',
      is_active: user.active ? 1 : 0,
      created_at: new Date().toISOString(),
    }))
    if (!users.length) users = [
      { id: 1, name: 'J. Monteverde', email: 'j.monteverde@crsholdings.test', role: 'Loan Officer', is_active: 1, created_at: new Date().toISOString() },
      { id: 2, name: 'Admin User', email: 'admin@crsholdings.test', role: 'Super Admin', is_active: 1, created_at: new Date().toISOString() },
    ]
    const query = (params.search || '').toLowerCase()
    if (query) users = users.filter(user => `${user.name} ${user.email}`.toLowerCase().includes(query))
    if (params.role) users = users.filter(user => user.role === params.role)
    if (params.is_active !== undefined && params.is_active !== '') users = users.filter(user => Boolean(Number(user.is_active)) === (params.is_active === 'true'))
    return {
      users,
      meta: {
        total: users.length,
        active: users.filter(user => Number(user.is_active) === 1).length,
        inactive: users.filter(user => Number(user.is_active) !== 1).length,
      },
    }
  },
  createUser(input) {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
    if (!settings.users) settings.users = []
    if (!settings.roles) settings.roles = [{ id: 1, name: 'Super Admin' }, { id: 2, name: 'Manager' }, { id: 3, name: 'Loan Officer' }, { id: 4, name: 'Staff' }]
    const role = settings.roles.find(item => item.name === input.role) || settings.roles[0]
    const user = { id: Date.now(), name: input.name, username: input.email.split('@')[0], password: input.password || '', email: input.email, role_id: role.id, active: Boolean(input.is_active) }
    settings.users.push(user)
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
    return { ...user, role: role.name, is_active: user.active ? 1 : 0 }
  },
  updateUser(id, input) {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
    if (!settings.users) settings.users = []
    if (!settings.roles) settings.roles = [{ id: 1, name: 'Super Admin' }, { id: 2, name: 'Manager' }, { id: 3, name: 'Loan Officer' }, { id: 4, name: 'Staff' }]
    const index = settings.users.findIndex(user => user.id === Number(id))
    if (index === -1) throw new Error('User not found')
    const role = settings.roles.find(item => item.name === input.role) || settings.roles[0]
    settings.users[index] = { ...settings.users[index], name: input.name, email: input.email, role_id: role.id, active: Boolean(input.is_active), ...(input.password ? { password: input.password } : {}) }
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
    return { ...settings.users[index], role: role.name, is_active: settings.users[index].active ? 1 : 0 }
  },
  toggleUserActive(id) {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
    if (!settings.users) settings.users = []
    const user = settings.users.find(item => item.id === Number(id))
    if (!user) throw new Error('User not found')
    user.active = !user.active
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
    return { user, message: user.active ? 'User reactivated.' : 'User deactivated.' }
  },
  resetUserPassword(id) {
    const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
    if (!settings.users) settings.users = []
    const user = settings.users.find(item => item.id === Number(id))
    if (!user) throw new Error('User not found')
    const temp = `CRS-${Math.floor(100000 + Math.random() * 900000)}`
    user.password = temp
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
    return { temp_password: temp, message: 'Temporary password generated.' }
  },

  getPayments(params = {}) {
    const data = ensureBillingData(loadStore())
    let payments = [...data.payments]
    if (params.loan_id) payments = payments.filter(payment => Number(payment.loan_id) === Number(params.loan_id))
    if (params.payment_date) payments = payments.filter(payment => payment.payment_date === params.payment_date)
    return payments.sort((a, b) => new Date(b.payment_date || b.created_at) - new Date(a.payment_date || a.created_at))
  },
  createPayment(input) {
    const data = ensureBillingData(loadStore())
    const loan = attachLoanLabels(data).find(item => item.id === Number(input.loan_id))
    if (!loan) throw new Error('Loan not found')
    const payment = {
      id: Date.now(),
      loan_id: Number(input.loan_id),
      loan_no: loan.loan_no,
      schedule_id: input.schedule_id || null,
      schedule_key: input.schedule_key || `${input.loan_id}-${input.period_no}`,
      period_no: Number(input.period_no),
      or_number: input.or_number,
      payment_date: input.payment_date,
      amount_paid: Number(input.amount_paid || 0),
      method: input.method || 'Cash',
      payment_type: input.payment_type || 'direct',
      remarks: input.remarks || '',
      created_at: new Date().toISOString(),
    }
    data.payments = [payment, ...data.payments]
    saveStore(data)
    return payment
  },

  getShareCapitalLedger(params = {}) {
    const data = ensureBillingData(loadStore())
    let rows = [...data.shareCapitalLedger]
    if (params.member_id) rows = rows.filter(row => Number(row.member_id) === Number(params.member_id))
    if (params.type) rows = rows.filter(row => row.type === params.type)
    if (params.date_from) rows = rows.filter(row => (row.date || row.transaction_date) >= params.date_from)
    if (params.date_to) rows = rows.filter(row => (row.date || row.transaction_date) <= params.date_to)
    if (params.search) {
      const query = params.search.toLowerCase()
      const members = new Map(data.members.map(member => [member.id, member]))
      rows = rows.filter(row => {
        const member = members.get(Number(row.member_id)) || {}
        return [row.reference, row.remarks, row.type, member.member_no, member.first_name, member.last_name, `${member.first_name || ''} ${member.last_name || ''}`]
          .some(value => String(value || '').toLowerCase().includes(query))
      })
    }
    return rows.sort((a, b) => new Date(b.date || b.transaction_date) - new Date(a.date || a.transaction_date) || Number(b.id) - Number(a.id))
  },
  createShareCapitalEntry(input) {
    const data = ensureBillingData(loadStore())
    const member = data.members.find(item => Number(item.id) === Number(input.member_id))
    if (!member) throw new Error('Member not found')
    if (input.source_key) {
      const existing = data.shareCapitalLedger.find(row => row.source_key === input.source_key)
      if (existing) return existing
    }
    const entry = {
      id: Date.now(),
      member_id: Number(input.member_id),
      date: input.date,
      transaction_date: input.date,
      type: input.type || 'DEPOSIT',
      amount: Number(input.amount || 0),
      reference: input.reference || `SC-${new Date().getFullYear()}-${String((data.shareCapitalLedger || []).length + 1).padStart(4, '0')}`,
      source: input.source || '',
      company: input.company || '',
      remarks: input.remarks || '',
      source_key: input.source_key || '',
      balance_after: 0,
      voided: false,
      created_at: new Date().toISOString(),
    }
    data.shareCapitalLedger = [entry, ...data.shareCapitalLedger]
    recomputeShareLedger(data, entry.member_id)
    saveStore(data)
    return entry
  },
  updateShareCapitalEntry(id, input) {
    const data = ensureBillingData(loadStore())
    const entry = data.shareCapitalLedger.find(row => Number(row.id) === Number(id))
    if (!entry) throw new Error('Ledger entry not found')
    entry.voided = Boolean(input.voided)
    entry.voided_at = entry.voided ? new Date().toISOString() : null
    recomputeShareLedger(data, entry.member_id)
    saveStore(data)
    return entry
  },

}

async function withFallback(path, action) {
  try {
    return await action.remote()
  } catch {
    return action.local()
  }
}

export const api = {
  getMembers: (params = {}) => withFallback('/members.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/members.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getMembers(params),
  }),
  getMember: (id) => withFallback('/members.php', {
    remote: () => request(`/members.php?id=${id}`),
    local: () => fallback.getMember(id),
  }),
  createMember: (data) => withFallback('/members.php', {
    remote: () => request('/members.php', { method: 'POST', body: data }),
    local: () => fallback.createMember(data),
  }),
  updateMember: (id, data) => withFallback('/members.php', {
    remote: () => request(`/members.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => fallback.updateMember(id, data),
  }),
  deleteMember: (id) => withFallback('/members.php', {
    remote: () => request(`/members.php?id=${id}`, { method: 'DELETE' }),
    local: () => fallback.deleteMember(id),
  }),

  getLoans: (params = {}) => withFallback('/loans.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/loans.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getLoans(params),
  }),
  getLoan: (id) => withFallback('/loans.php', {
    remote: () => request(`/loans.php?id=${id}`),
    local: () => fallback.getLoan(id),
  }),
  getPipeline: () => withFallback('/loans.php?action=pipeline', {
    remote: () => request('/loans.php?action=pipeline'),
    local: () => fallback.getPipeline(),
  }),
  createLoan: (data) => withFallback('/loans.php', {
    remote: () => request('/loans.php', { method: 'POST', body: data }),
    local: () => fallback.createLoan(data),
  }),
  updateLoan: (id, data) => withFallback('/loans.php', {
    remote: () => request(`/loans.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => fallback.updateLoan(id, data),
  }),
  calcLoan: (data) => withFallback('/loans.php?action=calc', {
    remote: () => request('/loans.php?action=calc', { method: 'POST', body: data }),
    local: () => fallback.calcLoan(data),
  }),
  updateSchedulePeriod: (data) => withFallback('/loans.php?action=schedule-status', {
    remote: () => request('/loans.php?action=schedule-status', { method: 'PUT', body: data }),
    local: () => fallback.updateSchedulePeriod(data),
  }),

  getLoanTypes: () => withFallback('/loan-types.php', {
    remote: () => request('/loan-types.php'),
    local: () => fallback.getLoanTypes(),
  }),

  getCompanies: () => withFallback('/bills.php?action=companies', {
    remote: () => request('/bills.php?action=companies'),
    local: () => fallback.getCompanies(),
  }),
  getBills: (params = {}) => withFallback('/bills.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/bills.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getBills(params),
  }),
  getBill: (id) => withFallback('/bills.php', {
    remote: () => request(`/bills.php?id=${id}`),
    local: () => fallback.getBill(id),
  }),
  createBill: (data) => withFallback('/bills.php?action=create', {
    remote: () => request('/bills.php?action=create', { method: 'POST', body: data }),
    local: () => fallback.createBill(data),
  }),
  issueBill: (id) => withFallback('/bills.php?action=issue', {
    remote: () => request(`/bills.php?id=${id}&action=issue`, { method: 'POST', body: {} }),
    local: () => fallback.issueBill(id),
  }),
  remitBill: (id, data) => withFallback('/bills.php?action=remittance', {
    remote: () => request(`/bills.php?id=${id}&action=remittance`, { method: 'POST', body: data }),
    local: () => fallback.remitBill(id, data),
  }),
  settleBill: (id) => withFallback('/bills.php?action=settle', {
    remote: () => request(`/bills.php?id=${id}&action=settle`, { method: 'POST', body: {} }),
    local: () => fallback.settleBill(id),
  }),
  cancelBill: (id) => withFallback('/bills.php?action=cancel', {
    remote: () => request(`/bills.php?id=${id}&action=cancel`, { method: 'POST', body: {} }),
    local: () => fallback.cancelBill(id),
  }),

  getPayments: (params = {}) => withFallback('/payments.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/payments.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getPayments(params),
  }),
  createPayment: (data) => withFallback('/payments.php', {
    remote: () => request('/payments.php', { method: 'POST', body: data }),
    local: () => fallback.createPayment(data),
  }),

  getShareCapitalLedger: (params = {}) => withFallback('/share-capital.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/share-capital.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getShareCapitalLedger(params),
  }),
  createShareCapitalEntry: (data) => withFallback('/share-capital.php', {
    remote: () => request('/share-capital.php', { method: 'POST', body: data }),
    local: () => fallback.createShareCapitalEntry(data),
  }),
  updateShareCapitalEntry: (id, data) => withFallback('/share-capital.php', {
    remote: () => request(`/share-capital.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => fallback.updateShareCapitalEntry(id, data),
  }),

  getDashboard: () => withFallback('/dashboard.php', {
    remote: () => request('/dashboard.php'),
    local: () => fallback.getDashboard(),
  }),
  getAuditLogs: (params = {}) => withFallback('/audit-logs.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/audit-logs.php${q ? '?' + q : ''}`)
    },
    local: () => [],
  }),
  createAuditLog: (data) => withFallback('/audit-logs.php', {
    remote: () => request('/audit-logs.php', { method: 'POST', body: data }),
    local: () => ({ created: true }),
  }),
  getNotificationLogs: (params = {}) => withFallback('/notification-logs.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/notification-logs.php${q ? '?' + q : ''}`)
    },
    local: () => [],
  }),
  createNotificationLog: (data) => withFallback('/notification-logs.php', {
    remote: () => request('/notification-logs.php', { method: 'POST', body: data }),
    local: () => ({ ...data, id: data.source_key || Date.now(), created_at: new Date().toISOString() }),
  }),
  updateNotificationLog: (id, data) => withFallback('/notification-logs.php', {
    remote: () => request(`/notification-logs.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => ({ id, ...data, updated_at: new Date().toISOString() }),
  }),
  getUsers: (params = {}) => withFallback('/users.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/users.php${q ? '?' + q : ''}`)
    },
    local: () => fallback.getUsers(params),
  }),
  createUser: (data) => withFallback('/users.php', {
    remote: () => request('/users.php', { method: 'POST', body: data }),
    local: () => fallback.createUser(data),
  }),
  updateUser: (id, data) => withFallback('/users.php', {
    remote: () => request(`/users.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => fallback.updateUser(id, data),
  }),
  toggleUserActive: (id) => withFallback('/users.php?action=toggle-active', {
    remote: () => request(`/users.php?id=${id}&action=toggle-active`, { method: 'POST', body: {} }),
    local: () => fallback.toggleUserActive(id),
  }),
  resetUserPassword: (id) => withFallback('/users.php?action=reset-password', {
    remote: () => request(`/users.php?id=${id}&action=reset-password`, { method: 'POST', body: {} }),
    local: () => fallback.resetUserPassword(id),
  }),
  changeOwnPassword: (data) => withFallback('/users.php?action=change-own-password', {
    remote: () => request('/users.php?action=change-own-password', { method: 'POST', body: data }),
    local: () => ({ message: 'Password changed successfully.' }),
  }),
  savePermSetting: (key, value) => withFallback('/users.php?action=save-setting', {
    remote: () => request('/users.php?action=save-setting', { method: 'POST', body: { key, value } }),
    local: () => ({ saved: true }),
  }),
  getPermSettings: () => withFallback('/users.php?action=get-settings', {
    remote: () => request('/users.php?action=get-settings'),
    local: () => [],
  }),

  getMemberPortalAccounts: (params = {}) => withFallback('/member-portal-accounts.php', {
    remote: async () => {
      const q = new URLSearchParams(params).toString()
      return request(`/member-portal-accounts.php${q ? '?' + q : ''}`)
    },
    local: () => {
      const settings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}')
      return settings.memberPortalAccess || []
    },
  }),
  createMemberPortalAccount: (data) => withFallback('/member-portal-accounts.php', {
    remote: () => request('/member-portal-accounts.php', { method: 'POST', body: data }),
    local: () => data,
  }),
  updateMemberPortalAccount: (id, data) => withFallback('/member-portal-accounts.php', {
    remote: () => request(`/member-portal-accounts.php?id=${id}`, { method: 'PUT', body: data }),
    local: () => ({ id, ...data }),
  }),
  toggleMemberPortalAccount: (id) => withFallback('/member-portal-accounts.php?action=toggle-active', {
    remote: () => request(`/member-portal-accounts.php?id=${id}&action=toggle-active`, { method: 'POST', body: {} }),
    local: () => ({ id }),
  }),
  resetMemberPortalPassword: (id) => withFallback('/member-portal-accounts.php?action=reset-password', {
    remote: () => request(`/member-portal-accounts.php?id=${id}&action=reset-password`, { method: 'POST', body: {} }),
    local: () => ({ id, temp_password: 'member123' }),
  }),
  provisionAllMembers: () => request('/member-portal-accounts.php?action=provision-all', { method: 'POST', body: {} }),
  provisionOneMember: (memberId) => request(`/member-portal-accounts.php?id=${memberId}&action=provision-one`, { method: 'POST', body: {} }),
  getAllMembersWithPortalStatus: (search = '') => request(`/member-portal-accounts.php?action=all-members${search ? '&search=' + encodeURIComponent(search) : ''}`),

  getLandingSettings: () => request('/landing-settings.php'),
  saveLandingSettings: (data) => request('/landing-settings.php?action=save', { method: 'POST', body: data }),
  uploadLandingImage: async (slot, file) => {
    const session = JSON.parse(localStorage.getItem('crs-admin-session') || 'null')
    const formData = new FormData()
    formData.append('image', file)
    formData.append('slot', slot)
    const res = await fetch(`${BASE}/landing-settings.php?action=upload`, {
      method: 'POST',
      headers: session?.token ? { Authorization: `Bearer ${session.token}` } : {},
      body: formData,
    })
    const json = await res.json()
    if (!json.success) throw new Error(json.message || 'Upload failed')
    return json.data
  },



}

export const auth = {
  login: (data) => request('/admin-auth.php', { method: 'POST', body: data }),
  logout: async () => {
    // Use raw fetch — avoids the 401 handler in request() which would redirect
    // to /login before handleLogout() can redirect to /landing/
    try {
      const session = JSON.parse(localStorage.getItem('crs-admin-session') || 'null')
      await fetch(`${BASE}/admin-auth.php?action=logout`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(session?.token ? { Authorization: `Bearer ${session.token}` } : {}) },
        body: JSON.stringify({}),
      })
    } catch { /* server-side failure must not block local clear */ }
    localStorage.removeItem('crs-admin-session')
  },
  getSession: () => JSON.parse(localStorage.getItem('crs-admin-session') || 'null'),
  isAuthenticated: () => {
    const s = JSON.parse(localStorage.getItem('crs-admin-session') || 'null')
    if (!s?.token || !s?.expires_at) return false
    return new Date(s.expires_at) > new Date()
  },
}
