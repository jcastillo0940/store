<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PickingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'whatsapp',
        'email',
        'branch',
        'delivery_method',
        'payment_method',
        'delivery_address',
        'raw_text_input',
        'items_as_text',
        'hub_order_id',
        'status',
        'hub_response',
        'hub_updates',
        'dispatch_error',
        'dispatch_attempts',
        'last_dispatch_attempt',
        'dispatched_at',
        'magento_order_id',
    ];

    protected $casts = [
        'items_as_text' => 'array',
        'hub_response' => 'array',
        'hub_updates' => 'array',
        'last_dispatch_attempt' => 'datetime',
        'dispatched_at' => 'datetime',
    ];

    /**
     * Fragmenta el texto libre en items individuales
     */
    public function fragmentItems(): array
    {
        $text = $this->raw_text_input;

        // Limpiar y normalizar
        $text = trim($text);
        $text = preg_replace('/\s+/', ' ', $text); // Múltiples espacios a uno

        // Separar por comas, saltos de línea o "y"
        $items = preg_split('/[,\n]+|\s+y\s+/i', $text);

        // Limpiar cada item
        $items = array_map(function ($item) {
            return trim($item);
        }, $items);

        // Remover vacíos
        $items = array_filter($items, function ($item) {
            return !empty($item) && strlen($item) > 1;
        });

        return array_values($items);
    }

    /**
     * Marca como listo para despachar
     */
    public function markPendingDispatch(): void
    {
        $this->update([
            'status' => 'pending_dispatch',
            'items_as_text' => $this->fragmentItems(),
        ]);
    }

    /**
     * Marca como despachando
     */
    public function markDispatching(): void
    {
        $this->update([
            'status' => 'dispatching',
            'dispatch_attempts' => $this->dispatch_attempts + 1,
            'last_dispatch_attempt' => now(),
        ]);
    }

    /**
     * Marca como enviado exitosamente
     */
    public function markSentToHub(string $hubOrderId, array $hubResponse): void
    {
        $this->update([
            'status' => 'sent_to_hub',
            'hub_order_id' => $hubOrderId,
            'hub_response' => $hubResponse,
            'dispatched_at' => now(),
            'dispatch_error' => null,
        ]);
    }

    /**
     * Marca como fallido
     */
    public function markDispatchFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'dispatch_error' => $error,
        ]);
    }

    /**
     * Agrega una actualización del Hub
     */
    public function addHubUpdate(array $update): void
    {
        $updates = $this->hub_updates ?? [];
        $updates[] = array_merge($update, ['received_at' => now()->toIso8601String()]);

        $this->update([
            'hub_updates' => $updates,
            'status' => $update['status'] ?? $this->status,
        ]);
    }

    /**
     * Scope para órdenes pendientes de despacho
     */
    public function scopePendingDispatch($query)
    {
        return $query->where('status', 'pending_dispatch');
    }

    /**
     * Scope para órdenes que fallaron y pueden reintentarse
     */
    public function scopeFailedRetryable($query, int $maxAttempts = 3)
    {
        return $query->where('status', 'failed')
            ->where('dispatch_attempts', '<', $maxAttempts)
            ->where(function ($q) {
                $q->whereNull('last_dispatch_attempt')
                    ->orWhere('last_dispatch_attempt', '<', now()->subMinutes(5));
            });
    }

    /**
     * Puede reintentarse?
     */
    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->status === 'failed'
            && $this->dispatch_attempts < $maxAttempts
            && ($this->last_dispatch_attempt === null || $this->last_dispatch_attempt->lt(now()->subMinutes(5)));
    }
}
