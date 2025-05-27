<?php

namespace App\Services;

/**
 * RISEN metodologijos promptų kūrimo servisas
 * 
 * Šis servisas kuria propagandos ir dezinformacijos analizės promptus
 * lietuviško teksto analizei.
 *
 * Kursinio darbo autorius: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
 * Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
 *
 * Duomenų šaltiniai ir metodologija:
 * - ATSPARA korpuso duomenys ir anotavimo metodologija: https://www.atspara.mif.vu.lt/
 */
class PromptBuilderService
{
    public function buildRisenPrompt(array $config): string
    {
        $role = $config['role'] ?? 'Tu esi propagandos ir dezinformacijos analizės ekspertas.';
        $instructions = $config['instructions'] ?? $this->getDefaultInstructions();
        $situation = $config['situation'] ?? $this->getDefaultSituation();
        $execution = $config['execution'] ?? $this->getDefaultExecution();
        $needle = $config['needle'] ?? $this->getDefaultNeedle();

        return "{$role}\n\n{$instructions}\n\n{$situation}\n\n{$execution}\n\n{$needle}";
    }

    public function getDefaultRisenConfig(): array
    {
        return [
            'role' => 'Tu esi propagandos ir dezinformacijos analizės ekspertas.',
            'instructions' => $this->getDefaultInstructions(),
            'situation' => $this->getDefaultSituation(),
            'execution' => $this->getDefaultExecution(),
            'needle' => $this->getDefaultNeedle(),
        ];
    }

    private function getDefaultInstructions(): string
    {
        return "Išanalizuok pateiktą tekstą ir identifikuok propagandos technikas pagal šiuos kriterijus:
1. Emocinis poveikis - ar tekstas siekia sukelti stiprų emocinį atsaką
2. Informacijos iškraipymas - ar pateikiama klaidinanti informacija  
3. Polarizacija - ar skatinama \"mes prieš juos\" mentalitetas
4. Autoriteto manipuliavimas - ar naudojami melagingų autoritetų argumentai
5. Logikos pažeidimai - ar pateikiami klaidingi argumentai";
    }

    private function getDefaultSituation(): string
    {
        return "Analizuojamas tekstas gali būti:
- Socialinio tinklo įrašas
- Naujienų straipsnis  
- Politinis pasisakymas
- Reklamos turinys
- Komentaras ar diskusijos dalis

Tekstas bus pateiktas lietuvių kalba arba verčiamas į lietuvių kalbą.";
    }

    private function getDefaultExecution(): string
    {
        return "Atlikdamas analizę:
1. Perskaityk tekstą atidžiai
2. Identifikuok kiekvieną propagandos techniką
3. Paaiškink kodėl manai, kad tai yra propaganda
4. Įvertink propagandos intensyvumą (silpnas/vidutinis/stiprus)
5. Pateik rezultatus JSON formatu";
    }

    private function getDefaultNeedle(): string
    {
        return "SVARBU: Atsakymą pateik TIKTAI JSON formatu be jokių papildomų komentarų:
{
  \"propaganda_detected\": true/false,
  \"techniques\": [\"technika1\", \"technika2\"],
  \"intensity\": \"silpnas/vidutinis/stiprus\",
  \"explanation\": \"trumpas paaiškinimas\"
}";
    }
}