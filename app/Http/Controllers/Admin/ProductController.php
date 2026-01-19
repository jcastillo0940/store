<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SearchCuration;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Vista 1: Tabla general de productos (Auditoría).
     */
    public function index(Request $request)
    {
        $products = Product::when($request->search, function($q, $search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('sku', 'LIKE', "%{$search}%");
        })->latest()->paginate(50);

        return view('admin.products.index', compact('products'));
    }

    /**
     * Vista 2: El "Entrenador" de búsqueda.
     */
    public function curationView()
    {
        return view('admin.curation.index');
    }

    /**
     * API AJAX: Buscar productos con lógica de coincidencia exacta y carga de curaduría.
     */
    public function searchProducts(Request $request)
    {
        $term = strtolower(trim($request->term));
        
        if (empty($term)) {
            return response()->json(['products' => [], 'curation' => null]);
        }

        // 1. Obtener curaduría existente si la hay
        $curation = SearchCuration::where('search_term', $term)->first();

        // 2. Búsqueda de productos por palabra exacta (Evita que "pan" traiga "panal")
        // MySQL REGEXP '[[:<:]]' y '[[:>:]]' aseguran límites de palabra
        $products = Product::where('name', 'REGEXP', '[[:<:]]' . $term . '[[:>:]]')
            ->orWhere('sku', $term)
            ->limit(40)
            ->get();

        // 3. Si ya existe una curaduría, reorganizar los productos para poner los guardados primero
        if ($curation) {
            $savedSkus = array_merge([$curation->pinned_sku], $curation->alternative_skus ?? []);
            
            // Obtenemos los productos guardados en el orden exacto de la curaduría
            $savedProducts = Product::whereIn('sku', $savedSkus)
                ->get()
                ->sortBy(function($p) use ($savedSkus) {
                    return array_search($p->sku, $savedSkus);
                })->values();

            // Combinamos: primero lo guardado, luego los nuevos resultados (sin duplicados)
            $products = $savedProducts->merge($products->whereNotIn('sku', $savedSkus));
        }

        return response()->json([
            'products' => $products,
            'curation' => $curation
        ]);
    }

    /**
     * API AJAX: Guardar o actualizar la regla.
     */
    public function saveOrder(Request $request)
    {
        $request->validate([
            'search_term' => 'required|string',
            'skus' => 'required|array|min:1'
        ]);

        $searchTerm = strtolower(trim($request->search_term));
        $skus = $request->skus;
        $pinned = $skus[0];
        $alternatives = count($skus) > 1 ? array_slice($skus, 1) : [];

        $curation = SearchCuration::updateOrCreate(
            ['search_term' => $searchTerm],
            [
                'pinned_sku' => $pinned,
                'alternative_skus' => $alternatives,
                'synonyms' => $request->synonyms ?? ''
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Entrenamiento guardado para '{$searchTerm}'",
            'data' => $curation
        ]);
    }

    /**
     * API PÚBLICA: Alternativas inteligentes para el cliente.
     */
    public function getAlternatives($sku)
    {
        $curation = SearchCuration::where('pinned_sku', $sku)->first();

        if ($curation && !empty($curation->alternative_skus)) {
            return response()->json(Product::whereIn('sku', $curation->alternative_skus)->get());
        }

        $originalProduct = Product::where('sku', $sku)->first();
        if (!$originalProduct) return response()->json([]);

        // Fallback automático por nombre similar
        $words = explode(' ', $originalProduct->name);
        $searchQuery = (count($words) > 1) ? $words[0] . ' ' . $words[1] : $words[0];

        return response()->json(
            Product::where('name', 'LIKE', "%{$searchQuery}%")
                ->where('sku', '!=', $sku)
                ->limit(6)
                ->get()
        );
    }
}