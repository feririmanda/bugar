<?php

namespace App\Services;

/**
 * Kalkulasi body metrics: BMI dan estimasi body fat (US Navy Method).
 *
 * SEMUA hasil adalah ESTIMASI berbasis prinsip umum, BUKAN diagnosis medis
 * (lihat CLAUDE.md — wajib disertai disclaimer di setiap output angka kesehatan).
 */
class BodyMetricsService
{
    public const DISCLAIMER = 'Angka ini estimasi untuk edukasi, bukan diagnosis medis. '
        . 'Hasil bisa berbeda dari pengukuran klinis. Konsultasikan ke dokter/ahli gizi untuk kebutuhan medis.';

    /**
     * Body Mass Index = berat (kg) / tinggi (m)^2. Null bila data tidak memadai.
     */
    public function hitungBmi(?float $beratBadanKg, ?float $tinggiBadanCm): ?float
    {
        if (! $beratBadanKg || ! $tinggiBadanCm || $tinggiBadanCm <= 0) {
            return null;
        }

        $tinggiM = $tinggiBadanCm / 100;

        return round($beratBadanKg / ($tinggiM ** 2), 1);
    }

    /**
     * Kategori BMI versi Asia-Pasifik (lebih relevan untuk populasi Indonesia).
     */
    public function kategoriBmi(?float $bmi): ?string
    {
        if ($bmi === null) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'kurus',
            $bmi < 23 => 'normal',
            $bmi < 25 => 'berlebih',
            default => 'obesitas',
        };
    }

    /**
     * Estimasi body fat (%) dengan US Navy Method. Ukuran dalam cm.
     *
     * Pria : butuh lingkar leher & pinggang.
     * Wanita: butuh lingkar leher, pinggang, & pinggul.
     *
     * Mengembalikan null bila input tidak lengkap/tidak valid (mis. menghasilkan
     * log dari angka <= 0).
     */
    public function estimasiBodyFatUsNavy(
        string $jenisKelamin,
        ?float $tinggiBadanCm,
        ?float $lingkarLeherCm,
        ?float $lingkarPinggangCm,
        ?float $lingkarPinggulCm = null
    ): ?float {
        if (! $tinggiBadanCm || ! $lingkarLeherCm || ! $lingkarPinggangCm) {
            return null;
        }

        if ($jenisKelamin === 'pria') {
            $selisih = $lingkarPinggangCm - $lingkarLeherCm;
            if ($selisih <= 0) {
                return null;
            }

            $bf = 495 / (1.0324 - 0.19077 * log10($selisih) + 0.15456 * log10($tinggiBadanCm)) - 450;
        } elseif ($jenisKelamin === 'wanita') {
            if (! $lingkarPinggulCm) {
                return null;
            }

            $jumlah = $lingkarPinggangCm + $lingkarPinggulCm - $lingkarLeherCm;
            if ($jumlah <= 0) {
                return null;
            }

            $bf = 495 / (1.29579 - 0.35004 * log10($jumlah) + 0.22100 * log10($tinggiBadanCm)) - 450;
        } else {
            return null;
        }

        // Body fat realistis berada di rentang ini; di luar itu berarti input tidak masuk akal.
        if ($bf < 2 || $bf > 60) {
            return null;
        }

        return round($bf, 1);
    }
}
