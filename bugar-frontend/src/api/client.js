import axios from 'axios'

export const TOKEN_KEY = 'bugar_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

// Selalu sisipkan Bearer token (token-based, beda domain dengan backend).
client.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

/**
 * Ambil pesan error yang ramah dari respons Laravel.
 * 422 -> { message, errors: { field: [..] } }, 4xx/5xx lain -> { message }.
 */
export function extractError(error, fallback = 'Terjadi kesalahan. Coba lagi.') {
  const data = error?.response?.data
  if (data?.errors) {
    return Object.values(data.errors).flat().join(' ')
  }
  return data?.message ?? error?.message ?? fallback
}

export default client
