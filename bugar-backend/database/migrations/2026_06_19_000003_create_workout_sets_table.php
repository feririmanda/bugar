<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catatan tiap set yang dikerjakan dalam satu workout, per exercise.
     * exercise_id mengacu ke id exercise di program_latihan.json (mis. "dd002"),
     * bukan foreign key ke tabel database (data exercise ada di JSON, bukan DB).
     */
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();

            // Referensi ke exercise di program_latihan.json (string id, mis. "dd002")
            $table->string('exercise_id');
            // Snapshot nama exercise saat dicatat (jaga histori bila JSON berubah)
            $table->string('nama_exercise')->nullable();

            // Urutan set dalam exercise tersebut (set ke-1, ke-2, dst)
            $table->unsignedSmallInteger('set_ke');

            // Untuk exercise berbasis repetisi
            $table->unsignedSmallInteger('reps')->nullable();
            // 0 / null untuk bodyweight murni, terisi untuk dumbbell/barbel
            $table->decimal('beban_kg', 6, 2)->nullable();

            // Untuk exercise berbasis waktu (mis. Plank, Wall Sit)
            $table->unsignedSmallInteger('durasi_detik')->nullable();

            $table->timestamps();

            $table->index(['workout_id', 'exercise_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
