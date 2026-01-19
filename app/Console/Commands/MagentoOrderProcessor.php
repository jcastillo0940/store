<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class MagentoOrderProcessor extends Command
{
    // El comando ahora no requiere parámetros: php artisan magento:process-order
    protected $signature = 'magento:process-order';
    protected $description = 'Procesa la última orden local hacia Magento automáticamente';

    public function handle()
    {
        // 1. Buscar la última orden que no tenga un magento_id (o simplemente la última)
        $order = Order::with('items')->latest()->first();

        if (!$order) {
            $this->error("❌ No se encontró ninguna orden en la base de datos local.");
            return 1;
        }

        $this->info("🚀 Procesando Orden Local #{$order->id} de {$order->customer_name}");

        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');
        $sourceCode = (string)$this->mapBranchToSourceCode($order->branch);

        try {
            // STEP 1: Crear Carrito Guest
            $res = $this->sendRequest('POST', "{$baseUrl}/rest/V1/guest-carts", []);
            $quoteId = str_replace('"', '', $res->body());
            $this->info("✅ Carrito Guest creado: $quoteId");

            // STEP 2: Agregar Items
            foreach ($order->items as $item) {
                $payload = [
                    'cartItem' => [
                        'quote_id' => $quoteId,
                        'sku' => $item->sku,
                        'qty' => (float)$item->quantity ?: 1
                    ]
                ];
                $this->sendRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/items", $payload);
            }

            // STEP 3: Shipping Information
            $address = [
                'firstname' => $order->customer_name,
                'lastname' => 'Cliente',
                'street' => [$order->delivery_address ?? 'WhatsApp'],
                'city' => $order->branch,
                'country_id' => 'PA',
                'postcode' => '00000',
                'telephone' => $order->whatsapp,
                'email' => $order->email,
            ];

            $this->sendRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/shipping-information", [
                'addressInformation' => [
                    'shipping_address' => $address,
                    'billing_address' => $address,
                    'shipping_method_code' => ($order->delivery_method === 'pickup') ? 'pickup' : 'bestway',
                    'shipping_carrier_code' => ($order->delivery_method === 'pickup') ? 'instore' : 'tablerate'
                ]
            ]);

            // --- 🛡️ VERIFICACIÓN DE INTEGRIDAD (Anti-Orden Vacía) ---
            $this->warn("🛡️ Verificando productos en Magento antes de cerrar...");
            sleep(4); 
            
            $verify = $this->sendRequest('GET', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/items", []);
            if (empty($verify->json())) {
                $this->error("❌ ERROR: El pedido se iba a crear vacío. Abortando.");
                return 1;
            }

            // STEP 4: Crear Orden Final
            $this->info("🚀 Creando Orden Final...");
            $resFinal = $this->sendRequest('POST', "{$baseUrl}/rest/V1/guest-carts/{$quoteId}/payment-information", [
                'email' => $order->email,
                'paymentMethod' => [
                    'method' => 'cashondelivery',
                    'extension_attributes' => ['agreement_ids' => ["7"]]
                ],
                'billing_address' => $address
            ]);

            if ($resFinal->successful()) {
                $magentoOrderId = str_replace('"', '', $resFinal->body());
                $this->info("\n🎉 ¡ÉXITO! Orden creada en Magento: " . $magentoOrderId);
                
                // Vincular sucursal
                $this->assignSource($magentoOrderId, $sourceCode, $baseUrl, $token);
            }

        } catch (\Exception $e) {
            $this->error("💥 Fallo: " . $e->getMessage());
        }
    }

    private function sendRequest($method, $url, $payload)
    {
        $response = Http::withToken(config('services.magento.token'))->withHeaders([
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'application/json',
            'Cache-Control' => 'no-cache'
        ])->timeout(90)->send($method, $url, empty($payload) ? [] : ['json' => $payload]);

        if (!$response->successful()) {
            $this->error("❌ Error en [$url]: " . $response->body());
            exit;
        }
        return $response;
    }

    private function assignSource($id, $code, $url, $token) {
        Http::withToken($token)->put("{$url}/rest/V1/orders/{$id}", [
            'entity' => ['entity_id' => $id, 'extension_attributes' => ['source_code' => $code]]
        ]);
    }

    private function mapBranchToSourceCode($branch) {
        $map = ['Aguadulce' => '1', 'Chitré' => '10', 'La Chorrera' => '11', 'Albrook' => '12', 'Costa Verde' => '13', 'Santiago' => '3', 'Las Tablas' => '7', 'Penonomé' => '8', 'Arraiján' => '9'];
        return $map[$branch] ?? '1';
    }
}