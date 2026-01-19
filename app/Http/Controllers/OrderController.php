<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SearchCuration;
use App\Models\CustomerPreference;
use App\Services\MagentoService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    protected $magentoService;
    protected $geminiService;

    public function __construct(MagentoService $magentoService, GeminiService $geminiService)
    {
        $this->magentoService = $magentoService;
        $this->geminiService = $geminiService;
    }

    private function parseLineIntelligence($line)
    {
        $line = strtolower(trim($line));
        $pattern = '/^(\d+\/\d+|\d+(?:\.\d+)?)\s*(lb|libra|lbs|kg|kilo|kilos|k|gr|gramos|g|oz|onzas)?\s*(?:de\s+)?(.+)$/i';
        
        if (preg_match($pattern, $line, $matches)) {
            $quantity = $this->evaluateValue($matches[1]);
            $unit = !empty($matches[2]) ? strtolower($matches[2]) : null;
            $term = trim($matches[3]);
            return [$quantity, $unit, $term];
        }
        return [1, null, $line];
    }

    private function evaluateValue($value)
    {
        if (strpos($value, '/') !== false) {
            list($num, $den) = explode('/', $value);
            return $den != 0 ? $num / $den : 1;
        }
        return (float) $value;
    }

    private function calculateWeightAndPrice($product, $rawQty, $unit)
    {
        $basePrice = (float) $product->price;
        $unitWeight = (float) ($product->weight ?? 1);
        $customOptions = $product->custom_options;
        $isByWeight = in_array($unit, ['lb', 'libra', 'lbs', 'kg', 'kilo', 'kilos', 'k', 'gr', 'gramos', 'g', 'oz', 'onzas']);

        if ($isByWeight) {
            $qtyInKg = match ($unit) {
                'lb', 'libra', 'lbs' => round($rawQty * 0.453592, 3),
                'gr', 'gramos', 'g' => $rawQty / 1000,
                'oz', 'onzas' => round($rawQty * 0.0283495, 3),
                'kg', 'kilo', 'kilos', 'k' => $rawQty,
                default => $rawQty
            };
            return [
                'magento_qty' => $qtyInKg,
                'unit_price' => $basePrice,
                'is_by_weight' => true,
                'custom_option_id' => $this->getCustomOptionId($customOptions, 'Kilogramo')
            ];
        } else {
            return [
                'magento_qty' => $rawQty,
                'unit_price' => $basePrice * $unitWeight,
                'is_by_weight' => false,
                'custom_option_id' => $this->getCustomOptionId($customOptions, 'Unidad')
            ];
        }
    }

    private function getCustomOptionId($customOptions, $targetTitle)
    {
        if (!$customOptions) return null;
        foreach ($customOptions as $option) {
            if (isset($option['values'])) {
                foreach ($option['values'] as $value) {
                    if ($value['title'] === $targetTitle) return $value['option_type_id'];
                }
            }
        }
        return null;
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'raw_text_input' => 'required|string',
            'whatsapp' => 'required',
            'customer_name' => 'required',
            'payment_method' => 'required'
        ]);

        return DB::transaction(function () use ($request) {
            $order = Order::create([
                'email' => $request->email,
                'customer_name' => $request->customer_name,
                'whatsapp' => $request->whatsapp,
                'branch' => $request->branch ?? 'Aguadulce',
                'delivery_method' => $request->delivery_method ?? 'pickup',
                'payment_method' => $request->payment_method,
                'raw_text_input' => $request->raw_text_input,
                'status' => 'pending'
            ]);

            $history = CustomerPreference::where('whatsapp', $request->whatsapp)->get()->toArray();
            $aiProcessedList = $this->geminiService->analyzeList($request->raw_text_input, $history);
            $lines = $aiProcessedList ?: explode("\n", str_replace("\r", "", $request->raw_text_input));

            foreach ($lines as $lineData) {
                $term = is_array($lineData) ? $lineData['term'] : $lineData;
                if (empty(trim($term))) continue;

                list($rawQty, $unit, $termClean) = $this->parseLineIntelligence(
                    is_array($lineData) ? ($lineData['qty'] . " " . $lineData['unit'] . " " . $lineData['term']) : $lineData
                );

                $match = Product::where('name', 'LIKE', "%{$termClean}%")
                    ->orWhere('sku', $termClean)
                    ->first();

                if ($match) {
                    $details = $this->calculateWeightAndPrice($match, $rawQty, $unit);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'magento_product_id' => $match->magento_id,
                        'sku' => $match->sku,
                        'name' => $match->name,
                        'price' => $details['unit_price'],
                        'quantity' => $details['magento_qty'],
                        'is_by_weight' => $details['is_by_weight'],
                        'search_term_origin' => $termClean,
                        'is_confirmed' => false,
                        'has_alternatives' => true,
                        'custom_option_id' => $details['custom_option_id'],
                        'image_url' => "https://mcstaging.supercarnes.com/media/catalog/product" . $match->image_path
                    ]);
                }
            }
            return redirect()->route('orders.confirm', $order->id);
        });
    }

    public function show(Order $order)
    {
        $order->refresh()->load('items');
        return view('orders.confirm', compact('order'));
    }

    public function replaceItem(Request $request)
    {
        try {
            $item = OrderItem::findOrFail($request->order_item_id);
            $order = $item->order;
            $newProduct = Product::where('sku', $request->new_sku)->firstOrFail();

            CustomerPreference::updateOrInsert(
                ['whatsapp' => $order->whatsapp, 'search_term' => $item->search_term_origin],
                ['selected_sku' => $newProduct->sku, 'hit_count' => DB::raw('hit_count + 1'), 'updated_at' => now()]
            );

            $item->update([
                'magento_product_id' => $newProduct->magento_id,
                'sku' => $newProduct->sku,
                'name' => $newProduct->name,
                'price' => $newProduct->price
            ]);

            return response()->json([
                'status' => 'success',
                'new_name' => $newProduct->name,
                'new_price' => number_format($newProduct->price, 2),
                'new_image' => "https://mcstaging.supercarnes.com/media/catalog/product" . $newProduct->image_path
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function checkout(Request $request, Order $order)
    {
        $itemsData = $request->input('items', []);

        return DB::transaction(function () use ($order, $itemsData) {
            $finalTotal = 0;
            foreach ($itemsData as $itemId => $data) {
                $item = OrderItem::find($itemId);
                if ($item && isset($data['confirmed'])) {
                    $item->update(['quantity' => $data['qty'], 'is_confirmed' => true]);
                    $finalTotal += ($item->price * $item->quantity);
                } elseif ($item) {
                    $item->delete();
                }
            }

            $order->update(['total_amount' => $finalTotal, 'status' => 'processing']);
            $magentoOrderId = $this->magentoService->createOrder($order);

            if ($magentoOrderId) {
                $order->update(['magento_order_id' => $magentoOrderId, 'status' => 'completed']);
                return redirect('/')->with('success', "Pedido #{$magentoOrderId} procesado.");
            }
            return back()->with('error', 'Error con Magento.');
        });
    }

    public function getAlternatives($sku)
    {
        $product = Product::where('sku', $sku)->first();
        if (!$product) return response()->json([]);

        $words = explode(' ', trim($product->name));
        if (empty($words) || empty($words[0])) {
            return response()->json([]);
        }

        $firstWord = $words[0];
        $alternatives = Product::where('name', 'LIKE', "{$firstWord}%")
            ->where('sku', '!=', $sku)
            ->limit(8)
            ->get();

        return response()->json($alternatives);
    }
}