<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estado de Orden #{{ $order->id }} - Store Virzi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-4 md:p-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-3xl shadow-xl p-8 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-black text-gray-900">Orden #{{ $order->id }}</h1>
                        <p class="text-gray-600">{{ $order->customer_name }} • {{ $order->created_at->format('d/m/Y H:i') }}</p>
                    </div>

                    <!-- Estado Badge -->
                    @php
                        $statusConfig = [
                            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => '📝 Borrador'],
                            'pending_dispatch' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => '⏳ Pendiente'],
                            'dispatching' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => '📤 Enviando'],
                            'sent_to_hub' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => '✅ Enviado al Hub'],
                            'hub_processing' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'label' => '🔄 Procesando'],
                            'picking' => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-800', 'label' => '🛒 En Picking'],
                            'picked' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-800', 'label' => '📦 Picking Completado'],
                            'ready_delivery' => ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-800', 'label' => '🚚 Listo para Entrega'],
                            'completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => '✅ Completado'],
                            'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => '❌ Error'],
                            'cancelled' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => '🚫 Cancelado'],
                        ];
                        $config = $statusConfig[$order->status] ?? $statusConfig['draft'];
                    @endphp

                    <span class="px-4 py-2 rounded-full font-bold text-sm {{ $config['bg'] }} {{ $config['text'] }}">
                        {{ $config['label'] }}
                    </span>
                </div>

                <!-- Mensajes -->
                @if(session('success'))
                    <div class="bg-green-50 border-2 border-green-200 text-green-800 px-4 py-3 rounded-xl mb-4">
                        ✅ {{ session('success') }}
                    </div>
                @endif

                @if(session('warning'))
                    <div class="bg-yellow-50 border-2 border-yellow-200 text-yellow-800 px-4 py-3 rounded-xl mb-4">
                        ⚠️ {{ session('warning') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border-2 border-red-200 text-red-800 px-4 py-3 rounded-xl mb-4">
                        ❌ {{ session('error') }}
                    </div>
                @endif

                <!-- Error de Dispatch -->
                @if($order->status === 'failed' && $order->dispatch_error)
                    <div class="bg-red-50 border-2 border-red-200 rounded-xl p-4 mb-4">
                        <p class="font-bold text-red-800 mb-2">❌ Error al enviar al Hub:</p>
                        <p class="text-sm text-red-700">{{ $order->dispatch_error }}</p>
                        <p class="text-xs text-red-600 mt-2">Intentos: {{ $order->dispatch_attempts }}/3</p>

                        @if($order->canRetry())
                            <form method="POST" action="{{ route('picking-orders.retry', $order->id) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2 rounded-lg text-sm">
                                    🔄 Reintentar Envío
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                <!-- Información del Pedido -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="font-bold text-gray-900 mb-2">📞 Contacto</h3>
                        <p class="text-sm text-gray-600">WhatsApp: {{ $order->whatsapp }}</p>
                        <p class="text-sm text-gray-600">Email: {{ $order->email }}</p>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 mb-2">📍 Entrega</h3>
                        <p class="text-sm text-gray-600">Sucursal: {{ $order->branch }}</p>
                        <p class="text-sm text-gray-600">Método: {{ $order->delivery_method === 'pickup' ? '🏪 Recoger en tienda' : '🚚 Entrega a domicilio' }}</p>
                        @if($order->delivery_address)
                            <p class="text-sm text-gray-600">Dirección: {{ $order->delivery_address }}</p>
                        @endif
                        <p class="text-sm text-gray-600">Pago: {{ ucfirst($order->payment_method) }}</p>
                    </div>
                </div>

                <!-- Tu Lista Original -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="font-bold text-gray-900 mb-2">📝 Tu Lista Original</h3>
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap font-mono">{{ $order->raw_text_input }}</pre>
                </div>

                <!-- Items Fragmentados -->
                @if($order->items_as_text)
                    <div class="mt-4 bg-blue-50 rounded-xl p-4">
                        <h3 class="font-bold text-gray-900 mb-2">🔍 Items Detectados ({{ count($order->items_as_text) }})</h3>
                        <ul class="space-y-1">
                            @foreach($order->items_as_text as $item)
                                <li class="text-sm text-gray-700">• {{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- IDs de Referencia -->
                @if($order->hub_order_id || $order->magento_order_id)
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($order->hub_order_id)
                            <div class="bg-purple-50 rounded-xl p-3">
                                <p class="text-xs font-bold text-purple-800">Hub Order ID</p>
                                <p class="text-sm text-purple-900 font-mono">{{ $order->hub_order_id }}</p>
                            </div>
                        @endif

                        @if($order->magento_order_id)
                            <div class="bg-green-50 rounded-xl p-3">
                                <p class="text-xs font-bold text-green-800">Magento Order ID</p>
                                <p class="text-sm text-green-900 font-mono">{{ $order->magento_order_id }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Actualizaciones del Hub -->
            @if($order->hub_updates && count($order->hub_updates) > 0)
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <h2 class="text-2xl font-black text-gray-900 mb-6">📡 Actualizaciones del Hub</h2>

                    <div class="space-y-4">
                        @foreach(array_reverse($order->hub_updates) as $update)
                            <div class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-900">{{ $update['status'] ?? 'Actualización' }}</p>
                                        @if(isset($update['message']))
                                            <p class="text-sm text-gray-600 mt-1">{{ $update['message'] }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($update['received_at'])->format('d/m/Y H:i') }}
                                    </span>
                                </div>

                                @if(isset($update['items_matched']))
                                    <div class="mt-2 text-sm text-gray-600">
                                        <p class="font-bold">Items identificados:</p>
                                        <ul class="ml-4 mt-1 space-y-1">
                                            @foreach($update['items_matched'] as $matched)
                                                <li>
                                                    @if($matched['matched'])
                                                        <span class="text-green-600">✓</span>
                                                    @else
                                                        <span class="text-red-600">✗</span>
                                                    @endif
                                                    {{ $matched['text'] }}
                                                    @if(isset($matched['sku']))
                                                        <span class="text-gray-500 text-xs">(SKU: {{ $matched['sku'] }})</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Botones de Acción -->
            <div class="mt-6 flex gap-4">
                <a href="{{ route('picking-orders.create') }}" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl text-center">
                    🛒 Nueva Orden
                </a>
                <a href="{{ route('picking-orders.index') }}?whatsapp={{ $order->whatsapp }}" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 rounded-xl text-center">
                    📋 Mis Órdenes
                </a>
            </div>

            <p class="text-center text-xs text-gray-500 mt-4">
                Esta página se actualiza automáticamente cada 30 segundos
            </p>
        </div>
    </div>
</body>
</html>
