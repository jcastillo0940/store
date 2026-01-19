<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('customer_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('whatsapp')->index(); // Identificador del cliente
            $table->string('search_term');       // Lo que escribió: "papa"
            $table->string('selected_sku');      // Lo que eligió: "00417"
            $table->integer('hit_count')->default(1); // Relevancia
            $table->timestamps();

            // Evitamos duplicidad: Un término por cliente
            $table->unique(['whatsapp', 'search_term', 'selected_sku'], 'unique_preference');
        });
    }

    public function down() { Schema::dropIfExists('customer_preferences'); }
};