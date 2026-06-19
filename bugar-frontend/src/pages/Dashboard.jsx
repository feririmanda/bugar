import { useAuth } from '../context/AuthContext'

export default function Dashboard() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
        <h1 className="text-lg font-bold text-emerald-700">Bugar</h1>
        <div className="flex items-center gap-4">
          <span className="text-sm text-gray-600">{user?.name ?? user?.email}</span>
          <button
            onClick={logout}
            className="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
          >
            Keluar
          </button>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-6 py-10">
        <h2 className="text-2xl font-bold text-gray-900">Halo, {user?.name ?? 'pengguna'} 👋</h2>
        <p className="mt-2 text-gray-600">
          Kamu sudah login dan email terverifikasi. Fitur meal plan, program latihan, dan body
          metrics akan ditambahkan di sini.
        </p>

        <div className="mt-8 grid gap-4 sm:grid-cols-3">
          {['Meal Plan', 'Program Latihan', 'Body Metrics'].map((f) => (
            <div key={f} className="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
              <h3 className="font-semibold text-gray-800">{f}</h3>
              <p className="mt-1 text-sm text-gray-500">Segera hadir</p>
            </div>
          ))}
        </div>
      </main>
    </div>
  )
}
