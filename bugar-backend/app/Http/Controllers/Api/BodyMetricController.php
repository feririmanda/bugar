<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BodyMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BodyMetricController extends Controller
{
    /**
     * Riwayat body metrics user (untuk tracking progress), terbaru dulu.
     */
    public function index(Request $request): JsonResponse
    {
        $metrics = $request->user()->bodyMetrics()
            ->orderByDesc('tanggal_pencatatan')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $metrics,
            'disclaimer' => BodyMetricsService::DISCLAIMER,
        ]);
    }

    /**
     * Catat body metrics baru. BMI dihitung otomatis; body fat dihitung via
     * US Navy Method bila metode 'us_navy' (atau pakai input manual).
     */
    public function store(Request $request, BodyMetricsService $service): JsonResponse
    {
        $data = $request->validate([
            'tanggal_pencatatan' => ['required', 'date'],
            'berat_badan_kg' => ['required', 'numeric', 'min:20', 'max:300'],
            'tinggi_badan_cm' => ['nullable', 'numeric', 'min:50', 'max:250'],
            'metode_body_fat' => ['nullable', Rule::in(['manual', 'us_navy'])],
            'body_fat_persen' => ['nullable', 'numeric', 'min:2', 'max:60'],
            'jenis_kelamin' => ['nullable', Rule::in(['pria', 'wanita'])],
            'lingkar_leher_cm' => ['nullable', 'numeric', 'min:10', 'max:100'],
            'lingkar_pinggang_cm' => ['nullable', 'numeric', 'min:30', 'max:200'],
            'lingkar_pinggul_cm' => ['nullable', 'numeric', 'min:30', 'max:200'],
            'catatan' => ['nullable', 'string'],
        ]);

        $bmi = $service->hitungBmi(
            (float) $data['berat_badan_kg'],
            isset($data['tinggi_badan_cm']) ? (float) $data['tinggi_badan_cm'] : null,
        );

        // Body fat: manual pakai input langsung, us_navy dihitung dari ukuran tubuh.
        $bodyFat = $data['body_fat_persen'] ?? null;
        if (($data['metode_body_fat'] ?? null) === 'us_navy' && isset($data['jenis_kelamin'])) {
            $bodyFat = $service->estimasiBodyFatUsNavy(
                $data['jenis_kelamin'],
                isset($data['tinggi_badan_cm']) ? (float) $data['tinggi_badan_cm'] : null,
                isset($data['lingkar_leher_cm']) ? (float) $data['lingkar_leher_cm'] : null,
                isset($data['lingkar_pinggang_cm']) ? (float) $data['lingkar_pinggang_cm'] : null,
                isset($data['lingkar_pinggul_cm']) ? (float) $data['lingkar_pinggul_cm'] : null,
            );
        }

        $metric = $request->user()->bodyMetrics()->create([
            ...$data,
            'bmi' => $bmi,
            'body_fat_persen' => $bodyFat,
        ]);

        return response()->json([
            'data' => $metric,
            'kategori_bmi' => $service->kategoriBmi($bmi),
            'disclaimer' => BodyMetricsService::DISCLAIMER,
        ], 201);
    }
}
