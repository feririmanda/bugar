<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_id',
        'exercise_id',
        'nama_exercise',
        'set_ke',
        'reps',
        'beban_kg',
        'durasi_detik',
    ];

    protected $casts = [
        'set_ke' => 'integer',
        'reps' => 'integer',
        'beban_kg' => 'decimal:2',
        'durasi_detik' => 'integer',
    ];

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
