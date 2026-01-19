<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected $apiKey;
    // URL sincronizada con tu prueba exitosa
    protected $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    public function analyzeList($userInput, $history = [])
    {
        if (!$this->apiKey) return null;

        $prompt = "Eres un experto en Super Carnes. Convierte la lista en JSON.\n";
        $prompt .= "Entrada: '{$userInput}'\n";
        $prompt .= "Formato: [{'term': 'producto', 'normalized': 'nombre', 'sku': 'código', 'qty': 1, 'unit': 'lb'}]";

        try {
            $response = Http::post("{$this->baseUrl}?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json' // Esto garantiza que no traiga texto basura
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json('candidates.0.content.parts.0.text');
                return json_decode($content, true);
            }
        } catch (\Exception $e) {
            Log::error("Gemini Error: " . $e->getMessage());
        }

        return null;
    }
}