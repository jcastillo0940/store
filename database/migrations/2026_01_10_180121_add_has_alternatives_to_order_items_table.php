<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Verificamos por seguridad antes de añadirla
            if (!Schema::hasColumn('order_items', 'has_alternatives')) {
                $table->boolean('has_alternatives')->default(false)->after('is_confirmed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('has_alternatives');
        });
    }
};