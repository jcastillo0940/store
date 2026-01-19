<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class MagentoOrderDebug extends Command
{
    protected $signature = 'magento:debug-checkout {order_id}';
    protected $description = 'Bypass exitoso: Creación de orden con T&C y headers anti-WAF';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::with('items')->find($orderId);

        if (!$order) {
            $this->error("❌ Orden local #{$orderId} no encontrada.");
            return 1;
        }

        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');
        $sourceCode = (string)$this->mapBranchToSourceCode($order->branch);

        $this->info("🚀 EJECUTANDO CHECKOUT FINAL - ORDEN: {$orderId}");

        try {
            // 1. Crear Carrito
            $res = $this->sendCleanRequest('POST', "{$baseUrl}/rest/V1/guest-carts", []);
            $quoteId = str_replace('"', '', $res->body());
            $this->info("✅ Quote ID: {$quoteId}");

            // 2. Agregar Items
            foreach ($order->items as $item) {
                $this->sendCleanRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/items", [
                    'cartItem' => ['quote_id' => $quoteId, 'sku' => $item->sku, 'qty' => (float)$item->quantity ?: 1]
                ]);
            }

            // 3. Shipping Information
            $address = [
                'firstname' => $order->customer_name, 'lastname' => 'Cliente',
                'street' => [$order->delivery_address ?? 'WhatsApp'], 'city' => $order->branch,
                'country_id' => 'PA', 'postcode' => '00000', 'telephone' => $order->whatsapp, 'email' => $order->email,
            ];
            $this->sendCleanRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/shipping-information", [
                'addressInformation' => [
                    'shipping_address' => $address, 'billing_address' => $address,
                    'shipping_method_code' => ($order->delivery_method === 'pickup') ? 'pickup' : 'bestway',
                    'shipping_carrier_code' => ($order->delivery_method === 'pickup') ? 'instore' : 'tablerate'
                ]
            ]);

            // 4. EL PASO DE LA VERDAD: Unificado con T&C
            $this->warn("\n🚀 Creando Orden Final (Bypass WAF activo)...");
            
            $finalPayload = [
                'email' => $order->email,
                'paymentMethod' => [
                    'method' => 'cashondelivery',
                    'extension_attributes' => [
                        'agreement_ids' => ["7"] // <-- OBLIGATORIO ENVIARLO AQUÍ
                    ]
                ],
                'billing_address' => $address
            ];

            $resFinal = $this->sendCleanRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/payment-information", $finalPayload);

            if ($resFinal->successful()) {
                $magentoId = str_replace('"', '', $resFinal->body());
                $this->info("\n🎉 ¡LO LOGRAMOS! ORDEN CREADA: " . $magentoId);
                
                // 5. Vincular MSI
                $this->assignSource($magentoId, $sourceCode, $baseUrl, $token);
            }

        } catch (\Exception $e) {
            $this->error("💥 Error: " . $e->getMessage());
        }
    }

    private function sendCleanRequest($method, $url, $payload)
    {
        $this->line("📡 [$method] $url");
        
        // Headers reforzados para evitar que el Firewall piense que es un ataque
        $response = Http::withToken(config('services.magento.token'))->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Requested-With' => 'XMLHttpRequest' // 💡 Esto ayuda a saltar algunas reglas de WAF
        ])->timeout(100)->send($method, $url, ['json' => $payload]);

        if (!$response->successful()) {
            $this->error("❌ Error " . $response->status() . ": " . (str_contains($response->body(), '<html') ? 'BLOQUEO DE RED' : $response->body()));
            exit;
        }
        return $response;
    }

    private function assignSource($id, $code, $url, $token) {
        $this->info("📍 Vinculando sucursal...");
        Http::withToken($token)->put("{$url}/rest/V1/orders/{$id}", [
            'entity' => ['entity_id' => $id, 'extension_attributes' => ['source_code' => $code]]
        ]);
    }

    private function mapBranchToSourceCode($branch) {
        $map = ['Aguadulce' => '1', 'Chitré' => '10', 'La Chorrera' => '11', 'Albrook' => '12', 'Costa Verde' => '13', 'Santiago' => '3', 'Las Tablas' => '7', 'Penonomé' => '8', 'Arraiján' => '9'];
        return $map[$branch] ?? '1';
    }
}