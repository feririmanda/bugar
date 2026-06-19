import { createContext, useContext, useEffect, useState } from 'react'
import client, { getToken, setToken } from '../api/client'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setTokenState] = useState(() => getToken())
  // 'loading' true selama cek token awal ke /me, supaya ProtectedRoute tidak salah redirect.
  const [loading, setLoading] = useState(Boolean(getToken()))

  // Saat refresh halaman: kalau ada token, validasi & ambil data user.
  // (Tanpa token, 'loading' sudah di-init false, jadi cukup berhenti di sini.)
  useEffect(() => {
    if (!token) return

    let aktif = true
    client
      .get('/me')
      .then((res) => {
        if (aktif) setUser(res.data)
      })
      .catch(() => {
        if (aktif) clearAuth()
      })
      .finally(() => {
        if (aktif) setLoading(false)
      })

    return () => {
      aktif = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function clearAuth() {
    setToken(null)
    setTokenState(null)
    setUser(null)
  }

  async function login(email, password) {
    const res = await client.post('/login', { email, password })
    setToken(res.data.token)
    setTokenState(res.data.token)
    setUser(res.data.user)
    return res.data
  }

  async function register(payload) {
    const res = await client.post('/register', payload)
    return res.data
  }

  async function logout() {
    try {
      await client.post('/logout')
    } catch {
      // Abaikan error logout — token tetap dibersihkan di sisi klien.
    }
    clearAuth()
  }

  const value = {
    user,
    token,
    loading,
    isAuthenticated: Boolean(token),
    login,
    register,
    logout,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) {
    throw new Error('useAuth harus dipakai di dalam <AuthProvider>')
  }
  return ctx
}
