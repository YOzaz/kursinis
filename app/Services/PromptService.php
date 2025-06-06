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
     * Gauti standartinį RISEN ATSPARA prompt'ą.
     */
    public function getStandardRisenPrompt(): array
    {
        return [
            'role' => 'Tu esi ATSPARA projekto propagandos analizės ekspertas, specializuojantis lietuvių kalbos tekstų analizėje.',
            'instructions' => 'Analizuok pateiktą tekstą ir identifikuok visas propagandos technikas bei dezinformacijos naratyvus pagal ATSPARA projekto metodologiją. Būk preciziškas ir objektyvus.',
            'situation' => 'Analizuojamas tekstas yra iš lietuviškų žiniasklaidos šaltinių, socialinių tinklų ar viešųjų pranešimų. Tavo užduotis - identifikuoti propaganda technikas ir įvertinti tekstą.',
            'execution' => 'Atlikdamas analizę: 1) Perskaityk tekstą atidžiai ir įvertink jo ilgį 2) Identifikuok aiškiai matomus propaganda elementus 3) Nurodyk tikslias teksto dalis (15-60% teksto, ne mažiau 50 simbolių) 4) SVARBU: Naudok UNICODE SIMBOLIŲ pozicijas (ne baitų), skaičiuok lietuviškus simbolius ą,č,ę,ė,į,š,ų,ū,ž kaip po 1 simbolį 5) Patikrink pozicijų tikslumą (start/end) 6) Klasifikuok pagal ATSPARA kategoriją 7) Pateik rezultatus JSON formatu',
            'needle' => 'Gražink TIKSLIAI šio formato JSON atsakymą be jokių papildomų komentarų ar paaiškinimų:'
        ];
    }

    /**
     * Generuoti RISEN prompt'ą su custom dalimis.
     */
    public function generateCustomRisenPrompt(array $customParts): string
    {
        $standard = $this->getStandardRisenPrompt();
        $parts = array_merge($standard, $customParts);
        
        return $this->buildRisenPrompt($parts);
    }

    /**
     * Sukonstruoti pilną RISEN prompt'ą.
     */
    private function buildRisenPrompt(array $parts): string
    {
        $techniques = config('llm.propaganda_techniques');
        
        $prompt = "**ROLE**: {$parts['role']}\n\n";
        $prompt .= "**INSTRUCTIONS**: {$parts['instructions']}\n\n";
        $prompt .= "**SITUATION**: {$parts['situation']}\n\n";
        $prompt .= "**EXECUTION**: {$parts['execution']}\n\n";
        
        // Pridėti technikas
        $prompt .= "**PROPAGANDOS TECHNIKOS (ATSPARA metodologija)**:\n";
        foreach ($techniques as $key => $description) {
            $prompt .= "- {$key}: {$description}\n";
        }
        
        $prompt .= "\n**ANALIZĖS METODOLOGIJA**:\n";
        $prompt .= "- Identifikuok aiškiai matomus propaganda elementus\n";
        $prompt .= "- Anotacijos turi sudaryti 15-60% teksto (ne mažiau 50 simbolių)\n";
        $prompt .= "- Vengti per smulkių (<10 simbolių) fragmentų\n";
        $prompt .= "- Prioritetas - Zarankos ir ATSPARA metodologijos tikslumui\n";
        
        $prompt .= "\n**TEKSTO IŠGAVIMO TAISYKLĖS (KRITIŠKAI SVARBU)**:\n";
        $prompt .= "Kai išgauni teksto fragmentą, VISADA:\n";
        $prompt .= "- Skaičiuok \"simbolius\" kaip Unicode kodo taškus (kaip Python len() funkcija)\n";
        $prompt .= "- Start pozicija įskaitoma, end pozicija neįskaitoma (Python stiliaus text[start:end])\n";
        $prompt .= "- Grąžink: fragmentą, start ir end indeksus, simbolių skaičių (naudodamas savo skaičiavimo metodą)\n";
        $prompt .= "- PAVYZDYS: tekstui=\"AąBčD\", text[0:3] turėtų būti \"AąB\" (3 simboliai)\n";
        $prompt .= "- Patikrink rezultatą išvedant ilgį naudodamas savo skaičiavimo metodą\n";
        $prompt .= "- Lietuviški simboliai ą,č,ę,ė,į,š,ų,ū,ž = po 1 Unicode simbolį, ne 2-3 baitus\n";
        $prompt .= "- NIEKADA nenaudok baitų pozicijų - tik Unicode simbolių pozicijas!\n";
        
        $prompt .= "\n**NEEDLE**: {$parts['needle']}\n\n";
        $prompt .= $this->getJsonFormat();
        
        return $prompt;
    }

    /**
     * Gauti JSON formato specifikaciją.
     */
    private function getJsonFormat(): string
    {
        return '```json
{
  "primaryChoice": {
    "choices": ["yes"] // arba ["no"] - ar propaganda dominuoja (>40% teksto)
  },
  "annotations": [
    {
      "type": "labels",
      "value": {
        "start": 0,
        "end": 50,
        "text": "tikslus tekstas iš dokumento be pakeitimų (UNICODE simbolių pozicijos!)",
        "labels": ["konkretūs_technikų_pavadinimai"]
      }
    }
  ],
  "desinformationTechnique": {
    "choices": ["naratyvo_pavadinimas"] // arba []
  }
}
```

**ANALIZĖS KOKYBĖS REIKALAVIMAI**:
1. Būk konservatyvus - žymi tik aiškiai identifikuojamas propaganda technikas
2. Anotacijos turi sudaryti 15-60% teksto, ne mažiau nei 50 simbolių
3. Vengti mikroskopinių fragmentų (<10 simbolių) ar viso teksto žymėjimo
4. Prioritetas - Zarankos ir ATSPARA metodologijos tikslumui

**UNICODE SIMBOLIŲ POZICIJŲ VALIDACIJA**:
Prieš grąžindamas JSON atsakymą, PRIVALAI patikrinti:
- Ar tavo skaičiuojamas teksto ilgis sutampa su Unicode simbolių skaičiumi
- Ar start/end pozicijos tiksliai atitinka pateiktą text fragmentą
- Ar lietuviški simboliai (ą,č,ę,ė,į,š,ų,ū,ž) skaičiuojami kaip po 1 simbolį
- PAVYZDYS tikrinimo: jei text="Aąžė", tai end-start turėtų būti 4, ne 7

**SVARBU**: Analizuojamas tekstas lietuvių kalba. NAUDOK UNICODE SIMBOLIŲ pozicijas! Atsakyk TIK JSON formatu.';
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

        $standard = $this->getStandardRisenPrompt();
        $prompt = $this->buildRisenPrompt($standard);
        
        // Add text statistics for better context
        $textLength = mb_strlen($text);
        $minAnnotationLength = max(50, intval($textLength * 0.15)); // At least 15% or 50 chars
        $maxAnnotationLength = intval($textLength * 0.60); // Max 60%
        
        $prompt .= "\n\n**TEKSTO STATISTIKA**:\n";
        $prompt .= "- Teksto ilgis: {$textLength} simbolių\n";
        $prompt .= "- Rekomenduojamas anotacijų kiekis: {$minAnnotationLength}-{$maxAnnotationLength} simbolių\n";
        $prompt .= "- Minimalus fragmento ilgis: 10 simbolių\n";
        
        return $prompt . "\n\n---\n\n**Analizuojamas tekstas:**\n{$text}";
    }

    /**
     * Gauti sistemos žinutę su instrukcijomis.
     */
    public function getSystemMessage(): string
    {
        return "Tu esi ATSPARA propagandos analizės sistema, specializuojanti lietuvių kalbos tekstų analizėje. Analizuoji tekstus pagal griežtus objektyvius kriterijus, orientuodamasis į Zarankos ir ATSPARA metodologijos standartus. Visada grąžink tik galiojantį JSON formatą be papildomų komentarų. \n\nKOKYBĖS REIKALAVIMAI:\n- Būk konservatyvus anotacijų kiekio atžvilgiu\n- Žymi fragmentus sudorančius 15-60% teksto\n- Tiksliai nurodyti pozicijas (start/end)\n- Vengti <10 simbolių fragmentų\n- Prioritetas - akademinio lygio tikslumui";
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

    /**
     * Gauti analizės prompt šabloną be teksto (batch analizei).
     */
    public function getAnalysisPromptTemplate(): string
    {
        $standard = $this->getStandardRisenPrompt();
        return $this->buildRisenPrompt($standard);
    }
}