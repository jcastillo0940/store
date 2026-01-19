<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MagentoGetRegions extends Command
{
    protected $signature = 'magento:get-regions {country=PA}';
    protected $description = 'Obtener regiones de un país en Magento';

    public function handle()
    {
        $country = $this->argument('country');
        
        $this->info("🌎 Obteniendo regiones de {$country}...\n");
        
        $baseUrl = rtrim(config('services.magento.base_url'), '/');
        $token = config('services.magento.token');
        
        $response = Http::withToken($token)
            ->get("{$baseUrl}/rest/V1/directory/countries/{$country}");
        
        if ($response->failed()) {
            $this->error("❌ Error: " . $response->body());
            return 1;
        }
        
        $data = $response->json();
        $regions = $data['available_regions'] ?? [];
        
        if (empty($regions)) {
            $this->warn("⚠️  No hay regiones configuradas para {$country}");
            return 0;
        }
        
        $this->info("✅ Regiones encontradas:\n");
        
        $table = [];
        foreach ($regions as $region) {
            $table[] = [
                $region['id'] ?? 'N/A',
                $region['code'] ?? 'N/A',
                $region['name'] ?? 'N/A'
            ];
        }
        
        $this->table(['ID', 'Code', 'Name'], $table);
        
        return 0;
    }
}