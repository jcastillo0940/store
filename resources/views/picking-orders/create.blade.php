<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden - Store Virzi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-2xl w-full">
            <!-- Logo/Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-black text-gray-900 mb-2">🛒 Store Virzi</h1>
                <p class="text-gray-600">Escribe tu lista de compras y nosotros nos encargamos del resto</p>
            </div>

            <!-- Formulario -->
            <form method="POST" action="{{ route('picking-orders.store') }}" class="bg-white rounded-3xl shadow-xl p-8 space-y-6">
                @csrf

                <!-- Información del Cliente -->
                <div class="space-y-4">
                    <h2 class="text-xl font-bold text-gray-900">📋 Tus Datos</h2>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Nombre Completo</label>
                        <input
                            type="text"
                            name="customer_name"
                            value="{{ old('customer_name') }}"
                            required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                            placeholder="Juan Pérez"
                        >
                        @error('customer_name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">WhatsApp</label>
                            <input
                                type="text"
                                name="whatsapp"
                                value="{{ old('whatsapp') }}"
                                required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                                placeholder="+507 6000-0000"
                            >
                            @error('whatsapp')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Email</label>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                                placeholder="correo@ejemplo.com"
                            >
                            @error('email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Sucursal</label>
                            <select
                                name="branch"
                                required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                            >
                                <option value="Aguadulce">Aguadulce</option>
                                <option value="Chitré">Chitré</option>
                                <option value="La Chorrera">La Chorrera</option>
                                <option value="Santiago">Santiago</option>
                                <option value="Las Tablas">Las Tablas</option>
                                <option value="Penonomé">Penonomé</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Método de Entrega</label>
                            <select
                                name="delivery_method"
                                required
                                class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                                onchange="toggleAddress(this.value)"
                            >
                                <option value="pickup">Recoger en tienda</option>
                                <option value="delivery">Entrega a domicilio</option>
                            </select>
                        </div>
                    </div>

                    <div id="addressField" class="hidden">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Dirección de Entrega</label>
                        <input
                            type="text"
                            name="delivery_address"
                            value="{{ old('delivery_address') }}"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                            placeholder="Calle, número de casa, referencias"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Método de Pago</label>
                        <select
                            name="payment_method"
                            required
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition"
                        >
                            <option value="efectivo">Efectivo</option>
                            <option value="yappy">Yappy</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                </div>

                <!-- Lista de Compras -->
                <div class="space-y-4">
                    <h2 class="text-xl font-bold text-gray-900">🛍️ Tu Lista de Compras</h2>
                    <p class="text-sm text-gray-600">
                        Escribe o pega tu lista como quieras. Separa los productos por comas, saltos de línea o "y".
                    </p>

                    <div>
                        <textarea
                            name="raw_text_input"
                            rows="8"
                            required
                            minlength="3"
                            class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:ring focus:ring-blue-200 transition font-mono text-sm"
                            placeholder="Ejemplo:&#10;2 leches descremadas&#10;1 libra de carne molida&#10;pan blanco&#10;una coca cola 2 litros&#10;queso amarillo"
                        >{{ old('raw_text_input') }}</textarea>
                        @error('raw_text_input')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-gray-500 mt-2">
                            💡 No te preocupes por el formato, nuestro sistema entenderá tu lista
                        </p>
                    </div>
                </div>

                <!-- Botón de Envío -->
                <button
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-lg py-4 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
                >
                    🚀 Enviar Mi Orden
                </button>

                <p class="text-center text-xs text-gray-500">
                    Tu orden será procesada por nuestro equipo de picking y recibirás actualizaciones por WhatsApp
                </p>
            </form>
        </div>
    </div>

    <script>
        function toggleAddress(method) {
            const addressField = document.getElementById('addressField');
            const addressInput = addressField.querySelector('input');

            if (method === 'delivery') {
                addressField.classList.remove('hidden');
                addressInput.required = true;
            } else {
                addressField.classList.add('hidden');
                addressInput.required = false;
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const deliveryMethod = document.querySelector('select[name="delivery_method"]');
            toggleAddress(deliveryMethod.value);
        });
    </script>
</body>
</html>
