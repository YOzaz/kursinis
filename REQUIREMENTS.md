# Reikalavimai sistemai "Propagandos ir dezinformacijos tekstų anotavimas su LLM įrankiu per API sąsają"

## 1. Sistemos apžvalga

### 1.1 Tikslas
Sukurti tyrimui skirtą sistemą, kuri naudodama tris LLM modelius (Claude 4, Gemini 2.5 Pro, ChatGPT 4.1 arba naujesnius) atpažintų propagandos technikas ir dezinformacijos naratyvus tekstuose, lyginant rezultatus su ekspertų anotacijomis.

### 1.2 Pagrindiniai komponentai
- API serveris su endpointais LLM užklausoms
- Promptų generatorius pagal RISEN metodologiją
- Rezultatų agregavimo modulis
- Minimali vartotojo sąsaja JSON failų įkėlimui
- Duomenų eksporto funkcionalumas

## 2. Funkciniai reikalavimai

### 2.1 LLM API integracija

#### 2.1.1 Palaikomi modeliai
- Anthropic Claude 4 (arba naujesnis)
- Google Gemini 2.5 Pro (arba naujesnis)
- OpenAI ChatGPT 4.1 (arba naujesnis)

#### 2.1.2 API funkcionalumas
- Asinchroninės užklausos per eiles (queue) 1000 tekstų apdorojimui
- API raktų valdymas per environment kintamuosius
- Klaidų apdorojimas ir pakartotiniai bandymai
- Rate limiting valdymas kiekvienam modeliui

### 2.2 RISEN promptų šablonai

#### 2.2.1 Pagrindinis prompt'as propagandos technikų atpažinimui

```
Role: Tu esi propagandos ir dezinformacijos analizės ekspertas, specializuojantis politinių tekstų vertinime.

Instructions: Išanalizuok pateiktą tekstą ir identifikuok propagandos technikas bei dezinformacijos naratyvus. Kiekvienai identifikuotai technikai nurodyk tikslią teksto vietą (pradžios ir pabaigos pozicijas simboliais) ir pateik teksto ištrauką.

Steps:
1. Perskaityk visą tekstą ir susidaryti bendrą įspūdį
2. Identifikuok propagandos technikas iš šio sąrašo:
   - simplification (supaprastinimas)
   - emotionalExpression (emocinė išraiška)
   - uncertainty (neapibrėžtumas)
   - doubt (abejonių sėjimas)
   - wavingTheFlag (patriotizmo išnaudojimas)
   - reductioAdHitlerum (lyginimas su totalitariniais režimais)
   - repetition (kartojimas)
3. Kiekvienai technikai rask konkrečias teksto vietas
4. Identifikuok pagrindinius dezinformacijos naratyvus:
   - distrustOfLithuanianInstitutions (nepasitikėjimas Lietuvos institucijomis)
   - Pasitikėjimo NATO mažinimas
   - Kiti pastebėti naratyvai

End goal: Grąžink JSON formatą su anotacijomis, atitinkančiu pateiktą struktūrą.

Narrowness: Analizuok tik aiškiai identifikuojamas propagandos technikas. Jei abejoji, geriau praleisk. Kiekviena anotacija turi turėti tikslią teksto poziciją.
```

#### 2.2.2 JSON formato instrukcijos LLM modeliams

```
Grąžink rezultatus šiuo JSON formatu:
{
  "primaryChoice": {
    "choices": ["yes"] // jei rastos propagandos technikos, ["no"] jei ne
  },
  "annotations": [
    {
      "type": "labels",
      "value": {
        "start": [pradžios pozicija simboliais],
        "end": [pabaigos pozicija simboliais],
        "text": "[tikslus tekstas iš dokumento]",
        "labels": ["technika1", "technika2"] // iš apibrėžto sąrašo
      }
    }
  ],
  "desinformationTechnique": {
    "choices": ["naratyvas1", "naratyvas2"] // iš apibrėžto sąrašo
  }
}
```

### 2.3 Analizės funkcionalumas

#### 2.3.1 Propagandos technikų atpažinimas
Sistema turi atpažinti šias technikas pagal ekspertų apibrėžimus:
- **simplification**: sudėtingų klausimų pernelyg paprastas pristatymas
- **emotionalExpression**: stiprių emocijų naudojimas racionalių argumentų vietoje
- **uncertainty**: neapibrėžtų teiginių naudojimas be įrodymų
- **doubt**: abejonių sėjimas patikimomis institucijomis ar faktais
- **wavingTheFlag**: patriotizmo išnaudojimas manipuliacijai
- **reductioAdHitlerum**: nepagrįsti lyginimai su totalitariniais režimais
- **repetition**: tų pačių teiginių kartojimas įtikimumui didinti

#### 2.3.2 Naratyvų identifikavimas
- distrustOfLithuanianInstitutions
- Pasitikėjimo NATO mažinimas
- Kiti vartotojo apibrėžti naratyvai iš JSON failo

### 2.4 Rezultatų agregavimas ir palyginimas

#### 2.4.1 Statistinė analizė
- **Sutapimo su ekspertais metrika**: kiek procentų LLM anotacijų sutampa su ekspertų anotacijomis
- **Tikslumo (Precision)**: kiek LLM pažymėtų anotacijų yra teisingos
- **Atšaukimo (Recall)**: kiek ekspertų anotacijų LLM atpažino
- **F1 balas**: harmoninis vidurkis tarp tikslumo ir atšaukimo
- **Pozicijų tikslumas**: ar LLM teisingai nurodo teksto pradžią ir pabaigą (+/- 10 simbolių tolerancija)
- **Cohen's Kappa**: sutarimo lygis tarp LLM ir ekspertų

#### 2.4.2 Modelių palyginimas
- Kiekvieno modelio metrikos atskirai
- Modelių tarpusavio palyginimas
- Standartinis nuokrypis tarp modelių
- Geriausiai ir prasčiausiai atpažįstamos technikos pagal modelį

## 3. API specifikacija

### 3.1 Endpointai

#### POST /api/analyze
```json
Request:
{
  "text_id": "37735",
  "content": "tekstas analizei",
  "models": ["claude-4", "gemini-2.5-pro", "gpt-4.1"]
}

Response:
{
  "text_id": "37735",
  "results": {
    "claude-4": { /* anotacijų struktūra */ },
    "gemini-2.5-pro": { /* anotacijų struktūra */ },
    "gpt-4.1": { /* anotacijų struktūra */ }
  }
}
```

#### POST /api/batch-analyze
```json
Request:
{
  "file_content": { /* JSON turinys su ekspertų anotacijomis */ },
  "models": ["claude-4", "gemini-2.5-pro", "gpt-4.1"]
}

Response:
{
  "job_id": "unique-job-id",
  "status": "processing",
  "total_texts": 1000
}
```

#### GET /api/results/{job_id}
```json
Response:
{
  "job_id": "unique-job-id",
  "status": "completed",
  "comparison_metrics": {
    "claude-4": {
      "precision": 0.82,
      "recall": 0.75,
      "f1_score": 0.78,
      "cohen_kappa": 0.71
    },
    /* kiti modeliai */
  },
  "detailed_results": "url_to_download_csv"
}
```

## 4. Duomenų struktūra

### 4.1 Įvesties JSON formatas (ekspertų anotacijos)
```json
{
  "id": 37735,
  "annotations": [{
    "result": [{
      "type": "labels",
      "value": {
        "start": 0,
        "end": 360,
        "text": "analizuojamas tekstas",
        "labels": ["simplification"]
      }
    }],
    "desinformationTechnique": {
      "choices": ["distrustOfLithuanianInstitutions"]
    }
  }],
  "data": {
    "content": "pilnas tekstas"
  }
}
```

### 4.2 Duomenų bazės struktūra palyginimui

#### Lentelė: analysis_jobs
- job_id (PK)
- created_at
- status
- total_texts

#### Lentelė: text_analysis
- id (PK)
- job_id (FK)
- text_id
- expert_annotations (JSON)
- claude_annotations (JSON)
- gemini_annotations (JSON)
- gpt_annotations (JSON)

#### Lentelė: comparison_metrics
- id (PK)
- job_id (FK)
- text_id
- model_name
- true_positives
- false_positives
- false_negatives
- position_accuracy

### 4.3 Eksporto formatas (CSV)
```csv
text_id,technique,expert_start,expert_end,model,model_start,model_end,match,position_accuracy
37735,simplification,0,360,claude-4,0,355,true,0.98
37735,emotionalExpression,1089,1454,claude-4,1100,1450,true,0.95
```

## 5. Nefunkciniai reikalavimai

### 5.1 Našumas
- Asinchroninis 1000 tekstų apdorojimas
- Maksimalus lygiagretus užklausų skaičius pagal kiekvieno API limitus
- Progreso stebėjimas realiu laiku

### 5.2 Saugumas
- API raktų saugojimas environment kintamuosiuose
- HTTPS visoms API užklausoms

### 5.3 Konfigūracija
- Visi nustatymai per .env failą:
  - CLAUDE_API_KEY
  - GEMINI_API_KEY
  - OPENAI_API_KEY
  - MAX_CONCURRENT_REQUESTS
  - RETRY_ATTEMPTS

## 6. Vartotojo sąsaja (minimali)

### 6.1 Funkcionalumas
- JSON failo įkėlimo zona
- Modelių pasirinkimas (checkbox)
- "Pradėti analizę" mygtukas
- Progreso juosta
- Rezultatų eksporto mygtukas (CSV formatu)

### 6.2 Rezultatų atvaizdavimas
- Bendra palyginimo lentelė:
  - Modelis | Precision | Recall | F1 | Cohen's Kappa
- Eksporto į CSV galimybė detaliai analizei

## 7. API dokumentacija

Atskiras dokumentas su:
- Autentifikacijos instrukcijomis
- Visų endpointų aprašymais
- Request/Response pavyzdžiais
- Klaidų kodais
- Rate limiting informacija
- Naudojimo pavyzdžiais Python/JavaScript/PHP
