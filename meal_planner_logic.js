const foodDB = require('./nutrisi_tkpi.json');

function calculateProteinNeeds(weightKg, goal) {
  const factors = { maintenance: 1.4, hypertrophy: 1.8, cutting: 2.2 };
  const factor = factors[goal] || 1.6;
  return Math.round(weightKg * factor);
}

function proteinFromPortion(food, grams) {
  return Math.round((food.protein_g / 100) * grams * 10) / 10;
}
function kaloriFromPortion(food, grams) {
  return Math.round((food.energi_kal / 100) * grams);
}

/**
 * Bangun kombinasi realistis pakai porsi_lumrah_g dari database (bukan hardcode)
 */
function buildRealisticMeal(targetProteinG, foodDatabase, proteinCategories) {
  const allProteinFoods = [];
  proteinCategories.forEach(cat => {
    (foodDatabase.kategori[cat] || []).forEach(food => {
      const portion = food.porsi_lumrah_g;
      allProteinFoods.push({
        food, portion,
        proteinFromPortion: proteinFromPortion(food, portion)
      });
    });
  });

  const results = [];
  // Single food yang udah mendekati target
  allProteinFoods.forEach(a => {
    if (Math.abs(a.proteinFromPortion - targetProteinG) <= targetProteinG * 0.2) {
      results.push({
        items: [{ nama: a.food.nama, porsi_g: a.portion, protein_g: a.proteinFromPortion, kalori: kaloriFromPortion(a.food, a.portion) }],
        totalProtein: a.proteinFromPortion
      });
    }
  });
  // Kombinasi 2 bahan
  for (let i = 0; i < allProteinFoods.length; i++) {
    for (let j = i + 1; j < allProteinFoods.length; j++) {
      const a = allProteinFoods[i], b = allProteinFoods[j];
      const combo = a.proteinFromPortion + b.proteinFromPortion;
      if (Math.abs(combo - targetProteinG) <= targetProteinG * 0.15) {
        results.push({
          items: [
            { nama: a.food.nama, porsi_g: a.portion, protein_g: a.proteinFromPortion, kalori: kaloriFromPortion(a.food, a.portion) },
            { nama: b.food.nama, porsi_g: b.portion, protein_g: b.proteinFromPortion, kalori: kaloriFromPortion(b.food, b.portion) }
          ],
          totalProtein: Math.round(combo * 10) / 10
        });
      }
    }
  }
  return results;
}

function generateDayMealPlan(weightKg, goal, mealsPerDay, foodDatabase, budgetPreference) {
  const totalProtein = calculateProteinNeeds(weightKg, goal);
  const proteinPerMeal = Math.round(totalProtein / mealsPerDay);
  const categoryByBudget = {
    budget: ['protein_nabati', 'telur'],
    mixed: ['protein_nabati', 'telur', 'ikan', 'unggas'],
    premium: ['ikan', 'unggas', 'daging_merah', 'telur']
  };
  const categories = categoryByBudget[budgetPreference] || categoryByBudget.mixed;
  const allCombos = buildRealisticMeal(proteinPerMeal, foodDatabase, categories);

  // Acak urutan biar tiap meal dapat variasi berbeda, lalu ambil bergilir
  const shuffled = [...allCombos].sort(() => Math.random() - 0.5);
  const meals = [];
  for (let i = 0; i < mealsPerDay; i++) {
    meals.push({
      mealNumber: i + 1,
      targetProtein: proteinPerMeal,
      pilihan: shuffled[i % shuffled.length]
    });
  }
  return { totalProteinTarget: totalProtein, proteinPerMeal, totalKombinasiTersedia: allCombos.length, meals };
}

const result = generateDayMealPlan(70, 'hypertrophy', 4, foodDB, 'mixed');
console.log(`Target protein harian: ${result.totalProteinTarget}g`);
console.log(`Target per meal: ${result.proteinPerMeal}g`);
console.log(`Total kombinasi tersedia di database: ${result.totalKombinasiTersedia}\n`);
result.meals.forEach(m => {
  console.log(`Meal ${m.mealNumber} (target ${m.targetProtein}g):`);
  m.pilihan.items.forEach(item => console.log(`  - ${item.nama}: ${item.porsi_g}g (${item.protein_g}g protein, ${item.kalori} kal)`));
  console.log(`  Total: ${m.pilihan.totalProtein}g protein\n`);
});
