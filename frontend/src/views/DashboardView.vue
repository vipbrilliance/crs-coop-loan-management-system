<template>
  <div class="dash-wrap">
    <!-- Header + Stats Bar -->
    <header class="dash-header">
      <div class="dash-header-top">
        <div>
          <div class="dash-title">Dashboard</div>
          <div class="dash-sub">CRS Holdings · Employees Credit Cooperative</div>
        </div>
        <div class="dash-header-actions">
          <span class="dash-date">{{ todayLabel }}</span>
          <button class="dash-btn" @click="load">Refresh</button>
        </div>
      </div>

      <!-- Stat Cards Bar -->
      <div class="stat-bar" v-if="!loading">
        <div class="stat-chip">
          <div class="chip-label">Total Share Capital</div>
          <div class="chip-value">{{ peso(shareCapital.total_balance) }}</div>
        </div>
        <div class="stat-chip">
          <div class="chip-label">Total Active Members</div>
          <div class="chip-value">{{ stats.total_members ?? 0 }}</div>
        </div>
        <div class="stat-chip">
          <div class="chip-label">Collection Rate</div>
          <div class="chip-value" :style="{ color: collectionRateColor }">{{ stats.collection_rate ?? 0 }}%</div>
          <div class="chip-sub"><span :style="{ color: collectionDeltaColor }">{{ collectionDeltaLabel }}</span> vs last month</div>
        </div>
        <div class="stat-chip">
          <div class="chip-label">Active Loans</div>
          <div class="chip-value">{{ stats.active_loans ?? 0 }}</div>
          <div class="chip-sub">+{{ stats.new_loans_this_month ?? 0 }} this month</div>
        </div>
        <div class="stat-chip">
          <div class="chip-label">Total Outstanding</div>
          <div class="chip-value">{{ peso(stats.total_outstanding) }}</div>
          <div class="chip-sub">across all active loans</div>
        </div>
        <div class="stat-chip">
          <div class="chip-label">Overdue Accounts</div>
          <div class="chip-value" style="color:#EF4444;">{{ stats.overdue_count ?? 0 }}</div>
          <div class="chip-sub">{{ peso(stats.overdue_balance) }} outstanding</div>
        </div>
      </div>
    </header>

    <main class="dash-body">
      <!-- Loading -->
      <div v-if="loading" class="dash-loading">
        <div class="dash-spinner"></div>
      </div>

      <template v-else>

        <!-- Row 2: Monthly Collections + Loan Status -->
        <div class="charts-row">
          <!-- Monthly Collections bar chart -->
          <div class="panel">
            <div class="panel-title">Monthly Collections</div>
            <div class="legend">
              <span class="legend-item"><span class="legend-dot" style="background:#3B82F6;"></span>Expected</span>
              <span class="legend-item"><span class="legend-dot" style="background:#1D9E75;"></span>Collected</span>
            </div>
            <svg :viewBox="`0 0 ${mcW} ${mcH}`" class="chart-svg" preserveAspectRatio="xMidYMid meet">
              <!-- Gridlines -->
              <line v-for="(gl, i) in mcGridlines" :key="'gl'+i"
                :x1="mcLeft" :y1="gl.y" :x2="mcW - 8" :y2="gl.y"
                stroke="#E5E7EB" stroke-width="1" />
              <!-- Y labels -->
              <text v-for="(gl, i) in mcGridlines" :key="'yl'+i"
                :x="mcLeft - 4" :y="gl.y + 3" text-anchor="end" class="axis-label">{{ gl.label }}</text>
              <!-- Bars -->
              <g v-for="(grp, i) in mcGroups" :key="'grp'+i">
                <rect :x="grp.expX" :y="grp.expY" :width="mcBarW" :height="grp.expH" fill="#3B82F6" rx="2"/>
                <rect :x="grp.colX" :y="grp.colY" :width="mcBarW" :height="grp.colH" fill="#1D9E75" rx="2"/>
              </g>
              <!-- X labels -->
              <text v-for="(grp, i) in mcGroups" :key="'xl'+i"
                :x="grp.labelX" :y="mcH - 4" text-anchor="middle" class="axis-label">{{ grp.month }}</text>
            </svg>
          </div>

          <!-- Loan Status Donut -->
          <div class="panel">
            <div class="panel-title">Loan Status</div>
            <div class="donut-wrap">
              <svg viewBox="0 0 200 200" class="donut-svg">
                <circle v-if="loanStatusTotal === 0" cx="100" cy="100" r="70" fill="none"
                  stroke="#E5E7EB" stroke-width="40"/>
                <circle v-for="(seg, i) in donutSegments" :key="i"
                  cx="100" cy="100" r="70" fill="none"
                  :stroke="seg.color" stroke-width="40"
                  :stroke-dasharray="`${seg.dash} ${seg.gap}`"
                  :stroke-dashoffset="seg.offset"
                  transform="rotate(-90 100 100)"
                />
                <text x="100" y="96" text-anchor="middle" style="font-size:22px;font-weight:700;fill:#111827;">{{ loanStatusTotal }}</text>
                <text x="100" y="114" text-anchor="middle" style="font-size:10px;fill:#6B7280;">TOTAL</text>
              </svg>
              <div class="donut-legend">
                <div v-for="seg in donutSegments" :key="seg.status" class="donut-legend-item">
                  <span class="legend-dot" :style="{ background: seg.color }"></span>
                  <span class="donut-legend-status">{{ seg.status }}</span>
                  <span class="donut-legend-count">{{ seg.count }}</span>
                </div>
                <div v-if="donutSegments.length === 0" class="donut-legend-item">
                  <span class="donut-legend-status" style="color:#9CA3AF;">No data</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 3: New Loans Disbursed + Outstanding by Loan Type -->
        <div class="charts-row-2">
          <!-- New Loans Disbursed — dual axis -->
          <div class="panel">
            <div class="panel-title">New Loans Disbursed — Last 6 Months</div>
            <div class="legend">
              <span class="legend-item"><span class="legend-dot" style="background:#7C3AED;"></span>Count (bars)</span>
              <span class="legend-item">
                <svg width="24" height="10" style="margin-right:4px;vertical-align:middle;">
                  <line x1="0" y1="5" x2="24" y2="5" stroke="#EC4899" stroke-width="2" stroke-dasharray="4 2"/>
                </svg>
                <span style="color:#6B7280;font-size:12px;">Amount (₱000s)</span>
              </span>
            </div>
            <svg :viewBox="`0 0 ${dbW} ${dbH}`" class="chart-svg" preserveAspectRatio="xMidYMid meet">
              <!-- Gridlines -->
              <line v-for="(gl, i) in dbGridlines" :key="'dgl'+i"
                :x1="dbLeft" :y1="gl.y" :x2="dbW - dbRight" :y2="gl.y"
                stroke="#E5E7EB" stroke-width="1"/>
              <!-- Left Y labels (count) -->
              <text v-for="(gl, i) in dbGridlines" :key="'dlyl'+i"
                :x="dbLeft - 4" :y="gl.y + 3" text-anchor="end" class="axis-label">{{ gl.countLabel }}</text>
              <!-- Right Y labels (amount) -->
              <text v-for="(gl, i) in dbGridlines" :key="'dryl'+i"
                :x="dbW - dbRight + 4" :y="gl.y + 3" text-anchor="start" class="axis-label">{{ gl.amtLabel }}</text>
              <!-- Purple bars -->
              <rect v-for="(b, i) in dbBars" :key="'db'+i"
                :x="b.x" :y="b.y" :width="b.w" :height="b.h" fill="#7C3AED" rx="2"/>
              <!-- Pink dashed line -->
              <polyline v-if="dbLinePoints.length > 1"
                :points="dbLinePoints"
                fill="none" stroke="#EC4899" stroke-width="2"
                stroke-dasharray="6 3"/>
              <!-- Line dots -->
              <circle v-for="(pt, i) in dbLineDots" :key="'dlpt'+i"
                :cx="pt.x" :cy="pt.y" r="3" fill="#EC4899"/>
              <!-- X labels -->
              <text v-for="(b, i) in dbBars" :key="'dxl'+i"
                :x="b.x + b.w / 2" :y="dbH - 4" text-anchor="middle" class="axis-label">{{ b.month }}</text>
            </svg>
          </div>

          <!-- Outstanding by Loan Type — horizontal bars -->
          <div class="panel">
            <div class="panel-title">Outstanding by Loan Type</div>
            <div class="hbar-list">
              <div v-if="loanTypes.length === 0" class="hbar-empty">No active loan data</div>
              <div v-for="(row, i) in loanTypeRows" :key="row.label" class="hbar-item">
                <div class="hbar-top">
                  <span class="hbar-label">{{ row.label }}</span>
                  <span class="hbar-value">{{ peso(row.amount) }}</span>
                </div>
                <div class="hbar-track">
                  <div class="hbar-fill" :style="{ width: row.pct + '%', background: row.color }"></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 4: Overdue Aging + Share Capital Activity -->
        <div class="charts-row-3">
          <!-- Overdue Aging bar chart -->
          <div class="panel">
            <div class="panel-title">Overdue Aging</div>
            <div class="legend">
              <span class="legend-item"><span class="legend-dot" style="background:#1D9E75;"></span>0–30 days</span>
              <span class="legend-item"><span class="legend-dot" style="background:#F59E0B;"></span>31–60 days</span>
              <span class="legend-item"><span class="legend-dot" style="background:#F97316;"></span>61–90 days</span>
              <span class="legend-item"><span class="legend-dot" style="background:#EF4444;"></span>90+ days</span>
            </div>
            <svg :viewBox="`0 0 ${agW} ${agH}`" class="chart-svg" preserveAspectRatio="xMidYMid meet">
              <line v-for="(gl, i) in agGridlines" :key="'agl'+i"
                :x1="agLeft" :y1="gl.y" :x2="agW - 8" :y2="gl.y"
                stroke="#E5E7EB" stroke-width="1"/>
              <text v-for="(gl, i) in agGridlines" :key="'agyl'+i"
                :x="agLeft - 4" :y="gl.y + 3" text-anchor="end" class="axis-label">{{ gl.label }}</text>
              <rect v-for="(b, i) in agBars" :key="'agb'+i"
                :x="b.x" :y="b.y" :width="b.w" :height="b.h" :fill="b.color" rx="2"/>
              <text v-for="(b, i) in agBars" :key="'agxl'+i"
                :x="b.x + b.w / 2" :y="agH - 4" text-anchor="middle" class="axis-label">{{ b.label }}</text>
            </svg>
          </div>

          <!-- Share Capital Activity (no redundant total — shown in top bar) -->
          <div class="panel sc-panel">
            <div class="panel-title">Share Capital Activity</div>
            <div class="sc-stat-row">
              <div class="sc-stat">
                <div class="sc-stat-label">Active Members</div>
                <div class="sc-stat-value">{{ shareCapital.active_members ?? 0 }}</div>
              </div>
              <div class="sc-stat">
                <div class="sc-stat-label">Credits This Month</div>
                <div class="sc-stat-value sc-green">+{{ peso(shareCapital.credits_this_month) }}</div>
              </div>
              <div class="sc-stat">
                <div class="sc-stat-label">Debits This Month</div>
                <div class="sc-stat-value sc-red">-{{ peso(shareCapital.debits_this_month) }}</div>
              </div>
            </div>
            <div class="sc-net">
              <span class="sc-net-label">Net Movement</span>
              <span :class="scNetPositive ? 'sc-green' : 'sc-red'" class="sc-net-value">
                {{ scNetPositive ? '+' : '' }}{{ peso((shareCapital.credits_this_month ?? 0) - (shareCapital.debits_this_month ?? 0)) }}
              </span>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { api } from '../composables/useApi.js'

const loading = ref(true)
const stats = ref({})
const monthly = ref([])
const loanStatus = ref([])
const loanTypes = ref([])
const shareCapital = ref({})
const overdueAging = ref({})
const disbursed = ref([])

const today = new Date()
const todayLabel = today.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

const peso = (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

async function load() {
  loading.value = true
  try {
    const d = await api.getDashboard()
    stats.value = d.stats || {}
    monthly.value = d.monthly_collections || []
    loanStatus.value = d.loan_status || []
    loanTypes.value = d.loan_types || []
    shareCapital.value = d.share_capital || {}
    overdueAging.value = d.overdue_aging || {}
    disbursed.value = d.disbursed_monthly || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const scNetPositive = computed(() =>
  (shareCapital.value.credits_this_month ?? 0) >= (shareCapital.value.debits_this_month ?? 0)
)

// --- Collection rate color & delta ---
const collectionRateColor = computed(() => {
  const r = stats.value.collection_rate ?? 0
  if (r >= 80) return '#1D9E75'
  if (r >= 50) return '#F59E0B'
  return '#EF4444'
})

const collectionDeltaLabel = computed(() => {
  if (monthly.value.length < 2) return ''
  const last = monthly.value[monthly.value.length - 1]?.rate ?? 0
  const prev = monthly.value[monthly.value.length - 2]?.rate ?? 0
  const delta = +(last - prev).toFixed(1)
  return delta >= 0 ? `+${delta}%` : `${delta}%`
})

const collectionDeltaColor = computed(() => {
  if (monthly.value.length < 2) return '#6B7280'
  const last = monthly.value[monthly.value.length - 1]?.rate ?? 0
  const prev = monthly.value[monthly.value.length - 2]?.rate ?? 0
  return last >= prev ? '#1D9E75' : '#EF4444'
})

// ============================================================
// MONTHLY COLLECTIONS SVG (grouped bar chart)
// ============================================================
const mcW = 560
const mcH = 180
const mcLeft = 52
const mcBottom = 28
const mcPlotH = mcH - mcBottom - 8
const mcBarW = 16
const mcGapBetweenBars = 4
const mcGroupW = computed(() => {
  const n = monthly.value.length || 6
  return (mcW - mcLeft - 8) / n
})

const mcMax = computed(() => {
  const vals = monthly.value.flatMap(m => [m.expected, m.collected])
  return Math.max(...vals, 1)
})

const mcGridlines = computed(() => {
  const steps = 5
  return Array.from({ length: steps + 1 }, (_, i) => {
    const val = (mcMax.value / steps) * (steps - i)
    const y = 8 + (i / steps) * mcPlotH
    return { y, label: val >= 1000 ? `₱${Math.round(val / 1000)}k` : `₱${Math.round(val)}` }
  })
})

const mcGroups = computed(() => {
  return monthly.value.map((m, i) => {
    const gx = mcLeft + i * mcGroupW.value
    const cx = gx + (mcGroupW.value - mcBarW * 2 - mcGapBetweenBars) / 2
    const expH = Math.max(2, (m.expected / mcMax.value) * mcPlotH)
    const colH = Math.max(2, (m.collected / mcMax.value) * mcPlotH)
    return {
      month: m.month,
      expX: cx,
      expY: 8 + mcPlotH - expH,
      expH,
      colX: cx + mcBarW + mcGapBetweenBars,
      colY: 8 + mcPlotH - colH,
      colH,
      labelX: cx + mcBarW + mcGapBetweenBars / 2,
    }
  })
})

// ============================================================
// LOAN STATUS DONUT
// ============================================================
const STATUS_COLORS = {
  ACTIVE: '#1D9E75',
  PENDING: '#3B82F6',
  CLOSED: '#F59E0B',
  DRAFT: '#9CA3AF',
  APPROVED: '#7C3AED',
  RELEASED: '#06B6D4',
}

const loanStatusTotal = computed(() => loanStatus.value.reduce((s, r) => s + Number(r.count), 0))

const donutSegments = computed(() => {
  const total = loanStatusTotal.value
  if (total === 0) return []
  const circumference = 2 * Math.PI * 70
  let offset = 0
  return loanStatus.value.map(r => {
    const pct = Number(r.count) / total
    const dash = pct * circumference
    const gap = circumference - dash
    const seg = {
      status: r.status,
      count: Number(r.count),
      color: STATUS_COLORS[r.status] || '#6B7280',
      dash,
      gap,
      offset: -offset,
    }
    offset += dash
    return seg
  })
})

// ============================================================
// NEW LOANS DISBURSED (dual-axis SVG)
// ============================================================
const dbW = 500
const dbH = 180
const dbLeft = 48
const dbRight = 48
const dbBottom = 28
const dbPlotH = dbH - dbBottom - 8
const dbPlotW = computed(() => dbW - dbLeft - dbRight)

const dbMaxCount = computed(() => Math.max(...disbursed.value.map(d => d.count), 1))
const dbMaxAmt = computed(() => Math.max(...disbursed.value.map(d => d.amount), 1))

const dbGridlines = computed(() => {
  const steps = 4
  return Array.from({ length: steps + 1 }, (_, i) => {
    const y = 8 + (i / steps) * dbPlotH
    const countVal = Math.round((dbMaxCount.value / steps) * (steps - i))
    const amtVal = (dbMaxAmt.value / steps) * (steps - i) / 1000
    return {
      y,
      countLabel: countVal,
      amtLabel: `₱${amtVal.toFixed(0)}k`,
    }
  })
})

const dbBarW = computed(() => {
  const n = disbursed.value.length || 6
  return Math.max(8, (dbPlotW.value / n) * 0.45)
})

const dbBars = computed(() => {
  const n = disbursed.value.length || 1
  const step = dbPlotW.value / n
  return disbursed.value.map((d, i) => {
    const barH = Math.max(2, (d.count / dbMaxCount.value) * dbPlotH)
    return {
      month: d.month,
      x: dbLeft + i * step + (step - dbBarW.value) / 2,
      y: 8 + dbPlotH - barH,
      w: dbBarW.value,
      h: barH,
    }
  })
})

const dbLineDots = computed(() => {
  const n = disbursed.value.length || 1
  const step = dbPlotW.value / n
  return disbursed.value.map((d, i) => {
    const y = 8 + dbPlotH - (d.amount / dbMaxAmt.value) * dbPlotH
    const x = dbLeft + i * step + step / 2
    return { x, y }
  })
})

const dbLinePoints = computed(() => {
  return dbLineDots.value.map(pt => `${pt.x},${pt.y}`).join(' ')
})

// ============================================================
// OUTSTANDING BY LOAN TYPE (horizontal bars)
// ============================================================
const LOAN_TYPE_COLORS = ['#1D9E75', '#3B82F6', '#F59E0B', '#7C3AED', '#EF4444', '#EC4899']

const loanTypeRows = computed(() => {
  const maxAmt = Math.max(...loanTypes.value.map(t => Number(t.amount)), 1)
  return loanTypes.value.map((t, i) => ({
    label: t.label,
    amount: Number(t.amount),
    pct: Math.max(2, (Number(t.amount) / maxAmt) * 100),
    color: LOAN_TYPE_COLORS[i % LOAN_TYPE_COLORS.length],
  }))
})

// ============================================================
// OVERDUE AGING (bar chart)
// ============================================================
const agW = 480
const agH = 180
const agLeft = 60
const agBottom = 28
const agPlotH = agH - agBottom - 8

const agValues = computed(() => [
  { label: '0–30 days', value: overdueAging.value.bucket_30 ?? 0, color: '#1D9E75' },
  { label: '31–60 days', value: overdueAging.value.bucket_60 ?? 0, color: '#F59E0B' },
  { label: '61–90 days', value: overdueAging.value.bucket_90 ?? 0, color: '#F97316' },
  { label: '90+ days', value: overdueAging.value.bucket_90plus ?? 0, color: '#EF4444' },
])

const agMax = computed(() => Math.max(...agValues.value.map(v => v.value), 1))

const agGridlines = computed(() => {
  const steps = 4
  return Array.from({ length: steps + 1 }, (_, i) => {
    const val = (agMax.value / steps) * (steps - i)
    const y = 8 + (i / steps) * agPlotH
    return { y, label: val >= 1000 ? `₱${Math.round(val / 1000)}k` : `₱${Math.round(val)}` }
  })
})

const agBars = computed(() => {
  const n = agValues.value.length
  const plotW = agW - agLeft - 8
  const barW = (plotW / n) * 0.55
  const step = plotW / n
  return agValues.value.map((v, i) => {
    const barH = Math.max(2, (v.value / agMax.value) * agPlotH)
    return {
      label: v.label,
      x: agLeft + i * step + (step - barW) / 2,
      y: 8 + agPlotH - barH,
      w: barW,
      h: barH,
      color: v.color,
    }
  })
})
</script>

<style scoped>
/* ── Shell ── */
.dash-wrap { display: flex; flex-direction: column; height: 100%; overflow: hidden; background: #F6F7FB; }

/* ── Header ── */
.dash-header { padding: 18px 28px 0; border-bottom: 1px solid #E3E7EF; flex-shrink: 0; background: #fff; }
.dash-header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.dash-title { font-size: 20px; font-weight: 800; color: #111827; }
.dash-sub { font-size: 12px; color: #9CA3AF; margin-top: 2px; }
.dash-header-actions { display: flex; align-items: center; gap: 10px; }
.dash-date { font-size: 12px; color: #9CA3AF; }
.dash-btn { padding: 6px 14px; border-radius: 7px; border: 1px solid #E3E7EF; background: #fff; color: #374151; font-size: 12px; font-weight: 600; cursor: pointer; }
.dash-btn:hover { background: #F9FAFB; }

/* ── Stat bar ── */
.stat-bar { display: grid; grid-template-columns: repeat(6, 1fr); margin: 0 -28px; border-top: 1px solid #E3E7EF; }
.stat-chip { padding: 10px 16px 12px; border-right: 1px solid #E3E7EF; min-width: 0; }
.stat-chip:last-child { border-right: none; }
.chip-label { font-size: 9.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chip-value { font-size: 16px; font-weight: 800; color: #111827; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chip-sub { font-size: 10.5px; color: #9CA3AF; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Body ── */
.dash-body { flex: 1; overflow: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.dash-loading { display: flex; align-items: center; justify-content: center; min-height: 300px; }
.dash-spinner { width: 32px; height: 32px; border: 3px solid #E5E7EB; border-top-color: #1D9E75; border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Grid rows ── */
.charts-row   { display: grid; grid-template-columns: 3fr 2fr; gap: 16px; align-items: stretch; }
.charts-row-2 { display: grid; grid-template-columns: 1fr 1fr;  gap: 16px; align-items: stretch; }
.charts-row-3 { display: grid; grid-template-columns: 3fr 2fr; gap: 16px; align-items: stretch; }

/* ── Panel ── */
.panel { background: #fff; border: 1px solid #E3E7EF; border-radius: 10px; padding: 18px 20px; min-width: 0; }
.panel-title { font-size: 10px; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 12px; }

/* ── SVG / Charts ── */
.chart-svg { width: 100%; height: auto; display: block; }
.axis-label { font-size: 10px; fill: #9CA3AF; }
.legend { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 10px; align-items: center; }
.legend-item { display: flex; align-items: center; font-size: 11px; color: #6B7280; }
.legend-dot { width: 9px; height: 9px; border-radius: 2px; display: inline-block; margin-right: 5px; flex-shrink: 0; }

/* ── Donut ── */
.donut-wrap { display: flex; flex-direction: column; align-items: center; gap: 14px; }
.donut-svg { width: 140px; height: 140px; }
.donut-legend { display: flex; flex-direction: column; gap: 7px; width: 100%; }
.donut-legend-item { display: flex; align-items: center; gap: 8px; }
.donut-legend-status { flex: 1; font-size: 10px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #6B7280; }
.donut-legend-count { font-weight: 700; color: #111827; font-size: 13px; }

/* ── Horizontal bars ── */
.hbar-list { display: flex; flex-direction: column; gap: 13px; }
.hbar-empty { color: #9CA3AF; font-size: 12px; padding: 20px 0; text-align: center; }
.hbar-item { display: flex; flex-direction: column; gap: 5px; }
.hbar-top { display: flex; justify-content: space-between; align-items: baseline; }
.hbar-label { font-size: 12px; color: #374151; font-weight: 500; }
.hbar-value { font-size: 11px; color: #374151; font-weight: 600; white-space: nowrap; }
.hbar-track { height: 8px; background: #F3F4F6; border-radius: 999px; overflow: hidden; }
.hbar-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }

/* ── Share Capital Activity ── */
.sc-panel { display: flex; flex-direction: column; gap: 0; }
.sc-stat-row { display: flex; flex-direction: column; gap: 0; border: 1px solid #F3F4F6; border-radius: 8px; overflow: hidden; margin-bottom: 12px; }
.sc-stat { padding: 12px 14px; border-bottom: 1px solid #F3F4F6; }
.sc-stat:last-child { border-bottom: none; }
.sc-stat-label { font-size: 10px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #9CA3AF; margin-bottom: 4px; }
.sc-stat-value { font-size: 18px; font-weight: 700; color: #111827; }
.sc-green { color: #1D9E75 !important; }
.sc-red { color: #EF4444 !important; }
.sc-net { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F9FAFB; border-radius: 8px; }
.sc-net-label { font-size: 11px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: .05em; }
.sc-net-value { font-size: 15px; font-weight: 700; }

/* ── Responsive ── */
@media (max-width: 1200px) {
  .stat-bar { grid-template-columns: repeat(3, 1fr); }
  .charts-row, .charts-row-2, .charts-row-3 { grid-template-columns: 1fr; }
}
@media (max-width: 720px) {
  .dash-body { padding: 14px 12px; }
  .stat-bar { grid-template-columns: repeat(2, 1fr); }
}
</style>
