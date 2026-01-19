<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MagentoDebug extends Command
{
    protected $signature = 'magento:debug';
    protected $description = 'Diagnóstico comparativo con lógica de App anterior';

    public function handle()
{
    $apiUrl = config('services.magento.base_url');
    $apiToken = config('services.magento.token');

    $this->info("🔍 Iniciando diagnóstico...");
    $this->line("URL Base: {$apiUrl}");

    // Construye la URL correctamente
    $urlBase = rtrim($apiUrl, '/');
    $urlFinal = $urlBase . '/rest/V1/orders';

    $params = [
        'searchCriteria[filterGroups][0][filters][0][field]' => 'created_at',
        'searchCriteria[filterGroups][0][filters][0][value]' => now()->subDays(30)->toIso8601String(),
        'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'gt',
        'searchCriteria[pageSize]' => 1,
    ];

    try {
        $response = Http::withToken($apiToken)->get($urlFinal, $params);
        
        $this->line("--------------------------------------------------");
        $this->line("URL ejecutada: " . $urlFinal);
        $this->line("STATUS: " . $response->status());

        if ($response->successful()) {
            $this->info("✅ ¡ÉXITO!");
            $data = $response->json();
            $this->line("Pedidos encontrados: " . ($data['total_count'] ?? 0));
        } else {
            $this->error("❌ FALLÓ");
            $this->line("Respuesta: " . $response->body());
            
            if ($response->status() == 401) {
                $this->warn("⚠️  Token sin permisos para Magento_Sales::actions_view");
                $this->line("Solución: En Magento Admin → System → Integrations");
                $this->line("Edita tu integración y marca 'Sales' en Resource Access");
            }
        }
    } catch (\Exception $e) {
        $this->error("Excepción: " . $e->getMessage());
    }
}
}