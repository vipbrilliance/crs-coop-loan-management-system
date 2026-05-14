const CRS_API_BASE = window.CRS_API_BASE || 'http://localhost/crs-coop/backend/api'
const CRS_SETTINGS_KEY = 'crs-coop-preview-settings'
const CRS_DATA_KEY = 'crs-coop-preview-data'

const demoMember = {
  id: 1,
  member_no: 'CRS-00081',
  first_name: 'Josefina',
  last_name: 'Monteverde',
  email: 'j.monteverde@crsholdings.test',
  contact: '09171234567',
  address: 'A.C. Cortes Avenue, Mandaue City, Cebu',
  company: 'CRS Holdings Corporation',
  department: 'Operations',
  position: 'Loan Officer',
  status: 'REGULAR',
  member_status: 'ACTIVE',
  monthly_salary: 42000,
  share_capital: 41000,
}

const demoPortal = {
  member: demoMember,
  loans: [
    {
      loan_no: 'LN-2026-0001',
      type: 'Salary Loan',
      amount: 60000,
      outstanding: 66441.67,
      next_due_date: '2026-05-15',
      next_due_amount: 1133.33,
      status: 'ACTIVE',
    },
  ],
  payments: [
    { date: '2026-05-11', reference: 'OR-2026-0007', loan_no: 'LN-2026-0001', amount: 1500, status: 'POSTED' },
    { date: '2026-05-01', reference: 'OR-2026-0006', loan_no: 'LN-2026-0001', amount: 1000, status: 'POSTED' },
  ],
  shareCapital: [
    { date: '2026-05-11', reference: 'SC-2026-00007', type: 'DEPOSIT', amount: 1500, balance: 41000 },
    { date: '2026-01-01', reference: 'SC-OPEN-CRS-00081', type: 'OPENING', amount: 38500, balance: 38500 },
  ],
  beneficiaries: [
    { name: 'Josefina Monteverde Jr.', relationship: 'Child', allocation: 60, type: 'Primary', contact: '09171234567' },
    { name: 'Ana Monteverde', relationship: 'Spouse', allocation: 40, type: 'Primary', contact: '09170000001' },
  ],
}

function readJson(key, fallback) {
  try {
    return JSON.parse(localStorage.getItem(key) || 'null') || fallback
  } catch {
    return fallback
  }
}

function fullName(member) {
  return [member.first_name, member.middle_name, member.last_name].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim()
}

function previewMembers() {
  const data = readJson(CRS_DATA_KEY, null)
  return data?.members?.length ? data.members : [demoMember]
}

function previewSettings() {
  return readJson(CRS_SETTINGS_KEY, {})
}

function findPortalAccess(identifier, password) {
  const normalized = String(identifier || '').trim().toLowerCase()
  const settings = previewSettings()
  const accessRows = settings.memberPortalAccess || []
  return accessRows.find(access => {
    const keys = [access.username, access.email, access.member_no].map(value => String(value || '').toLowerCase())
    const savedPassword = access.temporary_password || access.password
    return access.active !== false && keys.includes(normalized) && savedPassword === password
  })
}

function memberFromAccess(access) {
  const member = previewMembers().find(item => Number(item.id) === Number(access.member_id) || item.member_no === access.member_no)
  if (!member) {
    const [first_name = '', ...rest] = String(access.member_name || '').split(' ')
    return {
      ...demoMember,
      id: access.member_id,
      member_no: access.member_no,
      first_name,
      last_name: rest.join(' '),
      email: access.email,
    }
  }
  return member
}

function portalForMember(member) {
  const data = readJson(CRS_DATA_KEY, {})
  const loans = (data.loans || demoPortal.loans).filter(loan => Number(loan.member_id || member.id) === Number(member.id))
  return {
    ...demoPortal,
    member,
    loans: loans.length ? loans.map(loan => ({
      loan_no: loan.loan_no,
      type: loan.loan_type_label || loan.type || 'Loan',
      amount: loan.amount,
      outstanding: loan.outstanding || loan.amount,
      next_due_date: loan.next_due_date || loan.first_due_date,
      next_due_amount: loan.next_due_amount || 0,
      status: loan.status,
    })) : [],
  }
}

async function apiRequest(path, options = {}) {
  const response = await fetch(`${CRS_API_BASE}${path}`, {
    headers: { 'Content-Type': 'application/json', ...options.headers },
    ...options,
  })

  const json = await response.json()
  if (!json.success) throw new Error(json.message || 'API request failed')
  return json.data
}

window.CrsMemberApi = {
  async login(identifier, password) {
    try {
      return await apiRequest('/member-auth.php', {
        method: 'POST',
        body: JSON.stringify({ identifier, password }),
      })
    } catch {
      if (!identifier || !password) throw new Error('Enter your member number/email and password.')
      const access = findPortalAccess(identifier, password)
      if (access) {
        const member = memberFromAccess(access)
        return {
          token: `preview-member-${access.id}`,
          member,
          access: {
            id: access.id,
            username: access.username,
            modules: access.modules || [],
            force_password_change: access.force_password_change,
          },
        }
      }

      const demoMatch = ['crs-00081', 'j.monteverde@crsholdings.test'].includes(String(identifier).toLowerCase())
      if (demoMatch && password === 'member123') return { token: 'demo-member-token', member: demoMember }
      throw new Error('Invalid member login. Check the username/email and temporary password from CRS Settings.')
    }
  },

  async getPortalData() {
    try {
      const session = window.CrsMemberSession?.get?.()
      return await apiRequest('/member-portal.php', {
        headers: session?.token ? { Authorization: `Bearer ${session.token}` } : {},
      })
    } catch {
      const session = window.CrsMemberSession?.get?.()
      return session?.member ? portalForMember(session.member) : demoPortal
    }
  },
}
