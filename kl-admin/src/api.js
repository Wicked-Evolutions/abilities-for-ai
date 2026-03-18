const BASE_URL = window.abilitiesKL?.rest?.url || '/wp-json/abilities-kl/v1'
const NONCE = window.abilitiesKL?.rest?.nonce || ''

async function request(endpoint, options = {}) {
  const url = endpoint.includes('?')
    ? `${BASE_URL}/${endpoint}`
    : `${BASE_URL}/${endpoint}`

  const headers = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': NONCE,
    ...options.headers,
  }

  // Method override for PUT/DELETE (WordPress compatibility).
  let method = options.method || 'GET'
  if (['PUT', 'PATCH', 'DELETE'].includes(method)) {
    headers['X-HTTP-Method-Override'] = method
    method = 'POST'
  }

  const response = await fetch(url, { ...options, method, headers })
  if (!response.ok) {
    const text = await response.text()
    throw new Error(text)
  }
  return response.json()
}

export const api = {
  get(endpoint, params) {
    const qs = params ? '?' + new URLSearchParams(params).toString() : ''
    return request(endpoint + qs)
  },
  post(endpoint, data) {
    return request(endpoint, { method: 'POST', body: JSON.stringify(data) })
  },
  put(endpoint, data) {
    return request(endpoint, { method: 'PUT', body: JSON.stringify(data) })
  },
  del(endpoint) {
    return request(endpoint, { method: 'DELETE' })
  },
}
