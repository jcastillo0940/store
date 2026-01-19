<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPreference extends Model
{
    protected $fillable = ['whatsapp', 'search_term', 'selected_sku', 'hit_count'];
}