<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabla para órdenes de intención que se envían al Hub para picking
     */
    public function up(): void
    {
        Schema::create('picking_orders', function (Blueprint $table) {
            $table->id();

            // Datos del cliente
            $table->string('customer_name');
            $table->string('whatsapp');
            $table->string('email');
            $table->string('branch')->default('Aguadulce');
            $table->string('delivery_method')->default('pickup'); // pickup o delivery
            $table->string('payment_method')->default('efectivo');
            $table->text('delivery_address')->nullable();

            // Texto original del cliente
            $table->text('raw_text_input');

            // Items fragmentados (array JSON de strings)
            $table->json('items_as_text')->nullable();

            // Integración con Hub
            $table->string('hub_order_id')->nullable()->index();
            $table->enum('status', [
                'draft',              // Borrador, no enviado
                'pending_dispatch',   // Listo para enviar al Hub
                'dispatching',        // Enviando al Hub
                'sent_to_hub',        // Enviado exitosamente al Hub
                'hub_processing',     // Hub está procesando (matching)
                'picking',            // En proceso de picking en Hub
                'picked',             // Picking completado
                'ready_delivery',     // Listo para entrega
                'completed',          // Completado
                'failed',             // Falló el envío al Hub
                'cancelled'           // Cancelado
            ])->default('draft');

            // Respuestas del Hub
            $table->json('hub_response')->nullable(); // Respuesta del dispatch
            $table->json('hub_updates')->nullable();  // Array de actualizaciones via webhook
            $table->text('dispatch_error')->nullable(); // Error si falla el envío

            // Reintentos de envío
            $table->integer('dispatch_attempts')->default(0);
            $table->timestamp('last_dispatch_attempt')->nullable();
            $table->timestamp('dispatched_at')->nullable();

            // Magento (cuando el Hub crea la orden)
            $table->string('magento_order_id')->nullable()->index();

            $table->timestamps();

            // Índices
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'dispatch_attempts']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('picking_orders');
    }
};
