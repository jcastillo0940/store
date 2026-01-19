<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;

class TestGeminiLearning extends Command
{
    protected $signature = 'ai:test {whatsapp} {text}';
    protected $description = 'Debug de la conexión con Gemini 2.0';

    public function handle(GeminiService $geminiService)
    {
        $whatsapp = $this->argument('whatsapp');
        $text = $this->argument('text');

        $this->info("🔍 Iniciando Debug para: $whatsapp");
        
        $key = config('services.gemini.key');
        if (empty($key)) {
            $this->error("❌ ERROR: La API Key está vacía en config/services.php");
            return;
        }
        $this->line("✅ API Key cargada.");

        $this->comment("🛰️  Enviando petición directa a Google (Modelo 2.0)...");
        
        // URL EXACTA DE TU CURL EXITOSO
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $key;
        
        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [['parts' => [['text' => "Genera un JSON con el producto: $text. Formato: [{'term': 'papa', 'sku': '123'}]"]]]]
            ]);

        if ($response->failed()) {
            $this->error("❌ ERROR DE API (Status: " . $response->status() . ")");
            $this->line("Respuesta Cruda: " . $response->body());
            return;
        }

        $this->info("✅ Respuesta recibida con éxito.");
        $this->line("Contenido: " . $response->json('candidates.0.content.parts.0.text'));

        $this->comment("🧠 Probando el Servicio Completo...");
        $result = $geminiService->analyzeList($text, []);

        if ($result) {
            $this->info("🎉 ¡ÉXITO TOTAL! El sistema procesó el JSON:");
            print_r($result);
        } else {
            $this->error("❌ El servicio falló al decodificar. Revisa storage/logs/laravel.log");
        }
    }
}