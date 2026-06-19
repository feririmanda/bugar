const exerciseDB = require('./program_latihan.json');

/**
 * Struktur histori workout yang dibutuhkan (nanti dari database):
 * {
 *   exercise_id: 'dd002',
 *   sessions: [
 *     { date: '2026-06-10', sets: [{reps: 10, weight_kg: 60}, {reps: 9, weight_kg: 60}, {reps: 8, weight_kg: 60}] },
 *     { date: '2026-06-13', sets: [{reps: 10, weight_kg: 60}, {reps: 10, weight_kg: 60}, {reps: 10, weight_kg: 60}] }
 *   ]
 * }
 */

/**
 * Parse target_reps string ("8-12") jadi {min, max}
 */
function parseRepsRange(targetRepsStr) {
  const match = targetRepsStr.match(/(\d+)-(\d+)/);
  if (!match) return { min: null, max: null }; // contoh "30-60 detik" tetap fallback
  return { min: parseInt(match[1]), max: parseInt(match[2]) };
}

/**
 * Cek apakah exercise menggunakan beban (dumbbell/barbel) atau pure bodyweight
 */
function usesExternalWeight(exercise) {
  return !exercise.equipment.includes('bodyweight') || exercise.equipment.length > 1;
}

/**
 * Cek apakah SEMUA set di sebuah sesi mencapai target_reps maksimum
 */
function hitMaxRepsAllSets(session, repsMax) {
  if (!repsMax) return false;
  return session.sets.every(s => s.reps >= repsMax);
}

/**
 * Cek apakah ADA set yang gagal mencapai target_reps minimum
 */
function failedMinRepsAnySet(session, repsMin) {
  if (!repsMin) return false;
  return session.sets.some(s => s.reps < repsMin);
}

/**
 * Hitung berapa set yang gagal capai reps minimum
 */
function countFailedSets(session, repsMin) {
  if (!repsMin) return 0;
  return session.sets.filter(s => s.reps < repsMin).length;
}

/**
 * FUNGSI UTAMA: Evaluasi progressive overload berdasarkan 2 sesi terakhir
 */
function evaluateProgressiveOverload(exerciseId, historyData, db) {
  const exercise = Object.values(db.exercises)
    .flat()
    .find(ex => ex.id === exerciseId);

  if (!exercise) {
    return { error: `Exercise dengan id ${exerciseId} tidak ditemukan` };
  }

  const { min: repsMin, max: repsMax } = parseRepsRange(exercise.target_reps);

  // Butuh minimal 2 sesi untuk evaluasi
  if (!historyData.sessions || historyData.sessions.length < 2) {
    return {
      exercise: exercise.nama,
      rekomendasi: 'belum_cukup_data',
      pesan: 'Butuh minimal 2 sesi tercatat untuk evaluasi progressive overload.'
    };
  }

  // Ambil 2 sesi terakhir
  const sorted = [...historyData.sessions].sort((a, b) => new Date(a.date) - new Date(b.date));
  const lastTwo = sorted.slice(-2);
  const [sesiSebelum, sesiTerakhir] = lastTwo;

  const isBodyweight = !usesExternalWeight(exercise);

  // === KONDISI NAIK ===
  const naikDiKeduaSesi = hitMaxRepsAllSets(sesiSebelum, repsMax) && hitMaxRepsAllSets(sesiTerakhir, repsMax);

  if (naikDiKeduaSesi) {
    if (isBodyweight) {
      return {
        exercise: exercise.nama,
        rekomendasi: 'naik_reps_atau_variasi',
        pesan: `Mantap! 2 sesi terakhir kamu sudah mencapai ${repsMax} reps di semua set. Saatnya naikkan target reps (+2-3) atau coba variasi yang lebih sulit.`,
        target_reps_baru: `${repsMax + 2}-${repsMax + 5}`,
        detail: { sesiSebelum: sesiSebelum.date, sesiTerakhir: sesiTerakhir.date }
      };
    } else {
      const isLowerBody = ['kk'].some(prefix => exerciseId.startsWith(prefix));
      const kenaikanKg = isLowerBody ? 5 : 2.5;
      const bebanTerakhir = sesiTerakhir.sets[0]?.weight_kg || 0;

      return {
        exercise: exercise.nama,
        rekomendasi: 'naik_beban',
        pesan: `Mantap! 2 sesi terakhir kamu sudah mencapai ${repsMax} reps di semua set dengan beban ${bebanTerakhir}kg. Saatnya naikkan beban.`,
        beban_sekarang_kg: bebanTerakhir,
        beban_disarankan_kg: bebanTerakhir + kenaikanKg,
        target_reps_setelah_naik: `${repsMin}-${repsMax}`,
        detail: { sesiSebelum: sesiSebelum.date, sesiTerakhir: sesiTerakhir.date }
      };
    }
  }

  // === KONDISI TURUN ===
  const gagalSesiSebelum = countFailedSets(sesiSebelum, repsMin) >= 2;
  const gagalSesiTerakhir = countFailedSets(sesiTerakhir, repsMin) >= 2;

  if (gagalSesiSebelum && gagalSesiTerakhir) {
    if (isBodyweight) {
      return {
        exercise: exercise.nama,
        rekomendasi: 'turun_variasi',
        pesan: `2 sesi terakhir kamu kesulitan mencapai minimal ${repsMin} reps di beberapa set. Coba variasi yang lebih mudah dulu, atau fokuskan teknik.`,
        detail: { sesiSebelum: sesiSebelum.date, sesiTerakhir: sesiTerakhir.date }
      };
    } else {
      const bebanTerakhir = sesiTerakhir.sets[0]?.weight_kg || 0;
      const bebanBaru = Math.round(bebanTerakhir * 0.9 * 2) / 2; // turun 10%, dibulatkan ke 0.5kg

      return {
        exercise: exercise.nama,
        rekomendasi: 'turun_beban',
        pesan: `2 sesi terakhir kamu kesulitan mencapai minimal ${repsMin} reps. Turunkan beban sedikit untuk menjaga form dan konsistensi.`,
        beban_sekarang_kg: bebanTerakhir,
        beban_disarankan_kg: bebanBaru,
        detail: { sesiSebelum: sesiSebelum.date, sesiTerakhir: sesiTerakhir.date }
      };
    }
  }

  // === KONDISI TETAP (default) ===
  return {
    exercise: exercise.nama,
    rekomendasi: 'tetap',
    pesan: `Pertahankan beban/reps saat ini. Masih dalam progress menuju target ${repsMin}-${repsMax} reps.`,
    detail: { sesiSebelum: sesiSebelum.date, sesiTerakhir: sesiTerakhir.date }
  };
}

module.exports = { evaluateProgressiveOverload, parseRepsRange, usesExternalWeight };

// ============ TEST CASES ============
if (require.main === module) {
  console.log('=== TEST 1: Bench Press, capai max reps di SEMUA set, 2 sesi (harus rekomendasi NAIK BEBAN) ===');
  const test1 = evaluateProgressiveOverload('dd002', {
    sessions: [
      { date: '2026-06-10', sets: [{ reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }] },
      { date: '2026-06-13', sets: [{ reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }, { reps: 12, weight_kg: 60 }] }
    ]
  }, exerciseDB);
  console.log(JSON.stringify(test1, null, 2));

  console.log('\n=== TEST 2: Push Up (bodyweight), capai max reps 2 sesi (harus NAIK REPS/VARIASI) ===');
  const test2 = evaluateProgressiveOverload('dd001', {
    sessions: [
      { date: '2026-06-10', sets: [{ reps: 15, weight_kg: 0 }, { reps: 15, weight_kg: 0 }, { reps: 15, weight_kg: 0 }] },
      { date: '2026-06-13', sets: [{ reps: 15, weight_kg: 0 }, { reps: 15, weight_kg: 0 }, { reps: 15, weight_kg: 0 }] }
    ]
  }, exerciseDB);
  console.log(JSON.stringify(test2, null, 2));

  console.log('\n=== TEST 3: Barbell Squat, gagal capai reps minimum 2 sesi (harus TURUN BEBAN) ===');
  const test3 = evaluateProgressiveOverload('kk002', {
    sessions: [
      { date: '2026-06-10', sets: [{ reps: 6, weight_kg: 80 }, { reps: 5, weight_kg: 80 }, { reps: 5, weight_kg: 80 }, { reps: 4, weight_kg: 80 }] },
      { date: '2026-06-13', sets: [{ reps: 6, weight_kg: 80 }, { reps: 5, weight_kg: 80 }, { reps: 6, weight_kg: 80 }, { reps: 5, weight_kg: 80 }] }
    ]
  }, exerciseDB);
  console.log(JSON.stringify(test3, null, 2));

  console.log('\n=== TEST 4: Progress wajar, belum capai max, belum gagal (harus TETAP) ===');
  const test4 = evaluateProgressiveOverload('dd002', {
    sessions: [
      { date: '2026-06-10', sets: [{ reps: 9, weight_kg: 60 }, { reps: 8, weight_kg: 60 }, { reps: 8, weight_kg: 60 }, { reps: 8, weight_kg: 60 }] },
      { date: '2026-06-13', sets: [{ reps: 10, weight_kg: 60 }, { reps: 9, weight_kg: 60 }, { reps: 9, weight_kg: 60 }, { reps: 8, weight_kg: 60 }] }
    ]
  }, exerciseDB);
  console.log(JSON.stringify(test4, null, 2));

  console.log('\n=== TEST 5: Data kurang dari 2 sesi (harus belum_cukup_data) ===');
  const test5 = evaluateProgressiveOverload('dd002', {
    sessions: [
      { date: '2026-06-13', sets: [{ reps: 10, weight_kg: 60 }] }
    ]
  }, exerciseDB);
  console.log(JSON.stringify(test5, null, 2));
}
