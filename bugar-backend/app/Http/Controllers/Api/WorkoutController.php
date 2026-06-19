<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutController extends Controller
{
    /**
     * Daftar workout milik user (terbaru dulu), beserta set-nya.
     */
    public function index(Request $request): JsonResponse
    {
        $workouts = $request->user()->workouts()
            ->with('sets')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $workouts]);
    }

    /**
     * Catat satu sesi workout beserta set per exercise.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'label_hari' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'sets' => ['required', 'array', 'min:1'],
            'sets.*.exercise_id' => ['required', 'string', 'max:50'],
            'sets.*.nama_exercise' => ['nullable', 'string', 'max:255'],
            'sets.*.set_ke' => ['required', 'integer', 'min:1'],
            // Set harus punya reps ATAU durasi (exercise berbasis waktu).
            'sets.*.reps' => ['nullable', 'required_without:sets.*.durasi_detik', 'integer', 'min:0'],
            'sets.*.beban_kg' => ['nullable', 'numeric', 'min:0'],
            'sets.*.durasi_detik' => ['nullable', 'required_without:sets.*.reps', 'integer', 'min:0'],
        ]);

        $workout = DB::transaction(function () use ($request, $data) {
            $workout = $request->user()->workouts()->create([
                'tanggal' => $data['tanggal'],
                'label_hari' => $data['label_hari'] ?? null,
                'catatan' => $data['catatan'] ?? null,
            ]);

            $workout->sets()->createMany($data['sets']);

            return $workout->load('sets');
        });

        return response()->json(['data' => $workout], 201);
    }
}
