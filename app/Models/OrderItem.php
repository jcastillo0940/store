<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    // Confirmamos el nombre de la tabla
    protected $table = 'order_items';

    protected $fillable = [
    'order_id',
    'magento_product_id',
    'sku',
    'custom_option_id',
    'is_by_weight',  // ⬅️ AGREGAR
    'name',
    'image_url',
    'quantity',
    'price',
    'is_confirmed',
    'search_term_origin',
    'has_alternatives', 
    ];

    protected $casts = [
    'is_confirmed' => 'boolean',
    'has_alternatives' => 'boolean',
    'is_by_weight' => 'boolean',  // ⬅️ AGREGAR
    'price' => 'decimal:2',
    'quantity' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}