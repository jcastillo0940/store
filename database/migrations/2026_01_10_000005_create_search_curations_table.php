<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_curations', function (Blueprint $table) {
            $table->id();
            $table->string('search_term')->unique()->index(); // Ejemplo: "leche"
            $table->string('pinned_sku')->nullable(); // El SKU que aparecerá por defecto
            $table->json('alternative_skus')->nullable(); // SKUs para el pop-up de reemplazo
            $table->text('synonyms')->nullable(); // Términos relacionados separados por coma
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_curations');
    }
};