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

const loginForm = document.getElementById('loginForm')
if (loginForm) {
  loginForm.addEventListener('submit', async event => {
    event.preventDefault()
    const message = document.getElementById('loginMessage')
    const identifier = document.getElementById('loginIdentifier').value.trim()
    const password = document.getElementById('loginPassword').value

    message.textContent = 'Signing in...'

    try {
      const session = await window.CrsMemberApi.login(identifier, password)
      setSession(session)
      window.location.href = 'dashboard.html'
    } catch (error) {
      message.textContent = error.message
    }
  })
}

const logoutButton = document.getElementById('logoutButton')
if (logoutButton) {
  logoutButton.addEventListener('click', () => {
    clearSession()
    window.location.href = 'index.html'
  })
}
