<?php

namespace App\Http\Controllers;

use App\Models\PickingOrder;
use App\Services\HubService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PickingOrderController extends Controller
{
    protected HubService $hubService;

    public function __construct(HubService $hubService)
    {
        $this->hubService = $hubService;
    }

    /**
     * Muestra el formulario de captura
     */
    public function create()
    {
        return view('picking-orders.create');
    }

    /**
     * Procesa y despacha la orden al Hub
     */
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'whatsapp' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'branch' => 'required|string',
            'delivery_method' => 'required|in:pickup,delivery',
            'payment_method' => 'required|string',
            'delivery_address' => 'nullable|string',
            'raw_text_input' => 'required|string|min:3',
        ]);

        return DB::transaction(function () use ($request) {
            // Crear orden de intención
            $order = PickingOrder::create([
                'customer_name' => $request->customer_name,
                'whatsapp' => $request->whatsapp,
                'email' => $request->email,
                'branch' => $request->branch ?? 'Aguadulce',
                'delivery_method' => $request->delivery_method ?? 'pickup',
                'payment_method' => $request->payment_method ?? 'efectivo',
                'delivery_address' => $request->delivery_address,
                'raw_text_input' => $request->raw_text_input,
                'status' => 'draft',
            ]);

            // Fragmentar items
            $order->markPendingDispatch();

            // Intentar despachar inmediatamente
            $order->markDispatching();
            $result = $this->hubService->dispatchPickingOrder($order);

            if ($result['success']) {
                $order->markSentToHub(
                    $result['hub_order_id'],
                    $result['data']
                );

                return redirect()
                    ->route('picking-orders.show', $order->id)
                    ->with('success', 'Orden enviada al sistema de picking exitosamente');
            }

            // Si falla, marcar como fallido pero continuar
            $order->markDispatchFailed($result['error']);

            return redirect()
                ->route('picking-orders.show', $order->id)
                ->with('warning', 'Orden creada pero no se pudo enviar al Hub. Se reintentará automáticamente.');
        });
    }

    /**
     * Muestra el estado de la orden
     */
    public function show(PickingOrder $pickingOrder)
    {
        return view('picking-orders.show', [
            'order' => $pickingOrder,
        ]);
    }

    /**
     * Webhook: Recibe actualizaciones del Hub
     */
    public function webhook(Request $request)
    {
        // Validar API key
        $apiKey = $request->header('X-API-Key');
        if ($apiKey !== config('services.hub.api_key')) {
            Log::warning('Webhook con API key inválido', [
                'ip' => $request->ip(),
                'api_key' => $apiKey,
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'store_order_id' => 'required|integer',
            'hub_order_id' => 'required|string',
            'status' => 'required|string',
            'magento_order_id' => 'nullable|string',
            'items_matched' => 'nullable|array',
            'message' => 'nullable|string',
        ]);

        $order = PickingOrder::find($data['store_order_id']);

        if (!$order) {
            Log::error('Webhook para orden inexistente', ['data' => $data]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Actualizar orden
        $order->addHubUpdate($data);

        // Actualizar magento_order_id si viene
        if (!empty($data['magento_order_id'])) {
            $order->update(['magento_order_id' => $data['magento_order_id']]);
        }

        Log::info('Webhook procesado exitosamente', [
            'order_id' => $order->id,
            'status' => $data['status'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Reintenta el envío al Hub
     */
    public function retry(PickingOrder $pickingOrder)
    {
        if (!$pickingOrder->canRetry()) {
            return back()->with('error', 'No se puede reintentar esta orden');
        }

        $pickingOrder->markDispatching();
        $result = $this->hubService->dispatchPickingOrder($pickingOrder);

        if ($result['success']) {
            $pickingOrder->markSentToHub(
                $result['hub_order_id'],
                $result['data']
            );

            return back()->with('success', 'Orden enviada exitosamente');
        }

        $pickingOrder->markDispatchFailed($result['error']);

        return back()->with('error', 'Error al enviar: ' . $result['error']);
    }

    /**
     * API: Lista de órdenes (para el cliente)
     */
    public function index(Request $request)
    {
        $query = PickingOrder::query()->orderBy('created_at', 'desc');

        // Filtrar por WhatsApp si se proporciona
        if ($request->filled('whatsapp')) {
            $query->where('whatsapp', $request->whatsapp);
        }

        $orders = $query->paginate(20);

        if ($request->expectsJson()) {
            return response()->json($orders);
        }

        return view('picking-orders.index', ['orders' => $orders]);
    }
}
