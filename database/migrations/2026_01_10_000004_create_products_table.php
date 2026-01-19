<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración para el catálogo espejo de Magento.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // --- Identificadores de Magento ---
            // magento_id: ID numérico único en el sistema de Magento
            $table->unsignedBigInteger('magento_id')->unique()->index();
            // sku: Código único del producto, vital para el picking en dark stores
            $table->string('sku')->unique()->index();
            
            // --- Información del Producto ---
            // name: Nombre del producto optimizado para SEO y búsqueda de usuario
            $table->string('name')->index(); 
            $table->decimal('price', 10, 2);
            $table->string('image_url')->nullable();
            
            // --- Estado e Inventario ---
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            
            // --- Campos de Auditoría ---
            $table->timestamps();
            
            // Fulltext Index: Permite búsquedas potentes por nombre o SKU
            // Esto soluciona que "leche" no encontraba nada si el usuario no escribía el nombre exacto
            $table->fullText(['name', 'sku']);
        });
    }

    /**
     * Revierte la migración.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};