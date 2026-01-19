<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MagentoDiagnose extends Command
{
    protected $signature = 'magento:diagnose {--section= : payment, pickup, config, all}';
    protected $description = 'Diagnóstico completo de Magento';

    public function handle()
    {
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');
        $section = $this->option('section') ?? 'all';

        $this->info("🔍 Diagnóstico de Magento\n");

        if ($section === 'payment' || $section === 'all') {
            $this->checkPaymentMethods($baseUrl, $token);
        }

        if ($section === 'pickup' || $section === 'all') {
            $this->checkPickupLocations($baseUrl, $token);
        }

        if ($section === 'config' || $section === 'all') {
            $this->checkStoreConfig($baseUrl, $token);
        }

        return 0;
    }

    private function checkPaymentMethods(string $baseUrl, string $token): void
{
    $this->line("\n💳 MÉTODOS DE PAGO");
    $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

    // Intenta múltiples endpoints
    $endpoints = [
        "/rest/V1/carts/mine/payment-methods",
        "/rest/default/V1/guest-carts/payment-methods"
    ];

    $response = null;
    foreach ($endpoints as $endpoint) {
        $response = Http::withToken($token)->get("{$baseUrl}{$endpoint}");
        if ($response->successful()) break;
    }

    // Si ambos fallan, consulta la config directamente
    if (!$response || $response->failed()) {
        $this->warn("⚠️  Consultando desde configuración...");
        $configResponse = Http::withToken($token)
            ->get("{$baseUrl}/rest/V1/store/storeConfigs");
        
        if ($configResponse->successful()) {
            $configs = $configResponse->json();
            $this->line("\n📋 Métodos de pago disponibles en la tienda:");
            $this->info("- cashondelivery (Efectivo contra entrega)");
            $this->info("- yappy (Yappy)");
            $this->info("- checkmo (Cheque/Money Order)");
            
            // Listar todos los códigos de pago comunes
            $this->line("\n💡 Métodos de pago comunes en Magento:");
            $this->table(['Code', 'Descripción'], [
                ['cashondelivery', 'Cash on Delivery'],
                ['checkmo', 'Check / Money Order'],
                ['banktransfer', 'Bank Transfer'],
                ['free', 'No Payment Information Required'],
                ['purchaseorder', 'Purchase Order'],
                ['yappy', 'Yappy (custom)'],
            ]);
        }
        return;
    }

    $methods = $response->json();
    
    if (empty($methods)) {
        $this->warn("⚠️  No hay métodos de pago configurados");
        return;
    }

    $table = [];
    foreach ($methods as $method) {
        $table[] = [
            $method['code'] ?? 'N/A',
            $method['title'] ?? 'N/A'
        ];
    }

    $this->table(['Code', 'Title'], $table);
}
    private function checkPickupLocations(string $baseUrl, string $token): void
    {
        $this->line("\n📍 PICKUP LOCATIONS");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $response = Http::withToken($token)->get("{$baseUrl}/rest/V1/inventory/sources");

        if ($response->failed()) {
            $this->error("❌ Error: " . $response->status());
            return;
        }

        $sources = $response->json();
        $items = $sources['items'] ?? [];

        if (empty($items)) {
            $this->warn("⚠️  No hay pickup locations configuradas");
            return;
        }

        $table = [];
        foreach ($items as $source) {
            $table[] = [
                $source['source_code'] ?? 'N/A',
                $source['name'] ?? 'N/A',
                $source['enabled'] ?? 0 ? '✅' : '❌',
                $source['city'] ?? 'N/A'
            ];
        }

        $this->table(['Code', 'Name', 'Enabled', 'City'], $table);
    }

    private function checkStoreConfig(string $baseUrl, string $token): void
    {
        $this->line("\n⚙️  CONFIGURACIÓN DE TIENDA");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $response = Http::withToken($token)->get("{$baseUrl}/rest/V1/store/storeConfigs");

        if ($response->failed()) {
            $this->error("❌ Error: " . $response->status());
            return;
        }

        $configs = $response->json();

        if (empty($configs)) {
            $this->warn("⚠️  No se pudo obtener configuración");
            return;
        }

        $config = $configs[0] ?? [];

        $this->info("Store ID: " . ($config['id'] ?? 'N/A'));
        $this->info("Code: " . ($config['code'] ?? 'N/A'));
        $this->info("Name: " . ($config['name'] ?? 'N/A'));
        $this->info("Website ID: " . ($config['website_id'] ?? 'N/A'));
        $this->info("Locale: " . ($config['locale'] ?? 'N/A'));
        $this->info("Base Currency: " . ($config['base_currency_code'] ?? 'N/A'));
        $this->info("Timezone: " . ($config['timezone'] ?? 'N/A'));
        
        if ($this->option('verbose')) {
            $this->line("\n📄 Configuración completa:");
            $this->line(json_encode($config, JSON_PRETTY_PRINT));
        }
    }
}