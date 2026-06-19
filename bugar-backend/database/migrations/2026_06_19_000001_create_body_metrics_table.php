<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catatan pencatatan body metrics user (tracking progress dari waktu ke waktu).
     * Semua angka (bmi, body_fat_persen) adalah ESTIMASI, bukan diagnosis medis.
     */
    public function up(): void
    {
        Schema::create('body_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Tanggal pencatatan, dipakai untuk tracking progress berat badan
            $table->date('tanggal_pencatatan');

            // Data dasar
            $table->decimal('berat_badan_kg', 5, 2);
            $table->decimal('tinggi_badan_cm', 5, 2)->nullable();

            // Hasil estimasi (boleh dihitung saat input, disimpan sebagai snapshot)
            $table->decimal('bmi', 5, 2)->nullable();
            $table->decimal('body_fat_persen', 5, 2)->nullable();

            // Metode estimasi body fat: 'manual' (input langsung) atau 'us_navy'
            $table->string('metode_body_fat')->nullable();

            // Diperlukan untuk kalkulasi US Navy Method (nullable karena tidak selalu dipakai)
            $table->string('jenis_kelamin')->nullable(); // 'pria' / 'wanita'
            $table->decimal('lingkar_leher_cm', 5, 2)->nullable();
            $table->decimal('lingkar_pinggang_cm', 5, 2)->nullable();
            $table->decimal('lingkar_pinggul_cm', 5, 2)->nullable(); // hanya untuk wanita

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'tanggal_pencatatan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_metrics');
    }
};
