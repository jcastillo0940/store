<?php

namespace App\Services;

use App\Models\PickingOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubService
{
    protected ?string $baseUrl;
    protected ?string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.hub.base_url'), '/');
        $this->apiKey = config('services.hub.api_key');
        $this->timeout = config('services.hub.timeout', 30);

        if (empty($this->baseUrl)) {
            Log::warning("Hub base URL not configured in .env");
        }
    }

    /**
     * Despacha una orden de picking al Hub
     */
    public function dispatchPickingOrder(PickingOrder $order): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Hub not configured. Check HUB_BASE_URL and HUB_API_KEY in .env',
            ];
        }

        try {
            Log::info("Despachando orden #{$order->id} al Hub", [
                'order_id' => $order->id,
                'customer' => $order->customer_name,
                'items_count' => count($order->items_as_text ?? []),
            ]);

            // Preparar payload
            $payload = [
                'store_order_id' => $order->id,
                'customer_name' => $order->customer_name,
                'whatsapp' => $order->whatsapp,
                'email' => $order->email,
                'branch' => $order->branch,
                'delivery_method' => $order->delivery_method,
                'payment_method' => $order->payment_method,
                'delivery_address' => $order->delivery_address,
                'raw_text_input' => $order->raw_text_input,
                'items_as_text' => $order->items_as_text,
            ];

            // Hacer request al Hub
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/picking-orders", $payload);

            // Log de la respuesta
            Log::info("Respuesta del Hub", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'hub_order_id' => $data['hub_order_id'] ?? $data['id'] ?? null,
                    'data' => $data,
                ];
            }

            // Error del Hub
            $errorMessage = $response->json('message') ?? $response->body();
            Log::error("Error del Hub al despachar orden", [
                'order_id' => $order->id,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Excepción al despachar orden al Hub", [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Consulta el estado de una orden en el Hub
     */
    public function getOrderStatus(string $hubOrderId): array
    {
        if (!$this->baseUrl || !$this->apiKey) {
            return [
                'success' => false,
                'error' => 'Hub not configured',
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/api/picking-orders/{$hubOrderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('message') ?? 'Hub error',
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error("Error consultando estado en Hub", [
                'hub_order_id' => $hubOrderId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verifica si el Hub está disponible
     */
    public function healthCheck(): bool
    {
        if (!$this->baseUrl) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/api/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Hub health check failed: " . $e->getMessage());
            return false;
        }
    }
}
