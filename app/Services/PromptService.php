<?php

namespace App\Services;

/**
 * Prompt generavimo servisas.
 * 
 * Sukuria RISEN metodologijos pagrindu struktūrizuotus prompt'us
 * remiantis ATSPARA projekto anotavimo instrukcijomis.
 * 
 * Kursinio darbo autorius: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
 * Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
 * Duomenų šaltiniai ir metodologija: ATSPARA projektas (https://www.atspara.mif.vu.lt/)
 */
class PromptService
{
    /**
     * Generuoti Claude prompt'ą.
     */
    public function generateClaudePrompt(string $text): string
    {
        return $this->generateDetailedAnalysisPrompt($text, 'Claude');
    }

    /**
     * Generuoti Gemini prompt'ą.
     */
    public function generateGeminiPrompt(string $text): string
    {
        return $this->generateDetailedAnalysisPrompt($text, 'Gemini');
    }

    /**
     * Generuoti OpenAI prompt'ą.
     */
    public function generateOpenAIPrompt(string $text): string
    {
        return $this->generateDetailedAnalysisPrompt($text, 'ChatGPT');
    }

    /**
     * Generuoti detalų analizės prompt'ą remiantis ATSPARA instrukcijomis.
     */
    private function generateDetailedAnalysisPrompt(string $text, string $modelName): string
    {
        $techniques = config('llm.propaganda_techniques');
        $narratives = config('llm.disinformation_narratives');

        $techniquesList = collect($techniques)->map(function ($description, $key) {
            return "   - {$key}: {$description}";
        })->implode("\n");

        $narrativesList = collect($narratives)->map(function ($description, $key) {
            return "   - {$key}: {$description}";
        })->implode("\n");

        return "**ATSPARA Propagandos analizės sistema**

**Role**: Tu esi tikslus propagandos ir dezinformacijos analizės ekspertas, specializuojantis lietuvių kalbos tekstų vertinime pagal ATSPARA projekto metodologiją.

**Instructions**: Išanalizuok pateiktą lietuvių kalbos tekstą ir objektyviai identifikuok propagandos technikas nepriklausomai nuo politinės stovyklos ar šaltinio. Vadovaukis ATSPARA projekto anotavimo principais:

1. **Objektyvumas**: Žymi tik aiškiai identifikuojamas technikas be šališkumo
2. **Tikslumas**: Kiekviena anotacija turi turėti tikslią teksto poziciją simboliais
3. **Konservatyvumas**: Jei abejoji, geriau praleisk - būk griežtas kriterijų atžvilgiu
4. **Proporcingumas**: Vertink ar propaganda sudaro >40% teksto (jei taip, tada primaryChoice = \"yes\")

**Steps**:
1. **Susipažinimas**: Atidžiai perskaityk visą tekstą lietuvių kalba
2. **Technikų identifikavimas**: Ieškokių šių ATSPARA apibrėžtų propagandos technikų:

{$techniquesList}

3. **Pozicijų nustatymas**: Kiekvienai technikai rask tikslią poziciją simboliais nuo teksto pradžios
4. **Ištraukų pateikimas**: Kopijuok tikslų tekstą be pakeitimų
5. **Dezinformacijos naratyvų identifikavimas**:

{$narrativesList}

6. **Bendro sprendimo formavimas**: Ar propaganda sudaro didesnę teksto dalį (>40%)?

**End goal**: Grąžink tik JSON formatą su tiksliais rezultatais pagal ATSPARA struktūrą.

**Narrowness**: 
- Analizuok TIK aiškiai matomą, nepriešginį propagandą
- Žymi tik tuos fragmentus, kur 100% tikras propagandos technikos buvimu
- Neinterpretuko dviprasmiškai - jei neaišku, praleisk
- Tiksliai nurodyk pradžios ir pabaigos pozicijas
- Kopijuok teksto fragmentą TIKSLIAI kaip parašyta originale

**Reikalaujamas JSON formatas (grąžink TIK JSON, nieko daugiau):**
```json
{
  \"primaryChoice\": {
    \"choices\": [\"yes\"] // arba [\"no\"] - ar propaganda dominuoja (>40% teksto)
  },
  \"annotations\": [
    {
      \"type\": \"labels\",
      \"value\": {
        \"start\": 0,
        \"end\": 50,
        \"text\": \"tikslus tekstas iš dokumento be pakeitimų\",
        \"labels\": [\"konkretūs_technikų_pavadinimai\"]
      }
    }
  ],
  \"desinformationTechnique\": {
    \"choices\": [\"naratyvo_pavadinimas\"] // arba []
  }
}
```

**SVARBU**: Analizuojamas tekstas lietuvių kalba. Atsakyk TIK JSON formatu.

---

**Analizuojamas tekstas:**
{$text}";
    }

    /**
     * Gauti sistemos žinutę su instrukcijomis.
     */
    public function getSystemMessage(): string
    {
        return "Tu esi ATSPARA propagandos analizės sistema. Analizuoji lietuvių kalbos tekstus pagal griežtus objektyvius kriterijus. Visada grąžink tik galiojantį JSON formatą be papildomų komentarų. Būk maksimaliai tikslus pozicijų nustatyme ir konservatyvus anotacijų kiekio atžvilgiu - žymi tik aiškiai identifikuojamas propagandos technikas.";
    }

    /**
     * Validuoti ar atsakymas atitinka reikalavimus.
     */
    public function validateResponse(array $response): bool
    {
        $requiredKeys = ['primaryChoice', 'annotations', 'desinformationTechnique'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($response[$key])) {
                return false;
            }
        }

        // Patikrinti primaryChoice struktūrą
        if (!isset($response['primaryChoice']['choices']) || 
            !is_array($response['primaryChoice']['choices'])) {
            return false;
        }

        // Patikrinti annotations struktūrą
        if (isset($response['annotations']) && is_array($response['annotations'])) {
            foreach ($response['annotations'] as $annotation) {
                if (!isset($annotation['type'], $annotation['value'])) {
                    return false;
                }
                
                $value = $annotation['value'];
                if (!isset($value['start'], $value['end'], $value['text'], $value['labels'])) {
                    return false;
                }

                // Patikrinti ar pozicijos yra skaičiai
                if (!is_numeric($value['start']) || !is_numeric($value['end'])) {
                    return false;
                }

                // Patikrinti ar labels yra masyvas
                if (!is_array($value['labels'])) {
                    return false;
                }
            }
        }

        // Patikrinti desinformationTechnique struktūrą
        if (!isset($response['desinformationTechnique']['choices']) || 
            !is_array($response['desinformationTechnique']['choices'])) {
            return false;
        }

        return true;
    }

    /**
     * Gauti pagrindinį analizės prompt'ą (atgalinis suderinamumas).
     */
    public function generateAnalysisPrompt(string $text, ?string $customPrompt = null): string
    {
        if ($customPrompt) {
            return $customPrompt . "\n\nAnalizuojamas tekstas:\n{$text}";
        }

        return $this->generateDetailedAnalysisPrompt($text, 'General');
    }
}