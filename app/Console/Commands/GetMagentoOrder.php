<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetMagentoOrder extends Command
{
    protected $signature = 'magento:get-order {id?}';
    protected $description = 'Extrae el JSON de una orden de Magento';

    public function handle()
    {
        $orderId = $this->argument('id');
        
        // URL base limpia + /rest/V1
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');

        $this->info("🔍 Obteniendo orden de Magento");
        $this->line("URL Base: {$baseUrl}");

        if (!$orderId) {
            // Obtener última orden
            $this->comment("📡 Solicitando última orden...");
            $url = "{$baseUrl}/rest/V1/orders";
            
            $params = [
                'searchCriteria[sortOrders][0][field]' => 'entity_id',
                'searchCriteria[sortOrders][0][direction]' => 'DESC',
                'searchCriteria[pageSize]' => 1
            ];

            $response = Http::withToken($token)->get($url, $params);
            
            $this->line("URL ejecutada: {$url}");
            $this->line("STATUS: " . $response->status());

            if ($response->failed()) {
                $this->error("❌ ERROR: " . $response->body());
                return 1;
            }

            $data = $response->json();

            if (empty($data['items'])) {
                $this->warn("⚠️ No hay órdenes en el sistema.");
                return 0;
            }

            $order = $data['items'][0];
            
            $this->info("✅ Última orden encontrada:");
            $this->line("ID: " . ($order['entity_id'] ?? 'N/A'));
            $this->line("Estado: " . ($order['status'] ?? 'N/A'));
            $this->line("Total: $" . number_format($order['grand_total'] ?? 0, 2));
            $this->line("Cliente: " . ($order['customer_firstname'] ?? '') . ' ' . ($order['customer_lastname'] ?? ''));
            $this->line("Fecha: " . ($order['created_at'] ?? 'N/A'));
            
            $this->newLine();
            $this->line("═══════════════════════════════════════");
            $this->line("JSON COMPLETO:");
            $this->line("═══════════════════════════════════════");
            echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
        
} else {
    // Obtener orden específica por increment_id
    $this->comment("📡 Solicitando orden #{$orderId}");
    $url = "{$baseUrl}/rest/V1/orders";
    
    $params = [
        'searchCriteria[filterGroups][0][filters][0][field]' => 'increment_id',
        'searchCriteria[filterGroups][0][filters][0][value]' => $orderId,
        'searchCriteria[filterGroups][0][filters][0][conditionType]' => 'eq'
    ];
    
    $response = Http::withToken($token)->get($url, $params);
    
    $this->line("URL ejecutada: {$url}");
    $this->line("STATUS: " . $response->status());

    if ($response->failed()) {
        $this->error("❌ ERROR: " . $response->body());
        return 1;
    }

    $data = $response->json();
    $order = $data['items'][0] ?? null;
    
    if (!$order) {
        $this->warn("⚠️ Orden #{$orderId} no encontrada.");
        return 0;
    }
    
    $this->info("✅ Orden encontrada:");
    $this->line("ID: " . ($order['entity_id'] ?? 'N/A'));
    $this->line("Increment ID: " . ($order['increment_id'] ?? 'N/A'));
    $this->line("Estado: " . ($order['status'] ?? 'N/A'));
    $this->line("Total: $" . number_format($order['grand_total'] ?? 0, 2));
    
    $this->newLine();
    $this->line("═══════════════════════════════════════");
    $this->line("JSON COMPLETO:");
    $this->line("═══════════════════════════════════════");
    echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
        return 0;
    }
}