<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../composables/useApi.js'

const router   = useRouter()
const form     = ref({ email: '', password: '' })
const error    = ref('')
const loading  = ref(false)

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
  <div class="login-page">
    <div class="lv-card">
      <p class="eyebrow">Secure Access</p>
      <h2>Sign in to CRS Coop</h2>
      <p class="lv-sub">Use your system account to continue.</p>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label" for="lv-email">Email</label>
          <input
            id="lv-email"
            class="form-input"
            type="email"
            v-model="form.email"
            required
            autocomplete="username"
            placeholder="admin@crsholdings.ph"
          />
        </div>

        <div class="form-group">
          <label class="form-label" for="lv-pw">Password</label>
          <input
            id="lv-pw"
            class="form-input"
            type="password"
            v-model="form.password"
            required
            autocomplete="current-password"
            placeholder="Password"
          />
        </div>

        <p v-if="error" class="login-error">{{ error }}</p>

        <button type="submit" class="btn btn-primary lv-submit" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--coop-mid, #F6F7FB);
  padding: 32px 16px;
}

.lv-card {
  width: min(460px, calc(100% - 48px));
  background: var(--coop-surface, #fff);
  border: 1px solid var(--coop-border, #E3E7EF);
  border-radius: 14px;
  padding: 32px;
  box-shadow: 0 18px 50px rgba(31,41,55,.09);
}

.lv-card h2 {
  font-family: var(--font-serif);
  font-size: 38px;
  line-height: 1.05;
  font-weight: 400;
  color: var(--coop-cream, #202838);
  margin: 8px 0 8px;
}

.lv-sub {
  color: var(--coop-muted, #6D7484);
  font-size: 14px;
  margin: 0 0 24px;
}

.lv-submit {
  width: 100%;
  min-height: 48px;
  justify-content: center;
  border-radius: 9px;
}
</style>
