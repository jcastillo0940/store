<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\Product;

class MagentoService
{
    protected ?string $baseUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.magento.base_url'), '/');
        $this->token = config('services.magento.token');

        if (empty($this->baseUrl) || empty($this->token)) {
            Log::critical("Magento API Error: Credenciales no encontradas en .env");
        }
    }

    private function getApiUrl(string $endpoint): string
    {
        return "{$this->baseUrl}/rest/V1/" . ltrim($endpoint, '/');
    }

    private function isValidJsonResponse($response): bool
    {
        if (!$response->successful()) return false;
        $contentType = $response->header('Content-Type');
        return str_contains($contentType, 'application/json');
    }

    private function logErrorSafe(string $context, $response): void
    {
        $status = $response->status();
        $bodyPreview = Str::limit($response->body(), 250);
        Log::error("Magento API Error [{$context}]: Status {$status} - Body: {$bodyPreview}");
    }

    private function mapBranchToSourceCode(string $branch): string
    {
        $mapping = [
            'Aguadulce' => '1', 'Chitré' => '10', 'La Chorrera' => '11', 'Albrook' => '12',
            'Costa Verde' => '13', 'Santiago' => '3', 'Las Tablas' => '7', 'Penonomé' => '8', 'Arraiján' => '9',
        ];
        return $mapping[$branch] ?? '1';
    }

    public function getProductBySku($sku)
    {
        if (!$this->token || !$this->baseUrl) return null;

        $encodedSku = urlencode(trim($sku));
        $url = $this->getApiUrl("products/{$encodedSku}");

        try {
            $response = Http::withToken($this->token)->timeout(10)->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Magento getProductBySku Falló: SKU {$sku} - Status: {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Magento Exception en getProductBySku: " . $e->getMessage());
        }

        return null;
    }

    public function searchProduct(string $term)
    {
        if (!$this->token || !$this->baseUrl) return [];

        $url = $this->getApiUrl("products");

        try {
            $response = Http::withToken($this->token)->timeout(10)->get($url, [
                'searchCriteria[filter_groups][0][filters][0][field]' => 'name',
                'searchCriteria[filter_groups][0][filters][0][value]' => "%{$term}%",
                'searchCriteria[filter_groups][0][filters][0][condition_type]' => 'like',
                'searchCriteria[pageSize]' => 3
            ]);

            if ($response->successful()) {
                return $response->json()['items'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error("Magento Search Error: " . $e->getMessage());
        }

        return [];
    }

    public function createOrder(Order $order)
    {
        if (!$this->token || !$this->baseUrl) {
            Log::error("Magento: Credenciales no configuradas");
            return null;
        }

        try {
            // 1. Crear Quote
            $quoteResponse = Http::withToken($this->token)->timeout(15)->post($this->getApiUrl("guest-carts"));
            if (!$this->isValidJsonResponse($quoteResponse)) {
                $this->logErrorSafe("Creación de Quote", $quoteResponse);
                return null;
            }
            $quoteId = $quoteResponse->json();
            Log::info("Quote creado en Magento: {$quoteId}");

            // 2. Agregar Items
            Log::info("Agregando items al carrito");
            foreach ($order->items as $item) {
                $qty = (float)$item->quantity;
                $cartItem = [
                    'cartItem' => [
                        'quote_id' => $quoteId,
                        'sku' => $item->sku,
                        'qty' => $qty < 0.1 ? 1 : $qty
                    ]
                ];

                // NUEVA ESTRUCTURA para Custom Options
                if ($item->custom_option_id) {
                    $product = Product::where('sku', $item->sku)->first();
                    if ($product && $product->custom_options) {
                        foreach ($product->custom_options as $option) {
                            if (isset($option['values'])) {
                                foreach ($option['values'] as $value) {
                                    if ($value['option_type_id'] == $item->custom_option_id) {
                                        // SIN product_option wrapper
                                        $cartItem['cartItem']['extension_attributes'] = [
                                            'custom_options' => [
                                                [
                                                    'option_id' => (string)$option['option_id'],
                                                    'option_value' => (string)$value['option_type_id']
                                                ]
                                            ]
                                        ];
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }

                Log::info("Enviando item SKU {$item->sku}", ['cartItem' => $cartItem]);

                $res = Http::withToken($this->token)->post("{$this->baseUrl}/rest/V1/guest-carts/{$quoteId}/items", $cartItem);

                if (!$res->successful() && str_contains($res->body(), 'fewest you may purchase is 1')) {
                    Log::warning("Reintentando con cantidad 1 para SKU {$item->sku}");
                    $cartItem['cartItem']['qty'] = 1;
                    $res = Http::withToken($this->token)->post("{$this->baseUrl}/rest/V1/guest-carts/{$quoteId}/items", $cartItem);
                }

                if ($res->successful()) {
                    Log::info("Item {$item->sku} agregado exitosamente");
                } else {
                    Log::error("Error agregando item {$item->sku}: " . $res->body());
                }
            }

            // 3. Dirección
            $address = [
                'firstname' => $order->customer_name,
                'lastname' => 'Cliente',
                'street' => [$order->delivery_address ?? 'WhatsApp Order'],
                'city' => $order->branch ?? 'Panamá',
                'country_id' => 'PA',
                'postcode' => '00000',
                'telephone' => $order->whatsapp,
                'email' => $order->email
            ];

            // 4. Shipping Information
            $shippingInfo = [
                'addressInformation' => [
                    'shipping_address' => $address,
                    'billing_address' => $address,
                    'shipping_method_code' => $order->delivery_method === 'pickup' ? 'pickup' : 'bestway',
                    'shipping_carrier_code' => $order->delivery_method === 'pickup' ? 'instore' : 'tablerate'
                ]
            ];
            
            $shippingResponse = Http::withToken($this->token)
                ->post($this->getApiUrl("guest-carts/{$quoteId}/shipping-information"), $shippingInfo);
            
            if (!$shippingResponse->successful()) {
                $this->logErrorSafe("Shipping Information", $shippingResponse);
                return null;
            }

            // 5. Payment Information (ENDPOINT PROBLEMÁTICO)
            $paymentMethod = match(strtolower($order->payment_method)) {
                'efectivo' => 'cashondelivery',
                'yappy' => 'yappy',
                default => 'checkmo'
            };

            $paymentInfo = [
                'email' => $order->email,
                'paymentMethod' => [
                    'method' => $paymentMethod,
                    'extension_attributes' => [
                        'agreement_ids' => ['7']
                    ]
                ]
            ];

            $orderResponse = Http::withToken($this->token)->timeout(60)
                ->post($this->getApiUrl("guest-carts/{$quoteId}/payment-information"), $paymentInfo);

            if ($this->isValidJsonResponse($orderResponse)) {
                $magentoOrderId = $orderResponse->json();
                if (is_numeric($magentoOrderId)) {
                    Log::info("Orden creada en Magento con éxito. ID: {$magentoOrderId}");
                    return $magentoOrderId;
                }
            }

            $this->logErrorSafe("Creación de Orden Final", $orderResponse);
            return null;

        } catch (\Exception $e) {
            Log::error("Excepción crítica en MagentoService: " . $e->getMessage());
            return null;
        }
    }
}