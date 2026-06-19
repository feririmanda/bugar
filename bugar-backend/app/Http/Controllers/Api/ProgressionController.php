<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressiveOverloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressionController extends Controller
{
    /**
     * Rekomendasi progressive overload untuk sebuah exercise, berdasarkan
     * histori workout user (otomatis ambil sesi-sesi yang memuat exercise tsb).
     */
    public function show(Request $request, string $exerciseId, ProgressiveOverloadService $evaluator): JsonResponse
    {
        $workouts = $request->user()->workouts()
            ->whereHas('sets', fn ($q) => $q->where('exercise_id', $exerciseId))
            ->with(['sets' => fn ($q) => $q->where('exercise_id', $exerciseId)->orderBy('set_ke')])
            ->orderBy('tanggal')
            ->get();

        $sessions = $workouts->map(fn ($workout) => [
            'date' => $workout->tanggal->format('Y-m-d'),
            'sets' => $workout->sets->map(fn ($set) => [
                'reps' => (int) $set->reps,
                'weight_kg' => (float) $set->beban_kg,
            ])->all(),
        ])->all();

        return response()->json(
            $evaluator->evaluate($exerciseId, ['sessions' => $sessions])
        );
    }
}
