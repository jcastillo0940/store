<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MagentoService;

class SearchMagentoProduct extends Command
{
    protected $signature = 'magento:search {term : Término de búsqueda o SKU}';
    protected $description = 'Busca productos en Magento por nombre o SKU';

    protected MagentoService $magentoService;

    public function __construct(MagentoService $magentoService)
    {
        parent::__construct();
        $this->magentoService = $magentoService;
    }

    public function handle()
    {
        $term = $this->argument('term');
        $this->info("🔍 Buscando productos en Magento: {$term}\n");

        // Intentar primero como SKU exacto
        $product = $this->magentoService->getProductBySku($term);

        if ($product) {
            $this->displayProduct($product);
            return 0;
        }

        // Si no es un SKU, buscar por nombre
        $this->line("No se encontró como SKU, buscando por nombre...\n");
        $products = $this->magentoService->searchProduct($term);

        if (empty($products)) {
            $this->warn("❌ No se encontraron productos.");
            return 1;
        }

        $this->info("✅ Se encontraron " . count($products) . " productos:\n");

        foreach ($products as $product) {
            $this->displayProduct($product);
            $this->line(str_repeat('─', 80));
        }

        return 0;
    }

    private function displayProduct(array $product): void
    {
        $this->line("📦 <fg=cyan;options=bold>{$product['name']}</>");
        $this->line("   SKU: <fg=yellow>{$product['sku']}</>");
        $this->line("   Precio: $" . number_format($product['price'] ?? 0, 2));

        // Verificar stock
        if (isset($product['extension_attributes']['stock_item'])) {
            $stockItem = $product['extension_attributes']['stock_item'];
            $qty = $stockItem['qty'] ?? 0;
            $inStock = $stockItem['is_in_stock'] ?? false;

            if ($inStock && $qty > 0) {
                $this->line("   Stock: <fg=green>✓ Disponible (Cantidad: {$qty})</>");
            } else {
                $this->line("   Stock: <fg=red>✗ No disponible</>");
            }
        } else {
            $this->line("   Stock: <fg=yellow>? Información no disponible</>");
        }

        // Mostrar status
        $status = $product['status'] ?? 0;
        $statusText = $status == 1 ? '<fg=green>Activo</>' : '<fg=red>Inactivo</>';
        $this->line("   Estado: {$statusText}");

        $this->newLine();
    }
}
