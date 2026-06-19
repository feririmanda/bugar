<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MealPlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MealPlanController extends Controller
{
    public function __construct(
        private MealPlannerService $mealPlanner
    ) {
    }

    /**
     * Generate meal plan harian berbasis kebutuhan protein.
     *
     * Semua angka adalah ESTIMASI edukatif — lihat field 'disclaimer' pada respons.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'berat_badan_kg' => ['required', 'numeric', 'min:20', 'max:300'],
            'tujuan' => ['required', Rule::in(['maintenance', 'hypertrophy', 'cutting'])],
            'jumlah_meal' => ['required', 'integer', 'min:1', 'max:6'],
            'preferensi_budget' => ['nullable', Rule::in(['budget', 'mixed', 'premium'])],
        ]);

        $result = $this->mealPlanner->generateDayMealPlan(
            (float) $data['berat_badan_kg'],
            $data['tujuan'],
            (int) $data['jumlah_meal'],
            $data['preferensi_budget'] ?? 'mixed',
        );

        return response()->json($result);
    }
}
