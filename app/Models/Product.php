<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos coinciden con tu nueva migración para el catálogo local.
     */
    protected $fillable = [
    'magento_id',
    'sku',
    'name',
    'price',
    'weight',
    'custom_options',
    'sellable_by_weight',
    'image_url',
    'stock_quantity',
    'is_active',
];

    /**
     * Casteo de tipos para asegurar integridad de datos.
     */
    protected $casts = [
    'price' => 'decimal:2',
    'weight' => 'decimal:3',
    'custom_options' => 'array',
    'sellable_by_weight' => 'boolean',
    'is_active' => 'boolean',
    'magento_id' => 'integer',
    'stock_quantity' => 'integer',
];
    /**
     * Scope para búsqueda inteligente (Full-Text Search).
     * Esto permite encontrar "leche chiricana" de forma eficiente.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) use ($term) {
                         $q->where('name', 'LIKE', "%{$term}%")
                           ->orWhere('sku', 'LIKE', "%{$term}%");
                     });
    }
}