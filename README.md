# Propagandos ir dezinformacijos tekstų analizės sistema

Sistema, skirta automatiškai atpažinti propagandos technikas ir dezinformacijos naratyvus lietuviškame tekste naudojant dirbtinius intelekto modelius (Claude, Gemini, ChatGPT) ir palyginti juos su ekspertų anotacijomis.

## 👨‍🎓 Autorystė ir moksliniai pagrindai

### Kursinio darbo autorius
**Marijus Plančiūnas** (marijus.planciunas@mif.stud.vu.lt)  
MIF Informatikos 3 kurso studentas  
**Dėstytojas:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)

*Šis projektas yra Marijaus Plančiūno kursinio darbo dalis, kuriame sukurta sistema propagandos ir dezinformacijos analizei lietuviškame tekste naudojant LLM modelius.*

### Duomenų šaltiniai ir metodologija

#### ATSPARA projektas (duomenų šaltinis)
Sistema naudoja [ATSPARA](https://www.atspara.mif.vu.lt/) (Automatinė propagandos ir dezinformacijos atpažinimo sistema) projekto **duomenis**. ATSPARA yra Vilniaus universiteto Matematikos ir informatikos fakulteto vykdomas mokslo projektas.

**ATSPARA indėlis:**
- Propagandos ir dezinformacijos korpuso duomenys lietuvių kalbai
- Ekspertų anotacijos teksto fragmentams
- Propagandos technikų klasifikacija

#### Klasifikavimo metodologija
Sistema naudoja Pauliaus Zarankos (paulius.zaranka@mif.vu.lt) magistrinio darbo *"Propagandos technikų fragmentų identifikavimas lietuviškame tekste naudojant transformeriais pagrįstus, iš anksto apmokytus daugiakalbius modelius"* **klasifikavimo metodologiją**.

**Metodologijos indėlis:**
- Propagandos technikų identifikavimo metodai lietuvių kalbai
- Klasifikavimo algoritmų pritaikymas
- Vertinimo metrikų metodologija

## 🎯 Sistemos tikslas

Sukurti tyrimui skirtą įrankį, kuris:
- Analizuoja tekstus automatiškai atpažįstant 7 propagandos technikas
- Palygina LLM rezultatus su ekspertų anotacijomis  
- Apskaičiuoja tikslumo metrikas (Precision, Recall, F1, Cohen's Kappa)
- Eksportuoja detalizuotus rezultatus CSV formatu

## ⭐ Pagrindinės funkcijos

### 🤖 LLM modelių integracija
- **Claude 4** (Anthropic)
- **Gemini 2.5 Pro** (Google) 
- **GPT-4.1** (OpenAI)

### 🏷️ Propagandos technikos
- `simplification` - Sudėtingų klausimų supaprastinimas
- `emotionalExpression` - Emocijų naudojimas argumentų vietoje
- `uncertainty` - Neapibrėžti teiginiai be įrodymų
- `doubt` - Abejonių sėjimas patikimomis institucijomis
- `wavingTheFlag` - Patriotizmo išnaudojimas
- `reductioAdHitlerum` - Lyginimai su totalitariniais režimais
- `repetition` - Teiginių kartojimas

### 📊 Metrikų skaičiavimas
- **Precision** - LLM teisingų anotacijų dalis
- **Recall** - Rastos ekspertų anotacijų dalis  
- **F1 Score** - Harmoninis precision ir recall vidurkis
- **Cohen's Kappa** - Sutarimo tarp LLM ir ekspertų koeficientas
- **Pozicijos tikslumas** - Teksto pozicijų atitikimas

## 🏗️ Sistemos architektūra

```
Web Browser ──► Nginx ──► Laravel App
                              │
                              ├─► Redis (Cache/Queue/Sessions)
                              ├─► MySQL (Database)
                              └─► Queue Workers ──► LLM APIs
```

## 🚀 Greitas startas

### Reikalavimai
- PHP 8.2+
- MySQL 8.0+
- **Redis 6.0+** ⭐ BŪTINA
- Composer 2.0+

### Instaliacija

```bash
# 1. Klonuoti projektą
git clone <repository>
cd propaganda-analysis

# 2. Instaliuoti priklausomybes
composer install

# 3. Konfigūruoti aplinką
cp .env.example .env
php artisan key:generate

# 4. Konfigūruoti duomenų bazę ir Redis
# Redaguoti .env failą su DB ir Redis nustatymais

# 5. Paleisti migracijas
php artisan migrate

# 6. (Opcionalu) Paleisti queue worker
php artisan queue:work redis
```

### Konfigūracija (.env)

```env
# Duomenų bazė
DB_CONNECTION=mysql
DB_DATABASE=propaganda_analysis
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis (BŪTINA)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# LLM API raktai
CLAUDE_API_KEY=your_claude_api_key
GEMINI_API_KEY=your_gemini_api_key  
OPENAI_API_KEY=your_openai_api_key
```

## 📖 Naudojimas

### Web sąsaja
1. Atidaryti http://propaganda.local
2. Įkelti JSON failą su ekspertų anotacijomis
3. Pasirinkti LLM modelius analizei
4. Stebėti progresą
5. Eksportuoti rezultatus CSV formatu

### API naudojimas

#### Vieno teksto analizė
```bash
curl -X POST http://propaganda.local/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "1",
    "content": "Analizuojamas tekstas",
    "models": ["claude-4", "gpt-4.1"]
  }'
```

#### Batch analizė
```bash
curl -X POST http://propaganda.local/api/batch-analyze \
  -H "Content-Type: application/json" \
  -d @expert_annotations.json
```

#### Rezultatų gavimas
```bash
# Statuso tikrinimas
curl http://propaganda.local/api/status/{job_id}

# JSON rezultatai
curl http://propaganda.local/api/results/{job_id}

# CSV eksportas
curl http://propaganda.local/api/results/{job_id}/export
```

## 📄 Duomenų formatai

### Įvesties JSON formatas
```json
[
  {
    "id": 1,
    "annotations": [{
      "result": [{
        "type": "labels",
        "value": {
          "start": 0,
          "end": 100,
          "text": "tekstas",
          "labels": ["doubt", "emotionalExpression"]
        }
      }],
      "desinformationTechnique": {
        "choices": ["distrustOfLithuanianInstitutions"]
      }
    }],
    "data": {
      "content": "Pilnas analizuojamas tekstas..."
    }
  }
]
```

### CSV eksporto formatas
```csv
text_id,technique,expert_start,expert_end,model,model_start,model_end,match,position_accuracy,precision,recall,f1_score
1,doubt,0,100,claude-4,0,95,true,0.95,0.82,0.75,0.78
```

## 🔧 Plėtojimas

### Projekto struktūra
```
app/
├── Http/Controllers/    # API ir Web kontroleriai
├── Services/           # LLM integracijos
├── Jobs/              # Queue darbai
└── Models/            # Eloquent modeliai

database/
└── migrations/        # DB schemos

resources/views/       # Blade šablonai
routes/               # API ir web maršrutai
config/llm.php        # LLM konfigūracija
```

### Queue sistema
Sistema naudoja Redis queue asinchroniniam tekstų apdorojimui:

```bash
# Development
php artisan queue:work redis --verbose

# Production (su Supervisor)
php artisan queue:work redis --sleep=3 --tries=3 --memory=512
```

### Testiniai duomenys
Projekte yra paruošti testiniai failai:
- `test_data.json` - 3 tekstai su anotacijomis
- `test_without_llm.json` - 1 tekstas testui

## 📊 Metrikų interpretacija

| Metrika | Aprašymas | Geros reikšmės |
|---------|-----------|----------------|
| **Precision** | Kiek LLM rastų anotacijų yra teisingos | > 0.8 |
| **Recall** | Kiek ekspertų anotacijų LLM atpažino | > 0.7 |
| **F1 Score** | Bendras tikslumo įvertis | > 0.75 |
| **Cohen's Kappa** | Sutarimo lygis tarp LLM ir ekspertų | > 0.6 |

## 🐛 Klaidų sprendimas

### Dažniausios problemos

**Redis connection refused**
```bash
sudo systemctl start redis
redis-cli ping  # Turi grąžinti: PONG
```

**Queue jobs nestartruoja**
```bash
php artisan queue:restart
php artisan queue:work redis --verbose
```

**API 404 klaidos**
- Patikrinti API raktus .env faile
- Patikrinti interneto ryšį

### Log stebėjimas
```bash
tail -f storage/logs/laravel.log
tail -f storage/logs/worker.log
```

## 📚 Dokumentacija

- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Išsami diegimo instrukcija
- **[REQUIREMENTS.md](REQUIREMENTS.md)** - Detalūs sistemos reikalavimai
- **[API dokumentacija](docs/api.md)** - API endpointų aprašymas

## 🤝 Prisidėjimas

1. Fork projekto
2. Sukurti feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit pakeitimai (`git commit -m 'Add AmazingFeature'`)
4. Push į branch (`git push origin feature/AmazingFeature`)
5. Atidaryti Pull Request

## 📄 Autorių teisės ir licencija

### Projekto autorystė
- **Autorius**: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
- **Institucija**: VU MIF Informatikos 3 kursas
- **Dėstytojas**: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
- **Projekto tipas**: Kursinio darbo dalis

### Duomenų šaltiniai ir metodologija
- **ATSPARA korpuso duomenys**: © Vilniaus universitetas, MIF (duomenų šaltinis)
- **Klasifikavimo metodologija**: Paulius Zaranka (paulius.zaranka@mif.vu.lt), magistrinis darbas
- **Sistemos implementacija**: Marijus Plančiūnas (kursinio darbo autorius)

### Licencija
Šis projektas yra licencijuotas MIT licencija mokslo tyrimų tikslams - žiūrėti [LICENSE](LICENSE) failą.

### Naudojimo sąlygos
- Sistema skirta **mokslo tyrimų ir studijų tikslams**
- ATSPARA duomenų komerciniam naudojimui reikalingas atskiras sutikimas
- Cituojant prašome nurodyti:
  - Marijų Plančiūną kaip sistemos autorių
  - ATSPARA projektą kaip duomenų šaltinį
  - Pauliaus Zarankos metodologiją klasifikavimui

### Duomenų apsauga
- Visi duomenys apdorojami pagal BDAR reikalavimus
- API raktai ir slapti duomenys saugomi užšifruoti
- Analizės rezultatai saugomi tik mokslo tyrimų tikslais

## 🙏 Padėkos

- **Prof. Dr. Dariui Plykynui** už vadovavimą ir konsultacijas
- **ATSPARA projekto komandai** už korpuso duomenis
- **Pauliui Zarankai** už klasifikavimo metodologiją
- **Vilniaus universiteto MIF** už studijų galimybes
- Anthropic už Claude API
- Google už Gemini API  
- OpenAI už GPT API
- Laravel community už framework'ą

## 📞 Kontaktai

**Projekto autorius:**
- Marijus Plančiūnas: marijus.planciunas@mif.stud.vu.lt

**Akademiniai klausimai:**
- Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)

**Duomenų šaltiniai:**
- ATSPARA projektas: https://www.atspara.mif.vu.lt/
- Paulius Zaranka: paulius.zaranka@mif.vu.lt

---

⭐ **Svarbu**: Redis yra būtinas sistemos komponentas. Be Redis cache, sessions ir queue neveiks!

🚀 **Rekomenduojama**: Naudoti Supervisor production aplinkoje queue worker'iams valdyti.

📚 **Moksliniai tyrimai**: Sistema skirta mokslo tyrimų tikslams naudojant ATSPARA korpuso duomenis.