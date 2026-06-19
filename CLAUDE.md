# CLAUDE.md — Project Bugar

Dokumen ini memberi konteks ke Claude Code soal project Bugar. Baca seluruh isi sebelum mengerjakan task apapun di repo ini.

## Apa itu Bugar

Aplikasi fitness & nutrisi berbasis web untuk publik Indonesia (bukan hanya kalangan tertentu — termasuk yang budget terbatas maupun menengah-atas). Tiga pilar utama:

1. **Body metrics** — BMI, estimasi body fat (manual input atau US Navy Method), tracking progress berat badan
2. **Program latihan adaptif** — generate program (2x/3x/4x per minggu) yang otomatis disesuaikan ke equipment yang user punya (bodyweight only / dumbbell / gym lengkap), dengan progressive overload tracking
3. **Meal plan berbasis kebutuhan protein** — kalkulasi kebutuhan protein harian dari berat badan & tujuan, lalu mapping ke kombinasi makanan nyata dari database resmi TKPI (Tabel Komposisi Pangan Indonesia, Kemenkes RI)

Hosting rencana: `bugar.unggulsemesta.co.id` (subdomain milik PT Unggul Semesta, tempat developer bekerja — app ini project personal, bukan project kerjaan resmi UGS).

## Prinsip Inti — JANGAN DILANGGAR

- **Tidak membuat klaim medis pasti.** Semua angka (body fat estimate, kebutuhan kalori, prediksi berat badan ideal) adalah ESTIMASI berbasis prinsip evidence-based, bukan diagnosis atau jaminan hasil. Setiap fitur yang menampilkan angka kesehatan harus ada disclaimer.
- **Data nutrisi HARUS bersumber dari TKPI resmi** (lihat `nutrisi_tkpi.json`), tidak boleh menambah data nutrisi dari asumsi/estimasi pribadi tanpa menandai jelas sebagai "estimasi, bukan dari TKPI".
- **Program latihan berbasis evidence**, bukan template asal. Lihat `_meta.evidence_basis` di `program_latihan.json` untuk rujukan riset yang dipakai (Schoenfeld et al., Baz-Valle et al.) — prinsip: volume 12-20 set/minggu per kelompok otot, frekuensi 2-4x/minggu sama validnya selama volume disetarakan.
- **Tidak memberi target/goal yang memicu pola makan tidak sehat.** Jangan buat fitur yang mendorong restriksi ekstrem, defisit kalori drastis, atau tracking obsesif yang berisiko memicu disordered eating.

## Tech Stack

- **Backend:** Laravel 10.x (PHP 8.1) — JANGAN upgrade ke Laravel 11/12 tanpa upgrade PHP dulu, environment lokal developer terkunci di PHP 8.1
- **Auth:** Laravel Sanctum, token-based (Bearer token, bukan cookie-based karena frontend beda domain)
- **Frontend:** React 18.x (Vite) + Tailwind CSS, di folder terpisah `bugar-frontend`
- **Database:** MySQL
- **Hosting target:** Backend di server yang support PHP (Railway/Render/VPS), frontend di Vercel

## Struktur Folder

```
bugar-backend/          → Laravel API only, tidak ada Blade view untuk app utama
  app/Models/
  app/Http/Controllers/Api/
  routes/api.php
  database/migrations/

bugar-frontend/          → React app terpisah, fetch ke bugar-backend via axios
  src/components/
  src/pages/
  src/api/client.js
```

## Data Reference Penting

- `nutrisi_tkpi.json` — 37 bahan pangan dari TKPI 2017/2018 Kemenkes, tiap item punya `porsi_lumrah_g` (porsi wajar Indonesia, BUKAN selalu 100g). Field `bdd_persen` = Berat Dapat Dimakan, penting untuk kalkulasi porsi beli vs porsi makan (terutama untuk ayam BDD 58%, ikan, dll).
- `program_latihan.json` — 44 exercise, 6 kelompok otot (dada, punggung, kaki, bahu, lengan, core), 3 template program (2x/3x/4x seminggu). Tiap exercise punya field `equipment` (array, bisa lebih dari satu) dan `rest_seconds`.
- `_meta.progressive_overload_rule` di `program_latihan.json` — aturan tertulis untuk logic naik/turun beban, sudah diimplementasikan di `progressive_overload.js` sebagai referensi porting ke PHP.

## Logic yang Sudah Ada (Referensi, Bahasa JS — perlu di-port ke PHP/Laravel)

File-file berikut sudah dibuat dan diuji sebagai prototype di Node.js, gunakan sebagai SPEC untuk implementasi ulang di Laravel (Service class atau Action class), JANGAN asal translate literal tanpa memahami logic-nya:

- `meal_planner_logic.js` — kalkulasi kebutuhan protein harian (`calculateProteinNeeds`), build kombinasi makanan realistis dari 1-2 bahan (`buildRealisticMeal`), generate meal plan harian.
- `program_generator_logic.js` — generate program adaptif berdasarkan equipment user, dengan substitusi exercise yang menghindari duplikasi dalam satu hari (lihat fungsi `findSubstitute`, perhatikan logic `usedIdsToday` di-pre-populate SEBELUM substitusi, ini fix penting untuk bug duplikasi yang pernah terjadi).
- `progressive_overload.js` — evaluasi 2 sesi terakhir per exercise, keluarkan rekomendasi `naik_beban` / `naik_reps_atau_variasi` / `turun_beban` / `turun_variasi` / `tetap` / `belum_cukup_data`.

## Auth Flow

Register → Laravel kirim email verifikasi otomatis (event `Registered`, User model implements `MustVerifyEmail`) → user klik link → endpoint `verification.verify` (signed route) → email terverifikasi → user bisa login dan dapat Sanctum token → token dipakai di header `Authorization: Bearer {token}` untuk semua request ke `routes/api.php` group yang pakai middleware `auth:sanctum, verified`.

**Penting:** Login endpoint HARUS reject user yang belum verifikasi (return 403), jangan biarkan bypass.

## Konvensi Kode

- Semua field database & relasi pakai bahasa Indonesia untuk konteks domain (`berat_badan_kg`, `tujuan_latihan`, `porsi_lumrah_g`) — konsisten dengan project Laravel lain milik developer (CERDAS), JANGAN campur ke bahasa Inggris di tengah jalan.
- Komentar kode boleh bilingual, tapi nama variabel/kolom konsisten Indonesia untuk domain-specific terms.
- Ikuti pola Livewire 3 (`#[Computed]`, `#[On]`) HANYA jika ada bagian admin/dashboard internal yang dibuat dengan Livewire — untuk app utama Bugar yang React-based, ini tidak relevan.

## Yang BELUM diimplementasi (jangan diasumsikan sudah ada)

- UI/komponen React untuk semua fitur (belum ada satupun komponen dibuat)
- Migration untuk tabel `workout_sets`, `body_metrics`, `programs`, `program_exercises`, `workouts` — baru `users` (dengan kolom tambahan) yang punya migration
- Endpoint API untuk workout logging, body metrics, meal plan — baru auth (`register`, `login`, `verify`, `resend`, `logout`, `me`) yang sudah ada controller-nya
- Integrasi progressive overload ke endpoint nyata (logic sudah ada di JS, belum di-port ke PHP/Laravel Service class)
- Halaman/komponen React apapun (Register, Login, Dashboard, dll) — belum dibuat sama sekali

## Larangan

- Jangan menambahkan Firebase, Express, atau stack non-Laravel/React ke project ini — sudah diputuskan untuk konsisten pakai stack yang developer kuasai.
- Jangan reproduce lirik, puisi, atau konten berhak cipta dalam fitur apapun (misal quote motivasi gym) tanpa parafrase.
- Jangan buat fitur yang memberi saran diet/nutrisi presisi tinggi tanpa disclaimer — App ini edukasi, bukan pengganti ahli gizi/dokter.
