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
     * Patikrinti ar servisas yra konfigūruotas.
     */
    public function isConfigured(): bool;
}