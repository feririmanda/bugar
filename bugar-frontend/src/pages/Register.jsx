import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { extractError } from '../api/client'
import { AuthShell, Field } from './Login'

export default function Register() {
  const { register } = useAuth()

  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const [error, setError] = useState('')
  const [sukses, setSukses] = useState('')
  const [loading, setLoading] = useState(false)

  function set(field) {
    return (value) => setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSukses('')
    setLoading(true)
    try {
      const res = await register(form)
      setSukses(res.message ?? 'Registrasi berhasil. Cek email untuk verifikasi.')
    } catch (err) {
      setError(extractError(err, 'Gagal mendaftar.'))
    } finally {
      setLoading(false)
    }
  }

  if (sukses) {
    return (
      <AuthShell title="Cek Email Kamu">
        <p className="rounded-md bg-green-50 px-3 py-2 text-sm text-green-700">{sukses}</p>
        <p className="text-center text-sm text-gray-600">
          Sudah verifikasi?{' '}
          <Link to="/login" className="font-medium text-emerald-600 hover:underline">
            Masuk di sini
          </Link>
        </p>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Daftar Bugar">
      {error && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p>}

      <form onSubmit={handleSubmit} className="space-y-4">
        <Field label="Nama" value={form.name} onChange={set('name')} required autoFocus />
        <Field label="Email" type="email" value={form.email} onChange={set('email')} required />
        <Field
          label="Password"
          type="password"
          value={form.password}
          onChange={set('password')}
          required
          minLength={8}
        />
        <Field
          label="Konfirmasi Password"
          type="password"
          value={form.password_confirmation}
          onChange={set('password_confirmation')}
          required
          minLength={8}
        />
        <button
          type="submit"
          disabled={loading}
          className="w-full rounded-md bg-emerald-600 py-2 font-medium text-white transition hover:bg-emerald-700 disabled:opacity-60"
        >
          {loading ? 'Memproses…' : 'Daftar'}
        </button>
      </form>

      <p className="text-center text-sm text-gray-600">
        Sudah punya akun?{' '}
        <Link to="/login" className="font-medium text-emerald-600 hover:underline">
          Masuk
        </Link>
      </p>
    </AuthShell>
  )
}
