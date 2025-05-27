<?php

namespace App\Services;

/**
 * Prompt generavimo servisas.
 * 
 * Sukuria RISEN metodologijos pagrindu struktūrizuotus prompt'us.
 */
class PromptService
{
    /**
     * Generuoti pagrindinį prompt'ą propagandos technikų analizei.
     */
    public function generateAnalysisPrompt(string $text, ?string $customPrompt = null): string
    {
        if ($customPrompt) {
            return $customPrompt . "\n\nAnalizuojamas tekstas:\n{$text}";
        }

        $techniques = config('llm.propaganda_techniques');
        $narratives = config('llm.disinformation_narratives');

        $techniquesList = collect($techniques)->map(function ($description, $key) {
            return "   - {$key} ({$description})";
        })->implode("\n");

        $narrativesList = collect($narratives)->map(function ($description, $key) {
            return "   - {$key} ({$description})";
        })->implode("\n");

        return "Role: Tu esi propagandos ir dezinformacijos analizės ekspertas, specializuojantis politinių tekstų vertinime.

Instructions: Išanalizuok pateiktą tekstą ir identifikuok propagandos technikas bei dezinformacijos naratyvus. Kiekvienai identifikuotai technikai nurodyk tikslią teksto vietą (pradžios ir pabaigos pozicijas simboliais) ir pateik teksto ištrauką.

Steps:
1. Perskaityk visą tekstą ir susidaryti bendrą įspūdį
2. Identifikuok propagandos technikas iš šio sąrašo:
{$techniquesList}
3. Kiekvienai technikai rask konkrečias teksto vietas
4. Identifikuok pagrindinius dezinformacijos naratyvus:
{$narrativesList}

End goal: Grąžink JSON formatą su anotacijomis, atitinkančiu pateiktą struktūrą.

Narrowness: Analizuok tik aiškiai identifikuojamas propagandos technikas. Jei abejoji, geriau praleisk. Kiekviena anotacija turi turėti tikslią teksto poziciją.

Grąžink rezultatus šiuo JSON formatu:
{
  \"primaryChoice\": {
    \"choices\": [\"yes\"] // jei rastos propagandos technikos, [\"no\"] jei ne
  },
  \"annotations\": [
    {
      \"type\": \"labels\",
      \"value\": {
        \"start\": [pradžios pozicija simboliais],
        \"end\": [pabaigos pozicija simboliais],
        \"text\": \"[tikslus tekstas iš dokumento]\",
        \"labels\": [\"technika1\", \"technika2\"] // iš apibrėžto sąrašo
      }
    }
  ],
  \"desinformationTechnique\": {
    \"choices\": [\"naratyvas1\", \"naratyvas2\"] // iš apibrėžto sąrašo
  }
}

Analizuojamas tekstas:
{$text}";
    }

    /**
     * Gauti sistemos žinutę su instrukcijomis.
     */
    public function getSystemMessage(): string
    {
        return "Tu esi tiksli propagandos analizės sistema. Visada grąžink tik JSON formatą be papildomų komentarų ar paaiškinimų. Būk tikslus teksto pozicijų nustatyme ir konservatyvus anotacijų kiekio atžvilgiu.";
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
            }
        }

        return true;
    }
}