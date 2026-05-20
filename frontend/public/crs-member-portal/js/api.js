const CRS_API_BASE = window.CRS_API_BASE || 'http://localhost:8000/api'

async function apiRequest(path, options = {}) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), 8000)
  let response
  try {
    response = await fetch(`${CRS_API_BASE}${path}`, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      signal: controller.signal,
      ...options,
    })
  } finally {
    clearTimeout(timer)
  }

  const json = await response.json()
  if (!json.success) throw new Error(json.message || 'Request failed.')
  return json.data
}

window.CrsMemberApi = {
  async login(identifier, password) {
    if (!identifier || !password) throw new Error('Enter your member number/email and password.')
    try {
      return await apiRequest('/member-auth.php', {
        method: 'POST',
        body: JSON.stringify({ identifier, password }),
      })
    } catch (e) {
      if (e.name === 'AbortError') throw new Error('Could not connect to the server. Please try again.')
      throw e
    }
  },

  async getPortalData() {
    const session = window.CrsMemberSession?.get?.()
    if (!session?.token) throw new Error('No active session.')
    try {
      return await apiRequest('/member-portal.php', {
        headers: { Authorization: `Bearer ${session.token}` },
      })
    } catch (e) {
      if (e.name === 'AbortError') throw new Error('Could not connect to the server. Please try again.')
      // Session expired — redirect to login
      if (e.message?.toLowerCase().includes('expired') || e.message?.toLowerCase().includes('invalid')) {
        window.CrsMemberSession?.clear?.()
        window.location.href = 'index.html'
        return
      }
      throw e
    }
  },
}
