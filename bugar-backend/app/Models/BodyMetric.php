<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal_pencatatan',
        'berat_badan_kg',
        'tinggi_badan_cm',
        'bmi',
        'body_fat_persen',
        'metode_body_fat',
        'jenis_kelamin',
        'lingkar_leher_cm',
        'lingkar_pinggang_cm',
        'lingkar_pinggul_cm',
        'catatan',
    ];

    protected $casts = [
        'tanggal_pencatatan' => 'date',
        'berat_badan_kg' => 'decimal:2',
        'tinggi_badan_cm' => 'decimal:2',
        'bmi' => 'decimal:2',
        'body_fat_persen' => 'decimal:2',
        'lingkar_leher_cm' => 'decimal:2',
        'lingkar_pinggang_cm' => 'decimal:2',
        'lingkar_pinggul_cm' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
