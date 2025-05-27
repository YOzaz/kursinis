# ATSPARA Anotavimo metodologija

## Apie šį dokumentą

Šis dokumentas aprašo ATSPARA projekto anotavimo metodologiją, kuri naudojama šiame propagandos analizės projekte. Metodologija buvo sukurta Vilniaus universiteto Matematikos ir informatikos fakulteto ATSPARA projekto metu.

**Projekto kontekstas:**
- **Autorius:** Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
- **Dėstytojas:** Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
- **Duomenų šaltinis:** ATSPARA projektas (https://www.atspara.mif.vu.lt/)

## Bendri anotavimo principai

### 1. Objektyvumas
- Objektyviai žymimos sutartos propagandos technikos, nepriklausomai iš kurios politinės stovyklos jos galėtų ateiti
- Tiesiog objektyviai be šališkumų sužymimos pastebėtos propagandos technikos pagal jų apibrėžimus

### 2. Nepriklausomumas
- Konkretaus anotuotojo visi straipsniai turi būti anotuojami nepriklausomai nuo kitų anotuotojų sužymėjimų

### 3. Propagandos vertinimo kriterijai
"Ar tai yra propaganda?" klausimo atveju:
- Anotuotojai turi argumentuotai atsakyti remiantis objektyviais propagandos apibrėžimais
- Vertinti kokią santykinę dalį nuo viso teksto propagandos fragmentai sudaro
- Jei >40% teksto sudaro propaganda, tuomet žymėti "TAIP"
- Jei konsoliduotas sprendimas nerandamas dėl kardinalaus nuomonių išsiskyrimo, paliekamas variantas "Neįmanoma nustatyti"

### 4. Papildomi žymėjimai
- **Vidinė politinė kova:** Jei straipsnis su didele tikimybe ateina iš vidinės politinės kovos ir nėra inspiruotas trečiųjų šalių
- **Auksinis propaganda standartas:** Straipsniai, kuriuose aiškiai dominuoja prokremliški naratyvai ir kurie vargu ar inspiruoti vidinės politinės kovos

## Propagandos technikos klasifikacija

### 1. Emocinė raiška
Bandoma sukelti stiprius jausmus; emocinė leksika, etiketės, vertybiniai argumentai, hiperbolizavimas/sumenkinimas.

#### 1.1 Apeliavimas į jausmus (Emotional Appeal)
- **Apibrėžimas:** Siekiama sukelti stiprius jausmus/emocijas, siekiant pakeisti nuomonę ar veiksmus
- **Konfigūracijos raktas:** `emotionalAppeal`

#### 1.2 Apeliavimas į baimę (Appeal to fear/prejudice)
- **Apibrėžimas:** Sėjama visuomenėje panika ir nerimas siekiant sutelkti paramą
- **Konfigūracijos raktas:** `appealToFear`
- **Pavyzdžiai:**
  - "either we go to war or we will perish"
  - "we must stop those refugees as they are terrorists"

#### 1.3 Vertinamoji, emocinė leksika (Loaded Language)
- **Apibrėžimas:** Vartojami stiprias asociacijas/konotacijas turintys žodžiai
- **Konfigūracijos raktas:** `loadedLanguage`
- **Pavyzdžiai:**
  - "a lone lawmaker's childish shouting"
  - "how stupid and petty things have become in Washington"

#### 1.4 Etikečių klijavimas (Name calling/Ad Hominem)
- **Apibrėžimas:** Neigiamą konotaciją turinčių žodžių vartojimas oponentui sumenkinti
- **Konfigūracijos raktas:** `nameCalling`
- **Pavyzdžiai:**
  - "Republican congressweasels"
  - "Bush the Lesser"

#### 1.5 Perdėtas vertinimas/hiperbolizavimas (Exaggeration or Minimisation)
- **Apibrėžimas:** Kažkas aprašoma perdėtai arba sumenkinamai
- **Konfigūracijos raktas:** `exaggeration`
- **Pavyzdžiai:**
  - "Democrats bolted as soon as Trump's speech ended"
  - "We're going to have unbelievable intelligence"

#### 1.6 "Blizgantys" apibendrinimai (Glittering generalities)
- **Apibrėžimas:** Patraukūs, tačiau migloti žodžiai be aiškios reikšmės
- **Konfigūracijos raktas:** `glitteringGeneralities`
- **Charakteristikos:** Vertybiniai žodžiai (taika, laimė, saugumas, laisvė, tiesa)

### 2. Whataboutism, Red Herring, Straw Man
Išsisukinėjimas; oponento pozicijos menkinimas; dėmesio nukreipimas kitur.

#### 2.1 Whataboutism
- **Apibrėžimas:** Diskredituoti oponento poziciją apkaltinant veidmainiavimu
- **Konfigūracijos raktas:** `whataboutism`
- **Pavyzdžiai:**
  - Šalis atsako į žmogaus teisių pažeidimų kritiką nurodydama į JAV vergijos istoriją
  - "Qatar spending profusely on Neymar, not fighting terrorism"

#### 2.2 Red Herring (Nereikšmingų duomenų pateikimas)
- **Apibrėžimas:** Nereikšmingi dalykais siekiant nukreipti dėmesį
- **Konfigūracijos raktas:** `redHerring`

#### 2.3 Straw Man (Pozicijos iškraipymas)
- **Apibrėžimas:** Oponento teiginys pakeičiamas panašiu teiginiu ir tada paneigiamas
- **Konfigūracijos raktas:** `strawMan`

### 3. Supaprastinimas
Daroma prielaida, kad yra viena problemos priežastis; kaltė perkeliama vienam asmeniui/grupei.

#### 3.1 Supaprastinimas (Causal Oversimplification)
- **Apibrėžimas:** Paprasti atsakymai į sudėtingas problemas
- **Konfigūracijos raktas:** `causalOversimplification`

#### 3.2 Juoda-balta (Black-and-white Fallacy)
- **Apibrėžimas:** Du alternatyvūs variantai kaip vienintelės galimybės
- **Konfigūracijos raktas:** `blackAndWhite`

#### 3.3 Klišės (Thought-terminating cliché)
- **Apibrėžimas:** Stereotipiniai posakiai, neskatinantys kritinio mąstymo
- **Konfigūracijos raktas:** `thoughtTerminatingCliche`
- **Pavyzdžiai:** "It is what it is", "Nobody's perfect"

#### 3.4 Šūkiai (Slogans)
- **Apibrėžimas:** Santrauka, žymi frazė
- **Konfigūracijos raktas:** `slogans`
- **Pavyzdžiai:** "Make America great again!"

### 4. Neapibrėžtumas (Obfuscation)
- **Apibrėžimas:** Sąmoningas neaiškios kalbos vartojimas
- **Konfigūracijos raktas:** `obfuscation`
- **Tikslas:** Auditorija žinutę gali interpretuoti savaip

### 5. Apeliavimas į autoritetą (Appeal to authority)
- **Apibrėžimas:** Cituojami garsūs autoritetai, remiantys propagandisto poziciją
- **Konfigūracijos raktas:** `appealToAuthority`
- **Pastaba:** Apima ir Testimonials

### 6. Mojavimas vėliava (Flag-waving)
- **Apibrėžimas:** Veiksmų pateisimas patriotiškumu
- **Konfigūracijos raktas:** `flagWaving`
- **Pavyzdžiai:** "patriotism mean no questions"

### 7. Sekimas iš paskos (Bandwagon)
- **Apibrėžimas:** Apeliavimas į "bandos jausmą"
- **Konfigūracijos raktas:** `bandwagon`
- **Pavyzdžiai:** "90% of citizens support our initiative. You should."

### 8. Abejojimas
#### 8.1 Abejojimas (Doubt)
- **Apibrėžimas:** Grupės/asmens patikimumo kvestionavimas
- **Konfigūracijos raktas:** `doubt`

#### 8.2 Šmeižtas (Smears)
- **Apibrėžimas:** Reputacijos kenkimas
- **Konfigūracijos raktas:** `smears`

### 9. Reductio ad hitlerum
- **Apibrėžimas:** Įtikinėjimas nepritarti nurodant, kad tai populiaru tarp nekenčiamų grupių
- **Konfigūracijos raktas:** `reductioAdHitlerum`
- **Pavyzdžiai:** "Do you know who else was doing that? Hitler!"

### 10. Pakartojimas (Repetition)
- **Apibrėžimas:** Tos pačios žinutės kartojimas tekste
- **Konfigūracijos raktas:** `repetition`

## Dezinformacijos naratyvai

Sistema identifikuoja šiuos pagrindinius dezinformacijos naratyvus lietuviškoje erdvėje:

### 1. Nepasitikėjimas Lietuvos institucijomis
- **Konfigūracijos raktas:** `distrustOfLithuanianInstitutions`
- **Aprašymas:** Siekiama sumažinti pasitikėjimą valstybės institucijomis

### 2. NATO nepasitikėjimas
- **Konfigūracijos raktas:** `natoDistrust`
- **Aprašymas:** Pasitikėjimo NATO ir Vakarų sąjungininkais mažinimas

## Techninė implementacija

### Anotacijų struktūra
```json
{
  "primaryChoice": {
    "choices": ["yes"] // arba ["no"]
  },
  "annotations": [
    {
      "type": "labels",
      "value": {
        "start": 0,
        "end": 50,
        "text": "tikslus tekstas",
        "labels": ["technika1", "technika2"]
      }
    }
  ],
  "desinformationTechnique": {
    "choices": ["naratyvas1"]
  }
}
```

### Sistemos konfigūracija
Visos propagandos technikos ir naratyvai konfigūruojami `config/llm.php` faile naudojant aukščiau nurodytus raktus.

## Naudojimas sistemoje

Ši metodologija įgyvendinta:
- **PromptService** klasėje - sukuria RISEN struktūros prompt'us
- **LLM servisuose** - Claude, Gemini, OpenAI analizė
- **MetricsService** klasėje - rezultatų palyginimas su ekspertų anotacijomis
- **Testų sistemoje** - automatizuotas metodologijos validavimas

---

*Šis dokumentas yra ATSPARA projekto anotavimo instrukcijų adaptacija Marijaus Plančiūno kursinio darbo sistemai. Originalūs ATSPARA projekto duomenys ir metodologija: https://www.atspara.mif.vu.lt/*