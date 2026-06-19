const exerciseDB = require('./program_latihan.json');

/**
 * Ambil detail exercise lengkap dari ID
 */
function getExerciseById(id, db) {
  for (const group of Object.values(db.exercises)) {
    const found = group.find(ex => ex.id === id);
    if (found) return found;
  }
  return null;
}

/**
 * Cek apakah exercise cocok dengan equipment yang user punya
 */
function isExerciseAvailable(exercise, userEquipment) {
  return exercise.equipment.some(eq => userEquipment.includes(eq));
}

/**
 * Cari pengganti exercise dari kelompok otot yang sama jika exercise asli
 * tidak cocok dengan equipment user. Menghindari exercise yang sudah dipakai
 * di hari yang sama agar tidak monoton.
 */
function findSubstitute(originalExercise, muscleGroup, userEquipment, db, usedIdsToday) {
  const candidates = db.exercises[muscleGroup] || [];
  const substitute = candidates.find(ex =>
    ex.id !== originalExercise.id &&
    isExerciseAvailable(ex, userEquipment) &&
    !usedIdsToday.has(ex.id)
  );
  return substitute || null;
}

/**
 * Generate program lengkap, sudah disesuaikan dengan equipment user
 */
function generateAdaptedProgram(frequencyPerWeek, userEquipment, db) {
  const templateKey = `${frequencyPerWeek}x_seminggu`;
  const template = db.program_templates[templateKey];
  if (!template) throw new Error('Frekuensi tidak didukung. Pilih 2, 3, atau 4.');

  const adaptedDays = template.hari.map(day => {
    const usedIdsToday = new Set();
    // Pre-register exercise yang TERSEDIA langsung (tidak perlu diganti), biar substitusi
    // untuk exercise lain tidak memilih ID yang sama
    day.exercise_ids.forEach(exId => {
      const ex = getExerciseById(exId, db);
      if (ex && isExerciseAvailable(ex, userEquipment)) {
        usedIdsToday.add(ex.id);
      }
    });

    const adaptedExercises = day.exercise_ids.map(exId => {
      const muscleGroup = day.kelompok_otot.find(mg =>
        (db.exercises[mg] || []).some(ex => ex.id === exId)
      );
      const original = getExerciseById(exId, db);

      if (isExerciseAvailable(original, userEquipment)) {
        return { ...original, status: 'sesuai' };
      }

      const substitute = findSubstitute(original, muscleGroup, userEquipment, db, usedIdsToday);
      if (substitute) {
        usedIdsToday.add(substitute.id);
        return { ...substitute, status: 'diganti', original_nama: original.nama };
      }

      return { ...original, status: 'tidak_tersedia' };
    });

    return { label: day.label, exercises: adaptedExercises };
  });

  return {
    nama_program: template.nama,
    deskripsi: template.deskripsi,
    equipment_user: userEquipment,
    hari: adaptedDays
  };
}

// Test: User cuma punya bodyweight (anak gym budget minim)
console.log('=== PROGRAM untuk user BODYWEIGHT ONLY, 3x/minggu ===\n');
const program1 = generateAdaptedProgram(3, ['bodyweight'], exerciseDB);
console.log(`Program: ${program1.nama_program}`);
console.log(`${program1.deskripsi}\n`);
program1.hari.forEach(day => {
  console.log(`${day.label}:`);
  day.exercises.forEach(ex => {
    const tag = ex.status === 'diganti' ? ` [diganti dari: ${ex.original_nama}]` : ex.status === 'tidak_tersedia' ? ' [TIDAK ADA PENGGANTI]' : '';
    console.log(`  - ${ex.nama}: ${ex.target_sets} set x ${ex.target_reps}${tag}`);
  });
  console.log('');
});

console.log('\n=== PROGRAM untuk user GYM LENGKAP, 3x/minggu ===\n');
const program2 = generateAdaptedProgram(3, ['gym_lengkap'], exerciseDB);
program2.hari.forEach(day => {
  console.log(`${day.label}:`);
  day.exercises.forEach(ex => console.log(`  - ${ex.nama}: ${ex.target_sets} set x ${ex.target_reps}`));
  console.log('');
});
