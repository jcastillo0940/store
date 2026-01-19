<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MagentoCheckPaymentConfig extends Command
{
    protected $signature = 'magento:check-payment {method?}';
    protected $description = 'Verificar configuración de métodos de pago';

    public function handle()
    {
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');
        $method = $this->argument('method');

        $this->info("💳 Verificando configuración de pagos\n");

        // Obtener configuración del sistema
        $response = Http::withToken($token)
            ->get("{$baseUrl}/rest/V1/store/storeConfigs");

        if ($response->failed()) {
            $this->error("❌ Error: " . $response->status());
            return 1;
        }

        $configs = $response->json();
        $storeConfig = $configs[0] ?? [];

        // Métodos de pago a verificar
        $paymentMethods = [
            'cashondelivery' => 'Cash on Delivery',
            'checkmo' => 'Check / Money Order',
            'banktransfer' => 'Bank Transfer Payment',
            'yappy' => 'Yappy',
            'free' => 'No Payment Required',
            'purchaseorder' => 'Purchase Order'
        ];

        if ($method) {
            $this->checkSpecificMethod($baseUrl, $token, $method);
        } else {
            $this->checkAllMethods($paymentMethods);
        }

        // Verificar configuración adicional
        $this->line("\n⚙️  CONFIGURACIÓN ADICIONAL");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        $this->info("Store Code: " . ($storeConfig['code'] ?? 'N/A'));
        $this->info("Currency: " . ($storeConfig['base_currency_code'] ?? 'N/A'));
        $this->info("Locale: " . ($storeConfig['locale'] ?? 'N/A'));

        return 0;
    }

    private function checkAllMethods(array $methods): void
    {
        $this->line("📋 MÉTODOS DE PAGO DISPONIBLES");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $table = [];
        foreach ($methods as $code => $title) {
            $table[] = [$code, $title, $this->isMethodActive($code)];
        }

        $this->table(['Code', 'Title', 'Status'], $table);

        $this->line("\n💡 Para verificar un método específico:");
        $this->comment("php artisan magento:check-payment cashondelivery");
    }

    private function checkSpecificMethod(string $baseUrl, string $token, string $method): void
    {
        $this->line("🔍 VERIFICANDO: {$method}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Intentar obtener configuración específica
        $endpoints = [
            "/rest/V1/store/storeConfigs",
            "/rest/default/V1/store/storeViews"
        ];

        foreach ($endpoints as $endpoint) {
            $response = Http::withToken($token)->get("{$baseUrl}{$endpoint}");
            
            if ($response->successful()) {
                $data = $response->json();
                $this->line("✅ Endpoint respondió: {$endpoint}");
            }
        }

        // Campos típicos de configuración
        $configFields = [
            'active' => 'Activo',
            'title' => 'Título',
            'order_status' => 'Estado de orden',
            'payment_action' => 'Acción de pago',
            'can_use_checkout' => 'Disponible en checkout',
            'can_authorize' => 'Puede autorizar',
            'can_capture' => 'Puede capturar',
            'sort_order' => 'Orden'
        ];

        $this->table(['Campo', 'Descripción'], 
            array_map(fn($k, $v) => [$k, $v], array_keys($configFields), $configFields)
        );

        $this->line("\n💡 Estos valores se configuran en:");
        $this->comment("app/code/Vendor/Module/etc/config.xml");
        $this->comment("o desde Admin: Stores → Configuration → Sales → Payment Methods");
    }

    private function isMethodActive(string $code): string
    {
        // Métodos que típicamente están activos
        $commonActive = ['cashondelivery', 'checkmo', 'free'];
        
        return in_array($code, $commonActive) ? '✅ Común' : '⚠️  Verificar';
    }
}