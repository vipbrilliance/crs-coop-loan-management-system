const SESSION_KEY = 'crs-member-portal-session'

function getSession() {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null')
  } catch {
    return null
  }
}

function setSession(session) {
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(session))
}

function clearSession() {
  sessionStorage.removeItem(SESSION_KEY)
}

window.CrsMemberSession = {
  get: getSession,
  set: setSession,
  clear: clearSession,
}

// ── Login page ──────────────────────────────────────────
const loginForm = document.getElementById('loginForm')
if (loginForm) {
  // Password toggle
  const togglePw = document.getElementById('togglePw')
  const pwInput  = document.getElementById('loginPassword')
  if (togglePw && pwInput) {
    togglePw.addEventListener('click', () => {
      const show = pwInput.type === 'password'
      pwInput.type = show ? 'text' : 'password'
      togglePw.setAttribute('aria-label', show ? 'Hide password' : 'Show password')
      const icon = document.getElementById('eyeIcon')
      if (icon) {
        icon.innerHTML = show
          ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
          : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
      }
    })
  }

  // Helpers to show/hide loading and error states
  function setLoading(on) {
    const btn     = document.getElementById('submitBtn')
    const label   = document.getElementById('submitLabel')
    const arrow   = document.getElementById('submitArrow')
    const spinner = document.getElementById('submitSpinner')
    if (!btn) return
    btn.disabled = on
    if (label)   label.textContent = on ? 'Signing in…' : 'Sign In'
    if (arrow)   arrow.style.display = on ? 'none' : ''
    if (spinner) spinner.style.display = on ? '' : 'none'
  }

  function showError(msg) {
    const box  = document.getElementById('loginMessage')
    const text = document.getElementById('loginMessageText')
    if (!box || !text) return
    text.textContent = msg
    box.style.display = 'flex'
  }

  function clearError() {
    const box = document.getElementById('loginMessage')
    if (box) box.style.display = 'none'
  }

  loginForm.addEventListener('submit', async event => {
    event.preventDefault()
    clearError()
    const identifier = document.getElementById('loginIdentifier').value.trim()
    const password   = document.getElementById('loginPassword').value

    setLoading(true)
    try {
      const session = await window.CrsMemberApi.login(identifier, password)
      setSession(session)
      window.location.href = 'dashboard.html'
    } catch (error) {
      setLoading(false)
      showError(error.message)
    }
  })
}

// ── Dashboard / other pages ─────────────────────────────
const logoutButton = document.getElementById('logoutButton')
if (logoutButton) {
  logoutButton.addEventListener('click', () => {
    clearSession()
    window.location.href = 'index.html'
  })
}
