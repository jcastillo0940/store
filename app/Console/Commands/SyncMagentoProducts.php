<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncMagentoProducts extends Command
{
    /**
     * El nombre y la firma del comando de consola.
     */
    protected $signature = 'magento:sync {--limit=15000 : Cantidad máxima de productos a sincronizar}';

    /**
     * La descripción del comando de consola.
     */
    protected $description = 'Descarga el catálogo completo de Magento con mapeo profundo de opciones personalizadas';

    protected $baseUrl;
    protected $token;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = rtrim(config('services.magento.base_url'), '/');
        $this->token = config('services.magento.token');
    }

    /**
     * Ejecuta el comando de consola.
     */
    public function handle()
    {
        $totalLimit = (int) $this->option('limit');
        $pageSize = 200;
        $currentPage = 1;
        $totalProcessed = 0;

        $this->info("Iniciando sincronización masiva (Límite: $totalLimit productos)...");

        if (!$this->baseUrl || !$this->token) {
            $this->error("Error: Credenciales no configuradas. Revisa tu archivo .env");
            return 1;
        }

        // Barra de progreso para feedback en consola
        $bar = $this->output->createProgressBar();
        $bar->start();

        try {
            while ($totalProcessed < $totalLimit) {
                // Petición a la API de Magento filtrando solo productos activos (status = 1)
                $response = Http::withToken($this->token)
                    ->timeout(60)
                    ->get("{$this->baseUrl}/rest/V1/products", [
                        'searchCriteria[filter_groups][0][filters][0][field]' => 'status',
                        'searchCriteria[filter_groups][0][filters][0][value]' => '1',
                        'searchCriteria[currentPage]' => $currentPage,
                        'searchCriteria[pageSize]' => $pageSize
                    ]);

                if (!$response->successful()) {
                    $this->error("\nError en página {$currentPage}: " . $response->status());
                    break;
                }

                $data = $response->json();
                $items = $data['items'] ?? [];

                if (empty($items)) {
                    break;
                }

                // Ajustar el máximo de la barra de progreso en la primera iteración
                if ($currentPage === 1 && isset($data['total_count'])) {
                    $realTotal = min($data['total_count'], $totalLimit);
                    $bar->setMaxSteps($realTotal);
                }

                foreach ($items as $item) {
                    // 1. Extraer imagen principal
                    $imageUrl = null;
                    if (!empty($item['media_gallery_entries'])) {
                        $imageUrl = $item['media_gallery_entries'][0]['file'] ?? null;
                    }

                    // 2. Extraer peso base
                    $weight = $item['weight'] ?? null;

                    // 3. MAPEO PROFUNDO DE CUSTOM OPTIONS
                    // Guardamos el ID del contenedor (option_id) y los IDs de los valores (option_type_id)
                    $customOptions = null;
                    if (!empty($item['options'])) {
                        $customOptions = collect($item['options'])->map(function($option) {
                            return [
                                'option_id' => $option['option_id'], // ID del grupo (ej. 45051)
                                'title' => $option['title'],
                                'type' => $option['type'],
                                'is_require' => $option['is_require'] ?? false,
                                'values' => collect($option['values'] ?? [])->map(function($value) {
                                    return [
                                        'option_type_id' => $value['option_type_id'], // ID del valor (ej. 29975)
                                        'title' => $value['title'],
                                        'price' => $value['price'] ?? 0
                                    ];
                                })->toArray()
                            ];
                        })->toArray();
                    }

                    // 4. Verificar atributo "sellable_by_weight" en custom_attributes
                    $sellableByWeight = false;
                    if (!empty($item['custom_attributes'])) {
                        foreach ($item['custom_attributes'] as $attr) {
                            if ($attr['attribute_code'] === 'sellable_by_weight' && $attr['value'] == '1') {
                                $sellableByWeight = true;
                                break;
                            }
                        }
                    }

                    // 5. Sincronizar con la base de datos local
                    Product::updateOrCreate(
                        ['magento_id' => $item['id']],
                        [
                            'sku' => $item['sku'],
                            'name' => $item['name'],
                            'price' => $item['price'] ?? 0,
                            'weight' => $weight,
                            'custom_options' => $customOptions, // Se guarda como JSON gracias al cast en el modelo Product
                            'sellable_by_weight' => $sellableByWeight,
                            'image_url' => $imageUrl,
                            'is_active' => true,
                            'stock_quantity' => 100, // Valor por defecto
                            'updated_at' => now(),
                        ]
                    );

                    $totalProcessed++;
                    $bar->advance();

                    if ($totalProcessed >= $totalLimit) {
                        break 2;
                    }
                }

                $currentPage++;
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Sincronización finalizada con éxito.");
            $this->info("Total de productos procesados: $totalProcessed");

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("Ocurrió un error crítico: " . $e->getMessage());
            Log::error("Magento Sync Exception: " . $e->getMessage());
        }

        return 0;
    }
}