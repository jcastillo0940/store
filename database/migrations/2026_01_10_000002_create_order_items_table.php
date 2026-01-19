<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            // Relación con el pedido principal
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            
            // --- Referencias de Magento ---
            $table->string('magento_product_id')->nullable()->index(); // Indexado para búsquedas rápidas
            $table->string('sku')->nullable()->index(); // El SKU es vital para el picking en dark stores
            $table->string('product_type')->nullable(); // Simple, configurable, etc.
            
            // --- Datos del Producto (Snapshot) ---
            $table->string('name'); 
            $table->string('image_url')->nullable(); 
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->decimal('row_total', 10, 2)->virtualAs('quantity * price'); // Columna calculada para el subtotal por línea
            
            // --- Inteligencia de la Lista ---
            // "search_term_origin" es lo que el usuario escribió (ej: "2 leches")
            $table->string('search_term_origin')->nullable(); 
            // "match_confidence" para saber qué tan seguro está el algoritmo del match
            $table->decimal('match_confidence', 5, 2)->nullable(); 
            
            // --- Validación y Sustitutos (UX) ---
            $table->boolean('is_confirmed')->default(false); // Etiqueta Verde de aprobación del cliente
            $table->text('customer_note')->nullable(); // Para notas como "bien maduro" o "sin grasa"
            $table->string('substitution_status')->default('none'); // approved, rejected, pending
            
            $table->timestamps();
            $table->softDeletes(); // Para auditoría si el cliente elimina un ítem sugerido
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};