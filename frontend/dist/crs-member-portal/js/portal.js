const pesoFormatter = new Intl.NumberFormat('en-PH', {
  style: 'currency',
  currency: 'PHP',
  minimumFractionDigits: 2,
})

function peso(value) {
  return pesoFormatter.format(Number(value || 0))
}

function formatDate(value) {
  return value ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' }) : '-'
}

function setText(id, value) {
  const el = document.getElementById(id)
  if (el) el.textContent = value
}

function renderEmpty(container, text) {
  container.innerHTML = `<div class="empty">${text}</div>`
}

function renderPortal(data) {
  const member = data.member || {}
  const loans = data.loans || []
  const payments = data.payments || []
  const shareCapital = data.shareCapital || []
  const beneficiaries = data.beneficiaries || []
  const activeLoans = loans.filter(loan => loan.status === 'ACTIVE')
  const nextLoan = activeLoans[0]

  setText('memberSubtitle', `${member.first_name || ''} ${member.last_name || ''} · ${member.member_no || ''} · ${member.company || ''}`)
  setText('memberBadge', member.member_status || 'ACTIVE')
  setText('shareCapitalValue', peso(member.share_capital))
  setText('activeLoansValue', String(activeLoans.length))
  setText('nextDueValue', nextLoan ? `${formatDate(nextLoan.next_due_date)} · ${peso(nextLoan.next_due_amount)}` : '-')
  setText('paymentStatusValue', payments[0]?.status || '-')

  const recentActivity = document.getElementById('recentActivity')
  recentActivity.innerHTML = [
    ...payments.slice(0, 2).map(row => ({
      title: `${peso(row.amount)} payment posted`,
      detail: `${row.reference} · ${formatDate(row.date)}`,
      status: row.status,
    })),
    ...shareCapital.slice(0, 1).map(row => ({
      title: `${peso(row.amount)} share capital ${row.type.toLowerCase()}`,
      detail: `${row.reference} · ${formatDate(row.date)}`,
      status: 'POSTED',
    })),
  ].map(item => `
    <div class="activity-row">
      <div>
        <strong>${item.title}</strong>
        <span>${item.detail}</span>
      </div>
      <div class="pill">${item.status}</div>
    </div>
  `).join('')

  const loanList = document.getElementById('loanList')
  if (!loans.length) renderEmpty(loanList, 'No loan records found.')
  else loanList.innerHTML = loans.map(loan => `
    <div class="record-row">
      <div>
        <strong>${loan.type}</strong>
        <span>${loan.loan_no} · Outstanding ${peso(loan.outstanding)}</span>
      </div>
      <div class="pill">${loan.status}</div>
    </div>
  `).join('')

  document.getElementById('paymentRows').innerHTML = payments.map(row => `
    <tr>
      <td>${formatDate(row.date)}</td>
      <td>${row.reference}</td>
      <td>${row.loan_no}</td>
      <td>${peso(row.amount)}</td>
      <td>${row.status}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="empty">No payments found.</td></tr>'

  document.getElementById('shareRows').innerHTML = shareCapital.map(row => `
    <tr>
      <td>${formatDate(row.date)}</td>
      <td>${row.reference}</td>
      <td>${row.type}</td>
      <td>${peso(row.amount)}</td>
      <td>${peso(row.balance)}</td>
    </tr>
  `).join('') || '<tr><td colspan="5" class="empty">No share capital transactions found.</td></tr>'

  const beneficiaryList = document.getElementById('beneficiaryList')
  if (!beneficiaries.length) renderEmpty(beneficiaryList, 'No beneficiaries encoded.')
  else beneficiaryList.innerHTML = beneficiaries.map(row => `
    <div class="record-row">
      <div>
        <strong>${row.name}</strong>
        <span>${row.type} · ${row.relationship} · ${row.contact || 'No contact'}</span>
      </div>
      <div class="pill">${row.allocation}%</div>
    </div>
  `).join('')

  document.getElementById('profileGrid').innerHTML = [
    ['Member No.', member.member_no],
    ['Full Name', `${member.first_name || ''} ${member.last_name || ''}`],
    ['Email', member.email],
    ['Contact', member.contact],
    ['Address', member.address],
    ['Company', member.company],
    ['Department', member.department],
    ['Position', member.position],
    ['Employment Status', member.status],
    ['Monthly Salary', peso(member.monthly_salary)],
  ].map(([label, value]) => `
    <div class="profile-item">
      <span>${label}</span>
      <strong>${value || '-'}</strong>
    </div>
  `).join('')
}

function bindNavigation() {
  document.querySelectorAll('.nav-item').forEach(button => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'))
      document.querySelectorAll('.view-section').forEach(section => section.classList.remove('active'))
      button.classList.add('active')
      document.getElementById(button.dataset.view).classList.add('active')
    })
  })
}

async function boot() {
  const session = window.CrsMemberSession.get()
  if (!session) {
    window.location.href = 'index.html'
    return
  }

  bindNavigation()
  const data = await window.CrsMemberApi.getPortalData()
  renderPortal(data)
}

boot()
