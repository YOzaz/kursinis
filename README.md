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
Sistema naudoja ATSPARA projekto sukurtą **anotavimo ir klasifikavimo metodologiją** lietuvių kalbos propagandos analizei.

**Metodologijos indėlis:**
- Objektyvūs propagandos technikų identifikavimo kriterijai
- 10 pagrindinių propagandos technikų + 6 dezinformacijos naratyvai
- Ekspertų anotavimo principai ir instrukcijos
- Statistinių metrikų skaičiavimo metodai

## 🎯 Sistemos tikslas

Universali propagandos analizės platforma, kuri veikia dviem pagrindiniais režimais:

### 🔬 Tyrimų režimas (Research Mode)
- **Su ekspertų anotacijomis**: Palygina LLM rezultatus su ATSPARA ekspertų anotacijomis
- Apskaičiuoja tikslumo metrikas (Precision, Recall, F1, Cohen's Kappa)
- Generuoja detalizuotas palyginimo ataskaitas mokslo tyrimams

### 🛠️ Praktinio naudojimo režimas (Practical Mode)  
- **Be ekspertų anotacijų**: Analizuoja naują lietuvių kalbos tekstą
- Identifikuoja ATSPARA propagandos technikas ir disinformacijos naratyvus
- Generuoja struktūrizuotus analizės rezultatus praktiniam naudojimui

## ⭐ Pagrindinės funkcijos

### 🤖 LLM modelių integracija
- **Claude Opus 4** (Anthropic) - claude-opus-4-20250514
- **Claude Sonnet 4** (Anthropic) - claude-sonnet-4-20250514  
- **GPT-4.1** (OpenAI) - Latest flagship model
- **GPT-4o Latest** (OpenAI) - Multimodal flagship model
- **Gemini 2.5 Pro** (Google) - gemini-2.5-pro-preview-05-06
- **Gemini 2.5 Flash** (Google) - gemini-2.5-flash-preview-05-20

### 🏷️ Propagandos technikos (ATSPARA klasifikacija)
1. **Emocinė raiška** - Stiprių jausmų kėlimas, emocinė leksika
2. **Whataboutism/Red Herring** - Išsisukinėjimas, dėmesio nukreipimas
3. **Supaprastinimas** - Sudėtingų problemų pernelyg paprastas pristatymas
4. **Neapibrėžtumas** - Sąmoningas neaiškios kalbos vartojimas
5. **Apeliavimas į autoritetą** - Garsiųjų nuomonių cituojimas
6. **Mojavimas vėliava** - Patriotizmu grįsti argumentai
7. **Bandwagon** - Apeliavimas į "bandos jausmą"
8. **Abejojimas** - Patikimumo kvestionavimas, šmeižtas
9. **Reductio ad hitlerum** - Lyginimai su nekenčiamomis grupėmis
10. **Pakartojimas** - Tos pačios žinutės kartojimas

### 📊 Sistemos funkcionalumas
- **Dashboard**: Centralizuotas sistemos vaizdas su statistikomis ir greitais veiksmais
- **Analizių valdymas**: Galimybė paleisti, stebėti ir eksportuoti analizių rezultatus
- **Mission Control**: Real-time sistemos monitoringas su log'ų stebėjimu
- **Eksportavimas**: JSON/CSV formatuose su detaliais metrikų duomenimis
- **Metrikų skaičiavimas**: Precision, Recall, F1 Score, Cohen's Kappa

### 🎨 Vartotojo sąsaja
- **Responsive dizainas**: Optimizuota peržiūra visuose įrenginiuose
- **Statistikos dashboard**: Modelių našumo palyginimas ir sistemos metrikos
- **Mission Control**: Sistemos monitoringas su log'ų kopijavimo funkcionalumu
- **Rezultatų vizualizacija**: Interaktyvūs grafikai ir lentelės

## 🚀 Greitas startas

### Reikalavimai
- PHP 8.2+
- MySQL 8.0+ arba SQLite 3.8+
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
# Duomenų bazė (MySQL)
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

#### 📊 Sistemos naudojimas
1. Atidaryti http://propaganda.local (Dashboard kaip pradinis puslapis)
2. Spausti **"Nauja analizė"** greito veiksmo mygtuką
3. Įkelti JSON failą su tekstais (su arba be ekspertų anotacijų)
4. Pasirinkti LLM modelius analizei  
5. Stebėti progresą **Mission Control** puslapyje
6. Peržiūrėti rezultatus ir eksportuoti duomenis

#### 🎛️ Mission Control
- Real-time sistemos monitoringas
- Log'ų stebėjimas ir filtravimas
- Kopijuoti log pranešimus į clipboard
- Sistemos našumo metrikos

### API naudojimas

#### Vieno teksto analizė
```bash
curl -X POST http://propaganda.local/api/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "text_id": "1",
    "content": "Analizuojamas tekstas",
    "models": ["claude-opus-4", "gpt-4.1"]
  }'
```

#### Batch analizė
```bash
curl -X POST http://propaganda.local/api/batch-analyze \
  -H "Content-Type: application/json" \
  -d @lithuanian-neutral-text.json
```

## 📄 Duomenų formatai

### Įvesties JSON formatas
Sistema palaiko ATSPARA anotavimo formatą:

```json
[
  {
    "id": 12345,
    "annotations": [{
      "result": [{
        "type": "labels",
        "value": {
          "start": 0,
          "end": 100,
          "text": "tekstas",
          "labels": ["emotionalExpression"]
        }
      }]
    }],
    "data": {
      "content": "Pilnas analizuojamas tekstas..."
    }
  }
]
```

### Testiniai duomenys
- **docs/atspara-excerpt.json** - Pavyzdys su propaganda anotacijomis
- **docs/lithuanian-neutral-text.json** - Lietuviškas tekstas be propagandos

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

**Model configuration issues**
- Patikrinti API raktus .env faile
- Patikrinti interneto ryšį
- Naudoti Mission Control sistemų monitoringui

### Log stebėjimas
```bash
tail -f storage/logs/laravel.log
```

## 📚 Dokumentacija

- **[API dokumentacija](docs/API.md)** - API endpointų aprašymas
- **[Architektūros dokumentacija](docs/ARCHITECTURE.md)** - Sistemos architektūros aprašymas
- **[ATSPARA Anotavimo metodologija](docs/ATSPARA-ANNOTATION-METHODOLOGY.md)** - Propagandos technikų klasifikavimo kriterijai
- **[Metrikų vadovas](docs/METRICS-GUIDE.md)** - Išsami metrikų analizė ir interpretacija
- **[Problemų sprendimas](docs/TROUBLESHOOTING.md)** - Dažniausių problemų sprendimo vadovas

## 📄 Autorių teisės ir licencija

### Projekto autorystė
- **Autorius**: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
- **Institucija**: VU MIF Informatikos 3 kursas
- **Dėstytojas**: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
- **Projekto tipas**: Kursinio darbo dalis

### Duomenų šaltiniai ir metodologija
- **ATSPARA korpuso duomenys ir metodologija**: © Vilniaus universitetas, MIF
- **Sistemos implementacija**: Marijus Plančiūnas (kursinio darbo autorius)

### Licencija
Šis projektas yra licencijuotas MIT licencija mokslo tyrimų tikslams - žiūrėti [LICENSE](LICENSE) failą.

## 🙏 Padėkos

- **Prof. Dr. Dariui Plykynui** už vadovavimą ir konsultacijas
- **ATSPARA projekto komandai** už korpuso duomenis ir anotavimo metodologiją
- **Vilniaus universiteto MIF** už studijų galimybes
- **[Claude Code](https://claude.ai/code)** už neįkainojamą pagalbą sistemų plėtojime
- **Anthropic, Google, OpenAI** už LLM API prieigą

## 📞 Kontaktai

**Projekto autorius:**
- Marijus Plančiūnas: marijus.planciunas@mif.stud.vu.lt

**Akademiniai klausimai:**
- Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)

**Duomenų šaltiniai:**
- ATSPARA projektas: https://www.atspara.mif.vu.lt/

---

⭐ **Svarbu**: Redis yra būtinas sistemos komponentas. Be Redis cache, sessions ir queue neveiks!

📚 **Moksliniai tyrimai**: Sistema skirta mokslo tyrimų tikslams naudojant ATSPARA korpuso duomenis.