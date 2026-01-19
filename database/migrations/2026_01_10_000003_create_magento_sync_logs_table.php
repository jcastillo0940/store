<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magento_sync_logs', function (Blueprint $table) {
            $table->id();
            
            // Relación con el pedido para saber qué fallo exactamente
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            
            // --- Detalles de la Transacción ---
            $table->string('method')->default('POST'); // GET, POST, PUT
            $table->string('endpoint'); // V1/products, V1/guest-carts, etc.
            $table->integer('response_status')->index(); // 200, 401, 404, 500
            
            // --- Datos de Auditoría ---
            // Usamos longText o json para asegurar que listas largas de productos no corten el log
            $table->json('payload_sent')->nullable(); 
            $table->json('response_received')->nullable(); 
            
            // --- Diagnóstico ---
            $table->text('error_message')->nullable(); // Mensaje de excepción de Laravel o Magento
            $table->string('request_id')->nullable()->index(); // Para rastrear en logs del servidor
            $table->integer('execution_time_ms')->nullable(); // Tiempo que tardó la API en responder
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magento_sync_logs');
    }
};