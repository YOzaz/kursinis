<?php

namespace App\Services;

/**
 * LLM serviso sąsaja.
 * 
 * Apibrėžia bendrus metodus visiems LLM servisant.
 */
interface LLMServiceInterface
{
    /**
     * Analizuoti tekstą su propagandos technikų atpažinimu.
     */
    public function analyzeText(string $text, ?string $customPrompt = null): array;

    /**
     * Gauti modelio pavadinimą.
     */
    public function getModelName(): string;

    /**
     * Gauti tikrą modelio pavadinimą (pvz., claude-sonnet-4-20250514).
     */
    public function getActualModelName(): string;

    /**
     * Patikrinti ar servisas yra konfigūruotas.
     */
    public function isConfigured(): bool;
}