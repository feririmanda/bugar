<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Satu sesi latihan (workout session) yang dilakukan user pada tanggal tertentu.
     * Detail set per exercise dicatat di tabel workout_sets.
     */
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Tanggal sesi latihan (dipakai progressive_overload untuk ambil 2 sesi terakhir)
            $table->date('tanggal');

            // Label hari latihan, mis. "Push", "Full Body A" (dari program_templates)
            $table->string('label_hari')->nullable();

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
