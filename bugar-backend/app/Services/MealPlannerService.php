<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * Port dari prototipe meal_planner_logic.js ke Laravel.
 *
 * Logic inti dipertahankan: hitung kebutuhan protein harian dari berat badan & tujuan,
 * lalu bangun kombinasi makanan nyata (1-2 bahan) dari TKPI memakai porsi_lumrah_g.
 *
 * Penyesuaian sadar terhadap prototipe JS (bukan translate literal):
 *  - Data TKPI di-inject lewat constructor (default: resources/data/nutrisi_tkpi.json)
 *    agar service deployable & mudah di-test.
 *  - Menambahkan porsi_beli_g berbasis bdd_persen (porsi mentah yang perlu dibeli),
 *    sesuai penekanan CLAUDE.md soal Berat Dapat Dimakan (mis. ayam BDD 58%).
 *  - Guard saat tidak ada kombinasi cocok (prototipe JS bisa division-by-zero / NaN).
 *  - Menyertakan disclaimer dari _meta TKPI pada hasil (semua angka = estimasi edukatif).
 */
class MealPlannerService
{
    /**
     * Faktor kebutuhan protein (gram per kg berat badan) per tujuan.
     */
    protected const FAKTOR_PROTEIN = [
        'maintenance' => 1.4,
        'hypertrophy' => 1.8,
        'cutting' => 2.2,
    ];

    protected const FAKTOR_DEFAULT = 1.6;

    /**
     * Kategori bahan protein yang dipilih berdasarkan preferensi budget.
     */
    protected const KATEGORI_PER_BUDGET = [
        'budget' => ['protein_nabati', 'telur'],
        'mixed' => ['protein_nabati', 'telur', 'ikan', 'unggas'],
        'premium' => ['ikan', 'unggas', 'daging_merah', 'telur'],
    ];

    /**
     * @var array Struktur data TKPI (key: '_meta', 'kategori')
     */
    protected array $foodDatabase;

    public function __construct(?array $foodDatabase = null)
    {
        $this->foodDatabase = $foodDatabase ?? $this->loadDefaultDatabase();
    }

    protected function loadDefaultDatabase(): array
    {
        $path = resource_path('data/nutrisi_tkpi.json');

        if (! is_file($path)) {
            throw new RuntimeException("File data TKPI tidak ditemukan di: {$path}");
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * Kebutuhan protein harian (gram) dari berat badan & tujuan latihan.
     */
    public function calculateProteinNeeds(float $beratBadanKg, string $tujuan): int
    {
        $faktor = self::FAKTOR_PROTEIN[$tujuan] ?? self::FAKTOR_DEFAULT;

        return (int) round($beratBadanKg * $faktor);
    }

    /**
     * Bangun kombinasi makanan realistis (1-2 bahan) yang totalnya mendekati
     * target protein, memakai porsi_lumrah_g dari TKPI.
     *
     * @return array<int, array{items: array, total_protein_g: float}>
     */
    public function buildRealisticMeal(float $targetProteinG, array $kategoriProtein): array
    {
        $allProteinFoods = [];
        foreach ($kategoriProtein as $cat) {
            foreach (($this->foodDatabase['kategori'][$cat] ?? []) as $food) {
                $porsi = $food['porsi_lumrah_g'];
                $allProteinFoods[] = [
                    'food' => $food,
                    'porsi' => $porsi,
                    'protein' => $this->proteinFromPortion($food, $porsi),
                ];
            }
        }

        $results = [];

        // Single food yang sudah mendekati target (toleransi ±20%)
        foreach ($allProteinFoods as $a) {
            if (abs($a['protein'] - $targetProteinG) <= $targetProteinG * 0.2) {
                $results[] = [
                    'items' => [$this->buatItem($a['food'], $a['porsi'])],
                    'total_protein_g' => $a['protein'],
                ];
            }
        }

        // Kombinasi 2 bahan (toleransi ±15%)
        $count = count($allProteinFoods);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $allProteinFoods[$i];
                $b = $allProteinFoods[$j];
                $combo = $a['protein'] + $b['protein'];

                if (abs($combo - $targetProteinG) <= $targetProteinG * 0.15) {
                    $results[] = [
                        'items' => [
                            $this->buatItem($a['food'], $a['porsi']),
                            $this->buatItem($b['food'], $b['porsi']),
                        ],
                        'total_protein_g' => round($combo, 1),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Generate meal plan harian (dibagi ke beberapa meal).
     */
    public function generateDayMealPlan(
        float $beratBadanKg,
        string $tujuan,
        int $mealsPerDay,
        string $budgetPreference = 'mixed'
    ): array {
        if ($mealsPerDay < 1) {
            throw new InvalidArgumentException('mealsPerDay minimal 1.');
        }

        $totalProtein = $this->calculateProteinNeeds($beratBadanKg, $tujuan);
        $proteinPerMeal = (int) round($totalProtein / $mealsPerDay);

        $kategori = self::KATEGORI_PER_BUDGET[$budgetPreference] ?? self::KATEGORI_PER_BUDGET['mixed'];
        $allCombos = $this->buildRealisticMeal($proteinPerMeal, $kategori);
        $jumlahKombinasi = count($allCombos);

        // Acak urutan agar tiap meal dapat variasi berbeda, lalu ambil bergilir.
        if ($jumlahKombinasi > 0) {
            shuffle($allCombos);
        }

        $meals = [];
        for ($i = 0; $i < $mealsPerDay; $i++) {
            $meals[] = [
                'meal_ke' => $i + 1,
                'target_protein_g' => $proteinPerMeal,
                // Guard: prototipe JS bisa division-by-zero saat tidak ada kombinasi.
                'pilihan' => $jumlahKombinasi > 0 ? $allCombos[$i % $jumlahKombinasi] : null,
            ];
        }

        return [
            'target_protein_harian_g' => $totalProtein,
            'protein_per_meal_g' => $proteinPerMeal,
            'total_kombinasi_tersedia' => $jumlahKombinasi,
            'meals' => $meals,
            'disclaimer' => $this->foodDatabase['_meta']['disclaimer']
                ?? 'Estimasi kebutuhan gizi untuk edukasi, bukan pengganti konsultasi ahli gizi/dokter.',
        ];
    }

    protected function proteinFromPortion(array $food, float $grams): float
    {
        return round(($food['protein_g'] / 100) * $grams, 1);
    }

    protected function kaloriFromPortion(array $food, float $grams): int
    {
        return (int) round(($food['energi_kal'] / 100) * $grams);
    }

    /**
     * Porsi mentah yang perlu DIBELI berdasarkan bdd_persen (Berat Dapat Dimakan).
     * porsi_lumrah_g = porsi yang dimakan (sudah bersih), sedangkan untuk belanja
     * perlu memperhitungkan bagian terbuang. Mis. ayam BDD 58%: untuk makan 100g,
     * perlu beli ~172g. Mengembalikan null bila bdd_persen tidak tersedia di TKPI.
     */
    protected function porsiBeliFromPortion(array $food, float $grams): ?int
    {
        $bdd = $food['bdd_persen'] ?? null;

        if ($bdd === null || $bdd <= 0) {
            return null;
        }

        return (int) round($grams / ($bdd / 100));
    }

    protected function buatItem(array $food, float $porsi): array
    {
        return [
            'nama' => $food['nama'],
            'porsi_g' => $porsi,
            'porsi_beli_g' => $this->porsiBeliFromPortion($food, $porsi),
            'protein_g' => $this->proteinFromPortion($food, $porsi),
            'kalori' => $this->kaloriFromPortion($food, $porsi),
        ];
    }
}
