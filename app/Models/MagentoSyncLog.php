<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagentoSyncLog extends Model
{
    protected $table = 'magento_sync_logs';

    protected $fillable = [
        'order_id',
        'endpoint',
        'response_status',
        'payload_sent',
        'response_received',
        'error_message',
    ];

    /**
     * Convertir JSON de la base de datos a Arrays de PHP automáticamente.
     */
    protected $casts = [
        'payload_sent' => 'array',
        'response_received' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}