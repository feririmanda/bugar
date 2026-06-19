<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Port dari prototipe program_generator_logic.js ke Laravel.
 *
 * Generate program latihan adaptif berdasarkan equipment yang dimiliki user,
 * dengan substitusi exercise dari kelompok otot yang sama bila exercise asli
 * tidak cocok dengan equipment.
 *
 * Fix penting yang DIPERTAHANKAN dari prototipe (lihat CLAUDE.md): usedIdsToday
 * di-pre-populate dengan exercise yang sudah tersedia langsung SEBELUM proses
 * substitusi, agar substitusi tidak memilih ID yang sama (mencegah duplikasi
 * dalam satu hari).
 */
class ProgramGeneratorService
{
    /**
     * @var array Struktur data program_latihan.json (key: '_meta', 'exercises', 'program_templates')
     */
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
     * Generate program lengkap yang sudah disesuaikan dengan equipment user.
     *
     * @param  int  $frekuensiPerMinggu  2, 3, atau 4
     * @param  array<int, string>  $equipmentUser  mis. ['bodyweight'] atau ['dumbbell', 'gym_lengkap']
     */
    public function generateAdaptedProgram(int $frekuensiPerMinggu, array $equipmentUser): array
    {
        $templateKey = "{$frekuensiPerMinggu}x_seminggu";
        $template = $this->db['program_templates'][$templateKey] ?? null;

        if (! $template) {
            throw new InvalidArgumentException('Frekuensi tidak didukung. Pilih 2, 3, atau 4.');
        }

        $adaptedDays = [];
        foreach ($template['hari'] as $day) {
            $adaptedDays[] = $this->adaptDay($day, $equipmentUser);
        }

        return [
            'nama_program' => $template['nama'],
            'deskripsi' => $template['deskripsi'],
            'equipment_user' => $equipmentUser,
            'hari' => $adaptedDays,
            'disclaimer' => $this->db['_meta']['disclaimer']
                ?? 'Program berdasarkan prinsip umum exercise science. Hasil individu bervariasi.',
        ];
    }

    protected function adaptDay(array $day, array $equipmentUser): array
    {
        // Pre-register exercise yang TERSEDIA langsung agar substitusi tidak memilih ID yang sama.
        $usedIdsToday = [];
        foreach ($day['exercise_ids'] as $exId) {
            $ex = $this->getExerciseById($exId);
            if ($ex && $this->isExerciseAvailable($ex, $equipmentUser)) {
                $usedIdsToday[$ex['id']] = true;
            }
        }

        $adaptedExercises = [];
        foreach ($day['exercise_ids'] as $exId) {
            $original = $this->getExerciseById($exId);

            if ($original && $this->isExerciseAvailable($original, $equipmentUser)) {
                $adaptedExercises[] = $original + ['status' => 'sesuai'];
                continue;
            }

            // Cari kelompok otot exercise ini (dari kelompok_otot hari tsb)
            $muscleGroup = $this->findMuscleGroup($day['kelompok_otot'], $exId);
            $substitute = $muscleGroup
                ? $this->findSubstitute($original, $muscleGroup, $equipmentUser, $usedIdsToday)
                : null;

            if ($substitute) {
                $usedIdsToday[$substitute['id']] = true;
                $adaptedExercises[] = $substitute + [
                    'status' => 'diganti',
                    'original_nama' => $original['nama'] ?? null,
                ];
                continue;
            }

            $adaptedExercises[] = ($original ?? ['id' => $exId]) + ['status' => 'tidak_tersedia'];
        }

        return ['label' => $day['label'], 'exercises' => $adaptedExercises];
    }

    protected function getExerciseById(string $id): ?array
    {
        foreach ($this->db['exercises'] as $group) {
            foreach ($group as $ex) {
                if ($ex['id'] === $id) {
                    return $ex;
                }
            }
        }

        return null;
    }

    protected function isExerciseAvailable(array $exercise, array $equipmentUser): bool
    {
        return count(array_intersect($exercise['equipment'], $equipmentUser)) > 0;
    }

    protected function findMuscleGroup(array $kelompokOtot, string $exId): ?string
    {
        foreach ($kelompokOtot as $mg) {
            foreach (($this->db['exercises'][$mg] ?? []) as $ex) {
                if ($ex['id'] === $exId) {
                    return $mg;
                }
            }
        }

        return null;
    }

    /**
     * Cari pengganti dari kelompok otot sama, cocok dengan equipment user,
     * dan belum dipakai di hari yang sama.
     */
    protected function findSubstitute(?array $original, string $muscleGroup, array $equipmentUser, array $usedIdsToday): ?array
    {
        foreach (($this->db['exercises'][$muscleGroup] ?? []) as $ex) {
            $bedaDariAsli = ! $original || $ex['id'] !== $original['id'];
            if ($bedaDariAsli
                && $this->isExerciseAvailable($ex, $equipmentUser)
                && ! isset($usedIdsToday[$ex['id']])
            ) {
                return $ex;
            }
        }

        return null;
    }
}
