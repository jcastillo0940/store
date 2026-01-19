<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Models\SearchCuration;

class TestMagentoSearch extends Command
{
    protected $signature = 'magento:search {products}';
    protected $description = 'Prueba el motor de búsqueda local con prioridad de PIN';

    public function handle()
    {
        $input = $this->argument('products');
        $terms = preg_split('/[,\n\r]+/', $input, -1, PREG_SPLIT_NO_EMPTY);

        $this->info("Buscando con Inteligencia de Curaduría...");
        $this->newLine();

        $results = [];

        foreach ($terms as $term) {
            $term = strtolower(trim($term));
            if (empty($term)) continue;

            // 1. LÓGICA DE PIN: Buscar primero en curaduría
            $curation = SearchCuration::where('search_term', $term)->first();
            $match = null;
            $tipo = 'Búsqueda Normal';

            if ($curation && $curation->pinned_sku) {
                $match = Product::where('sku', $curation->pinned_sku)->first();
                $tipo = '★ PIN CONFIGURADO';
            }

            // 2. FALLBACK: Si no hay pin, usar LIKE normal
            if (!$match) {
                $match = Product::where('name', 'LIKE', "%{$term}%")
                                ->orWhere('sku', 'LIKE', "%{$term}%")
                                ->first();
            }

            if ($match) {
                $results[] = [
                    'Buscado' => $term,
                    'Resultado' => $match->name,
                    'SKU' => $match->sku,
                    'Precio' => '$' . number_format($match->price, 2),
                    'Tipo' => $tipo
                ];
            } else {
                $results[] = [
                    'Buscado' => $term,
                    'Resultado' => 'No encontrado',
                    'SKU' => '---',
                    'Precio' => '---',
                    'Tipo' => '✗'
                ];
            }
        }

        $this->table(['Término', 'Producto', 'SKU', 'Precio', 'Origen'], $results);
    }
}