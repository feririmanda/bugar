<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgramGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    /**
     * Generate program latihan adaptif sesuai equipment user.
     */
    public function generate(Request $request, ProgramGeneratorService $generator): JsonResponse
    {
        $data = $request->validate([
            'frekuensi' => ['required', 'integer', Rule::in([2, 3, 4])],
            'equipment' => ['required', 'array', 'min:1'],
            'equipment.*' => [Rule::in(['bodyweight', 'dumbbell', 'gym_lengkap'])],
        ]);

        return response()->json(
            $generator->generateAdaptedProgram((int) $data['frekuensi'], $data['equipment'])
        );
    }
}
