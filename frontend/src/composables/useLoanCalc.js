// src/composables/useLoanCalc.js
// Client-side loan math — mirrors the PHP backend exactly.
// Diminishing balance schedule.

export function computeSchedule({ principal, termMonths, frequency, annualRate = 0.12 }) {
  const monthlyRate = annualRate / 12
  let periodsPerMonth, periodRateFactor
  if (frequency === 'monthly')   { periodsPerMonth = 1; periodRateFactor = 1; }
  else if (frequency === 'bimonthly') { periodsPerMonth = 2; periodRateFactor = 0.5; }
  else if (frequency === 'weekly')    { periodsPerMonth = 4; periodRateFactor = 0.25; }
  else { periodsPerMonth = 1; periodRateFactor = 1; }

  const nPeriods = termMonths * periodsPerMonth
  const principalPerPeriod = principal / nPeriods
  const schedule = []
  let remaining = principal
  let totalInterest = 0

  for (let i = 0; i < nPeriods; i++) {
    const interest = remaining * monthlyRate * periodRateFactor
    const payment  = principalPerPeriod + interest
    const balance  = Math.max(0, remaining - principalPerPeriod)
    schedule.push({
      period: i + 1,
      principal: +principalPerPeriod.toFixed(2),
      interest:  +interest.toFixed(2),
      payment:   +payment.toFixed(2),
      balance:   +balance.toFixed(2),
    })
    remaining     -= principalPerPeriod
    totalInterest += interest
  }

  return {
    schedule,
    nPeriods,
    totalInterest: +totalInterest.toFixed(2),
    totalPayment:  +(principal + totalInterest).toFixed(2),
    firstPayment:  schedule[0].payment,
    lastPayment:   schedule[nPeriods - 1].payment,
  }
}

export function peso(n) {
  return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export function freqLabel(f) {
  return { monthly: 'Monthly', bimonthly: 'Bi-Monthly', weekly: 'Weekly' }[f] || f
}

export function statusClass(s) {
  return 'badge badge-' + (s || 'draft').toLowerCase()
}
