@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f4f7f9] py-12 px-4">
    <div class="max-w-xl mx-auto">
        
        {{-- Mensajes de error por si falla la validación del controlador --}}
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-xl shadow-sm">
                <p class="text-xs font-black uppercase tracking-widest mb-2">Por favor corrige lo siguiente:</p>
                <ul class="list-disc list-inside text-sm font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <header class="text-center mb-10">
            <h1 class="text-4xl font-light text-gray-900 tracking-tight mb-2">
                Arma tu lista <span class="font-bold text-[#0f60e3]">Inteligente</span>
            </h1>
            <p class="text-gray-500 text-sm font-medium">Escribe lo que necesitas, nosotros nos encargamos del resto.</p>
        </header>

        <form action="{{ route('orders.process') }}" method="POST" class="space-y-4">
            @csrf

            {{-- Sección de Datos Personales --}}
            <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="space-y-6">
                    <div class="relative group">
                        <input type="text" name="customer_name" value="{{ old('customer_name') }}" required 
                            class="peer w-full px-0 py-3 border-b-2 border-gray-100 bg-transparent focus:border-[#0f60e3] outline-none transition-all font-medium text-gray-800 placeholder-transparent" 
                            placeholder="Nombre">
                        <label class="absolute left-0 -top-3.5 text-xs font-bold text-gray-400 uppercase tracking-widest transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-[#0f60e3] peer-focus:text-xs">Nombre Completo</label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="relative group">
                            <input type="tel" name="whatsapp" value="{{ old('whatsapp') }}" required 
                                class="peer w-full px-0 py-3 border-b-2 border-gray-100 bg-transparent focus:border-[#0f60e3] outline-none transition-all font-medium text-gray-800 placeholder-transparent" 
                                placeholder="WhatsApp">
                            <label class="absolute left-0 -top-3.5 text-xs font-bold text-gray-400 uppercase tracking-widest transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-[#0f60e3] peer-focus:text-xs">WhatsApp</label>
                        </div>
                        <div class="relative group">
                            <input type="email" name="email" value="{{ old('email') }}" required 
                                class="peer w-full px-0 py-3 border-b-2 border-gray-100 bg-transparent focus:border-[#0f60e3] outline-none transition-all font-medium text-gray-800 placeholder-transparent" 
                                placeholder="Email">
                            <label class="absolute left-0 -top-3.5 text-xs font-bold text-gray-400 uppercase tracking-widest transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-gray-400 peer-placeholder-shown:top-3 peer-focus:-top-3.5 peer-focus:text-[#0f60e3] peer-focus:text-xs">Correo Electrónico</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sección de la Lista --}}
            <div class="bg-white rounded-3xl p-8 border border-gray-200 shadow-sm">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-4">Detalle del Pedido</label>
                <div class="relative bg-[#f9fafb] rounded-2xl p-4 border border-transparent focus-within:border-[#dfecff] focus-within:bg-white transition-all">
                    <textarea name="raw_text_input" required rows="6"
                        class="w-full bg-transparent border-none focus:ring-0 text-gray-700 font-medium leading-relaxed placeholder-gray-300 resize-none" 
                        placeholder="Escribe aquí tu lista... Ej: 2 leches, 5 lb de papa...">{{ old('raw_text_input') }}</textarea>
                    
                    <div class="absolute bottom-4 right-4 flex items-center gap-2">
                        <div class="h-2 w-2 rounded-full bg-[#ffd100] animate-pulse"></div>
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">Motor de búsqueda activo</span>
                    </div>
                </div>
            </div>

            {{-- Selectores de Configuración --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-white rounded-3xl p-4 border border-gray-200 flex flex-col gap-1 shadow-sm">
                    <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest">Sucursal</span>
                    <select name="branch" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-gray-800 p-0 cursor-pointer">
                        <option value="Aguadulce" {{ old('branch') == 'Aguadulce' ? 'selected' : '' }}>Aguadulce</option>
                        <option value="Chitré" {{ old('branch') == 'Chitré' ? 'selected' : '' }}>Chitré</option>
                        <option value="Santiago" {{ old('branch') == 'Santiago' ? 'selected' : '' }}>Santiago</option>
                        <option value="Arraiján" {{ old('branch') == 'Arraiján' ? 'selected' : '' }}>Arraiján</option>
                        <option value="Penonomé" {{ old('branch') == 'Penonomé' ? 'selected' : '' }}>Penonomé</option>
                    </select>
                </div>
                
                <div class="bg-white rounded-3xl p-4 border border-gray-200 flex flex-col gap-1 shadow-sm">
                    <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest">Entrega</span>
                    <select name="delivery_method" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-gray-800 p-0 cursor-pointer">
                        <option value="pickup" {{ old('delivery_method') == 'pickup' ? 'selected' : '' }}>Pasar a Recoger</option>
                        <option value="delivery" {{ old('delivery_method') == 'delivery' ? 'selected' : '' }}>Delivery</option>
                    </select>
                </div>

                <div class="bg-white rounded-3xl p-4 border border-gray-200 flex flex-col gap-1 shadow-sm">
                    <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest">Pago</span>
                    <select name="payment_method" class="border-none bg-transparent focus:ring-0 text-sm font-bold text-gray-800 p-0 cursor-pointer">
                        <option value="Efectivo" {{ old('payment_method') == 'Efectivo' ? 'selected' : '' }}>Efectivo</option>
                        <option value="Yappy" {{ old('payment_method') == 'Yappy' ? 'selected' : '' }}>Yappy</option>
                        <option value="Tarjeta" {{ old('payment_method') == 'Tarjeta' ? 'selected' : '' }}>Tarjeta / Clave</option>
                    </select>
                </div>
            </div>

            <button type="submit" 
                class="w-full bg-[#0f60e3] hover:bg-[#0c4eb8] text-white font-bold py-5 rounded-[2rem] transition-all duration-300 shadow-lg shadow-[#0f60e3]/20 flex items-center justify-center gap-3 active:scale-[0.98]">
                <span class="text-sm uppercase tracking-widest">Buscar en tienda</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </button>
        </form>
    </div>
</div>

<style>
    body { 
        -webkit-font-smoothing: antialiased; 
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    select { background-image: none !important; }
    textarea::placeholder { font-weight: 400; font-style: italic; opacity: 0.6; }
    
    .bg-white { 
        animation: cardEnter 0.8s cubic-bezier(0.16, 1, 0.3, 1) backwards; 
    }
    @keyframes cardEnter {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection