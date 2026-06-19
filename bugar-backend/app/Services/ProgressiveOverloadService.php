<?php

namespace App\Services;

use RuntimeException;

/**
 * Port dari prototipe progressive_overload.js ke Laravel.
 *
 * Evaluasi 2 sesi terakhir per exercise dan keluarkan rekomendasi:
 * naik_beban / naik_reps_atau_variasi / turun_beban / turun_variasi / tetap /
 * belum_cukup_data — mengikuti aturan _meta.progressive_overload_rule.
 *
 * Service ini DB-agnostic: input historyData berupa array sesi
 * [{ 'date' => 'Y-m-d', 'sets' => [['reps' => int, 'weight_kg' => float], ...] }].
 * Pemetaan dari tabel workout_sets ke bentuk ini dilakukan di controller.
 */
class ProgressiveOverloadService
{
    protected array $db;

    public function __construct(?array $exerciseDatabase = null)
    {
        $this->db = $exerciseDatabase ?? $this->loadDefaultDatabase();
    }

    protected function loadDefaultDatabase(): array
    {
        $path = resource_path('data/program_latihan.json');

        if (! is_file($path)) {
            throw new RuntimeException("File data program latihan tidak ditemukan di: {$path}");
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * @param  array{sessions: array<int, array{date: string, sets: array}>}  $historyData
     */
    public function evaluate(string $exerciseId, array $historyData): array
    {
        $exercise = $this->findExercise($exerciseId);

        if (! $exercise) {
            return ['error' => "Exercise dengan id {$exerciseId} tidak ditemukan"];
        }

        ['min' => $repsMin, 'max' => $repsMax] = $this->parseRepsRange($exercise['target_reps']);

        $sessions = $historyData['sessions'] ?? [];
        if (count($sessions) < 2) {
            return [
                'exercise' => $exercise['nama'],
                'rekomendasi' => 'belum_cukup_data',
                'pesan' => 'Butuh minimal 2 sesi tercatat untuk evaluasi progressive overload.',
            ];
        }

        // Ambil 2 sesi terakhir (urut berdasarkan tanggal)
        usort($sessions, fn ($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
        [$sesiSebelum, $sesiTerakhir] = array_slice($sessions, -2);

        $isBodyweight = ! $this->usesExternalWeight($exercise);

        // === KONDISI NAIK ===
        $naikDiKeduaSesi = $this->hitMaxRepsAllSets($sesiSebelum, $repsMax)
            && $this->hitMaxRepsAllSets($sesiTerakhir, $repsMax);

        if ($naikDiKeduaSesi) {
            return $isBodyweight
                ? $this->rekomendasiNaikRepsBodyweight($exercise, $repsMax, $sesiSebelum, $sesiTerakhir)
                : $this->rekomendasiNaikBeban($exercise, $exerciseId, $repsMin, $repsMax, $sesiTerakhir, $sesiSebelum);
        }

        // === KONDISI TURUN ===
        $gagalSesiSebelum = $this->countFailedSets($sesiSebelum, $repsMin) >= 2;
        $gagalSesiTerakhir = $this->countFailedSets($sesiTerakhir, $repsMin) >= 2;

        if ($gagalSesiSebelum && $gagalSesiTerakhir) {
            return $isBodyweight
                ? $this->rekomendasiTurunVariasi($exercise, $repsMin, $sesiSebelum, $sesiTerakhir)
                : $this->rekomendasiTurunBeban($exercise, $repsMin, $sesiTerakhir, $sesiSebelum);
        }

        // === KONDISI TETAP (default) ===
        return [
            'exercise' => $exercise['nama'],
            'rekomendasi' => 'tetap',
            'pesan' => "Pertahankan beban/reps saat ini. Masih dalam progress menuju target {$repsMin}-{$repsMax} reps.",
            'detail' => $this->detail($sesiSebelum, $sesiTerakhir),
        ];
    }

    protected function rekomendasiNaikRepsBodyweight(array $exercise, ?int $repsMax, array $sebelum, array $terakhir): array
    {
        return [
            'exercise' => $exercise['nama'],
            'rekomendasi' => 'naik_reps_atau_variasi',
            'pesan' => "Mantap! 2 sesi terakhir kamu sudah mencapai {$repsMax} reps di semua set. Saatnya naikkan target reps (+2-3) atau coba variasi yang lebih sulit.",
            'target_reps_baru' => ($repsMax + 2) . '-' . ($repsMax + 5),
            'detail' => $this->detail($sebelum, $terakhir),
        ];
    }

    protected function rekomendasiNaikBeban(array $exercise, string $exerciseId, ?int $repsMin, ?int $repsMax, array $terakhir, array $sebelum): array
    {
        $isLowerBody = str_starts_with($exerciseId, 'kk');
        $kenaikanKg = $isLowerBody ? 5 : 2.5;
        $bebanTerakhir = $terakhir['sets'][0]['weight_kg'] ?? 0;

        return [
            'exercise' => $exercise['nama'],
            'rekomendasi' => 'naik_beban',
            'pesan' => "Mantap! 2 sesi terakhir kamu sudah mencapai {$repsMax} reps di semua set dengan beban {$bebanTerakhir}kg. Saatnya naikkan beban.",
            'beban_sekarang_kg' => $bebanTerakhir,
            'beban_disarankan_kg' => $bebanTerakhir + $kenaikanKg,
            'target_reps_setelah_naik' => "{$repsMin}-{$repsMax}",
            'detail' => $this->detail($sebelum, $terakhir),
        ];
    }

    protected function rekomendasiTurunVariasi(array $exercise, ?int $repsMin, array $sebelum, array $terakhir): array
    {
        return [
            'exercise' => $exercise['nama'],
            'rekomendasi' => 'turun_variasi',
            'pesan' => "2 sesi terakhir kamu kesulitan mencapai minimal {$repsMin} reps di beberapa set. Coba variasi yang lebih mudah dulu, atau fokuskan teknik.",
            'detail' => $this->detail($sebelum, $terakhir),
        ];
    }

    protected function rekomendasiTurunBeban(array $exercise, ?int $repsMin, array $terakhir, array $sebelum): array
    {
        $bebanTerakhir = $terakhir['sets'][0]['weight_kg'] ?? 0;
        $bebanBaru = round($bebanTerakhir * 0.9 * 2) / 2; // turun 10%, dibulatkan ke 0.5kg

        return [
            'exercise' => $exercise['nama'],
            'rekomendasi' => 'turun_beban',
            'pesan' => "2 sesi terakhir kamu kesulitan mencapai minimal {$repsMin} reps. Turunkan beban sedikit untuk menjaga form dan konsistensi.",
            'beban_sekarang_kg' => $bebanTerakhir,
            'beban_disarankan_kg' => $bebanBaru,
            'detail' => $this->detail($sebelum, $terakhir),
        ];
    }

    protected function detail(array $sebelum, array $terakhir): array
    {
        return ['sesiSebelum' => $sebelum['date'], 'sesiTerakhir' => $terakhir['date']];
    }

    protected function findExercise(string $exerciseId): ?array
    {
        foreach ($this->db['exercises'] as $group) {
            foreach ($group as $ex) {
                if ($ex['id'] === $exerciseId) {
                    return $ex;
                }
            }
        }

        return null;
    }

    /**
     * Parse "8-12" jadi ['min' => 8, 'max' => 12]. "30-60 detik" → keduanya null.
     *
     * @return array{min: ?int, max: ?int}
     */
    public function parseRepsRange(string $targetReps): array
    {
        if (preg_match('/(\d+)-(\d+)/', $targetReps, $m)) {
            return ['min' => (int) $m[1], 'max' => (int) $m[2]];
        }

        return ['min' => null, 'max' => null];
    }

    public function usesExternalWeight(array $exercise): bool
    {
        return ! in_array('bodyweight', $exercise['equipment'], true)
            || count($exercise['equipment']) > 1;
    }

    protected function hitMaxRepsAllSets(array $session, ?int $repsMax): bool
    {
        if (! $repsMax) {
            return false;
        }

        foreach ($session['sets'] as $s) {
            if ($s['reps'] < $repsMax) {
                return false;
            }
        }

        return true;
    }

    protected function countFailedSets(array $session, ?int $repsMin): int
    {
        if (! $repsMin) {
            return 0;
        }

        return count(array_filter($session['sets'], fn ($s) => $s['reps'] < $repsMin));
    }
}
