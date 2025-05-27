# Eksperimentų vadovas

## 🎯 Kas yra eksperimentai?

**Eksperimentai** - tai custom prompt'ų testavimo ir palyginimo sistema propagandos analizės AI modeliams. Leidžia testuoti skirtingus AI instrukcijų formulavimus ir palyginti jų efektyvumą.

## 🧪 Eksperimentų tikslas

### Kodėl reikalingi eksperimentai?
- **Prompt optimizavimas**: Rasti geriausią AI instrukcijų formulavimą jūsų poreikiams
- **A/B testavimas**: Palyginti skirtingų prompt'ų efektyvumą objektyviai
- **Konteksto pritaikymas**: Adaptuoti AI modelius specifiniams tekstų tipams (naujienos, social media, akademiniai tekstai)
- **Metrikų palyginimas**: Matyti, kaip prompt'ų pakeitimai paveiks Precision, Recall, F1 Score

### Praktiniai pavyzdžiai
- **Vaidmens keitimas**: "Esi propagandos ekspertas" vs. "Esi žurnalistas"
- **Griežtumo lygis**: Griežtos instrukcijos vs. lankstūs nurodymai
- **Konteksto specifika**: "Lietuvos medijos analizė" vs. "Bendras teksto tyrimas"

## 🏗️ RISEN metodologija

Sistema naudoja **RISEN** prompt struktūravimo metodologiją:

### **R** - Role (Vaidmuo)
```
Esi propaganda ir dezinformacijos analizės ekspertas, specializuojantis lietuvių kalbos tekstų tyrimuose.
```

### **I** - Instructions (Instrukcijos)
```
Analizuokite pateiktą tekstą ir identifikuokite:
1. Propagandos technikas pagal ATSPARA klasifikaciją
2. Dezinformacijos naratyvus
3. Emocinius manipuliacijos elementus
```

### **S** - Situation (Situacija)
```
Analizuojate Lietuvos medijų tekstus, social media įrašus arba politinę komunikaciją.
Tekstai gali būti iš įvairių šaltinių: naujienų portalų, Facebook, Twitter, politikų pareiškimų.
```

### **E** - Execution (Vykdymas)
```
1. Perskaitykite tekstą atidžiai
2. Identifikuokite propagandos technikas
3. Pažymėkite tikslias tekstų vietas
4. Nurodykite patikimumą (1-10 skalėje)
```

### **N** - Needle (Esmė)
```
Grąžinkite JSON formatą su anotacijomis pagal ATSPARA standartą.
```

## 📋 Eksperimento kūrimo žingsniukai

### 1. Eksperimento planas
1. **Eikite į "Eksperimentai" skiltį**
2. **Spragtelėkite "Naujas eksperimentas"**
3. **Užpildykite pagrindą informaciją:**
   - Pavadinimas (pvz., "Griežtas vs. Lankstus prompt")
   - Aprašymas (eksperimento tikslas)

### 2. RISEN prompt'o redagavimas
Kiekvienai kategorijai pritaikykite tekstą:

**Role pavyzdžiai:**
- Ekspertas: "Esi propaganda analizės ekspertas"
- Žurnalistas: "Esi tyrimas žurnalistas"
- Analitikas: "Esi duomenų analitikas"

**Instructions pavyzdžiai:**
- Griežtas: "Tiksliai identifikuokite propagandos technikas pagal kriterijus"
- Lankstus: "Raskite galimus propagandos elementus tekste"

### 3. Prompt'o peržiūra
- **Real-time preview**: Matysite galutinį prompt'ą iš karto
- **Auto-update**: Prompt'as atsinaujins keičiant RISEN laukus
- **Testavimas**: Galite išmėginti prompt'ą prieš išsaugant

### 4. Išsaugojimas ir testavimas
1. **Išsaugokite eksperimentą**
2. **Naudokite jį analizės metu**
3. **Palyginkite rezultatus** su standartiniais prompt'ais

## 📊 Rezultatų analizė

### Metrikų palyginimas
Sistema apskaičiuoja:
- **Precision**: Kiek AI rastų anotacijų yra teisingos
- **Recall**: Kiek ekspertų anotacijų AI atpažino  
- **F1 Score**: Bendras efektyvumo įvertis
- **Cohen's Kappa**: Sutarimo su ekspertais lygis

### Eksportavimo galimybės
- **CSV failas**: Detalūs rezultatai Excel analizei
- **JSON failas**: Struktūrizuoti duomenys programiniam naudojimui
- **Statistikos CSV**: Suvestinės metrikos palyginimui

## 🎯 Praktiniai eksperimentų pavyzdžiai

### 1. Vaidmens eksperimentas
**Tikslas**: Testuoti, ar AI geriau atpažįsta propagandą būdamas "ekspertu" vs. "žurnalistu"

**Eksperimentas A - Ekspertas:**
```
Role: Esi propaganda analizės ekspertas su 10 metų patirtimi
```

**Eksperimentas B - Žurnalistas:**
```
Role: Esi investigacinio žurnalismo specialistas
```

### 2. Griežtumo eksperimentas
**Tikslas**: Palyginti griežtas vs. lankstus instrukcijas

**Eksperimentas A - Griežtas:**
```
Instructions: Tiksliai identifikuokite TIKTAI tuos fragmentus, kurie 100% atitinka ATSPARA kriterijus
```

**Eksperimentas B - Lankstus:**
```
Instructions: Raskite galimus propagandos elementus, net jei neatitinka visų kriterijų
```

### 3. Konteksto eksperimentas
**Tikslas**: Testuoti, ar konteksto nurodymas pagerina rezultatus

**Eksperimentas A - Su kontekstu:**
```
Situation: Analizuojate Lietuvos politinių partijų komunikaciją rinkimų laikotarpiu
```

**Eksperimentas B - Be konteksto:**
```
Situation: Analizuojate bendrus lietuvių kalbos tekstus
```

## 📈 Eksperimentų vertinimas

### Gerų rezultatų kriterijai
- **F1 Score > 0.75**: Eksperimentas efektyvus
- **Precision > 0.80**: Mažai klaidingų atpažinimų  
- **Recall > 0.70**: Nepraleista daug propagandos atvejų
- **Cohen's Kappa > 0.60**: Geras sutarimas su ekspertais

### Optimizavimo strategijos
1. **Iteratyvus tobulinimas**: Keiskite prompt'us pagal metrikus
2. **A/B testavimas**: Palyginkite 2-3 prompt'ų variantus
3. **Specifinio konteksto**: Pritaikykite prompt'us konkretiems teksto tipams
4. **Balansavimas**: Raskite optimalų Precision/Recall balansą

## ⚡ Greiti patarimai

### ✅ Geroji praktika
- Aiškūs, specifiniai nurodymai
- Lietuvių kalbos konteksto nuróymas
- Griežtas JSON formato reikalavimas
- Patikimimo lygio prašymas

### ❌ Vengtinos klaidos
- Perdėtai ilgi prompt'ai (>2000 žodžių)
- Prieštaringi nurodymai
- Per daug abstrakcijos
- Angliški terminai be paaiškinimo

## 🔗 Integracija su sistema

### Eksperimentų naudojimas
1. **Sukurkite eksperimentą**
2. **Nurodykite jį batch analizės metu**
3. **Palyginkite su standartiniais prompt'ais**
4. **Eksportuokite rezultatus analizei**

### API naudojimas
```bash
# Eksperimento naudojimas per API
curl -X POST /api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "test-1",
    "content": "Tekstas analizei...",
    "models": ["claude-4"],
    "experiment_id": 123
  }'
```

---

**💡 Atminkite**: Eksperimentai - tai galinga priemonė AI instrukcijų optimizavimui. Investuokite laiką į promtp'ų tobulinimą, ir jūsų analizės rezultatai žymiai pagerės!