<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchCuration extends Model
{
    protected $fillable = ['search_term', 'pinned_sku', 'alternative_skus', 'synonyms'];

    protected $casts = [
        'alternative_skus' => 'array',
    ];

    /**
     * Obtiene los productos alternativos configurados para este término.
     */
    public function getAlternatives()
    {
        if (!$this->alternative_skus) return collect();
        return Product::whereIn('sku', $this->alternative_skus)->get();
    }
}