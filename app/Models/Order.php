<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * Definición de la tabla y campos rellenables.
     */
    protected $table = 'orders';

    protected $fillable = [
        'email',
        'customer_name',
        'whatsapp',
        'branch',
        'delivery_method',
        'payment_method',
        'delivery_address',
        'raw_text_input',
        'status',
        'magento_cart_id',
        'magento_order_id',
        'total_amount',
    ];

    /**
     * Relación con los productos (ítems) del pedido.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Accesor para el formato de moneda (opcional para la UI).
     */
    public function getTotalFormattedAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }
}