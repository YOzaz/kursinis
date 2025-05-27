# Custom Prompt'ų ir Analizių Pakartojimo Vadovas

## 🎯 Kas yra custom prompt'ai?

**Custom prompt'ai** - tai pritaikytos AI instrukcijos, kurias galite naudoti propagandos analizės AI modeliams. Sistema leidžia kurti individualius prompt'us kiekvienai analizei arba pakartoti ankstesnes analizės su naujais prompt'ais.

## 🧪 Custom prompt'ų tikslas

### Kodėl reikalingi custom prompt'ai?
- **Prompt optimizavimas**: Rasti geriausią AI instrukcijų formulavimą jūsų poreikiams
- **A/B testavimas**: Palyginti skirtingų prompt'ų efektyvumą su tais pačiais tekstais
- **Konteksto pritaikymas**: Adaptuoti AI modelius specifiniams tekstų tipams (naujienos, social media, akademiniai tekstai)
- **Metrikų palyginimas**: Matyti, kaip prompt'ų pakeitimai paveiks analizės rezultatus
- **Duomenų taupymas**: Pakartoti analizę su nauju prompt'u nenaudojant failų iš naujo

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

## 📋 Custom prompt'ų naudojimo žingsniukai

### 1. Nauja analizė su custom prompt'u

**API endpoint**: `POST /api/analyze`

```json
{
  "text_id": "test-123",
  "content": "Jūsų analizuojamas tekstas...",
  "models": ["claude-sonnet-4", "gemini-2.5-pro"],
  "custom_prompt": "Esi propaganda analizės ekspertas. Atlikite detalų teksto tyrimą...",
  "name": "Ekspertinio prompt'o testas",
  "description": "Testuojame ekspertiškų instrukcijų efektyvumą"
}
```

### 2. Analizės pakartojimas su nauju prompt'u

**API endpoint**: `POST /api/repeat-analysis`

```json
{
  "reference_analysis_id": "550e8400-e29b-41d4-a716-446655440000",
  "models": ["claude-sonnet-4"],
  "custom_prompt": "Esi žurnalistas. Ieškokite manipuliacinių elementų...",
  "name": "Žurnalistinio prompt'o testas",
  "description": "Palyginimas su ekspertiniu prompt'u"
}
```

### 3. Rezultatų palyginimas

1. **Atlikite pirmą analizę** su standartiniu prompt'u
2. **Pakartokite su custom prompt'u** naudojant `repeat-analysis`
3. **Palyginkite rezultatus** API atsakymuose
4. **Eksportuokite duomenis** CSV formatui

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

## 🎯 Praktiniai custom prompt'ų pavyzdžiai

### 1. Vaidmens palyginimas
**Tikslas**: Testuoti, ar AI geriau atpažįsta propagandą būdamas "ekspertu" vs. "žurnalistu"

**Prompt A - Ekspertas:**
```
Esi propaganda analizės ekspertas su 10 metų patirtimi. Atidžiai išanalizuokite tekstą ir identifikuokite visas propagandos technikas pagal ATSPARA klasifikaciją. Būkite tikslūs ir objektyvūs.
```

**Prompt B - Žurnalistas:**
```
Esi investigacinio žurnalismo specialistas. Ieškokite tekste manipuliacinių elementų, kurie gali klaidinti skaitytojus. Atkreipkite dėmesį į subjektyvų žodžių naudojimą ir emocinius spaudimo metodus.
```

### 2. Griežtumo palyginimas
**Tikslas**: Palyginti griežtas vs. lankstus instrukcijas

**Prompt A - Griežtas:**
```
Analizuokite tekstą ir identifikuokite TIKTAI tuos fragmentus, kurie 100% tiksliai atitinka ATSPARA propagandos technikų kriterijus. Nežymėkite abejotinų atvejų.
```

**Prompt B - Lankstus:**
```
Raskite galimus propagandos elementus tekste, net jei jie tik iš dalies atitinka kriterijus. Geriau pažymėkite daugiau, nei praleiskite.
```

### 3. API naudojimo pavyzdys su Python

```python
import requests

# 1. Pirma analizė
response1 = requests.post('http://propaganda.local/api/analyze', json={
    'text_id': 'comparison-test',
    'content': 'Analizuojamas tekstas...',
    'models': ['claude-sonnet-4'],
    'name': 'Standartinis prompt'
})
job1_id = response1.json()['job_id']

# 2. Pakartota analizė su custom prompt'u
response2 = requests.post('http://propaganda.local/api/repeat-analysis', json={
    'reference_analysis_id': job1_id,
    'models': ['claude-sonnet-4'],
    'custom_prompt': 'Jūsų custom prompt...',
    'name': 'Custom prompt testas'
})
job2_id = response2.json()['job_id']

# 3. Palyginimas rezultatų
results1 = requests.get(f'http://propaganda.local/api/results/{job1_id}')
results2 = requests.get(f'http://propaganda.local/api/results/{job2_id}')
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