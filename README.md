# Bugar

Aplikasi fitness & nutrisi untuk semua kalangan masyarakat Indonesia — dari yang gym dengan budget terbatas (bodyweight only) sampai yang punya akses gym lengkap.

## Apa yang Dibuat

Bugar membantu menjawab pertanyaan yang sering bikin orang bingung mulai gym:

- **"Saya bisa gym berapa kali seminggu, program apa yang cocok?"** → Program latihan adaptif (2x/3x/4x per minggu) yang otomatis menyesuaikan ke equipment yang tersedia (bodyweight, dumbbell, atau gym lengkap)
- **"Saya cuma bisa makan tahu dan tempe, cukup gak proteinnya?"** → Kalkulasi kebutuhan protein harian berdasarkan berat badan & tujuan, lalu mapping ke kombinasi makanan nyata dari database resmi pemerintah
- **"Kapan saya harus naik beban?"** → Progressive overload tracking otomatis berdasarkan histori 2 sesi latihan terakhir

## Fitur

### 1. Body Metrics
- Kalkulasi BMI
- Estimasi body fat (input manual dari alat ukur, atau estimasi US Navy Method)
- Tracking progress berat badan & body fat over time

### 2. Program Latihan Adaptif
- Template berbasis evidence: Full Body A/B (2x), Push/Pull/Legs (3x), Upper/Lower Split (4x)
- Otomatis substitusi exercise sesuai equipment yang dimiliki user (bodyweight / dumbbell / gym lengkap)
- Progressive overload: rekomendasi naik beban, naik reps, atau turun beban berdasarkan performa 2 sesi terakhir

### 3. Meal Plan Berbasis Protein
- Kalkulasi kebutuhan protein harian (1.2-2.4 g/kg berat badan, tergantung tujuan)
- Database nutrisi dari **Tabel Komposisi Pangan Indonesia (TKPI) 2017/2018**, Kementerian Kesehatan RI
- Mapping otomatis ke kombinasi makanan dengan porsi realistis (bukan estimasi abstrak)
- Cakupan dari makanan budget (tempe, tahu, telur) sampai kelas menengah-atas (ikan kakap, daging sapi)

## Landasan Evidence

Program latihan dan kalkulasi nutrisi di Bugar didasarkan pada prinsip exercise science dan data gizi resmi, bukan template asal:

- Volume latihan 12-20 set per kelompok otot per minggu (Baz-Valle et al., 2022, *J Hum Kinet*)
- Frekuensi latihan 2-4x/minggu sama efektifnya selama volume disetarakan (Schoenfeld et al., 2019, *Sports Med*)
- Kebutuhan protein 1.6-2.2 g/kg untuk hipertrofi (konsensus ISSN position stand)
- Data nutrisi dari TKPI, database resmi Direktorat Gizi Masyarakat, Kementerian Kesehatan RI

**Disclaimer:** Bugar memberikan estimasi berbasis prinsip evidence-based untuk tujuan edukasi, bukan diagnosis medis. Hasil individu bervariasi tergantung genetik, nutrisi, recovery, dan konsistensi. Konsultasikan ke dokter atau ahli gizi untuk kebutuhan medis khusus.

## Tech Stack

**Backend**
- Laravel 10.x (PHP 8.1)
- Laravel Sanctum (autentikasi token-based dengan verifikasi email)
- MySQL

**Frontend**
- React 18.x (Vite)
- Tailwind CSS

## Struktur Project

```
bugar/
├── bugar-backend/          # Laravel API
│   ├── app/Models/
│   ├── app/Http/Controllers/Api/
│   ├── routes/api.php
│   └── database/migrations/
├── bugar-frontend/         # React app
│   └── src/
├── nutrisi_tkpi.json        # Database nutrisi dari TKPI
├── program_latihan.json     # Database exercise & template program
└── CLAUDE.md                 # Konteks project untuk AI-assisted development
```

## Setup Lokal

### Backend

```bash
cd bugar-backend
composer install
cp .env.example .env
php artisan key:generate
# Isi DB_DATABASE, DB_USERNAME, DB_PASSWORD di .env
php artisan migrate
composer require laravel/sanctum
php artisan serve
```

### Frontend

```bash
cd bugar-frontend
npm install
npm run dev
```

## Status Development

Project ini masih dalam tahap awal pengembangan. Fitur yang sudah berjalan:

- [x] Database nutrisi (TKPI) — 37 bahan pangan, 9 kategori
- [x] Database exercise & program template — 44 exercise, 3 template program
- [x] Logic kalkulasi kebutuhan protein → mapping makanan
- [x] Logic generate program adaptif berdasarkan equipment
- [x] Logic progressive overload (evaluasi 2 sesi terakhir)
- [x] Auth API (register, login, verifikasi email, logout)
- [ ] Migration tabel workout, body metrics, program tersimpan
- [ ] Endpoint API untuk workout logging & meal plan
- [ ] UI/komponen React untuk semua fitur

## Lisensi

Belum ditentukan.

## Kontak

Dikembangkan oleh [Feri Rimanda](https://github.com/feririmanda).
