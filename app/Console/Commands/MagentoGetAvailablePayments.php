<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MagentoGetAvailablePayments extends Command
{
    protected $signature = 'magento:available-payments {--json : Output en JSON}';
    protected $description = 'Ver métodos de pago disponibles (REST API)';

    public function handle()
    {
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');

        $this->info("🔍 Consultando métodos de pago vía REST API...\n");

        // Crear un quote/cart temporal vía REST
        $quoteResponse = Http::withToken($token)
            ->post("{$baseUrl}/rest/V1/carts/mine");

        if ($quoteResponse->failed()) {
            // Intentar con guest cart
            $quoteResponse = Http::post("{$baseUrl}/rest/V1/guest-carts");
        }

        if ($quoteResponse->failed()) {
            $this->error("❌ Error creando carrito: " . $quoteResponse->status());
            return 1;
        }

        $cartId = $quoteResponse->json();
        $this->line("✅ Cart ID: {$cartId}\n");

        // Obtener métodos de pago disponibles
        $paymentsUrl = "{$baseUrl}/rest/V1/guest-carts/{$cartId}/payment-methods";
        $response = Http::get($paymentsUrl);

        if ($response->failed()) {
            $this->error("❌ Error: " . $response->status());
            $this->line($response->body());
            return 1;
        }

        $methods = $response->json();

        if (empty($methods)) {
            $this->error("❌ NO HAY MÉTODOS DE PAGO HABILITADOS");
            return 1;
        }

        // Output JSON completo
        if ($this->option('json')) {
            echo json_encode($methods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return 0;
        }

        $this->info("✅ Métodos de pago disponibles:\n");
        $this->line("═══════════════════════════════════════════════════════════");
        
        foreach ($methods as $method) {
            $this->info("📋 " . ($method['title'] ?? 'N/A'));
            $this->line("   Code: " . ($method['code'] ?? 'N/A'));
            
            if (isset($method['is_deferred'])) {
                $this->line("   Is Deferred: " . ($method['is_deferred'] ? 'Yes' : 'No'));
            }
            
            $this->newLine();
        }

        $this->line("═══════════════════════════════════════════════════════════");
        $this->comment("\n💡 Para ver JSON completo: --json");

        return 0;
    }
}