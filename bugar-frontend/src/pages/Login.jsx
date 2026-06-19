import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import client, { extractError } from '../api/client'

const STATUS_PESAN = {
  verified: 'Email berhasil diverifikasi. Silakan login.',
  already_verified: 'Email kamu sudah terverifikasi sebelumnya. Silakan login.',
}

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [belumVerifikasi, setBelumVerifikasi] = useState(false)
  const [infoResend, setInfoResend] = useState('')
  const [loading, setLoading] = useState(false)

  const statusBanner = STATUS_PESAN[params.get('status')]

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setBelumVerifikasi(false)
    setInfoResend('')
    setLoading(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      if (err?.response?.status === 403) {
        setBelumVerifikasi(true)
      }
      setError(extractError(err, 'Gagal login.'))
    } finally {
      setLoading(false)
    }
  }

  async function handleResend() {
    setInfoResend('')
    try {
      const res = await client.post('/email/resend', { email })
      setInfoResend(res.data.message)
    } catch (err) {
      setInfoResend(extractError(err))
    }
  }

  return (
    <AuthShell title="Masuk ke Bugar">
      {statusBanner && (
        <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{statusBanner}</p>
      )}
      {error && (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>
      )}
      {belumVerifikasi && (
        <div className="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
          <button type="button" onClick={handleResend} className="font-medium underline">
            Kirim ulang link verifikasi
          </button>
          {infoResend && <p className="mt-1">{infoResend}</p>}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <Field label="Email" type="email" value={email} onChange={setEmail} required autoFocus />
        <Field label="Password" type="password" value={password} onChange={setPassword} required />
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-md bg-emerald-600 py-2 font-medium text-white transition hover:bg-emerald-700 disabled:opacity-60"
        >
          {loading ? 'Memproses…' : 'Masuk'}
        </button>
      </form>

      <p className="text-center text-sm text-gray-600">
        Belum punya akun?{' '}
        <Link to="/register" className="font-medium text-emerald-600 hover:underline">
          Daftar
        </Link>
      </p>
    </AuthShell>
  )
}

export function AuthShell({ title, children }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
      <div className="w-full max-w-md space-y-5 rounded-xl bg-white p-8 shadow-sm">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
          <p className="mt-1 text-sm text-gray-500">Fitness &amp; nutrisi berbasis evidence</p>
        </div>
        {children}
      </div>
    </div>
  )
}

export function Field({ label, type = 'text', value, onChange, ...rest }) {
  return (
    <label className="block">
      <span className="mb-1 block text-sm font-medium text-gray-700">{label}</span>
      <input
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-gray-300 px-3 py-2 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
        {...rest}
      />
    </label>
  )
}
