<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('customer_name');
            $table->string('whatsapp');
            $table->string('branch');
            $table->enum('delivery_method', ['pickup', 'delivery']);
            $table->string('payment_method');
            $table->text('delivery_address')->nullable();
            $table->text('raw_text_input');
            $table->enum('status', ['pending', 'awaiting_confirmation', 'processing', 'completed', 'cancelled'])->default('pending');
            $table->string('magento_cart_id')->nullable();
            $table->string('magento_order_id')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};