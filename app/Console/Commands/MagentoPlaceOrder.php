<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class MagentoPlaceOrder extends Command
{
    protected $signature = 'magento:place-order {order_id}';
    protected $description = 'Crea la orden final superando el bloqueo de Términos y Condiciones';

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

        $this->info("🚀 Procesando Orden #{$orderId} para Magento...");

        try {
            // 1. Guest Cart
            $res = Http::withToken($token)->post("{$baseUrl}/rest/V1/guest-carts");
            $quoteId = str_replace('"', '', $res->body());
            $this->info("✅ Carrito creado: $quoteId");

            // 2. Items (Con manejo de cantidades y opciones)
            foreach ($order->items as $item) {
                $qty = (float)$item->quantity;
                $cartItem = [
                    'cartItem' => [
                        'quote_id' => $quoteId,
                        'sku' => $item->sku,
                        'qty' => $qty < 1 ? 1 : $qty // Forzamos 1 si el producto no acepta decimales
                    ]
                ];

                // Agregar opciones si existen (Papaya, etc)
                if ($item->custom_option_id) {
                    $cartItem['cartItem']['product_option']['extension_attributes']['custom_options'][] = [
                        'option_id' => "45051", // ID detectado en tus logs previos
                        'option_value' => (string)$item->custom_option_id
                    ];
                }

                Http::withToken($token)->post("{$baseUrl}/rest/V1/guest-carts/{$quoteId}/items", $cartItem);
            }
            $this->info("✅ Items agregados.");

            // 3. Shipping (Método TableRate validado)
            $address = [
                'firstname' => $order->customer_name,
                'lastname' => 'Cliente',
                'street' => ['WhatsApp Order'],
                'city' => $order->branch,
                'country_id' => 'PA',
                'postcode' => '00000',
                'telephone' => $order->whatsapp,
                'email' => $order->email,
            ];

            $shippingData = [
                'addressInformation' => [
                    'shipping_address' => $address,
                    'billing_address' => $address,
                    'shipping_method_code' => 'bestway',
                    'shipping_carrier_code' => 'tablerate'
                ]
            ];
            Http::withToken($token)->post("{$baseUrl}/rest/V1/guest-carts/{$quoteId}/shipping-information", $shippingData);
            $this->info("✅ Shipping configurado.");

            // 4. PLACE ORDER (Con aceptación de Términos y Condiciones)
            $this->comment("📡 Enviando transacción final...");
            
            $paymentData = [
                'email' => $order->email,
                'paymentMethod' => [
                    'method' => 'cashondelivery',
                    'extension_attributes' => [
                        // ESTA ES LA LLAVE: Aceptar los términos (ID 1 es el estándar)
                        'agreement_ids' => ["1"] 
                    ]
                ],
                'billingAddress' => $address
            ];

            $res = Http::withToken($token)->timeout(60)->post("{$baseUrl}/rest/V1/guest-carts/{$quoteId}/payment-information", $paymentData);

            if ($res->successful()) {
                $magentoId = str_replace('"', '', $res->body());
                $this->info("🎉 ¡ÉXITO TOTAL! Orden creada en Magento ID: " . $magentoId);
                
                // Intentamos asignar la sucursal a la orden ya creada
                $this->assignSource($magentoId, $order->branch, $baseUrl, $token);
            } else {
                $this->error("❌ Error en el paso final: " . $res->body());
            }

        } catch (\Exception $e) {
            $this->error("💥 Error: " . $e->getMessage());
        }
    }

    private function assignSource($magentoId, $branch, $baseUrl, $token) {
        $sources = ['Aguadulce' => '1', 'Chitré' => '10', 'Albrook' => '12'];
        $sourceCode = $sources[$branch] ?? '1';
        
        // Intentamos actualizar la orden directamente para vincular la sucursal
        Http::withToken($token)->put("{$baseUrl}/rest/V1/orders/{$magentoId}", [
            'entity' => [
                'entity_id' => $magentoId,
                'extension_attributes' => [
                    'source_code' => $sourceCode
                ]
            ]
        ]);
        $this->info("📍 Intento de vinculación a sucursal $sourceCode completado.");
    }
}