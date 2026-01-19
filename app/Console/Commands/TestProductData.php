<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

class TestProductData extends Command
{
    protected $signature = 'product:debug {sku} {--raw : Imprime solo el JSON puro}';
    protected $description = 'Muestra absolutamente todos los datos de Magento para un SKU';

   public function handle()
{
    $sku = $this->argument('sku');
    $baseUrl = rtrim(config('services.magento.base_url'), '/');
    $token = config('services.magento.token');
    
    $url = "{$baseUrl}/rest/V1/products/" . urlencode(trim($sku));
    
    $this->info("🛰️  Consultando: {$url}");
    $response = Http::withToken($token)->timeout(15)->get($url);
    
    if ($response->failed()) {
        $this->error("❌ Error (Status: " . $response->status() . ")");
        $this->line($response->body());
        return;
    }

    $mgt = $response->json();
    
    if ($this->option('raw')) {
        $this->line(json_encode($mgt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }

        // Si usas --raw, solo sale el JSON y termina
        if ($this->option('raw')) {
            $this->line(json_encode($mgt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->info("✅ PRODUCTO: " . ($mgt['name'] ?? 'N/A'));

        // 1. Resumen en Tabla
        $this->table(
            ['Campo Principal', 'Valor'],
            [
                ['ID Interno', $mgt['id'] ?? 'N/A'],
                ['SKU', $mgt['sku'] ?? 'N/A'],
                ['Tipo', $mgt['type_id'] ?? 'N/A'],
                ['Precio Base', $mgt['price'] ?? '0.00'],
                ['Peso (Weight)', $mgt['weight'] ?? '0'],
                ['Status', ($mgt['status'] ?? '') == 1 ? 'Habilitado' : 'Deshabilitado'],
            ]
        );

        // 2. Custom Options (Con validación de llaves)
        if (!empty($mgt['options'])) {
            $this->warn("\n🎨 CUSTOM OPTIONS (OPCIONES PERSONALIZADAS):");
            foreach ($mgt['options'] as $option) {
                $type = $option['type'] ?? 'unknown';
                $this->line("<info>● {$option['title']}</info> [ID: {$option['option_id']}, Tipo: {$type}]");
                
                if (!empty($option['values'])) {
                    foreach ($option['values'] as $val) {
                        $vSku = $val['sku'] ?? 'N/A';
                        $vPrice = $val['price'] ?? '0';
                        $this->line("   └─ Valor: <comment>{$val['title']}</comment> (SKU: {$vSku}, Precio: +{$vPrice})");
                    }
                }
            }
        }

        // 3. Extension Attributes (Stock, etc)
        if (!empty($mgt['extension_attributes'])) {
            $this->warn("\n🔗 EXTENSION ATTRIBUTES (STOCK / WEBSITES):");
            $this->line(json_encode($mgt['extension_attributes'], JSON_PRETTY_PRINT));
        }

        // 4. Custom Attributes (Atributos de Producto)
        if (!empty($mgt['custom_attributes'])) {
            $this->warn("\n⚙️  CUSTOM ATTRIBUTES (META DATA):");
            foreach ($mgt['custom_attributes'] as $attr) {
                $val = is_array($attr['value']) ? implode(', ', $attr['value']) : $attr['value'];
                $this->line("- <info>{$attr['attribute_code']}:</info> {$val}");
            }
        }

        $this->warn("\n📝 FULL DATA (JSON INICIAL):");
        $this->line(json_encode($mgt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}