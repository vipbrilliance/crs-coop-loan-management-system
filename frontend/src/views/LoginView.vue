<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../composables/useApi.js'

const router   = useRouter()
const form     = ref({ email: '', password: '' })
const error    = ref('')
const loading  = ref(false)
const showPw   = ref(false)

async function handleLogin() {
  error.value   = ''
  loading.value = true
  try {
    const result = await auth.login(form.value)
    localStorage.setItem('crs-admin-session', JSON.stringify({
      token:      result.token,
      expires_at: result.expires_at,
      user:       result.user,
    }))
    router.push('/')
  } catch (e) {
    error.value = e.message || 'Invalid email or password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="lv-shell">

    <!-- Left brand panel -->
    <div class="lv-brand">
      <div class="lv-brand-inner">
        <!-- Logo -->
        <div class="lv-logo-row">
          <div class="lv-logo">CRS</div>
          <div class="lv-logo-text">
            <div class="lv-logo-name">CRS Holdings Corporation</div>
            <div class="lv-logo-sub">Employees Credit Cooperative</div>
          </div>
        </div>

        <!-- Headline -->
        <div class="lv-headline">
          <h1>Staff Portal</h1>
          <p>Manage loans, members, share capital, and compliance — all in one secure system.</p>
        </div>

        <!-- Stats strip -->
        <div class="lv-stats">
          <div class="lv-stat">
            <div class="lv-stat-num">312</div>
            <div class="lv-stat-lbl">Active Members</div>
          </div>
          <div class="lv-stat-div"></div>
          <div class="lv-stat">
            <div class="lv-stat-num">₱4.62M</div>
            <div class="lv-stat-lbl">Share Capital</div>
          </div>
          <div class="lv-stat-div"></div>
          <div class="lv-stat">
            <div class="lv-stat-num">2.5%</div>
            <div class="lv-stat-lbl">Monthly Rate</div>
          </div>
        </div>

        <!-- Bottom tag -->
        <div class="lv-brand-tag">
          <span class="lv-dot"></span>
          Mandaue City, Cebu · CDA Reg. 9909-XXX
        </div>
      </div>
    </div>

    <!-- Right login card -->
    <div class="lv-card-wrap">
      <div class="lv-card">
        <div class="lv-card-head">
          <div class="lv-eyebrow">Staff Access</div>
          <h2>Sign in to<br><em>CRS Coop</em></h2>
          <p>Use your system account credentials to continue.</p>
        </div>

        <form @submit.prevent="handleLogin" class="lv-form">
          <div class="lv-field">
            <label for="lv-email">Email</label>
            <input
              id="lv-email"
              type="email"
              v-model="form.email"
              required
              autocomplete="username"
              placeholder="admin@crsholdings.ph"
            />
          </div>

          <div class="lv-field">
            <label for="lv-pw">Password</label>
            <div class="lv-pw-wrap">
              <input
                id="lv-pw"
                :type="showPw ? 'text' : 'password'"
                v-model="form.password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
              />
              <button type="button" class="lv-eye" @click="showPw = !showPw" tabindex="-1">
                <svg v-if="!showPw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
          </div>

          <div v-if="error" class="lv-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            {{ error }}
          </div>

          <button type="submit" class="lv-submit" :disabled="loading">
            <span>{{ loading ? 'Signing in…' : 'Sign In' }}</span>
            <svg v-if="!loading" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
            <svg v-else class="lv-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10"/></svg>
          </button>
        </form>

        <a href="/landing/index.html" class="lv-back">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 8H3M7 4L3 8l4 4"/></svg>
          Back to homepage
        </a>
      </div>
    </div>

  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600;700&display=swap');

:root {
  --crimson: #7B1A1A;
  --crimson-dark: #561212;
  --crimson-deep: #3F0B0B;
  --amber: #F6C75A;
}

/* ── Shell ─────────────────────────────────────────── */
.lv-shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1fr 1fr;
  font-family: 'DM Sans', sans-serif;
}

/* ── Brand panel (left) ─────────────────────────────── */
.lv-brand {
  background: #7B1A1A;
  background-image:
    radial-gradient(ellipse 80% 60% at 100% 0%, rgba(246,199,90,.15), transparent 55%),
    radial-gradient(ellipse 60% 50% at 0% 100%, rgba(0,0,0,.3), transparent 60%);
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 56px;
}
.lv-brand::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.05) 1px, transparent 0);
  background-size: 24px 24px;
  mask-image: radial-gradient(ellipse 70% 70% at 30% 40%, #000 30%, transparent 70%);
  pointer-events: none;
}
.lv-brand-inner {
  position: relative;
  z-index: 1;
  max-width: 400px;
  width: 100%;
}

.lv-logo-row {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 56px;
}
.lv-logo {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  font-weight: 900;
  color: #7B1A1A;
  flex-shrink: 0;
}
.lv-logo-name {
  font-family: 'Lora', serif;
  font-size: 13.5px;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
}
.lv-logo-sub {
  font-size: 11px;
  color: rgba(255,255,255,.55);
  margin-top: 2px;
  font-weight: 300;
}

.lv-headline {
  margin-bottom: 48px;
}
.lv-headline h1 {
  font-family: 'Lora', serif;
  font-size: 42px;
  font-weight: 700;
  color: #fff;
  line-height: 1.12;
  letter-spacing: -.5px;
  margin-bottom: 16px;
}
.lv-headline p {
  font-size: 16px;
  font-weight: 300;
  color: rgba(255,255,255,.72);
  line-height: 1.65;
  max-width: 340px;
}

.lv-stats {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 48px;
  background: rgba(255,255,255,.07);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px;
  padding: 18px 24px;
}
.lv-stat { flex: 1; text-align: center; }
.lv-stat-num {
  font-family: 'Lora', serif;
  font-size: 22px;
  font-weight: 700;
  color: #F6C75A;
  line-height: 1;
  margin-bottom: 5px;
}
.lv-stat-lbl {
  font-size: 10.5px;
  font-weight: 600;
  color: rgba(255,255,255,.5);
  text-transform: uppercase;
  letter-spacing: .5px;
}
.lv-stat-div {
  width: 1px;
  height: 36px;
  background: rgba(255,255,255,.15);
  flex-shrink: 0;
}

.lv-brand-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 11.5px;
  color: rgba(255,255,255,.45);
  font-weight: 500;
  letter-spacing: .3px;
}
.lv-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #F6C75A;
  flex-shrink: 0;
}

/* ── Card panel (right) ─────────────────────────────── */
.lv-card-wrap {
  background: #FBF8F4;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px 48px;
}
.lv-card {
  width: min(420px, 100%);
}

.lv-card-head {
  margin-bottom: 36px;
}
.lv-eyebrow {
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 1.6px;
  text-transform: uppercase;
  color: #7B1A1A;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.lv-eyebrow::before {
  content: '';
  width: 18px;
  height: 1px;
  background: #7B1A1A;
}
.lv-card-head h2 {
  font-family: 'Lora', serif;
  font-size: 38px;
  font-weight: 700;
  color: #111827;
  line-height: 1.1;
  letter-spacing: -.4px;
  margin-bottom: 10px;
}
.lv-card-head h2 em {
  font-style: italic;
  color: #7B1A1A;
  font-weight: 600;
}
.lv-card-head p {
  font-size: 14px;
  color: #6B7280;
  font-weight: 400;
  line-height: 1.6;
}

/* ── Form ─────────────────────────────────────────── */
.lv-form { display: flex; flex-direction: column; gap: 18px; }
.lv-field { display: flex; flex-direction: column; gap: 6px; }
.lv-field label {
  font-size: 12px;
  font-weight: 700;
  color: #374151;
  letter-spacing: .5px;
  text-transform: uppercase;
}
.lv-field input {
  height: 48px;
  border: 1.5px solid #E5E7EB;
  border-radius: 9px;
  padding: 0 16px;
  font-size: 14.5px;
  font-family: 'DM Sans', sans-serif;
  color: #111827;
  background: #fff;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
.lv-field input:focus {
  border-color: #7B1A1A;
  box-shadow: 0 0 0 3px rgba(123,26,26,.08);
}
.lv-field input::placeholder { color: #9CA3AF; }

.lv-pw-wrap { position: relative; }
.lv-pw-wrap input { width: 100%; padding-right: 46px; }
.lv-eye {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: #9CA3AF;
  padding: 4px;
  display: flex;
  align-items: center;
  transition: color .15s;
}
.lv-eye:hover { color: #374151; }
.lv-eye svg { width: 16px; height: 16px; }

.lv-error {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  color: #B91C1C;
  font-size: 13px;
  font-weight: 500;
  padding: 10px 14px;
  border-radius: 8px;
}
.lv-error svg { width: 15px; height: 15px; flex-shrink: 0; }

.lv-submit {
  height: 50px;
  background: #7B1A1A;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-size: 15px;
  font-weight: 700;
  font-family: 'DM Sans', sans-serif;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 4px;
  transition: background .2s, transform .15s, box-shadow .2s;
}
.lv-submit:hover:not(:disabled) {
  background: #561212;
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(123,26,26,.28);
}
.lv-submit:disabled { opacity: .65; cursor: not-allowed; }
.lv-submit svg { width: 15px; height: 15px; }
.lv-spinner { animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.lv-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 20px;
  color: #9CA3AF;
  font-size: 12px;
  font-weight: 500;
}
.lv-divider::before,
.lv-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #E5E7EB;
}

.lv-member-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 48px;
  border: 1.5px solid #7B1A1A;
  border-radius: 9px;
  color: #7B1A1A;
  font-size: 14.5px;
  font-weight: 600;
  font-family: 'DM Sans', sans-serif;
  text-decoration: none;
  transition: background .2s, color .2s, transform .15s, box-shadow .2s;
  margin-top: 4px;
}
.lv-member-btn:hover {
  background: #7B1A1A;
  color: #fff;
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(123,26,26,.2);
}
.lv-member-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

.lv-back {
  display: flex;
  align-items: center;
  gap: 6px;
  justify-content: center;
  margin-top: 20px;
  font-size: 13px;
  font-weight: 500;
  color: #6B7280;
  text-decoration: none;
  transition: color .15s;
}
.lv-back:hover { color: #7B1A1A; }
.lv-back svg { width: 14px; height: 14px; }

/* ── Responsive ─────────────────────────────────────── */
@media (max-width: 860px) {
  .lv-shell { grid-template-columns: 1fr; }
  .lv-brand { display: none; }
  .lv-card-wrap { background: #7B1A1A; min-height: 100vh; padding: 40px 24px; }
  .lv-card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 24px 60px rgba(0,0,0,.2); }
}
</style>
